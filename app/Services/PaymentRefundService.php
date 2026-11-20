<?php

namespace App\Services;

use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\AdminAuditLog;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentRefundTransaction;
use App\Models\PaymentRefundTransactionEvent;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentRefundService
{
    public function __construct(private readonly BusinessAccessService $businessAccess) {}

    public function request(
        PaymentTransaction $payment,
        User $actor,
        int $amountMinor,
        string $reason,
        string $submissionKey,
    ): PaymentRefundTransaction {
        $this->assertAuthenticatedActor($actor);
        if ($amountMinor < 1) {
            throw ValidationException::withMessages(['refund_amount' => 'Refund amount must be greater than zero.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A refund reason is required.']);
        }
        if (! Str::isUuid($submissionKey)) {
            throw ValidationException::withMessages(['refund_submission_key' => 'The refund form has expired. Please reload the order.']);
        }

        try {
            return DB::transaction(function () use ($payment, $actor, $amountMinor, $reason, $submissionKey): PaymentRefundTransaction {
                $order = Order::query()->withoutGlobalScopes()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                $lockedPayment = PaymentTransaction::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
                $this->authorizeRequester($actor, $order);

                if ($order->seller_id !== null || $order->pet_listing_id !== null) {
                    throw new AuthorizationException('Marketplace Seller payments do not use internal business refunds.');
                }
                if ($lockedPayment->status !== PaymentTransactionStatus::Verified
                    || $lockedPayment->currency !== $order->currency
                    || (int) $lockedPayment->order_id !== (int) $order->getKey()) {
                    throw ValidationException::withMessages(['payment' => 'Only a verified payment in the order currency can be refunded.']);
                }

                $existing = PaymentRefundTransaction::query()->where('submission_key', $submissionKey)->first();
                if ($existing) {
                    if ((int) $existing->payment_transaction_id !== (int) $lockedPayment->getKey()
                        || (int) $existing->initiated_by_user_id !== (int) $actor->getKey()
                        || (int) $existing->amount_minor !== $amountMinor
                        || $existing->reason !== $reason) {
                        throw ValidationException::withMessages(['refund_submission_key' => 'This refund form was already used for a different request.']);
                    }

                    return $existing;
                }

                $reserved = (int) PaymentRefundTransaction::query()
                    ->where('payment_transaction_id', $lockedPayment->getKey())
                    ->whereIn('status', [PaymentRefundStatus::Requested->value, PaymentRefundStatus::Refunded->value])
                    ->sum('amount_minor');
                if ($reserved + $amountMinor > (int) $lockedPayment->amount_minor) {
                    throw ValidationException::withMessages(['refund_amount' => 'Refund requests cannot exceed the verified amount available on this payment.']);
                }

                $refund = PaymentRefundTransaction::create([
                    'payment_transaction_id' => $lockedPayment->getKey(),
                    'submission_key' => $submissionKey,
                    'original_transaction_reference' => $lockedPayment->transaction_reference,
                    'amount_minor' => $amountMinor,
                    'currency' => $lockedPayment->currency,
                    'status' => PaymentRefundStatus::Requested,
                    'reason' => $reason,
                    'initiated_by_user_id' => $actor->getKey(),
                ]);
                $this->event($refund, $actor, null, PaymentRefundStatus::Requested, $reason);

                AdminAuditLog::record('payment.refund.requested', "Refund #{$refund->id} was requested for payment #{$lockedPayment->id}.", [
                    'order_id' => $order->id,
                    'payment_transaction_id' => $lockedPayment->id,
                    'payment_refund_transaction_id' => $refund->id,
                    'amount_minor' => $amountMinor,
                    'currency' => $lockedPayment->currency,
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                ]);

                return $refund;
            }, 3);
        } catch (QueryException $exception) {
            $existing = PaymentRefundTransaction::query()->where('submission_key', $submissionKey)->first();
            if ($existing
                && (int) $existing->payment_transaction_id === (int) $payment->getKey()
                && (int) $existing->initiated_by_user_id === (int) $actor->getKey()
                && (int) $existing->amount_minor === $amountMinor
                && $existing->reason === $reason) {
                return $existing;
            }

            throw $exception;
        }
    }

    /** Approving here records that the authorized person has issued the refund. */
    public function approve(PaymentRefundTransaction $refund, User $actor, string $note): PaymentRefundTransaction
    {
        $this->assertAuthenticatedActor($actor);
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['approval_note' => 'Confirm the refund and record the approval evidence.']);
        }

        return DB::transaction(function () use ($refund, $actor, $note): PaymentRefundTransaction {
            $paymentId = PaymentRefundTransaction::query()->whereKey($refund->getKey())->value('payment_transaction_id');
            $orderId = PaymentTransaction::query()->whereKey($paymentId)->value('order_id');
            $order = Order::query()->withoutGlobalScopes()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $payment = PaymentTransaction::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            $locked = PaymentRefundTransaction::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            $this->authorizeApprover($actor, $order);
            if ((int) $locked->initiated_by_user_id === (int) $actor->getKey()) {
                throw new AuthorizationException('The refund initiator cannot approve or complete their own refund.');
            }
            if ($order->seller_id !== null || $order->pet_listing_id !== null
                || $payment->status !== PaymentTransactionStatus::Verified
                || $payment->currency !== $order->currency
                || $locked->currency !== $payment->currency) {
                throw ValidationException::withMessages(['refund' => 'This refund is no longer eligible for completion.']);
            }
            if ($locked->status !== PaymentRefundStatus::Requested) {
                throw ValidationException::withMessages(['refund' => 'Only a pending refund request can be completed.']);
            }

            $reserved = (int) PaymentRefundTransaction::query()
                ->where('payment_transaction_id', $payment->getKey())
                ->whereIn('status', [PaymentRefundStatus::Requested->value, PaymentRefundStatus::Refunded->value])
                ->sum('amount_minor');
            if ($reserved > (int) $payment->amount_minor) {
                throw ValidationException::withMessages(['refund' => 'The verified payment balance changed; this refund cannot be completed.']);
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => PaymentRefundStatus::Refunded,
                'approved_by_user_id' => $actor->getKey(),
                'approved_at' => now(),
                'approval_note' => $note,
                'refunded_at' => now(),
            ])->saveQuietly();
            $this->event($locked, $actor, $from, PaymentRefundStatus::Refunded, $note);

            $verifiedForOrder = (int) PaymentTransaction::query()->where('order_id', $order->getKey())
                ->where('status', PaymentTransactionStatus::Verified->value)->sum('amount_minor');
            $refundedForOrder = (int) PaymentRefundTransaction::query()
                ->whereIn('payment_transaction_id', PaymentTransaction::query()->select('id')->where('order_id', $order->getKey())
                    ->where('status', PaymentTransactionStatus::Verified->value))
                ->where('status', PaymentRefundStatus::Refunded->value)->sum('amount_minor');
            if ($verifiedForOrder > 0) {
                $netCollected = max(0, $verifiedForOrder - $refundedForOrder);
                $order->forceFill([
                    'payment_status' => $netCollected >= (int) $order->total_minor
                        ? PaymentStatus::Paid
                        : ($netCollected === 0 ? PaymentStatus::Refunded : PaymentStatus::Pending),
                ])->save();
            }

            AdminAuditLog::record('payment.refund.completed', "Refund #{$locked->id} was completed against payment #{$payment->id}.", [
                'order_id' => $order->id,
                'payment_transaction_id' => $payment->id,
                'payment_refund_transaction_id' => $locked->id,
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
            ]);

            return $locked->fresh(['initiatedBy', 'approvedBy', 'events.actor']);
        }, 3);
    }

    public function reject(PaymentRefundTransaction $refund, User $actor, string $note): PaymentRefundTransaction
    {
        $this->assertAuthenticatedActor($actor);
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['approval_note' => 'A reason is required to reject a refund.']);
        }

        return DB::transaction(function () use ($refund, $actor, $note): PaymentRefundTransaction {
            $paymentId = PaymentRefundTransaction::query()->whereKey($refund->getKey())->value('payment_transaction_id');
            $orderId = PaymentTransaction::query()->whereKey($paymentId)->value('order_id');
            $order = Order::query()->withoutGlobalScopes()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $payment = PaymentTransaction::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            $locked = PaymentRefundTransaction::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeApprover($actor, $order);
            if ((int) $locked->initiated_by_user_id === (int) $actor->getKey()) {
                throw new AuthorizationException('The refund initiator cannot review their own refund.');
            }
            if ($locked->status !== PaymentRefundStatus::Requested) {
                throw ValidationException::withMessages(['refund' => 'Only a pending refund request can be rejected.']);
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => PaymentRefundStatus::Rejected,
                'approved_by_user_id' => $actor->getKey(),
                'approved_at' => now(),
                'approval_note' => $note,
            ])->saveQuietly();
            $this->event($locked, $actor, $from, PaymentRefundStatus::Rejected, $note);

            AdminAuditLog::record('payment.refund.rejected', "Refund #{$locked->id} was rejected.", [
                'order_id' => $order->id,
                'payment_transaction_id' => $payment->id,
                'payment_refund_transaction_id' => $locked->id,
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
            ]);

            return $locked->fresh(['initiatedBy', 'approvedBy', 'events.actor']);
        }, 3);
    }

    private function authorizeRequester(User $actor, Order $order): void
    {
        if (! $actor->isStaff()) {
            if ($actor->isCustomer() && (int) $order->user_id === (int) $actor->getKey()) {
                return;
            }
            throw new AuthorizationException('Only the customer who placed the order or authorized business staff may request a refund.');
        }

        $membership = $this->lockedMembership($actor, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER]);
        Gate::forUser($actor)->authorize('update', $order);
        if ((int) $membership->company_id !== (int) $order->company_id
            || ($membership->role === BusinessAccessService::MANAGER && (int) $membership->branch_id !== (int) $order->branch_id)) {
            throw new AuthorizationException('This order is outside your assigned business scope.');
        }
        if ($membership->role === BusinessAccessService::MANAGER) {
            throw new AuthorizationException('Branch Managers review branch refunds; the customer or Shop Owner must initiate the request.');
        }
        if ($order->branch_id === null) {
            throw new AuthorizationException('The customer must initiate a branchless online refund for independent Shop Owner review.');
        }
    }

    private function authorizeApprover(User $actor, Order $order): void
    {
        $requiredRole = $order->branch_id === null ? BusinessAccessService::OWNER : BusinessAccessService::MANAGER;
        $membership = $this->lockedMembership($actor, [$requiredRole]);
        if ((int) $membership->company_id !== (int) $order->company_id
            || ($requiredRole === BusinessAccessService::MANAGER && (int) $membership->branch_id !== (int) $order->branch_id)) {
            throw new AuthorizationException('Only the authorized reviewer for this order scope can review the refund.');
        }
    }

    /** @param list<string> $roles */
    private function lockedMembership(User $actor, array $roles): BusinessMembership
    {
        if (Auth::id() !== $actor->getKey() || ! $actor->isStaff()) {
            throw new AuthorizationException('Refund actions require the authenticated business employee.');
        }
        $active = $this->businessAccess->activeMemberships($actor)->first();
        if (! $active || ! in_array($active->role, $roles, true)) {
            throw new AuthorizationException('Your business position cannot perform this refund action.');
        }
        $membership = BusinessMembership::query()->whereKey($active->getKey())->lockForUpdate()->first();
        if (! $membership?->is_active
            || (int) $membership->user_id !== (int) $actor->getKey()
            || ! in_array($membership->role, $roles, true)
            || (int) $membership->company_id !== (int) $active->company_id) {
            throw new AuthorizationException('Your business assignment changed before the refund action could be completed.');
        }
        $company = Company::query()->withoutGlobalScopes()
            ->whereKey($membership->company_id)->where('is_active', true)->lockForUpdate()->first();
        if (! $company) {
            throw new AuthorizationException('The assigned company is not active.');
        }
        $this->businessAccess->installationCompany((int) $company->getKey());
        if ($membership->branch_id !== null) {
            $branch = Branch::query()->withoutGlobalScopes()->whereKey($membership->branch_id)
                ->where('company_id', $membership->company_id)->where('is_active', true)->lockForUpdate()->first();
            if (! $branch) {
                throw new AuthorizationException('The assigned branch is not active for this company.');
            }
        }

        return $membership;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        if (Auth::id() !== $actor->getKey()) {
            throw new AuthorizationException('Refund actions must be performed by the authenticated account.');
        }
    }

    private function event(
        PaymentRefundTransaction $refund,
        User $actor,
        ?PaymentRefundStatus $from,
        PaymentRefundStatus $to,
        ?string $note,
    ): void {
        PaymentRefundTransactionEvent::create([
            'payment_refund_transaction_id' => $refund->getKey(),
            'actor_user_id' => $actor->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => trim((string) $note) ?: null,
            'created_at' => now(),
        ]);
    }
}

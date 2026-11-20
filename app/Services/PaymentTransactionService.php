<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SalesChannel;
use App\Models\AdminAuditLog;
use App\Models\BusinessMembership;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionEvent;
use App\Models\PaymentRefundTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentTransactionService
{
    private const RESERVED_STATUSES = ['pending', 'submitted', 'verified'];

    public function __construct(private readonly BusinessAccessService $businessAccess) {}

    public function recordPosSubmission(
        Order $order,
        User $actor,
        PaymentMethod $method,
        int $amountMinor,
        ?string $reference = null,
        ?string $note = null,
        ?string $submissionKey = null,
    ): PaymentTransaction {
        $this->assertAuthenticatedActor($actor);
        $membership = $this->employeeSellerMembership($actor);

        return $this->record($order, $actor, $method, $amountMinor, $reference, $note, PaymentSource::PointOfSale, function (Order $lockedOrder) use ($actor, $membership): void {
            $current = $this->assertMembershipStillActive($membership);
            if ($current->role !== BusinessAccessService::EMPLOYEE_SELLER
                || $lockedOrder->sales_channel !== SalesChannel::PointOfSale
                || (int) $lockedOrder->employee_seller_id !== (int) $actor->getKey()
                || (int) $lockedOrder->company_id !== (int) $current->company_id
                || (int) $lockedOrder->branch_id !== (int) $current->branch_id) {
                throw new AuthorizationException('This POS payment is outside your active employee assignment.');
            }
        }, $submissionKey);
    }

    public function recordBusinessPayment(
        Order $order,
        User $actor,
        PaymentMethod $method,
        int $amountMinor,
        ?string $reference = null,
        ?string $note = null,
        ?string $submissionKey = null,
    ): PaymentTransaction {
        $this->assertAuthenticatedActor($actor);
        $membership = $this->managerMembership($actor);

        return $this->record($order, $actor, $method, $amountMinor, $reference, $note, PaymentSource::AdminManual, function (Order $lockedOrder) use ($actor, $membership): void {
            $current = $this->assertMembershipStillActive($membership);
            Gate::forUser($actor)->authorize('update', $lockedOrder);
            if ($lockedOrder->seller_id !== null || $lockedOrder->pet_listing_id !== null
                || ! in_array($current->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)
                || (int) $lockedOrder->company_id !== (int) $current->company_id
                || ($current->role === BusinessAccessService::MANAGER && (int) $lockedOrder->branch_id !== (int) $current->branch_id)) {
                throw new AuthorizationException('This order is outside your active business assignment.');
            }
        }, $submissionKey);
    }

    public function verify(
        PaymentTransaction $transaction,
        User $actor,
        bool $failed,
        string $note,
    ): PaymentTransaction {
        $this->assertAuthenticatedActor($actor);
        $membership = $this->managerMembership($actor);

        return DB::transaction(function () use ($transaction, $actor, $failed, $note, $membership): PaymentTransaction {
            $order = Order::query()->withoutGlobalScopes()->whereKey($transaction->order_id)->lockForUpdate()->firstOrFail();
            $membership = $this->assertMembershipStillActive($membership);
            Gate::forUser($actor)->authorize('update', $order);
            if ($order->seller_id !== null || $order->pet_listing_id !== null
                || (int) $order->company_id !== (int) $membership->company_id
                || ($membership->role === BusinessAccessService::MANAGER && (int) $order->branch_id !== (int) $membership->branch_id)) {
                throw new AuthorizationException('This payment is outside your active business assignment.');
            }

            $locked = PaymentTransaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $locked->order_id !== (int) $order->getKey()
                || $locked->status !== PaymentTransactionStatus::Submitted) {
                throw ValidationException::withMessages(['payment' => 'Only submitted payments can be verified or marked failed.']);
            }
            if ($locked->currency !== $order->currency) {
                throw ValidationException::withMessages(['payment' => 'Payment and order currencies do not match. This transaction requires data review.']);
            }
            if (! $failed && (int) $locked->recorded_by_user_id === (int) $actor->getKey()) {
                throw new AuthorizationException('A different authorized reviewer must verify this payment submission.');
            }
            if ($order->status->value === 'cancelled') {
                throw ValidationException::withMessages(['payment' => 'Payments for cancelled orders cannot be verified.']);
            }

            $from = $locked->status;
            $to = $failed ? PaymentTransactionStatus::Failed : PaymentTransactionStatus::Verified;
            if (! $failed) {
                $otherVerified = (int) PaymentTransaction::query()
                    ->where('order_id', $order->getKey())
                    ->where('status', PaymentTransactionStatus::Verified->value)
                    ->where('id', '!=', $locked->getKey())
                    ->sum('amount_minor');
                $otherPending = (int) PaymentTransaction::query()
                    ->where('order_id', $order->getKey())
                    ->whereIn('status', ['pending', 'submitted'])
                    ->where('id', '!=', $locked->getKey())
                    ->sum('amount_minor');
                $refunded = $this->refundedForOrder($order);
                if ($otherVerified - $refunded + $otherPending + (int) $locked->amount_minor > (int) $order->total_minor) {
                    throw ValidationException::withMessages(['payment' => 'Verifying this payment would exceed the order total.']);
                }
            }

            $locked->forceFill([
                'status' => $to,
                'verified_by_user_id' => $failed ? null : $actor->getKey(),
                'verified_at' => $failed ? null : now(),
                'verification_note' => trim($note),
            ])->saveQuietly();
            $this->event($locked, $actor, $from, $to, trim($note));

            if (! $failed) {
                $verified = (int) PaymentTransaction::query()
                    ->where('order_id', $order->getKey())
                    ->where('status', PaymentTransactionStatus::Verified->value)
                    ->sum('amount_minor');
                $netVerified = max(0, $verified - $this->refundedForOrder($order));
                $order->forceFill([
                    'payment_status' => $netVerified >= (int) $order->total_minor
                        ? PaymentStatus::Paid
                        : ($verified > 0 && $netVerified === 0 ? PaymentStatus::Refunded : PaymentStatus::Pending),
                ])->save();
            }

            AdminAuditLog::record(
                $failed ? 'payment.failed' : 'payment.verified',
                "Payment transaction #{$locked->id} for order #{$order->order_number} was marked {$to->value}.",
                [
                    'order_id' => $order->id,
                    'payment_transaction_id' => $locked->id,
                    'payment_method' => $locked->payment_method->value,
                    'amount_minor' => $locked->amount_minor,
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                    'reason' => trim($note),
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                ],
            );

            return $locked->fresh(['recordedBy', 'verifiedBy', 'events.actor']);
        }, 3);
    }

    /** Marketplace Sellers may explicitly confirm receipt only for their own marketplace order. */
    public function confirmMarketplaceSellerReceipt(Order $order, User $actor, ?string $reference = null): PaymentTransaction
    {
        $this->assertAuthenticatedActor($actor);
        if (! $actor->isSeller()) {
            throw new AuthorizationException('Only the assigned Marketplace Seller can confirm this marketplace receipt.');
        }

        return DB::transaction(function () use ($order, $actor, $reference): PaymentTransaction {
            $lockedOrder = Order::query()->withoutGlobalScopes()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $lockedOrder);
            if ((int) $lockedOrder->seller_id !== (int) $actor->getKey()
                || $lockedOrder->branch_id !== null
                || $lockedOrder->total_minor < 1
                || $lockedOrder->status->value === 'cancelled') {
                throw new AuthorizationException('Only an active Marketplace Seller order can be confirmed here.');
            }

            if (PaymentTransaction::query()->where('order_id', $lockedOrder->getKey())->whereIn('status', self::RESERVED_STATUSES)->exists()) {
                throw ValidationException::withMessages(['payment' => 'This order already has a payment transaction recorded.']);
            }
            $method = $lockedOrder->payment_method === 'cod'
                ? PaymentMethod::Cash
                : PaymentMethod::tryFrom($lockedOrder->payment_method);
            if (! $method) {
                throw ValidationException::withMessages(['payment' => 'This marketplace request does not have a supported payment method.']);
            }

            $reference = $reference === null ? null : trim($reference);
            if ($reference === '') {
                $reference = null;
            }
            if ($method->requiresReference() && $reference === null) {
                throw ValidationException::withMessages(['payment_reference' => 'A transaction reference is required for this payment method.']);
            }
            $hash = $reference === null ? null : hash('sha256', $method->value."\0".mb_strtolower($reference));
            if ($hash !== null && PaymentTransaction::query()->where('reference_hash', $hash)->exists()) {
                throw ValidationException::withMessages(['payment_reference' => 'This transaction reference has already been recorded.']);
            }

            $transaction = PaymentTransaction::create([
                'order_id' => $lockedOrder->getKey(),
                'transaction_reference' => $reference,
                'reference_hash' => $hash,
                'payment_method' => $method,
                'amount_minor' => $lockedOrder->total_minor,
                'currency' => $lockedOrder->currency,
                'status' => PaymentTransactionStatus::Verified,
                'source' => PaymentSource::Online,
                'recorded_by_user_id' => $actor->getKey(),
                'verified_by_user_id' => $actor->getKey(),
                'verified_at' => now(),
                'verification_note' => 'Marketplace Seller explicitly confirmed receipt for their own order.',
            ]);
            $this->event($transaction, $actor, null, PaymentTransactionStatus::Verified, 'Marketplace Seller confirmed receipt.');
            $lockedOrder->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'payment_reference' => $reference,
            ])->save();

            return $transaction->fresh(['recordedBy', 'verifiedBy', 'events.actor']);
        }, 3);
    }

    /** @return array{recorded_minor:int, verified_minor:int, refunded_minor:int, net_collected_minor:int, outstanding_minor:int, reversed_refunded_minor:int, available_to_record_minor:int} */
    public function totalsForOrder(Order $order): array
    {
        $sum = fn (array $statuses): int => (int) PaymentTransaction::query()
            ->where('order_id', $order->getKey())->whereIn('status', $statuses)->sum('amount_minor');
        $recorded = $sum(['submitted', 'verified']);
        $verified = $sum(['verified']);
        $refunded = $this->refundedForOrder($order);
        $reserved = max(0, $sum(self::RESERVED_STATUSES) - $refunded);
        $netVerified = max(0, $verified - $refunded);
        $cancelled = $order->status->value === 'cancelled';

        return [
            'recorded_minor' => $recorded,
            'verified_minor' => $verified,
            'refunded_minor' => $refunded,
            'net_collected_minor' => $netVerified,
            'outstanding_minor' => $cancelled ? 0 : max(0, (int) $order->total_minor - $netVerified),
            'reversed_refunded_minor' => $sum(['reversed', 'refunded']),
            'available_to_record_minor' => $cancelled ? 0 : max(0, (int) $order->total_minor - $reserved),
        ];
    }

    public function amountToMinor(string $amount): int
    {
        $amount = trim($amount);
        if (! preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw ValidationException::withMessages(['payment_amount' => 'Enter an amount with up to two decimal places.']);
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 2, '0');
        if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) {
            throw ValidationException::withMessages(['payment_amount' => 'The payment amount is too large.']);
        }
        $minor = ($whole * 100) + $fraction;
        if ($minor < 1) {
            throw ValidationException::withMessages(['payment_amount' => 'Payment amount must be greater than zero.']);
        }

        return $minor;
    }

    private function record(
        Order $order,
        User $actor,
        PaymentMethod $method,
        int $amountMinor,
        ?string $reference,
        ?string $note,
        PaymentSource $source,
        callable $authorize,
        ?string $submissionKey,
    ): PaymentTransaction {
        if ($amountMinor < 1) {
            throw ValidationException::withMessages(['payment_amount' => 'Payment amount must be greater than zero.']);
        }
        $reference = $reference === null ? null : trim($reference);
        if ($method->requiresReference() && ($reference === null || $reference === '')) {
            throw ValidationException::withMessages(['payment_reference' => 'A transaction reference is required for this payment method.']);
        }
        if ($reference === '') {
            $reference = null;
        }

        $referenceHash = $reference === null
            ? null
            : hash('sha256', $method->value."\0".mb_strtolower($reference));

        try {
            return DB::transaction(function () use ($order, $actor, $method, $amountMinor, $reference, $referenceHash, $note, $source, $authorize, $submissionKey): PaymentTransaction {
            $lockedOrder = Order::query()->withoutGlobalScopes()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $authorize($lockedOrder);
            if ($lockedOrder->status->value === 'cancelled') {
                throw ValidationException::withMessages(['payment' => 'Payments cannot be recorded for cancelled orders.']);
            }

            if ($submissionKey !== null) {
                $existing = $this->existingSubmission($submissionKey, $lockedOrder, $actor, $method, $amountMinor, $referenceHash, $source);
                if ($existing) {
                    return $existing;
                }
            }

            $grossReserved = (int) PaymentTransaction::query()
                ->where('order_id', $lockedOrder->getKey())
                ->whereIn('status', self::RESERVED_STATUSES)
                ->sum('amount_minor');
            $refunded = $this->refundedForOrder($lockedOrder);
            $alreadyReserved = max(0, $grossReserved - $refunded);
            if ($alreadyReserved + $amountMinor > (int) $lockedOrder->total_minor) {
                throw ValidationException::withMessages(['payment_amount' => 'This payment exceeds the order balance after pending submissions.']);
            }

            if ($referenceHash !== null && PaymentTransaction::query()->where('reference_hash', $referenceHash)->exists()) {
                throw ValidationException::withMessages(['payment_reference' => 'This transaction reference has already been recorded.']);
            }

            $transaction = PaymentTransaction::create([
                'order_id' => $lockedOrder->getKey(),
                'submission_key' => $submissionKey,
                'transaction_reference' => $reference,
                'reference_hash' => $referenceHash,
                'payment_method' => $method,
                'amount_minor' => $amountMinor,
                'currency' => $lockedOrder->currency,
                'status' => PaymentTransactionStatus::Submitted,
                'source' => $source,
                'recorded_by_user_id' => $actor->getKey(),
            ]);
            $this->event($transaction, $actor, null, PaymentTransactionStatus::Submitted, $note);

            return $transaction;
            }, 3);
        } catch (QueryException $exception) {
            if ($submissionKey !== null) {
                $existing = $this->existingSubmission($submissionKey, $order, $actor, $method, $amountMinor, $referenceHash, $source);
                if ($existing) {
                    return $existing;
                }
            }
            if ($referenceHash !== null && PaymentTransaction::query()->where('reference_hash', $referenceHash)->exists()) {
                throw ValidationException::withMessages(['payment_reference' => 'This transaction reference has already been recorded.']);
            }

            throw $exception;
        }
    }

    private function existingSubmission(
        string $key,
        Order $order,
        User $actor,
        PaymentMethod $method,
        int $amountMinor,
        ?string $referenceHash,
        PaymentSource $source,
    ): ?PaymentTransaction {
        $existing = PaymentTransaction::query()->where('submission_key', $key)->first();
        if (! $existing) {
            return null;
        }

        if ((int) $existing->order_id !== (int) $order->getKey()
            || (int) $existing->recorded_by_user_id !== (int) $actor->getKey()
            || $existing->payment_method !== $method
            || (int) $existing->amount_minor !== $amountMinor
            || $existing->reference_hash !== $referenceHash
            || $existing->source !== $source) {
            throw ValidationException::withMessages(['payment_submission_key' => 'This payment form has already been used for a different submission.']);
        }

        return $existing;
    }

    private function employeeSellerMembership(User $actor): BusinessMembership
    {
        $membership = $actor->isStaff() ? $this->businessAccess->activeMemberships($actor)->first() : null;
        if (! $membership
            || $membership->role !== BusinessAccessService::EMPLOYEE_SELLER
            || $membership->branch_id === null) {
            throw new AuthorizationException('An active Employee Seller assignment is required.');
        }

        return $membership;
    }

    private function managerMembership(User $actor): BusinessMembership
    {
        if (! $actor->isStaff()) {
            throw new AuthorizationException('Only authorized business staff can manage payment records.');
        }
        $membership = $this->businessAccess->activeMemberships($actor)->first();
        if (! $membership || ! in_array($membership->role, [BusinessAccessService::OWNER, BusinessAccessService::MANAGER], true)) {
            throw new AuthorizationException('Only the Shop Owner or assigned Branch Manager can manage payment records.');
        }

        return $membership;
    }

    private function assertAuthenticatedActor(User $actor): void
    {
        if (Auth::id() !== $actor->getKey()) {
            throw new AuthorizationException('Payment actions must be performed by the authenticated account.');
        }
    }

    /** Rechecks and locks the assignment and its active tenant records within a write transaction. */
    private function assertMembershipStillActive(BusinessMembership $membership): BusinessMembership
    {
        $locked = BusinessMembership::query()->whereKey($membership->getKey())->lockForUpdate()->first();
        if (! $locked?->is_active) {
            throw new AuthorizationException('Your active business assignment has changed.');
        }

        $company = Company::query()->withoutGlobalScopes()
            ->whereKey($locked->company_id)->where('is_active', true)->lockForUpdate()->first();
        if (! $company) {
            throw new AuthorizationException('The assigned company is not active.');
        }
        $this->businessAccess->installationCompany((int) $company->getKey());

        if ($locked->branch_id !== null) {
            $branch = Branch::query()->withoutGlobalScopes()
                ->whereKey($locked->branch_id)
                ->where('company_id', $locked->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $branch) {
                throw new AuthorizationException('The assigned branch is not active for this company.');
            }
        }

        return $locked;
    }

    private function event(
        PaymentTransaction $transaction,
        User $actor,
        ?PaymentTransactionStatus $from,
        PaymentTransactionStatus $to,
        ?string $note,
    ): void {
        PaymentTransactionEvent::create([
            'payment_transaction_id' => $transaction->getKey(),
            'actor_user_id' => $actor->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note === null ? null : trim($note),
            'created_at' => now(),
        ]);
    }

    private function refundedForOrder(Order $order): int
    {
        return (int) PaymentRefundTransaction::query()
            ->whereIn('payment_transaction_id', PaymentTransaction::query()->select('id')->where('order_id', $order->getKey()))
            ->where('status', 'refunded')
            ->sum('amount_minor');
    }
}

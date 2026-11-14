<?php

namespace App\Http\Requests\Admin;

use App\Models\Invitation;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\InvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($this->input('role') === 'seller') {
            return $user->isAdmin();
        }

        try {
            $company = app(BusinessAccessService::class)->installationCompany();
        } catch (AuthorizationException) {
            return false;
        }

        return app(BusinessAccessService::class)->canManageEmployees($user, (int) $company->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isManager = in_array($this->input('role'), ['manager', 'branch_manager'], true);

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(['seller', 'manager', 'branch_manager'])],
            'branch_id' => $isManager
                ? ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)]
                : ['nullable', 'prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'A valid operational branch must be selected for a Branch Manager invitation.',
            'branch_id.exists' => 'The selected branch does not exist or is currently inactive.',
            'branch_id.prohibited' => 'Sellers operate across the general marketplace and cannot be assigned to an operational store branch.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = strtolower(trim((string) $this->input('email')));
            if ($email === '') {
                return;
            }

            if (app(InvitationService::class)->hasExistingAccount($email)) {
                $validator->errors()->add('email', 'A user account with this email address already exists.');

                return;
            }

            $pending = Invitation::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists();

            if ($pending) {
                $validator->errors()->add('email', 'An active invitation for this email address is already pending. You can resend or revoke it below.');
            }
        });
    }
}

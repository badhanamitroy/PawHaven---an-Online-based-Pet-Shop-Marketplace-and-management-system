<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\BusinessMembership;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        if ($user->isAdmin() || $user->isStaff()
            || BusinessMembership::query()->where('user_id', $user->getKey())->exists()) {
            $this->addError('account', __('Platform administrator and business employee accounts cannot be self-deleted. Arrange a replacement or access transfer with the installation administrator first.'));

            return;
        }

        if ($user->petListings()->exists()) {
            $this->addError('listings', __('You cannot delete your account while you own pet listings. Resolve or transfer your listings first.'));

            return;
        }

        if ($user->sellerOrders()->exists()) {
            $this->addError('marketplace_orders', __('You cannot delete your account while marketplace orders are assigned to you. Resolve or transfer those orders first.'));

            return;
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form method="POST" wire:submit="deleteUser" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

            <flux:subheading>
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </flux:subheading>
        </div>

        <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

        @error('listings')
            <flux:text class="text-red-600">{{ $message }}</flux:text>
        @enderror

        @error('marketplace_orders')
            <flux:text class="text-red-600">{{ $message }}</flux:text>
        @enderror

        @error('account')
            <flux:text class="text-red-600">{{ $message }}</flux:text>
        @enderror

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </div>
    </form>
</flux:modal>

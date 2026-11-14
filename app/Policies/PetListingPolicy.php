<?php

namespace App\Policies;

use App\Enums\PetListingStatus;
use App\Models\PetListing;
use App\Models\User;

class PetListingPolicy
{
    public function view(?User $user, PetListing $petListing): bool
    {
        if ($petListing->status === PetListingStatus::Published) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $user->isAdmin()
            || ($user->isSeller() && $petListing->seller_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isSeller();
    }

    public function update(User $user, PetListing $petListing): bool
    {
        return $user->isSeller() && $petListing->seller_id === $user->id;
    }

    public function delete(User $user, PetListing $petListing): bool
    {
        return $this->update($user, $petListing);
    }

    public function moderate(User $user, PetListing $petListing): bool
    {
        return $user->isAdmin();
    }
}

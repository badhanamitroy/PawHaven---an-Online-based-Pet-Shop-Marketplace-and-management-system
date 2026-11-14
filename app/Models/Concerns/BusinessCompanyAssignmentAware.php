<?php

namespace App\Models\Concerns;

use App\Models\User;

interface BusinessCompanyAssignmentAware
{
    public function shouldAssignDefaultBusinessCompany(?User $user): bool;
}

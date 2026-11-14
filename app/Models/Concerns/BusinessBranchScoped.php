<?php

namespace App\Models\Concerns;

interface BusinessBranchScoped
{
    /**
     * The branch column that defines model visibility, or null for company-only scope.
     */
    public function businessBranchColumn(): ?string;
}

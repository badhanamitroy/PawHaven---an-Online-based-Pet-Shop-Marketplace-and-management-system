<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Services\BusinessAccessService;
use App\Services\BusinessMembershipService;
use Illuminate\Database\Seeder;

class BusinessDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->warn('BusinessDemoSeeder is restricted to the local environment; no records were written.');

            return;
        }

        try {
            $company = app(BusinessAccessService::class)->installationCompany();
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->command->warn('The installation does not have one active company; no demo records were written.');

            return;
        }

        $branch = Branch::query()->withoutGlobalScopes()->firstOrCreate(
            ['code' => 'DEMO-CENTRAL'],
            [
                'company_id' => $company->id,
                'name' => 'Demo Central Branch',
                'city' => 'Local Demo',
                'address' => 'Local development fixture only',
                'is_active' => true,
            ],
        );

        if ((int) $branch->company_id !== (int) $company->id) {
            $this->command->warn('The demo branch code belongs to another company; no membership was written.');

            return;
        }

        $staff = User::query()->where('role', 'staff')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('business_memberships')
                    ->whereColumn('business_memberships.user_id', 'users.id')
                    ->where('business_memberships.is_active', true);
            })
            ->orderBy('id')->first();
        if ($staff === null) {
            $this->command->warn('No unassigned staff account is available for the demo branch.');

            return;
        }

        $ownerMembership = BusinessMembership::query()->where('company_id', $company->id)
            ->where('role', 'owner')->where('is_active', true)->whereNull('branch_id')->first();
        if (! $ownerMembership) {
            $this->command->warn('Assign the primary Shop Owner before creating a demo manager membership.');

            return;
        }

        app(BusinessMembershipService::class)->assign(
            $ownerMembership->user,
            $staff,
            $company,
            (int) $branch->id,
            BusinessAccessService::MANAGER,
        );

        $this->command->info("Local demo company and branch are ready; '{$staff->email}' is assigned as a branch manager.");
    }
}

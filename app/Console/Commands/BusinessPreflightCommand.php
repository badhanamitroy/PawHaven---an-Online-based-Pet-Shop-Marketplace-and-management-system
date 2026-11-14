<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessPreflightCommand extends Command
{
    protected $signature = 'pawhaven:business-preflight';

    protected $description = 'Read-only check of company, branch, and business membership integrity';

    public function handle(): int
    {
        $required = ['companies', 'branches', 'users', 'business_memberships'];
        $missing = array_values(array_filter($required, fn (string $table): bool => ! Schema::hasTable($table)));
        if ($missing !== []) {
            $this->error('Business schema is incomplete; missing tables: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $companyCount = DB::table('companies')->count();
        $this->line("Companies found: {$companyCount}");
        if ($companyCount !== 1) {
            $this->warn('The installation must resolve to exactly one company before business access is enabled.');
        }

        $checks = [
            'companies without an active Owner' => DB::table('companies as c')
                ->leftJoin('business_memberships as bm', function ($join): void {
                    $join->on('bm.company_id', '=', 'c.id')
                        ->where('bm.role', '=', 'owner')->where('bm.is_active', '=', true)->whereNull('bm.branch_id');
                })->whereNull('bm.id')->count(),
            'companies with multiple active Owners' => DB::table('business_memberships')
                ->where('role', 'owner')->where('is_active', true)->whereNull('branch_id')
                ->select('company_id')->groupBy('company_id')->havingRaw('COUNT(*) > 1')->count(),
            'memberships with a missing Company' => DB::table('business_memberships as bm')
                ->leftJoin('companies as c', 'c.id', '=', 'bm.company_id')->whereNull('c.id')->count(),
            'memberships with a missing or mismatched Branch' => DB::table('business_memberships as bm')
                ->leftJoin('branches as b', 'b.id', '=', 'bm.branch_id')->whereNotNull('bm.branch_id')
                ->where(function ($query): void {
                    $query->whereNull('b.id')->orWhereColumn('b.company_id', '!=', 'bm.company_id');
                })->count(),
            'users with duplicate active memberships' => DB::table('business_memberships')
                ->where('is_active', true)->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->count(),
            'memberships with unsupported or unresolved roles' => DB::table('business_memberships')
                ->whereNotIn('role', ['owner', 'manager', 'seller'])->count(),
            'staff users without a valid active membership' => DB::table('users as u')
                ->leftJoin('business_memberships as bm', function ($join): void {
                    $join->on('bm.user_id', '=', 'u.id')->where('bm.is_active', '=', true)
                        ->whereIn('bm.role', ['owner', 'manager', 'seller']);
                })->where('u.role', 'staff')->whereNull('bm.id')->count(),
            'Owner memberships attached to a Branch' => DB::table('business_memberships')
                ->where('role', 'owner')->whereNotNull('branch_id')->count(),
            'Manager/Seller memberships without a Branch' => DB::table('business_memberships')
                ->whereIn('role', ['manager', 'seller'])->whereNull('branch_id')->count(),
            'active memberships on inactive Companies' => DB::table('business_memberships as bm')
                ->join('companies as c', 'c.id', '=', 'bm.company_id')->where('bm.is_active', true)->where('c.is_active', false)->count(),
            'active memberships on inactive Branches' => DB::table('business_memberships as bm')
                ->join('branches as b', 'b.id', '=', 'bm.branch_id')->where('bm.is_active', true)->where('b.is_active', false)->count(),
            'Products with branch/company mismatch' => $this->branchMismatchCount('products'),
            'Orders with branch/company mismatch' => $this->branchMismatchCount('orders'),
            'Audit logs with branch/company mismatch' => $this->branchMismatchCount('admin_audit_logs'),
            'Invitations with branch/company mismatch' => $this->branchMismatchCount('invitations'),
        ];

        $issues = 0;
        foreach ($checks as $label => $count) {
            $this->line(sprintf('%-55s %d', $label.':', $count));
            $issues += $count;
        }

        if ($issues > 0 || $companyCount !== 1) {
            $this->warn('Review the reported records manually. This command does not modify data.');

            return self::FAILURE;
        }

        $this->info('No business integrity issues were found. No data was changed.');

        return self::SUCCESS;
    }

    private function branchMismatchCount(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id') || ! Schema::hasColumn($table, 'company_id')) {
            return 0;
        }

        return DB::table($table.' as source')->leftJoin('branches as branch', 'branch.id', '=', 'source.branch_id')
            ->whereNotNull('source.branch_id')
            ->where(function ($query): void {
                $query->whereNull('branch.id')->orWhereColumn('branch.company_id', '!=', 'source.company_id');
            })->count();
    }
}

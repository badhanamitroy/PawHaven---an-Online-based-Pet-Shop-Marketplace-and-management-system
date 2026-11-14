<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            throw new RuntimeException("Business integrity guards are not implemented for the [{$driver}] driver.");
        }

        $this->assertSafeCompanyAndMembershipState();

        if (! Schema::hasColumn('business_memberships', 'legacy_role_before_resolution')) {
            Schema::table('business_memberships', function (Blueprint $table): void {
                $table->string('legacy_role_before_resolution', 30)->nullable();
                $table->boolean('legacy_active_before_resolution')->nullable();
            });
        }

        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->string('role', 20)->default('unresolved')->change();
            $table->boolean('is_active')->default(false)->change();
        });

        // The previous migration could create active role='staff' memberships,
        // but historical fields do not reveal the employee's approved position.
        // Preserve their prior state and move them to an inactive unresolved row.
        DB::table('business_memberships')->where('role', 'staff')->get()->each(function (object $row): void {
            DB::table('business_memberships')->where('id', $row->id)->update([
                'legacy_role_before_resolution' => $row->role,
                'legacy_active_before_resolution' => (bool) $row->is_active,
                'role' => 'unresolved',
                'is_active' => false,
                'updated_at' => now(),
            ]);
        });

        if (! Schema::hasColumn('companies', 'installation_guard')) {
            Schema::table('companies', function (Blueprint $table): void {
                $this->addGeneratedColumn($table, 'installation_guard', '1', 'tiny');
                $table->unique('installation_guard', 'companies_single_installation_unique');
            });
        }

        if ($driver === 'sqlsrv') {
            if (! Schema::hasIndex('business_memberships', 'business_memberships_one_active_user_unique')) {
                DB::unprepared("CREATE UNIQUE INDEX business_memberships_one_active_user_unique ON business_memberships (user_id) WHERE is_active = 1 AND role IN ('owner','manager','seller')");
            }
            if (! Schema::hasIndex('business_memberships', 'business_memberships_one_active_owner_unique')) {
                DB::unprepared("CREATE UNIQUE INDEX business_memberships_one_active_owner_unique ON business_memberships (company_id) WHERE is_active = 1 AND role = 'owner' AND branch_id IS NULL");
            }
        } elseif (! Schema::hasColumn('business_memberships', 'active_assignment_user_id')) {
            $activeValue = $driver === 'pgsql' ? 'is_active = true' : 'is_active = 1';
            Schema::table('business_memberships', function (Blueprint $table) use ($activeValue): void {
                $this->addGeneratedColumn($table, 'active_assignment_user_id', "CASE WHEN {$activeValue} AND role IN ('owner', 'manager', 'seller') THEN user_id ELSE NULL END", 'big');
                $this->addGeneratedColumn($table, 'active_owner_company_id', "CASE WHEN {$activeValue} AND role = 'owner' AND branch_id IS NULL THEN company_id ELSE NULL END", 'big');
            });
            Schema::table('business_memberships', function (Blueprint $table): void {
                $table->unique('active_assignment_user_id', 'business_memberships_one_active_user_unique');
                $table->unique('active_owner_company_id', 'business_memberships_one_active_owner_unique');
            });
        }

        if (! Schema::hasIndex('branches', 'branches_id_company_unique')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->unique(['id', 'company_id'], 'branches_id_company_unique');
            });
        }

        if (! Schema::hasForeignKey('business_memberships', 'business_memberships_branch_company_fk')) {
            Schema::table('business_memberships', function (Blueprint $table): void {
                $table->foreign(['branch_id', 'company_id'], 'business_memberships_branch_company_fk')
                    ->references(['id', 'company_id'])->on('branches')->restrictOnDelete();
            });
        }

        $this->addBranchCompanyForeignKeys();

        $this->createMembershipGuards($driver);
        $this->addInvitationIntegrityKey();
        $this->createInvitationGuard($driver);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        $this->dropTriggers($driver);

        if (Schema::hasColumn('business_memberships', 'legacy_role_before_resolution')) {
            DB::table('business_memberships')
                ->where('role', 'unresolved')
                ->whereNotNull('legacy_role_before_resolution')
                ->update([
                    'role' => DB::raw('legacy_role_before_resolution'),
                    'is_active' => DB::raw('legacy_active_before_resolution'),
                    'updated_at' => now(),
                ]);
            DB::table('business_memberships')
                ->where('role', 'unresolved')
                ->whereNull('legacy_role_before_resolution')
                ->update([
                    'role' => 'staff',
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }
        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->string('role', 20)->default('staff')->change();
            $table->boolean('is_active')->default(true)->change();
        });

        if (Schema::hasTable('business_memberships')) {
            Schema::table('business_memberships', function (Blueprint $table): void {
                $table->dropForeign(['branch_id', 'company_id']);
            });
        }
        foreach ([
            'products' => 'products_branch_company_fk',
            'orders' => 'orders_branch_company_fk',
            'admin_audit_logs' => 'admin_audit_logs_branch_company_fk',
            'invitations' => 'invitations_branch_company_fk',
        ] as $tableName => $constraint) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id') && Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign(['branch_id', 'company_id']));
            }
        }
        if (Schema::hasIndex('branches', 'branches_id_company_unique')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropUnique('branches_id_company_unique');
            });
        }
        if ($driver === 'sqlsrv') {
            DB::unprepared('DROP INDEX IF EXISTS business_memberships_one_active_user_unique ON business_memberships');
            DB::unprepared('DROP INDEX IF EXISTS business_memberships_one_active_owner_unique ON business_memberships');
        }
        if (Schema::hasColumn('business_memberships', 'active_assignment_user_id')) {
            Schema::table('business_memberships', function (Blueprint $table): void {
                $table->dropUnique('business_memberships_one_active_user_unique');
                $table->dropUnique('business_memberships_one_active_owner_unique');
                $table->dropColumn(['active_assignment_user_id', 'active_owner_company_id']);
            });
        }
        if (Schema::hasColumn('companies', 'installation_guard')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropUnique('companies_single_installation_unique');
                $table->dropColumn('installation_guard');
            });
        }
        if (Schema::hasColumn('business_memberships', 'legacy_role_before_resolution')) {
            Schema::table('business_memberships', function (Blueprint $table): void {
                $table->dropColumn(['legacy_role_before_resolution', 'legacy_active_before_resolution']);
            });
        }
        if (Schema::hasColumn('invitations', 'active_email_key')) {
            Schema::table('invitations', function (Blueprint $table): void {
                $table->dropUnique('invitations_active_email_unique');
                $table->dropColumn('active_email_key');
            });
        }
    }

    private function assertSafeCompanyAndMembershipState(): void
    {
        if (DB::table('companies')->count() > 1) {
            throw new RuntimeException('Business integrity migration stopped: multiple companies require manual installation review.');
        }

        $unknownRole = DB::table('business_memberships')
            ->whereNotIn('role', ['owner', 'manager', 'seller', 'staff', 'unresolved'])->exists();
        if ($unknownRole) {
            throw new RuntimeException('Business integrity migration stopped: unsupported membership roles require manual review.');
        }

        $invalidBranch = DB::table('business_memberships as bm')
            ->leftJoin('branches as b', 'b.id', '=', 'bm.branch_id')
            ->whereNotNull('bm.branch_id')
            ->where(function ($query): void {
                $query->whereNull('b.id')->orWhereColumn('b.company_id', '!=', 'bm.company_id');
            })->exists();
        if ($invalidBranch) {
            throw new RuntimeException('Business integrity migration stopped: branch/company membership mismatches require manual review.');
        }

        $invalidRoleScope = DB::table('business_memberships')
            ->where(function ($query): void {
                $query->where(fn ($owner) => $owner->where('role', 'owner')->whereNotNull('branch_id'))
                    ->orWhere(fn ($employee) => $employee->whereIn('role', ['manager', 'seller'])->whereNull('branch_id'));
            })->exists();
        if ($invalidRoleScope) {
            throw new RuntimeException('Business integrity migration stopped: role/branch mismatches require manual review.');
        }

        $duplicateMembership = DB::table('business_memberships')
            ->select('user_id')->where('is_active', true)
            ->whereIn('role', ['owner', 'manager', 'seller'])
            ->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicateMembership) {
            throw new RuntimeException('Business integrity migration stopped: users with multiple active business memberships require manual review.');
        }

        $ownerCounts = DB::table('business_memberships')->where('role', 'owner')
            ->where('is_active', true)->whereNull('branch_id')
            ->select('company_id')->groupBy('company_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($ownerCounts) {
            throw new RuntimeException('Business integrity migration stopped: multiple active Owners require manual review.');
        }

        foreach (['products', 'orders', 'admin_audit_logs', 'invitations'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id') && Schema::hasColumn($tableName, 'company_id')) {
                $this->assertBranchCompanyAssignments($tableName);
            }
        }

        $inactiveCompanyAssignment = DB::table('business_memberships as bm')
            ->join('companies as c', 'c.id', '=', 'bm.company_id')
            ->where('bm.is_active', true)->where('c.is_active', false)->exists();
        $inactiveBranchAssignment = DB::table('business_memberships as bm')
            ->join('branches as b', 'b.id', '=', 'bm.branch_id')
            ->where('bm.is_active', true)->where('b.is_active', false)->exists();
        if ($inactiveCompanyAssignment || $inactiveBranchAssignment) {
            throw new RuntimeException('Business integrity migration stopped: active memberships on inactive companies or branches require manual review.');
        }

        $now = now();
        $pendingInvitations = DB::table('invitations')->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', $now)->orderBy('company_id')->orderBy('id')->get(['company_id', 'email']);
        $seenInvitationKeys = [];
        foreach ($pendingInvitations as $invitation) {
            $key = $invitation->company_id.':'.hash('sha256', mb_strtolower(trim($invitation->email)));
            if (isset($seenInvitationKeys[$key])) {
                throw new RuntimeException('Business integrity migration stopped: duplicate pending invitations require manual review.');
            }
            $seenInvitationKeys[$key] = true;
        }
    }

    private function assertBranchCompanyAssignments(string $tableName): void
    {
        $invalid = DB::table($tableName.' as source')
            ->leftJoin('branches as branch', 'branch.id', '=', 'source.branch_id')
            ->whereNotNull('source.branch_id')
            ->where(function ($query): void {
                $query->whereNull('branch.id')->orWhereColumn('branch.company_id', '!=', 'source.company_id');
            })->exists();
        if ($invalid) {
            throw new RuntimeException("Business integrity migration stopped: {$tableName} contains branch/company mismatches requiring manual review.");
        }
    }

    private function addInvitationIntegrityKey(): void
    {
        if (! Schema::hasColumn('invitations', 'active_email_key')) {
            Schema::table('invitations', function (Blueprint $table): void {
                $table->char('active_email_key', 64)->nullable();
            });
        }

        $now = now();
        $pending = DB::table('invitations')
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', $now)
            ->orderBy('id')->get(['id', 'company_id', 'email']);
        $seen = [];
        foreach ($pending as $invitation) {
            $key = hash('sha256', mb_strtolower(trim($invitation->email)));
            $scopedKey = $invitation->company_id.':'.$key;
            if (isset($seen[$scopedKey])) {
                throw new RuntimeException('Business integrity migration stopped: duplicate pending invitations require manual review.');
            }
            $seen[$scopedKey] = true;
            DB::table('invitations')->where('id', $invitation->id)->update(['active_email_key' => $key]);
        }

        if (! Schema::hasIndex('invitations', 'invitations_active_email_unique')) {
            Schema::table('invitations', function (Blueprint $table): void {
                $table->unique(['company_id', 'active_email_key'], 'invitations_active_email_unique');
            });
        }
    }

    private function createMembershipGuards(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS business_memberships_guard_insert
BEFORE INSERT ON business_memberships
FOR EACH ROW
WHEN NEW.role NOT IN ('owner', 'manager', 'seller', 'unresolved')
  OR (NEW.role = 'unresolved' AND (NEW.is_active <> 0 OR NEW.branch_id IS NOT NULL))
  OR (NEW.role = 'owner' AND NEW.branch_id IS NOT NULL)
  OR (NEW.role IN ('manager', 'seller') AND NEW.branch_id IS NULL)
  OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id))
  OR (NEW.is_active = 1 AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.id = NEW.company_id AND c.is_active = 1))
  OR (NEW.is_active = 1 AND NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id AND b.is_active = 1))
BEGIN
    SELECT RAISE(ABORT, 'Invalid business membership role, company, or branch assignment');
END;
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS business_memberships_guard_update
BEFORE UPDATE ON business_memberships
FOR EACH ROW
WHEN NEW.role NOT IN ('owner', 'manager', 'seller', 'unresolved')
  OR (NEW.role = 'unresolved' AND (NEW.is_active <> 0 OR NEW.branch_id IS NOT NULL))
  OR (NEW.role = 'owner' AND NEW.branch_id IS NOT NULL)
  OR (NEW.role IN ('manager', 'seller') AND NEW.branch_id IS NULL)
  OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id))
  OR (NEW.is_active = 1 AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.id = NEW.company_id AND c.is_active = 1))
  OR (NEW.is_active = 1 AND NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id AND b.is_active = 1))
BEGIN
    SELECT RAISE(ABORT, 'Invalid business membership role, company, or branch assignment');
END;
SQL);
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->createMysqlMembershipTrigger('insert', 'INSERT');
            $this->createMysqlMembershipTrigger('update', 'UPDATE');

            return;
        }

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION business_memberships_guard_fn() RETURNS trigger AS $$
BEGIN
    IF NEW.role NOT IN ('owner','manager','seller','unresolved')
      OR (NEW.role = 'unresolved' AND (NEW.is_active <> false OR NEW.branch_id IS NOT NULL))
      OR (NEW.role = 'owner' AND NEW.branch_id IS NOT NULL)
      OR (NEW.role IN ('manager','seller') AND NEW.branch_id IS NULL)
      OR (NEW.is_active = true AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.id = NEW.company_id AND c.is_active = true))
      OR (NEW.is_active = true AND NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id AND b.is_active = true))
    THEN RAISE EXCEPTION 'Invalid business membership role, company, or branch assignment'; END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::unprepared('CREATE TRIGGER business_memberships_guard_insert BEFORE INSERT ON business_memberships FOR EACH ROW EXECUTE FUNCTION business_memberships_guard_fn()');
            DB::unprepared('CREATE TRIGGER business_memberships_guard_update BEFORE UPDATE ON business_memberships FOR EACH ROW EXECUTE FUNCTION business_memberships_guard_fn()');

            return;
        }

        if ($driver === 'sqlsrv') {
            foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $operation) {
                DB::unprepared("CREATE TRIGGER business_memberships_guard_{$suffix} ON business_memberships AFTER {$operation} AS
                    BEGIN
                        SET NOCOUNT ON;
                        IF EXISTS (SELECT 1 FROM inserted i LEFT JOIN companies c ON c.id = i.company_id LEFT JOIN branches b ON b.id = i.branch_id
                            WHERE i.role NOT IN ('owner','manager','seller','unresolved')
                              OR (i.role = 'unresolved' AND (i.is_active <> 0 OR i.branch_id IS NOT NULL))
                              OR (i.role = 'owner' AND i.branch_id IS NOT NULL)
                              OR (i.role IN ('manager','seller') AND i.branch_id IS NULL)
                              OR (i.branch_id IS NOT NULL AND (b.id IS NULL OR b.company_id <> i.company_id))
                              OR (i.is_active = 1 AND (c.id IS NULL OR c.is_active = 0))
                              OR (i.is_active = 1 AND i.branch_id IS NOT NULL AND b.is_active = 0))
                            THROW 51000, 'Invalid business membership role, company, or branch assignment', 1;
                    END");
            }
        }
    }

    private function addBranchCompanyForeignKeys(): void
    {
        foreach ([
            'products' => 'products_branch_company_fk',
            'orders' => 'orders_branch_company_fk',
            'admin_audit_logs' => 'admin_audit_logs_branch_company_fk',
            'invitations' => 'invitations_branch_company_fk',
        ] as $tableName => $constraint) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id') || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            $invalid = DB::table($tableName.' as source')
                ->leftJoin('branches as branch', 'branch.id', '=', 'source.branch_id')
                ->whereNotNull('source.branch_id')
                ->where(function ($query): void {
                    $query->whereNull('branch.id')->orWhereColumn('branch.company_id', '!=', 'source.company_id');
                })->exists();
            if ($invalid) {
                throw new RuntimeException("Business integrity migration stopped: {$tableName} contains branch/company mismatches requiring manual review.");
            }
            if (! Schema::hasForeignKey($tableName, $constraint)) {
                Schema::table($tableName, function (Blueprint $table) use ($constraint): void {
                    $table->foreign(['branch_id', 'company_id'], $constraint)
                        ->references(['id', 'company_id'])->on('branches')->restrictOnDelete();
                });
            }
        }
    }

    private function createMysqlMembershipTrigger(string $suffix, string $operation): void
    {
        DB::unprepared("CREATE TRIGGER business_memberships_guard_{$suffix} BEFORE {$operation} ON business_memberships FOR EACH ROW BEGIN
            IF NEW.role NOT IN ('owner','manager','seller','unresolved')
              OR (NEW.role = 'unresolved' AND (NEW.is_active <> 0 OR NEW.branch_id IS NOT NULL))
              OR (NEW.role = 'owner' AND NEW.branch_id IS NOT NULL)
              OR (NEW.role IN ('manager','seller') AND NEW.branch_id IS NULL)
              OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id))
              OR (NEW.is_active = 1 AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.id = NEW.company_id AND c.is_active = 1))
              OR (NEW.is_active = 1 AND NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id AND b.is_active = 1))
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid business membership role, company, or branch assignment'; END IF;
        END");
    }

    private function createInvitationGuard(string $driver): void
    {
        if (in_array($driver, ['pgsql', 'sqlsrv'], true)) {
            return; // Composite branch/company foreign key provides this guard.
        }
        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS invitations_branch_company_guard_insert
BEFORE INSERT ON invitations
FOR EACH ROW
WHEN NEW.branch_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id
)
BEGIN
    SELECT RAISE(ABORT, 'Invitation branch must belong to its company');
END;
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS invitations_branch_company_guard_update
BEFORE UPDATE ON invitations
FOR EACH ROW
WHEN NEW.branch_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id
)
BEGIN
    SELECT RAISE(ABORT, 'Invitation branch must belong to its company');
END;
SQL);
            return;
        }

        foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $operation) {
            DB::unprepared("CREATE TRIGGER invitations_branch_company_guard_{$suffix} BEFORE {$operation} ON invitations FOR EACH ROW BEGIN
                IF NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches b WHERE b.id = NEW.branch_id AND b.company_id = NEW.company_id)
                THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invitation branch must belong to its company'; END IF;
            END");
        }
    }

    private function dropTriggers(string $driver): void
    {
        $names = [
            'business_memberships_guard_insert',
            'business_memberships_guard_update',
            'invitations_branch_company_guard_insert',
            'invitations_branch_company_guard_update',
        ];
        foreach ($names as $name) {
            if ($driver === 'pgsql') {
                $table = str_starts_with($name, 'business_memberships') ? 'business_memberships' : 'invitations';
                DB::unprepared("DROP TRIGGER IF EXISTS {$name} ON {$table}");
            } else {
                DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            }
        }

        if ($driver === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS business_memberships_guard_fn()');
        }
    }

    private function addGeneratedColumn(Blueprint $table, string $name, string $expression, string $size): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlsrv') {
            $table->computed($name, $expression)->persisted();

            return;
        }

        $column = $size === 'tiny'
            ? $table->unsignedTinyInteger($name)
            : $table->unsignedBigInteger($name);
        if ($driver === 'pgsql') {
            $column->storedAs($expression);
        } else {
            $column->virtualAs($expression);
        }
    }
};

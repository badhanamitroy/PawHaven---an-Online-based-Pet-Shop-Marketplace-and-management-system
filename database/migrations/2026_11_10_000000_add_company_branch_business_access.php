<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('legal_name', 180)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->restrictOnDelete();
            $table->index('company_id');
            $table->string('city', 100)->nullable();
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->restrictOnDelete();
            $table->index('company_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->restrictOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index(['company_id', 'branch_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->restrictOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index(['company_id', 'branch_id']);
        });

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->restrictOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index(['company_id', 'branch_id']);
        });

        Schema::create('business_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('staff');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['user_id', 'company_id', 'is_active']);
            $table->index(['company_id', 'branch_id', 'is_active']);
        });

        $now = now();
        $companyId = DB::table('companies')->where('slug', 'pawhaven')->value('id');
        if ($companyId === null) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'PawHaven',
                'slug' => 'pawhaven',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['branches', 'product_categories', 'products', 'orders'] as $table) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
        }

        // Legacy users.role='staff' does not distinguish Owner, Manager, or
        // Employee Seller. Do not invent an employee position or grant access.
        // Existing users are preserved and must be explicitly assigned later.
    }

    public function down(): void
    {
        Schema::dropIfExists('business_memberships');

        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropIndex(['company_id']);
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropIndex(['company_id']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('city');
        });

        Schema::dropIfExists('companies');
    }
};

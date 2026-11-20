<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
        });

        Schema::create('branch_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('price_minor');
            $table->string('currency', 3);
            $table->timestamps();
            $table->unique(['product_id', 'branch_id']);
            $table->index(['company_id', 'branch_id']);
        });

        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('discount_type', 20);
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('minimum_purchase_minor')->default(0);
            $table->unsignedInteger('maximum_discount_minor')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('sales_channel', 10)->default('both');
            $table->boolean('stack_with_coupon')->default(false);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('currency', 3);
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'is_active', 'starts_at', 'ends_at'], 'promotions_scope_active_idx');
        });

        Schema::create('promotion_products', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'product_id']);
        });
        Schema::create('promotion_categories', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'product_category_id'], 'promotion_categories_pk');
        });

        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 48)->unique();
            $table->string('name', 160);
            $table->string('discount_type', 20);
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('minimum_purchase_minor')->default(0);
            $table->unsignedInteger('maximum_discount_minor')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->string('sales_channel', 10)->default('both');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('currency', 3);
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'is_active', 'starts_at', 'ends_at'], 'coupons_scope_active_idx');
        });

        Schema::create('coupon_products', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });
        Schema::create('coupon_categories', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_category_id'], 'coupon_categories_pk');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->json('pricing_snapshot')->nullable();
            $table->unsignedInteger('promotion_discount_minor')->default(0);
            $table->unsignedInteger('coupon_discount_minor')->default(0);
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('base_price_minor')->nullable();
            $table->unsignedInteger('resolved_price_minor')->nullable();
            $table->unsignedInteger('promotion_discount_minor')->default(0);
            $table->unsignedInteger('coupon_discount_minor')->default(0);
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('discount_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_key', 64)->nullable();
            $table->string('coupon_code_snapshot', 48)->nullable();
            $table->unsignedInteger('promotion_discount_minor')->default(0);
            $table->unsignedInteger('coupon_discount_minor')->default(0);
            $table->string('currency', 3);
            $table->timestamps();
            $table->index(['coupon_id', 'customer_user_id']);
            $table->index(['coupon_id', 'customer_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['base_price_minor', 'resolved_price_minor', 'promotion_discount_minor', 'coupon_discount_minor']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['pricing_snapshot', 'promotion_discount_minor', 'coupon_discount_minor']);
        });
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotion_categories');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('branch_product_prices');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('archived_at'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('employee_seller_id')
                ->nullable()
                ->after('seller_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('sales_channel', 20)->default('online')->after('employee_seller_id');
            $table->index(['employee_seller_id', 'sales_channel'], 'orders_employee_seller_channel_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_employee_seller_channel_index');
            $table->dropConstrainedForeignId('employee_seller_id');
            $table->dropColumn('sales_channel');
        });
    }
};

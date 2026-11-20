<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('pos_submission_key', 36)->nullable();
            $table->unique('pos_submission_key', 'orders_pos_submission_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_pos_submission_key_unique');
            $table->dropColumn('pos_submission_key');
        });
    }
};

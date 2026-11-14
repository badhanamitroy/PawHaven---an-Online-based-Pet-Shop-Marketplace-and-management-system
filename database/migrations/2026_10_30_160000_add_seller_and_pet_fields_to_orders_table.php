<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('pet_listing_id')
                ->nullable()
                ->after('seller_id')
                ->constrained('pet_listings')
                ->nullOnDelete();

            $table->string('payment_reference', 100)
                ->nullable()
                ->after('payment_status');
        });

        Schema::create('saved_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('pet_listing_id')
                ->constrained('pet_listings')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'pet_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_pets');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropForeign(['pet_listing_id']);
            $table->dropColumn(['seller_id', 'pet_listing_id', 'payment_reference']);
        });
    }
};

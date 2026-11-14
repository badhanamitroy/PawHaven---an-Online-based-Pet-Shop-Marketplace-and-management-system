<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('pet_category_id')
                ->constrained('pet_categories')
                ->restrictOnDelete();
            $table->foreignId('breed_id')
                ->nullable()
                ->constrained('breeds')
                ->nullOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('listing_type', 20);
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['pet_category_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_listing_id')
                ->constrained('pet_listings')
                ->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['pet_listing_id', 'position']);
        });

        Schema::create('listing_moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_listing_id')
                ->nullable()
                ->constrained('pet_listings')
                ->nullOnDelete();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['pet_listing_id', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_moderation_actions');
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('pet_listings');
    }
};

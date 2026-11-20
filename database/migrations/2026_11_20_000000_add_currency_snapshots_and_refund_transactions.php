<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing integer-minor amounts were USD throughout the application.
        // The explicit default snapshots that history without converting values.
        Schema::table('products', function (Blueprint $table): void {
            $table->char('currency', 3)->default('USD');
        });
        Schema::table('pet_listings', function (Blueprint $table): void {
            $table->char('currency', 3)->default('USD');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->char('currency', 3)->default('USD');
        });

        Schema::create('payment_refund_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->restrictOnDelete();
            $table->string('submission_key', 36)->unique();
            $table->string('original_transaction_reference', 120)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('requested');
            $table->text('reason');
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['payment_transaction_id', 'status'], 'payment_refunds_payment_status_index');
        });

        Schema::create('payment_refund_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_refund_transaction_id')->constrained('payment_refund_transactions')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payment_refund_transaction_id', 'created_at'], 'payment_refund_events_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refund_events');
        Schema::dropIfExists('payment_refund_transactions');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('currency'));
        Schema::table('pet_listings', fn (Blueprint $table) => $table->dropColumn('currency'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('currency'));
    }
};

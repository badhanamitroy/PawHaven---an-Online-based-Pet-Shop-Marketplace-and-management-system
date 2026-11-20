<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('submission_key', 36)->nullable()->unique();
            $table->string('transaction_reference', 120)->nullable();
            $table->char('reference_hash', 64)->nullable()->unique();
            $table->string('payment_method', 30);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('submitted');
            $table->string('source', 20);
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status'], 'payment_transactions_order_status_index');
            $table->index(['payment_method', 'transaction_reference'], 'payment_transactions_method_reference_index');
        });

        Schema::create('payment_transaction_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['payment_transaction_id', 'created_at'], 'payment_transaction_events_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction_events');
        Schema::dropIfExists('payment_transactions');
    }
};

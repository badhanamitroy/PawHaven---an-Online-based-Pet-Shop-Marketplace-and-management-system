<?php

use App\Enums\FulfilmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fulfilment_status', 32)->default(FulfilmentStatus::AwaitingConfirmation->value);
            $table->string('delivery_method', 32)->nullable();
            $table->string('delivery_provider', 120)->nullable();
            $table->string('tracking_reference', 120)->nullable();
            $table->text('packing_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('picking_started_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('delivery_attempt_count')->default(0);
            $table->text('delivery_failed_reason')->nullable();
            $table->index(['company_id', 'branch_id', 'fulfilment_status'], 'orders_business_fulfilment_status_index');
        });

        Schema::create('order_fulfilment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('submission_key', 36)->nullable()->unique('order_fulfilment_events_submission_key_unique');
            $table->char('payload_hash', 64)->nullable();
            $table->text('internal_note')->nullable();
            $table->text('buyer_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at'], 'order_fulfilment_events_order_history_index');
            $table->index(['company_id', 'branch_id', 'to_status', 'created_at'], 'order_fulfilment_events_business_status_index');
        });

        $now = now();
        DB::table('orders')->select(['id', 'company_id', 'branch_id', 'status'])->orderBy('id')->chunkById(500, function ($orders) use ($now): void {
            foreach ($orders as $order) {
                $status = match ($order->status) {
                    'processing' => FulfilmentStatus::Confirmed->value,
                    'shipped' => FulfilmentStatus::Dispatched->value,
                    'delivered' => FulfilmentStatus::Delivered->value,
                    'cancelled' => FulfilmentStatus::Cancelled->value,
                    default => FulfilmentStatus::AwaitingConfirmation->value,
                };
                DB::table('orders')->where('id', $order->id)->update(['fulfilment_status' => $status]);
                // Marketplace and legacy pet-listing orders can have no company scope and do
                // not participate in the internal branch fulfilment event stream.
                if ($order->company_id === null) {
                    continue;
                }

                DB::table('order_fulfilment_events')->insert([
                    'order_id' => $order->id,
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'actor_user_id' => null,
                    'event_type' => 'legacy_backfill',
                    'from_status' => null,
                    'to_status' => $status,
                    'submission_key' => null,
                    'payload_hash' => null,
                    'internal_note' => 'Fulfilment state imported from the existing order status; the original transition time and actor were not recorded.',
                    'buyer_message' => null,
                    'created_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfilment_events');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_business_fulfilment_status_index');
            $table->dropColumn([
                'fulfilment_status', 'delivery_method', 'delivery_provider', 'tracking_reference',
                'packing_notes', 'confirmed_at', 'picking_started_at', 'picked_at', 'packed_at',
                'ready_at', 'dispatched_at', 'out_for_delivery_at', 'delivery_failed_at', 'delivered_at',
                'delivery_attempt_count', 'delivery_failed_reason',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('online_submission_key', 36)->nullable();
            $table->unique('online_submission_key', 'orders_online_submission_key_unique');
        });

        Schema::create('product_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('activity_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('submission_key', 36)->unique();
            $table->string('receipt_submission_key', 36)->nullable()->unique();
            $table->string('status', 20)->default('requested');
            $table->unsignedInteger('requested_quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('resellable_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->text('reason');
            $table->text('review_note')->nullable();
            $table->text('receipt_note')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['order_item_id', 'status'], 'product_returns_item_status_index');
            $table->index(['company_id', 'branch_id', 'status'], 'product_returns_business_status_index');
        });

        Schema::create('product_return_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_return_id')->constrained('product_returns')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_return_id', 'created_at'], 'product_return_events_history_index');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            // branch_id is the stock bucket; null means shared company stock.
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            // activity_branch_id records the branch that originated an order/return movement.
            $table->foreignId('activity_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_return_id')->nullable()->constrained('product_returns')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_after')->nullable();
            $table->string('reference_key', 180)->unique();
            $table->text('note');
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'created_at'], 'inventory_movements_stock_scope_index');
            $table->index(['company_id', 'activity_branch_id', 'created_at'], 'inventory_movements_activity_scope_index');
            $table->index(['product_id', 'created_at'], 'inventory_movements_product_history_index');
            $table->index(['movement_type', 'created_at'], 'inventory_movements_type_history_index');
            $table->index(['order_item_id', 'movement_type'], 'inventory_movements_order_item_type_index');
        });

        $createdAt = now();
        foreach (DB::table('products')->orderBy('id')->get(['id', 'company_id', 'branch_id', 'stock_quantity']) as $product) {
            $reservedItems = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.product_id', $product->id)
                ->whereNotNull('orders.inventory_reserved_at')
                ->orderBy('order_items.id')
                ->get([
                    'order_items.id as order_item_id',
                    'order_items.order_id',
                    'order_items.quantity',
                    'orders.branch_id as activity_branch_id',
                    'orders.sales_channel',
                    'orders.employee_seller_id',
                ]);
            $openingQuantity = (int) $product->stock_quantity + (int) $reservedItems->sum('quantity');

            DB::table('inventory_movements')->insert([
                'product_id' => $product->id,
                'company_id' => $product->company_id,
                'branch_id' => $product->branch_id,
                'activity_branch_id' => null,
                'movement_type' => 'opening',
                'quantity_delta' => $openingQuantity,
                'quantity_after' => $openingQuantity,
                'reference_key' => 'opening:product:'.$product->id,
                'note' => 'Opening stock baseline captured at migration; historical stock actor unavailable.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $quantityAfter = $openingQuantity;
            foreach ($reservedItems as $item) {
                $quantity = (int) $item->quantity;
                $quantityAfter -= $quantity;
                DB::table('inventory_movements')->insert([
                    'product_id' => $product->id,
                    'company_id' => $product->company_id,
                    'branch_id' => $product->branch_id,
                    'activity_branch_id' => $item->activity_branch_id,
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->order_item_id,
                    'actor_user_id' => $item->sales_channel === 'pos' ? $item->employee_seller_id : null,
                    'movement_type' => $item->sales_channel === 'pos' ? 'pos_reservation' : 'online_reservation',
                    'quantity_delta' => -$quantity,
                    'quantity_after' => max(0, $quantityAfter),
                    'reference_key' => 'legacy-reservation:order-item:'.$item->order_item_id,
                    'note' => 'Open order reservation backfilled from existing inventory marker.',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('product_return_events');
        Schema::dropIfExists('product_returns');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_online_submission_key_unique');
            $table->dropColumn('online_submission_key');
        });
    }
};

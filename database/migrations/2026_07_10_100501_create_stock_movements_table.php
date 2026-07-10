<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->enum('source', [
                // IN sources
                'purchase', 'warehouse_in', 'transfer_in',
                // OUT sources
                'usage', 'transfer_out', 'loss_damage',
                'return_supplier', 'warehouse_out', 'sale_other', 'adjustment',
            ]);
            $table->nullableMorphs('reference'); // DR / WS / TS
            $table->string('dr_ws_no')->nullable();
            $table->string('issued_to')->nullable();
            $table->decimal('quantity', 12, 2);       // always positive
            $table->decimal('balance_after', 12, 2);
            $table->date('movement_date');
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['site_id', 'item_variant_id', 'movement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

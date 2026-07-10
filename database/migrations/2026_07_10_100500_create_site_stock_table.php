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
        Schema::create('site_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->decimal('min_qty', 12, 2)->default(0);
            $table->decimal('max_qty', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['site_id', 'item_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_stock');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('label')->nullable();          // e.g. "12mm x 6m", "1/2 inch"
            $table->json('attributes')->nullable();       // {size, grade, brand, length...}
            $table->string('barcode')->nullable()->unique(); // barcode/QR; OPTIONAL, per variant
            $table->string('uom')->nullable();            // overrides item.uom if set
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variants');
    }
};

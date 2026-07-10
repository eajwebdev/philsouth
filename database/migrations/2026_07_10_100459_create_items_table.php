<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->string('uom');
            $table->string('category')->nullable();
            $table->boolean('has_variants')->default(false); // true = stocked by multiple variants
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // NOTE: barcode lives on item_variants — every stocked unit is a variant.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

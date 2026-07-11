<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen `source` from a fixed enum (supplier / other_project) to a string so
     * receipts can record stock that came from neither a supplier nor another
     * site — e.g. a generic "other" source with a free-text description.
     */
    public function up(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->string('source', 30)->default('supplier')->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->enum('source', ['supplier', 'other_project'])->change();
        });
    }
};

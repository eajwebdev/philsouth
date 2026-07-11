<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Moving-average unit cost of the on-hand balance at each site.
        Schema::table('site_stock', function (Blueprint $table) {
            $table->decimal('avg_cost', 14, 4)->default(0)->after('balance');
        });

        // Unit cost captured on each movement (purchase cost in, avg cost out).
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 4)->nullable()->after('quantity');
        });

        // Purchase cost entered per line when receiving.
        Schema::table('delivery_receipt_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('site_stock', fn (Blueprint $t) => $t->dropColumn('avg_cost'));
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('delivery_receipt_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
    }
};

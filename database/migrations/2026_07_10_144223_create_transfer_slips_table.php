<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_slips', function (Blueprint $table) {
            $table->id();
            $table->string('ts_no')->unique();
            $table->foreignId('from_site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('to_site_id')->constrained('sites')->cascadeOnDelete();
            $table->date('date');
            $table->string('time_delivered')->nullable();
            $table->string('delivered_to')->nullable();
            $table->string('delivered_by')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->enum('status', ['draft', 'in_transit', 'received', 'cancelled'])->default('draft');
            $table->date('date_received')->nullable();
            $table->string('time_received')->nullable();
            $table->string('received_by')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_slips');
    }
};

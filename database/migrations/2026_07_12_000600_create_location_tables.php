<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a stock-affecting action physically happened. Coordinates are
        // nullable: capture never blocks the action, so we record the reason
        // instead (denied / unsupported / insecure / timeout).
        Schema::create('location_stamps', function (Blueprint $table) {
            $table->id();
            $table->morphs('stampable');                 // DR / WS / TS / Site (count)
            $table->string('action');                    // posted, released, dispatched, received, counted
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->string('unavailable_reason')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });

        // Employee arrival at a site, with GPS.
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->string('unavailable_reason')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('location_stamps');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_slips', function (Blueprint $table) {
            $table->id();
            $table->string('ws_no')->unique();
            $table->string('project_code')->nullable();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('time')->nullable();
            $table->enum('requested_by_type', ['subcon', 'group_a', 'group_b', 'others']);
            $table->string('requested_by_other')->nullable();
            $table->string('delivered_to')->nullable();
            $table->string('remarks')->nullable();
            $table->enum('status', [
                'draft', 'pending_approval', 'approved',
                'released', 'received', 'rejected', 'cancelled',
            ])->default('draft');
            $table->foreignId('prepared_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->string('received_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_slips');
    }
};

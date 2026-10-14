<?php

declare(strict_types=1);

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
        Schema::create('jarvis_tool_calls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->nullable()->constrained('jarvis_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('jarvis_devices')->nullOnDelete();
            $table->string('tool', 128);
            $table->string('target', 16)->default('device');
            $table->string('status', 32)->default('pending')->index();
            $table->json('arguments')->nullable();
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
};

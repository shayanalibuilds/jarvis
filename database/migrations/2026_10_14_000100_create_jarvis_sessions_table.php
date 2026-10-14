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
        Schema::create('jarvis_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('idle')->index();
            $table->string('agent', 64)->default('jarvis');
            $table->string('conversation_id', 36)->nullable()->index();
            $table->string('current_tool')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('taken_over_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
};

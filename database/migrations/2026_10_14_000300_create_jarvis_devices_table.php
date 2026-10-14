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
        Schema::create('jarvis_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 32)->default('unknown');
            $table->string('token', 80)->unique();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
};

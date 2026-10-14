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
        Schema::create('jarvis_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('toolkit', 64);
            $table->string('name')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('auth_config_id')->nullable();
            $table->string('connected_account_id')->nullable()->index();
            $table->timestamp('connected_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'toolkit']);
        });
    }
};

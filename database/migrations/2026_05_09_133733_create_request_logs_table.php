<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('method', 8);
            $table->string('route');
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->boolean('success');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_hash', 64);
            $table->string('user_agent', 1024)->nullable();
            $table->text('command')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['api_key_id', 'created_at']);
            $table->index(['route', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};

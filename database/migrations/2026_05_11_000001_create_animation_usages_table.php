<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animation_usages', function (Blueprint $table): void {
            $table->id();
            $table->string('animation', 32)->index();
            $table->string('anon_token', 36)->index();
            $table->timestamp('started_at')->index();
            $table->string('country_iso', 2)->nullable()->index();
            $table->string('country', 64)->nullable();
            $table->string('city', 96)->nullable();
            $table->timestamps();
            $table->index(['anon_token', 'animation', 'started_at'], 'idx_cooldown');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animation_usages');
    }
};

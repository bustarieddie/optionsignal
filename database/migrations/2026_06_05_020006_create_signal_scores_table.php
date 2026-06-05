<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-component breakdown of a signal's confidence score (one row per
        // scoring component) — powers the "why grade A?" admin/analytics view.
        Schema::create('signal_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signal_id')->constrained()->cascadeOnDelete();
            // ema_crossover | rsi | vwap | volume | htf | sr
            $table->string('component', 30);
            $table->smallInteger('points')->default(0);
            $table->json('detail')->nullable();
            $table->timestamps();

            $table->index('signal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_scores');
    }
};

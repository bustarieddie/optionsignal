<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            // buy_call | buy_put | exit
            $table->string('rule_type', 20);
            // Which scoring component this rule maps to (ema_crossover, rsi, vwap, volume, htf, sr) — nullable for descriptive-only rules.
            $table->string('component', 30)->nullable();
            $table->string('condition_key', 255);
            $table->string('operator', 10)->nullable();
            $table->string('value')->nullable();
            // Optional per-rule point override (else config/signals weight is used).
            $table->unsignedSmallInteger('points')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['strategy_id', 'rule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_rules');
    }
};

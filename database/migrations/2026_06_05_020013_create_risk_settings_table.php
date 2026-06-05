<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('max_daily_loss', 6, 2)->default(3.00);
            $table->unsignedSmallInteger('max_trades_per_day')->default(5);
            $table->decimal('risk_per_trade_pct', 6, 2)->default(2.00);
            $table->decimal('max_position_size', 8, 2)->default(10.00);
            $table->decimal('stop_loss_pct', 6, 2)->default(25.00);
            $table->decimal('take_profit_pct', 6, 2)->default(40.00);
            $table->unsignedSmallInteger('cooldown_minutes_after_loss')->default(30);
            $table->string('no_trade_window_start', 5)->nullable();
            $table->string('no_trade_window_end', 5)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_settings');
    }
};

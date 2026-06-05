<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('watchlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tradingview_webhook_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticker', 10);
            $table->string('timeframe', 10);
            // buy_call | buy_put | exit
            $table->string('signal_type', 20);
            $table->decimal('price', 12, 4)->nullable();
            $table->decimal('ema9', 12, 4)->nullable();
            $table->decimal('ema21', 12, 4)->nullable();
            $table->decimal('rsi', 8, 4)->nullable();
            $table->decimal('rsi_ma', 8, 4)->nullable();
            $table->decimal('vwap', 12, 4)->nullable();
            $table->string('volume_status', 20)->nullable();
            // A+ | A | B | C | ignore
            $table->string('grade', 6)->default('ignore');
            $table->unsignedSmallInteger('total_score')->default(0);
            // active | expired | acted
            $table->string('status', 12)->default('active');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['ticker', 'occurred_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};

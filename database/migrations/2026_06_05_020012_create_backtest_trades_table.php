<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parsed individual trades from a backtest import; metrics aggregate these.
        Schema::create('backtest_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_id')->constrained()->cascadeOnDelete();
            $table->string('ticker', 10)->nullable();
            $table->string('timeframe', 10)->nullable();
            $table->string('direction', 4)->nullable();
            $table->decimal('entry', 12, 4)->nullable();
            $table->decimal('exit', 12, 4)->nullable();
            $table->decimal('pnl', 14, 2)->nullable();
            $table->string('grade', 6)->nullable();
            $table->string('setup')->nullable();
            // win | loss | be
            $table->string('result', 4)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index('backtest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_trades');
    }
};

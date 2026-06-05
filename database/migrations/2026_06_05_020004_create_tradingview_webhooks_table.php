<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw landing zone for every inbound TradingView webhook. Intentionally
        // FK-free so even a rejected/invalid payload is always recorded.
        Schema::create('tradingview_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 10)->nullable();
            $table->string('timeframe', 10)->nullable();
            $table->string('signal', 20)->nullable();
            $table->json('raw_payload');
            $table->string('idempotency_hash', 64)->unique();
            $table->string('source_ip', 45)->nullable();
            $table->boolean('secret_valid')->default(false);
            // received | processed | rejected | duplicate
            $table->string('status', 20)->default('received');
            $table->string('reject_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('ticker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tradingview_webhooks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Decision-support criteria only — NO broker / option-chain integration.
        Schema::create('option_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signal_id')->constrained()->cascadeOnDelete();
            // call | put
            $table->string('contract_type', 4);
            $table->decimal('suggested_delta_min', 4, 2)->nullable();
            $table->decimal('suggested_delta_max', 4, 2)->nullable();
            $table->string('suggested_expiry')->nullable();
            $table->text('spread_note')->nullable();
            $table->text('liquidity_note')->nullable();
            $table->text('risk_note')->nullable();
            $table->timestamps();

            $table->index('signal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_suggestions');
    }
};

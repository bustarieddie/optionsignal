<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticker', 10);
            // call | put
            $table->string('direction', 4);
            $table->string('contract_details')->nullable();
            $table->string('setup_name')->nullable();
            $table->string('signal_grade', 6)->nullable();
            $table->decimal('entry_price', 12, 4)->nullable();
            $table->decimal('exit_price', 12, 4)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            // open | closed | cancelled
            $table->string('status', 12)->default('open');
            $table->decimal('pnl', 14, 2)->nullable();
            $table->text('reason_for_entry')->nullable();
            $table->text('reason_for_exit')->nullable();
            $table->text('mistake_notes')->nullable();
            $table->text('lessons')->nullable();
            $table->unsignedTinyInteger('emotion_score')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['ticker', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};

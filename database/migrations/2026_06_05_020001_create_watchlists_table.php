<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ticker', 10);
            $table->string('company')->nullable();
            $table->string('sector')->nullable();
            $table->boolean('optionable')->default(true);
            $table->string('preferred_timeframe', 10)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'ticker']);
            $table->index('ticker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};

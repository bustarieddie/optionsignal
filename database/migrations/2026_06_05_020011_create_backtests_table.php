<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // csv | manual
            $table->string('source', 10)->default('csv');
            $table->string('file_path')->nullable();
            // pending | processing | done | failed
            $table->string('status', 12)->default('pending');
            $table->json('metrics')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtests');
    }
};

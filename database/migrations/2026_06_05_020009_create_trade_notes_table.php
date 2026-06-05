<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // user | mcp  (notes created by the MCP create_trade_note tool are tagged "mcp")
            $table->string('source', 10)->default('user');
            $table->timestamps();

            $table->index('trade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_notes');
    }
};

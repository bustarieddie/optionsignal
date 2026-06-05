<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Widen condition_key — MySQL enforces VARCHAR length (SQLite did not),
    // and some seeded rule descriptions exceed the original 60 chars.
    public function up(): void
    {
        Schema::table('strategy_rules', function (Blueprint $table) {
            $table->string('condition_key', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('strategy_rules', function (Blueprint $table) {
            $table->string('condition_key', 60)->change();
        });
    }
};

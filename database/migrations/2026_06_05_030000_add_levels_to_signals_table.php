<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->decimal('atr', 12, 4)->nullable()->after('vwap');
            $table->decimal('stop_loss', 12, 4)->nullable()->after('atr');
            $table->decimal('tp1', 12, 4)->nullable()->after('stop_loss');
            $table->decimal('tp2', 12, 4)->nullable()->after('tp1');
            $table->decimal('tp3', 12, 4)->nullable()->after('tp2');
        });
    }

    public function down(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->dropColumn(['atr', 'stop_loss', 'tp1', 'tp2', 'tp3']);
        });
    }
};

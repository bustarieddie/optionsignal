<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            // Relative-strength leadership vs benchmarks (QQQ + SPY):
            // leading_both | lagging_both | mixed (nullable for legacy rows).
            $table->string('rs_status', 20)->nullable()->after('volume_status');
        });
    }

    public function down(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->dropColumn('rs_status');
        });
    }
};

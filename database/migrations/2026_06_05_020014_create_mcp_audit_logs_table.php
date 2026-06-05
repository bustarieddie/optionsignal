<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail for every MCP tool invocation.
        Schema::create('mcp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('tool_name', 80);
            $table->boolean('is_write')->default(false);
            $table->json('arguments')->nullable();
            // ok | denied | error
            $table->string('result_status', 10)->default('ok');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tool_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_paused_at')->nullable()->after('sla_resolution_due_at');
            $table->unsignedInteger('total_paused_seconds')->default(0)->after('sla_paused_at');
            $table->boolean('sla_response_breached')->default(false)->after('total_paused_seconds');
            $table->boolean('sla_resolution_breached')->default(false)->after('sla_response_breached');

            $table->index('sla_response_due_at');
            $table->index('sla_resolution_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['sla_response_due_at']);
            $table->dropIndex(['sla_resolution_due_at']);
            $table->dropColumn([
                'sla_paused_at',
                'total_paused_seconds',
                'sla_response_breached',
                'sla_resolution_breached',
            ]);
        });
    }
};

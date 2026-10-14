<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('ticket_number', 20)->nullable()->unique();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 32);
            $table->string('symptom', 40)->nullable();
            $table->string('priority', 2)->default('P3');
            $table->string('status', 24)->default('baru');
            $table->string('title', 160);
            $table->text('description');
            $table->boolean('critical_confirmed')->default(false);
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_response_due_at')->nullable();   // diisi mesin SLA (Sprint 3)
            $table->timestamp('sla_resolution_due_at')->nullable(); // diisi mesin SLA (Sprint 3)
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('outlet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

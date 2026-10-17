<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Periode kuota: misalnya bulan ini (2026-10-01 ~ 2026-10-31)
            $table->date('period_start');
            $table->date('period_end');
            // Pemakaian aktual (dalam menit untuk jam, unit untuk request)
            $table->unsignedInteger('hours_used')->default(0);    // menit kerja terpakai
            $table->unsignedInteger('requests_used')->default(0); // jumlah tiket yang dibuat
            // Tambahan (add-on) yang dipesan di luar kuota paket
            $table->unsignedInteger('addon_hours')->default(0);
            $table->unsignedInteger('addon_requests')->default(0);
            $table->timestamps();

            $table->unique(['subscription_id', 'period_start'], 'quota_usage_unique');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_usages');
    }
};

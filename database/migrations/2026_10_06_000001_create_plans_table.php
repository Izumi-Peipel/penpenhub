<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 80);
            $table->decimal('price_monthly', 14, 2);
            $table->decimal('price_yearly', 14, 2);
            $table->unsignedSmallInteger('hours_quota');
            $table->unsignedSmallInteger('request_quota');
            $table->decimal('addon_hour_price', 14, 2)->default(0);
            $table->decimal('addon_request_price', 14, 2)->default(0);
            $table->unsignedSmallInteger('uptime_monitor_limit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

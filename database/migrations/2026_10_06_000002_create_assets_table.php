<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->restrictOnDelete(); // null = level brand
            $table->string('type', 32);
            $table->string('name', 120);
            $table->string('endpoint_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

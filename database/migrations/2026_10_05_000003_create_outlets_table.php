<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            // company_id didenormalisasi dari brand supaya global scope tenant murah (tanpa join).
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->text('address')->nullable();
            $table->json('operating_hours')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestamps();

            $table->unique(['brand_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->string('scope_type', 16); // company | brand | outlet
            $table->unsignedBigInteger('scope_id'); // untuk scope company = id perusahaan
            $table->timestamps();

            $table->unique(['user_id', 'company_id', 'role', 'scope_type', 'scope_id'], 'memberships_unique');
            $table->index(['company_id', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};

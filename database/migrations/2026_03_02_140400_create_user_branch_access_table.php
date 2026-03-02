<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branch_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('access_type', 20)->default('assigned');
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
            $table->index(['branch_id', 'access_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branch_access');
    }
};


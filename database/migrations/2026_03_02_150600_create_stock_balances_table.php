<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3)->default(0);
            $table->decimal('reserved_qty', 14, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'branch_id', 'product_id', 'batch_id'], 'stock_balances_org_branch_product_batch_unique');
            $table->index(['organization_id', 'branch_id', 'product_id']);
            $table->index(['organization_id', 'branch_id', 'last_movement_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};


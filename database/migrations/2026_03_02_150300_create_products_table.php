<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 80);
            $table->string('generic_name')->nullable();
            $table->string('strength', 60)->nullable();
            $table->string('dosage_form', 60)->nullable();
            $table->string('manufacturer_name')->nullable();
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('stock_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('purchase_to_stock_factor', 20, 6)->default(1);
            $table->decimal('purchase_price', 14, 4)->default(0);
            $table->decimal('mrp', 14, 4)->default(0);
            $table->decimal('selling_price', 14, 4)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->boolean('is_controlled_drug')->default(false);
            $table->boolean('requires_cold_chain')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'name']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};


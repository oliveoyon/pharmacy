<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('sold_qty', 14, 3);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('cost_at_sale', 14, 4)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->decimal('line_total', 14, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sales_invoice_id']);
            $table->index(['organization_id', 'product_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
    }
};


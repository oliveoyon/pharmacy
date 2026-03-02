<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_no', 80);
            $table->date('expiry_date')->nullable();
            $table->decimal('received_qty', 14, 3);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->decimal('line_total', 14, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'goods_receipt_id']);
            $table->index(['organization_id', 'product_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};


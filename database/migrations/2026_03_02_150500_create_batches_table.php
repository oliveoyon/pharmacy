<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 80);
            $table->date('expiry_date')->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('received_at')->nullable();
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->decimal('mrp', 14, 4)->default(0);
            $table->decimal('selling_price', 14, 4)->default(0);
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'product_id', 'batch_no', 'expiry_date'], 'batches_org_product_batch_expiry_unique');
            $table->index(['organization_id', 'product_id', 'expiry_date']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};


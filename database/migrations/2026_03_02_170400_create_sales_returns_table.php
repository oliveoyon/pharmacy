<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('return_no', 60);
            $table->string('status', 20)->default('posted');
            $table->decimal('return_total', 14, 4)->default(0);
            $table->text('reason')->nullable();
            $table->timestamp('returned_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'return_no']);
            $table->index(['organization_id', 'sales_invoice_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};


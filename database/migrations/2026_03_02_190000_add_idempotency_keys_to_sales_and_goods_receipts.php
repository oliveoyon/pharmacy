<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('invoice_no');
            $table->unique(['organization_id', 'idempotency_key'], 'sales_invoices_org_idempotency_unique');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->after('grn_no');
            $table->unique(['organization_id', 'idempotency_key'], 'goods_receipts_org_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropUnique('sales_invoices_org_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropUnique('goods_receipts_org_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};


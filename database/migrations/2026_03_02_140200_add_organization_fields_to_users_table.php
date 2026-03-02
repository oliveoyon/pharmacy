<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->boolean('is_platform_user')->default(false)->after('password');
            $table->boolean('is_organization_admin')->default(false)->after('is_platform_user');

            $table->index(['organization_id', 'is_organization_admin']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_organization_id_is_organization_admin_index');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['is_platform_user', 'is_organization_admin']);
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('user_licenses', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['user_email', 'tenant_id']);
            $table->string('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['user_email', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('user_licenses', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['user_email', 'tenant_id']);
            $table->string('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['user_email', 'tenant_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('user_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('user_email');
            // null = licença global (vale em todas as empresas)
            $table->string('tenant_id')->nullable();
            $table->uuid('license_key')->unique();
            // fluxo: solicitada (cadastrada) -> em análise -> ativa | desativada
            $table->enum('status', ['requested', 'in_review', 'active', 'inactive'])->default('requested');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['user_email', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('user_licenses');
    }
};

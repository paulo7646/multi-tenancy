<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de plataforma: configurações da empresa, gestão de documentos, modelos
 * de impressão e assinatura eletrônica. A mesma migration vale para o banco
 * central e para os clientes (database/migrations e database/migrations/tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 60)->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Documentos
        // ------------------------------------------------------------------

        Schema::create('doc_pastas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pai_id')->nullable()->constrained('doc_pastas')->restrictOnDelete();
            $table->string('nome', 100);
            $table->timestamps();
        });

        // o documento; cada envio de arquivo novo é uma versão (doc_versoes)
        Schema::create('doc_arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pasta_id')->nullable()->constrained('doc_pastas')->nullOnDelete();
            $table->string('nome', 200);
            $table->text('descricao')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedBigInteger('versao_atual_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('criado_por', 150)->nullable();
            $table->softDeletes(); // lixeira
            $table->timestamps();

            $table->index('nome');
        });

        Schema::create('doc_versoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arquivo_id')->constrained('doc_arquivos')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('disco', 30);
            $table->string('caminho');
            $table->string('nome_original');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamanho');
            $table->string('hash', 64); // sha256 do conteúdo
            $table->string('comentario')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('criado_por', 150)->nullable();
            $table->timestamps();

            $table->unique(['arquivo_id', 'numero']);
        });

        // documento ligado a um registro de qualquer tabela do dicionário
        Schema::create('doc_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arquivo_id')->constrained('doc_arquivos')->cascadeOnDelete();
            $table->string('tabela', 60);
            $table->unsignedBigInteger('registro_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['arquivo_id', 'tabela', 'registro_id']);
            $table->index(['tabela', 'registro_id']);
        });

        // ------------------------------------------------------------------
        // Impressão
        // ------------------------------------------------------------------

        Schema::create('imp_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('tipo', 10); // lista | registro
            $table->string('tabela', 60)->nullable(); // nome_fisico; vazio = qualquer tabela
            $table->boolean('padrao')->default(false);
            $table->string('papel', 5)->default('a4');
            $table->string('orientacao', 10)->default('portrait'); // portrait | landscape
            $table->string('titulo', 150)->nullable();
            $table->text('corpo')->nullable(); // vazio = layout automático
            $table->boolean('mostrar_cabecalho')->default(true);
            $table->text('rodape')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Assinatura eletrônica
        // ------------------------------------------------------------------

        Schema::create('ass_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->foreignId('versao_id')->constrained('doc_versoes')->restrictOnDelete();
            $table->string('hash_documento', 64); // sha256 do arquivo no momento do envio
            $table->string('status', 15)->default('rascunho'); // rascunho | em_andamento | concluido | cancelado | expirado
            $table->boolean('ordem_obrigatoria')->default(true);
            $table->boolean('exigir_codigo')->default(true); // código de 6 dígitos por e-mail antes de assinar
            $table->timestamp('expira_em')->nullable();
            $table->string('codigo_verificacao', 40)->unique();
            $table->string('tabela', 60)->nullable(); // registro de origem
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->foreignId('certificado_id')->nullable()->constrained('doc_arquivos')->nullOnDelete();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('criado_por', 150)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('ass_assinantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ass_documentos')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nome', 150);
            $table->string('email', 150);
            $table->string('identificacao', 30)->nullable(); // CPF, CNPJ ou outro
            $table->string('papel', 60)->nullable(); // ex.: Contratante, Testemunha
            $table->unsignedInteger('ordem')->default(1);
            $table->string('status', 10)->default('pendente'); // pendente | assinou | recusou
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expira')->nullable();
            $table->string('codigo_hash', 64)->nullable();
            $table->timestamp('codigo_expira')->nullable();
            $table->unsignedSmallInteger('tentativas')->default(0);
            $table->timestamp('notificado_em')->nullable();
            $table->timestamp('assinado_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('hash_no_ato', 64)->nullable(); // hash do documento que o assinante viu
            $table->text('motivo_recusa')->nullable();
            $table->timestamps();
        });

        // trilha de auditoria da assinatura; cada evento inclui o hash do anterior (corrente)
        Schema::create('ass_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('ass_documentos')->cascadeOnDelete();
            $table->foreignId('assinante_id')->nullable()->constrained('ass_assinantes')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('descricao');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('dados')->nullable();
            $table->string('hash_anterior', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['ass_eventos', 'ass_assinantes', 'ass_documentos', 'imp_modelos', 'doc_anexos', 'doc_versoes', 'doc_arquivos', 'doc_pastas', 'plt_configuracoes'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};

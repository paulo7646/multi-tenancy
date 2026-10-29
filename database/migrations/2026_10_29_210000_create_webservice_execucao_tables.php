<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Execução dos webservices: integrações (cada execução de um layout), lotes
 * (transações) e agendamentos. A mesma migration vale para o banco central e
 * para os clientes (database/migrations e database/migrations/tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        // o histórico fica mesmo que a operação ou a versão sejam apagadas
        Schema::create('ws_integracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->nullable()->constrained('ws_operacoes')->nullOnDelete();
            $table->foreignId('versao_id')->nullable()->constrained('ws_versoes')->nullOnDelete();
            $table->unsignedBigInteger('agendamento_id')->nullable();
            $table->string('origem', 15)->default('manual'); // manual | agendamento | recebida
            $table->string('status', 15)->default('pendente'); // pendente | processando | concluida | parcial | erro
            $table->string('formato', 10)->default('json');
            $table->string('arquivo')->nullable(); // caminho do arquivo salvo (json, xml...)
            $table->json('parametros')->nullable(); // valores informados na execução
            $table->text('erro')->nullable();
            $table->unsignedInteger('lotes_total')->default(0);
            $table->unsignedInteger('lotes_erro')->default(0);
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('ws_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracao_id')->constrained('ws_integracoes')->cascadeOnDelete();
            $table->foreignId('objeto_id')->nullable()->constrained('ws_objetos')->nullOnDelete();
            $table->unsignedInteger('numero');
            $table->string('status', 10); // ok | erro
            $table->text('mensagem')->nullable();
            $table->timestamps();

            $table->unique(['integracao_id', 'numero']);
        });

        Schema::create('ws_agendamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('tipo', 15); // codigo_fonte | layout_edi
            $table->string('tabela', 60)->nullable(); // codigo_fonte: tabela do controller
            $table->string('funcao', 100)->nullable(); // codigo_fonte: função do controller
            $table->foreignId('operacao_id')->nullable()->constrained('ws_operacoes')->cascadeOnDelete(); // layout_edi
            $table->string('frequencia', 10)->default('minutos'); // minutos | horas | diario | semanal | cron
            $table->unsignedSmallInteger('intervalo')->nullable();
            $table->string('hora', 5)->nullable();
            $table->unsignedTinyInteger('dia_semana')->nullable();
            $table->string('cron', 100); // calculado a partir da frequência
            $table->timestamp('expira_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultima_execucao')->nullable();
            $table->timestamp('proxima_execucao')->nullable();
            $table->string('ultimo_status', 10)->nullable(); // ok | erro
            $table->text('ultimo_erro')->nullable();
            $table->timestamps();

            $table->index(['ativo', 'proxima_execucao']);
        });
    }

    public function down(): void
    {
        foreach (['ws_agendamentos', 'ws_lotes', 'ws_integracoes'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};

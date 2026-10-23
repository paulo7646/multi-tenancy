<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dicionário de dados, versão 2:
 *  - tipo da tabela (cadastro, lista, conteúdo), itens das listas e ícone/cor da tabela;
 *  - campos sem componente (não aparecem no formulário) e campos do sistema;
 *  - traduções com nome (várias por tabela, de tabela e de lookup);
 *  - componentes do formulário numa tabela filha (md_formulario_componentes);
 *  - ícone sai do módulo (fica nas tabelas);
 *  - nomes físicos em maiúscula. As tabelas físicas são renomeadas pelas
 *    migrations "renomear_tabelas_maiusculas" (central e clientes).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $schema = Schema::connection('central');
        $db = DB::connection('central');

        $schema->table('md_tabelas', function (Blueprint $table) {
            $table->string('tipo', 10)->default('cadastro')->after('banco'); // cadastro | lista | conteudo
            $table->string('icone')->nullable()->after('tipo');
            $table->string('cor', 20)->nullable()->after('icone');
            $table->json('itens_gerados')->nullable()->after('generated_schema'); // itens da última migration de itens
        });

        $schema->table('md_campos', function (Blueprint $table) {
            $table->boolean('sem_componente')->default(false)->after('indice'); // não aparece no formulário
            $table->boolean('sistema')->default(false)->after('sem_componente'); // criado pelo tipo da tabela (lista/conteúdo)
        });

        // Traduções com nome: várias por tabela
        $schema->create('md_tabela_traducoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_tabela_id')->constrained('md_tabelas')->cascadeOnDelete();
            $table->string('tipo', 10); // tabela (datagrid) | lookup (formulário)
            $table->string('nome', 60);
            $table->json('campos'); // uids, em ordem
            $table->boolean('padrao')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        // Itens das tabelas lista/conteúdo: viram o enum do módulo e os registros da tabela
        $schema->create('md_tabela_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_tabela_id')->constrained('md_tabelas')->cascadeOnDelete();
            $table->unsignedInteger('codigo')->nullable(); // lista: sequência fixa
            $table->string('valor', 60)->nullable(); // conteúdo: definido pelo usuário
            $table->string('descricao', 100);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        $schema->create('md_formulario_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_formulario_id')->constrained('md_formularios')->cascadeOnDelete();
            $table->foreignId('pai_id')->nullable()->constrained('md_formulario_componentes')->cascadeOnDelete(); // grupo
            $table->unsignedInteger('pagina')->default(0);
            $table->unsignedInteger('ordem')->default(0);
            $table->string('tipo', 10)->default('campo'); // campo | grupo
            $table->string('campo', 40)->nullable(); // uid do md_campo (ou "situacao")
            $table->string('componente', 20)->nullable(); // tipo do componente (texto, memo, combo...)
            $table->foreignId('traducao_id')->nullable()->constrained('md_tabela_traducoes')->nullOnDelete(); // FK
            $table->string('titulo', 100)->nullable(); // título do grupo
            $table->unsignedTinyInteger('colunas')->default(1);
            $table->unsignedTinyInteger('colunas_grupo')->nullable();
            $table->boolean('quebra')->default(false); // próxima linha
            $table->boolean('completar')->default(false); // alinhar pela sugestão
            $table->boolean('obrigatorio')->nullable(); // null = como no campo
            $table->unsignedSmallInteger('largura')->nullable(); // px
            $table->unsignedSmallInteger('altura')->nullable(); // px
            $table->timestamps();
        });

        $schema->table('md_formularios', function (Blueprint $table) {
            $table->json('paginas')->nullable()->after('colunas'); // títulos das páginas
        });

        // --- traduções antigas (json) viram traduções "Padrão"
        foreach ($db->table('md_tabelas')->get() as $tabela) {
            foreach (['tabela' => $tabela->traducao_tabela, 'lookup' => $tabela->traducao_lookup] as $tipo => $json) {
                $uids = json_decode((string) $json, true) ?: [];

                if ($uids !== []) {
                    $db->table('md_tabela_traducoes')->insert([
                        'md_tabela_id' => $tabela->id, 'tipo' => $tipo, 'nome' => 'Padrão', 'campos' => json_encode($uids),
                        'padrao' => true, 'ordem' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // --- layout (json) vira componentes
        foreach ($db->table('md_formularios')->get() as $formulario) {
            $layout = json_decode((string) $formulario->layout, true) ?: [];
            $titulos = [];

            foreach (array_values($layout) as $p => $pagina) {
                $titulos[] = $pagina['titulo'] ?? 'Dados';

                foreach (array_values($pagina['campos'] ?? []) as $ordem => $elemento) {
                    $id = $this->inserirComponente($formulario->id, null, $p, $ordem, $elemento);

                    foreach (array_values($elemento['campos'] ?? []) as $o => $filho) {
                        $this->inserirComponente($formulario->id, $id, $p, $o, $filho);
                    }
                }
            }

            $db->table('md_formularios')->where('id', $formulario->id)->update(['paginas' => json_encode($titulos ?: ['Dados'])]);
        }

        $schema->table('md_formularios', fn (Blueprint $table) => $table->dropColumn('layout'));
        $schema->table('md_tabelas', fn (Blueprint $table) => $table->dropColumn(['traducao_tabela', 'traducao_lookup']));
        $schema->table('modulos', fn (Blueprint $table) => $table->dropColumn('icone'));

        // --- nomes físicos em maiúscula
        $upper = fn (?string $v) => $v === null ? null : mb_strtoupper($v);

        foreach ($db->table('md_status')->get() as $s) {
            $db->table('md_status')->where('id', $s->id)->update(['nome_fisico' => $upper($s->nome_fisico)]);
        }

        foreach ($db->table('md_campos')->get() as $c) {
            $db->table('md_campos')->where('id', $c->id)->update(['nome_fisico' => $upper($c->nome_fisico)]);
        }

        foreach (['md_formularios', 'md_gerenciamentos', 'md_formulario_botoes', 'md_gerenciamento_botoes'] as $t) {
            foreach ($db->table($t)->get() as $linha) {
                $db->table($t)->where('id', $linha->id)->update(['nome_fisico' => $upper($linha->nome_fisico)]);
            }
        }

        foreach ($db->table('md_tabelas')->get() as $t) {
            $snapshot = json_decode((string) $t->generated_schema, true);

            if ($snapshot) {
                foreach ($snapshot['fields'] ?? [] as $uid => $campo) {
                    $snapshot['fields'][$uid]['name'] = $upper($campo['name']);

                    if (! empty($campo['foreign']['table'])) {
                        $snapshot['fields'][$uid]['foreign']['table'] = $upper($campo['foreign']['table']);
                    }

                    // situação: os códigos (md_status) também ficaram em maiúscula
                    if ($uid === 'situacao') {
                        $snapshot['fields'][$uid]['options'] = array_map($upper, $campo['options'] ?? []);
                        $snapshot['fields'][$uid]['default'] = $upper($campo['default'] ?? null);
                    }
                }
            }

            $db->table('md_tabelas')->where('id', $t->id)->update([
                'nome_fisico' => $upper($t->nome_fisico),
                'campo_exibicao' => $upper($t->campo_exibicao),
                'generated_schema' => $snapshot ? json_encode($snapshot) : null,
            ]);
        }
    }

    protected function inserirComponente(int $formulario, ?int $pai, int $pagina, int $ordem, array $elemento): int
    {
        $tipo = ($elemento['tipo'] ?? 'campo') === 'grupo' ? 'grupo' : 'campo';

        return DB::connection('central')->table('md_formulario_componentes')->insertGetId([
            'md_formulario_id' => $formulario,
            'pai_id' => $pai,
            'pagina' => $pagina,
            'ordem' => $ordem,
            'tipo' => $tipo,
            'campo' => $tipo === 'campo' ? ($elemento['campo'] ?? null) : null,
            'componente' => ($elemento['tipo'] ?? null) === 'memo' ? 'memo' : null,
            'titulo' => $elemento['titulo'] ?? null,
            'colunas' => max(1, (int) ($elemento['colunas'] ?? 1)),
            'colunas_grupo' => $tipo === 'grupo' ? (int) ($elemento['colunasGrupo'] ?? 2) : null,
            'quebra' => (bool) ($elemento['quebra'] ?? false),
            'completar' => (bool) ($elemento['completar'] ?? false),
            'largura' => $elemento['largura'] ?? null,
            'altura' => $elemento['altura'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Volta só a estrutura (os nomes continuam em maiúscula).
        $schema = Schema::connection('central');

        $schema->table('modulos', fn (Blueprint $table) => $table->string('icone')->nullable());
        $schema->table('md_tabelas', function (Blueprint $table) {
            $table->json('traducao_tabela')->nullable();
            $table->json('traducao_lookup')->nullable();
            $table->dropColumn(['tipo', 'icone', 'cor', 'itens_gerados']);
        });
        $schema->table('md_formularios', function (Blueprint $table) {
            $table->json('layout')->nullable();
            $table->dropColumn('paginas');
        });
        $schema->dropIfExists('md_formulario_componentes');
        $schema->dropIfExists('md_tabela_itens');
        $schema->dropIfExists('md_tabela_traducoes');
        $schema->table('md_campos', fn (Blueprint $table) => $table->dropColumn(['sem_componente', 'sistema']));
    }
};

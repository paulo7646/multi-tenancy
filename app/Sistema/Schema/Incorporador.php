<?php

namespace App\Sistema\Schema;

use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdStatus;
use App\Models\Md\MdTabela;
use App\Models\Md\MdTabelaStatus;
use App\Models\Md\Modulo;
use App\Models\Tenant;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Enums\TipoTabela;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cadastra no dicionário uma tabela que já existe no banco (feita à mão), como se tivesse sido criada pela
 * aba Tabelas: módulo, campos, chaves estrangeiras, índices e a "foto" da estrutura. Fica marcada como
 * existente: o dicionário só a conhece (módulo, releases, permissões, FKs de outras tabelas), nunca gera
 * migration nem arquivos por cima do que foi escrito à mão.
 *
 * As colunas id, created_at e updated_at são implícitas (o dicionário já as trata assim).
 */
class Incorporador
{
    /** Colunas que toda tabela do dicionário tem sem cadastro de campo. */
    protected const IMPLICITAS = ['id', 'created_at', 'updated_at'];

    /**
     * @param  array{nome?: string, classe_model?: string, campo_exibicao?: string, descricao?: string}  $opcoes
     * @param  Tenant|null  $origem  empresa de onde ler a estrutura (banco de clientes)
     *
     * @throws RuntimeException se a tabela não existe no banco
     */
    public static function incorporar(Modulo $modulo, string $tabelaFisica, array $opcoes = [], ?Tenant $origem = null): MdTabela
    {
        [$colunas, $chaves, $indices] = static::estrutura($modulo->banco, $tabelaFisica, $origem);

        // a coluna MD_STATUS_ID é a "situação" do dicionário (Ativo, Inativo...): não é um campo comum
        $controlaSituacao = collect($colunas)->contains(fn (array $c) => $c['name'] === MdTabela::COLUNA_SITUACAO);

        $tabela = MdTabela::updateOrCreate(['nome_fisico' => $tabelaFisica], [
            'controla_situacao' => $controlaSituacao,
            'modulo_id' => $modulo->id,
            'nome' => $opcoes['nome'] ?? Str::headline($tabelaFisica),
            'descricao' => $opcoes['descricao'] ?? null,
            'banco' => $modulo->banco,
            'tipo' => TipoTabela::Cadastro,
            'campo_exibicao' => $opcoes['campo_exibicao'] ?? null,
            'classe_model' => $opcoes['classe_model'] ?? null,
            'existente' => true,
        ]);

        $unicos = static::colunasDeIndice($indices, unico: true);
        $comIndice = static::colunasDeIndice($indices, unico: false);
        $fks = collect($chaves)->filter(fn (array $fk) => count($fk['columns']) === 1)->keyBy(fn (array $fk) => $fk['columns'][0]);

        $ordem = 0;

        foreach ($colunas as $coluna) {
            if (static::implicita($coluna) || $coluna['name'] === MdTabela::COLUNA_SITUACAO) {
                continue;
            }

            $nomeColuna = $coluna['name'];
            $destino = isset($fks[$nomeColuna]) ? MdTabela::where('nome_fisico', $fks[$nomeColuna]['foreign_table'])->first() : null;
            [$tipo, $tamanho, $precisao, $escala] = $destino ? [TipoCampo::ForeignId, null, null, null] : static::tipo($coluna);

            MdCampo::updateOrCreate(['md_tabela_id' => $tabela->id, 'nome_fisico' => $nomeColuna], [
                'nome' => Str::headline($nomeColuna),
                'tipo' => $tipo,
                'tamanho' => $tamanho,
                'precisao' => $precisao,
                'escala' => $escala,
                'obrigatorio' => ! $coluna['nullable'],
                'unico' => in_array($nomeColuna, $unicos, true),
                'indice' => in_array($nomeColuna, $comIndice, true),
                'fk_tabela_id' => $destino?->id,
                'fk_on_delete' => static::aoExcluir($fks[$nomeColuna]['on_delete'] ?? null),
                'ordem' => ++$ordem,
            ]);
        }

        if ($controlaSituacao) {
            static::cadastrarSituacoes($tabela);
        }

        $tabela->load('modulo');
        $tabela->forceFill(['generated_at' => now(), 'generated_schema' => SchemaSnapshot::of($tabela->fresh())])->save();

        static::cadastrarTelas($tabela->fresh());

        return $tabela->fresh();
    }

    /**
     * Situações da tabela: Ativo (padrão) e Inativo, as do próprio dicionário.
     */
    protected static function cadastrarSituacoes(MdTabela $tabela): void
    {
        $status = MdStatus::query()->whereIn('nome_fisico', ['ATIVO', 'INATIVO'])->get()->keyBy('nome_fisico');

        foreach (['ATIVO' => 1, 'INATIVO' => 2] as $nome => $ordem) {
            if ($status->has($nome)) {
                MdTabelaStatus::updateOrCreate(
                    ['md_tabela_id' => $tabela->id, 'md_status_id' => $status[$nome]->id],
                    ['padrao' => $nome === 'ATIVO', 'ordem' => $ordem],
                );
            }
        }
    }

    /**
     * Formulário (layout padrão: todos os campos numa página) e gerenciamento (lista) da tabela, como se
     * criados pelas abas Formulários e Gerenciamentos. Só o cadastro: a tela de verdade continua sendo a
     * escrita à mão, então nenhum arquivo é gerado e nenhum Resource novo entra no painel.
     * Se a tabela já tem formulário ou gerenciamento (feito na tela), nada é mexido.
     */
    protected static function cadastrarTelas(MdTabela $tabela): void
    {
        $classe = Str::studly($tabela->nome_fisico);

        $formulario = $tabela->formularios()->first() ?? MdFormulario::create([
            'md_tabela_id' => $tabela->id,
            'nome' => $tabela->nome,
            'titulo' => $tabela->nome,
            'nome_fisico' => $tabela->nome_fisico,
            'nome_arquivo' => "View{$classe}",
            'paginado' => false,
            'colunas' => 2,
        ]);

        // a situação entra no formulário padrão: refaz o layout só se ainda não tem o campo (tabela que ganhou a coluna)
        $semSituacao = $tabela->controla_situacao && ! $formulario->componentes()->where('campo', MdFormulario::CAMPO_SITUACAO)->exists();

        if ($formulario->componentes()->doesntExist() || $semSituacao) {
            $formulario->salvarLayout(MdFormulario::layoutPadrao($tabela));
        }

        if ($tabela->gerenciamentos()->doesntExist()) {
            MdGerenciamento::create([
                'md_tabela_id' => $tabela->id,
                'md_formulario_id' => $formulario->id,
                'nome' => $tabela->nome,
                'titulo' => $tabela->nome,
                'nome_fisico' => $tabela->nome_fisico,
                'nome_arquivo' => "{$classe}Resource",
                'ordem' => 0,
            ]);
        }
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    protected static function estrutura(Banco $banco, string $tabela, ?Tenant $origem): array
    {
        $ler = function (callable $esquema) use ($tabela) {
            if (! $esquema()->hasTable($tabela)) {
                throw new RuntimeException("A tabela {$tabela} não existe no banco.");
            }

            return [$esquema()->getColumns($tabela), $esquema()->getForeignKeys($tabela), $esquema()->getIndexes($tabela)];
        };

        if ($banco === Banco::Central) {
            return $ler(fn () => Schema::connection('central'));
        }

        $origem ?? throw new RuntimeException('Para ler a estrutura de uma tabela de clientes é preciso ao menos uma empresa cadastrada.');

        return $origem->executar(fn () => $ler(fn () => Schema::connection(config('database.default'))));
    }

    /**
     * @param  array<string, mixed>  $coluna
     */
    protected static function implicita(array $coluna): bool
    {
        return in_array($coluna['name'], static::IMPLICITAS, true) && ($coluna['name'] !== 'id' || $coluna['auto_increment']);
    }

    /**
     * Colunas com índice de uma coluna só (únicas ou não), fora a chave primária.
     *
     * @param  list<array<string, mixed>>  $indices
     * @return list<string>
     */
    protected static function colunasDeIndice(array $indices, bool $unico): array
    {
        return collect($indices)
            ->reject(fn (array $i) => $i['primary'] || count($i['columns']) !== 1 || (bool) $i['unique'] !== $unico)
            ->map(fn (array $i) => $i['columns'][0])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $coluna
     * @return array{0: TipoCampo, 1: ?int, 2: ?int, 3: ?int} tipo, tamanho, precisão, escala
     */
    protected static function tipo(array $coluna): array
    {
        $nome = (string) $coluna['type_name'];
        preg_match('/\((\d+)(?:,\s*(\d+))?\)/', (string) $coluna['type'], $medidas);
        $primeira = isset($medidas[1]) ? (int) $medidas[1] : null;
        $segunda = isset($medidas[2]) ? (int) $medidas[2] : null;

        return match ($nome) {
            'varchar' => [TipoCampo::String, $primeira ?? 255, null, null],
            'bpchar' => [TipoCampo::Char, $primeira ?? 1, null, null],
            'text' => [TipoCampo::Text, null, null, null],
            'int2' => [TipoCampo::SmallInteger, null, null, null],
            'int4' => [TipoCampo::Integer, null, null, null],
            'int8' => [TipoCampo::BigInteger, null, null, null],
            'bool' => [TipoCampo::Boolean, null, null, null],
            'date' => [TipoCampo::Date, null, null, null],
            'time', 'timetz' => [TipoCampo::Time, null, null, null],
            'timestamp' => [TipoCampo::Timestamp, null, null, null],
            'timestamptz' => [TipoCampo::TimestampTz, null, null, null],
            'json' => [TipoCampo::Json, null, null, null],
            'jsonb' => [TipoCampo::Jsonb, null, null, null],
            'numeric' => [TipoCampo::Decimal, null, $primeira ?? 8, $segunda ?? 2],
            'float4' => [TipoCampo::Float, null, null, null],
            'float8' => [TipoCampo::Double, null, null, null],
            'uuid' => [TipoCampo::Uuid, null, null, null],
            default => [TipoCampo::String, 255, null, null],
        };
    }

    protected static function aoExcluir(?string $acao): string
    {
        return match (strtolower((string) $acao)) {
            'cascade' => 'cascade',
            'set null' => 'null',
            default => 'restrict',
        };
    }
}

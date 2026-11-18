<?php

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Plataforma\Impressao\Planilhas;
use App\Plataforma\Impressao\Relatorios;
use App\Plataforma\Impressao\Valores;
use App\Webservice\Models\Webservice;
use Illuminate\Support\Str;

/**
 * Usa a tabela "webservices" (que existe no banco de testes) descrita por uma
 * MdTabela de mentira, para exercitar as colunas, o PDF e as planilhas sem gerar
 * tabelas pelo dicionário. As tabelas md_* ficam na conexão "central" (sem
 * rollback), por isso o afterEach apaga o que criou.
 */
beforeEach(function () {
    $this->modulo = Modulo::create(['nome' => 'Teste imp', 'prefixo' => 'ti'.Str::lower(Str::random(2)), 'pasta' => 'Ti'.Str::random(6), 'banco' => 'central', 'roles' => []]);
    $this->tabela = MdTabela::create(['modulo_id' => $this->modulo->id, 'nome' => 'Webservices', 'nome_fisico' => 'TI_'.strtoupper(Str::random(6)), 'banco' => 'central', 'tipo' => 'cadastro']);

    foreach ([['Nome', 'nome', 'string', 1], ['Ativo', 'ativo', 'boolean', 2], ['Criado em', 'created_at', 'timestamp', 3]] as [$nome, $fisico, $tipo, $ordem]) {
        MdCampo::create(['uid' => (string) Str::uuid(), 'md_tabela_id' => $this->tabela->id, 'nome' => $nome, 'nome_fisico' => $fisico, 'tipo' => $tipo, 'ordem' => $ordem]);
    }
});

afterEach(function () {
    $this->tabela->delete();
    $this->modulo->delete();
});

it('lista as colunas da tabela e formata os valores', function () {
    $registro = Webservice::create(['nome' => 'Fornecedor', 'ativo' => true]);

    $colunas = Valores::colunas($this->tabela);

    expect(array_column($colunas, 0))->toBe(['ID', 'Nome', 'Ativo', 'Criado em'])
        ->and(Valores::de($registro, $colunas[0][1], $this->tabela))->toBe((string) $registro->id)
        ->and(Valores::de($registro, $colunas[1][1], $this->tabela))->toBe('Fornecedor')
        ->and(Valores::de($registro, $colunas[2][1], $this->tabela))->toBe('Sim')
        ->and(Valores::de($registro->fresh(), $colunas[3][1], $this->tabela))->toMatch('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#');
});

it('gera PDF da lista e da ficha do registro', function () {
    $registro = Webservice::create(['nome' => 'Fornecedor']);

    $lista = Relatorios::lista($this->tabela, Webservice::all());
    $ficha = Relatorios::registro($this->tabela, $registro);

    expect($lista)->toStartWith('%PDF')
        ->and($ficha)->toStartWith('%PDF');
});

it('exporta CSV e Excel com cabeçalho e uma linha por registro', function () {
    Webservice::create(['nome' => 'Primeiro']);
    Webservice::create(['nome' => 'Segundo']);

    $csv = Planilhas::gerar($this->tabela, Webservice::query()->orderBy('id'), 'csv');
    $xlsx = Planilhas::gerar($this->tabela, Webservice::query()->orderBy('id'), 'xlsx');

    $linhas = array_values(array_filter(preg_split('/\r?\n/', file_get_contents($csv))));

    expect($linhas[0])->toContain('ID')->toContain('Nome')
        ->and($linhas)->toHaveCount(3)
        ->and($linhas[1])->toContain('Primeiro')
        ->and($linhas[2])->toContain('Segundo')
        ->and(file_get_contents($xlsx, false, null, 0, 2))->toBe('PK'); // xlsx é um zip

    unlink($csv);
    unlink($xlsx);
});

it('neutraliza fórmulas nas planilhas', function () {
    expect(Planilhas::seguro('=1+1'))->toBe("'=1+1")
        ->and(Planilhas::seguro('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(Planilhas::seguro('-5'))->toBe('-5') // número de verdade
        ->and(Planilhas::seguro('Texto normal'))->toBe('Texto normal')
        ->and(Planilhas::seguro(''))->toBe('');
});

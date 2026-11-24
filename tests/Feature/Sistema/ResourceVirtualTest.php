<?php

use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Sistema\Base\BaseResource;
use App\Sistema\Registro;
use Tests\Support\TabelasDeTeste;

/**
 * Gerenciamento sem arquivos gerados: a tela padrão do dicionário (Resource e páginas em memória).
 */
beforeEach(fn () => compartilharConexaoCentral());

afterEach(fn () => TabelasDeTeste::limpar());

it('usa a tela padrão quando o Resource não foi gerado', function () {
    $modulo = TabelasDeTeste::modulo();
    $tabela = TabelasDeTeste::tabela($modulo, 'cliente', [['NOME', 'string', ['tamanho' => 40]]]);

    $formulario = MdFormulario::create([
        'md_tabela_id' => $tabela->id, 'nome' => 'cliente', 'titulo' => 'Cliente', 'nome_fisico' => 'ZZ_CLIENTE', 'nome_arquivo' => 'ViewCliente',
    ]);
    $gerenciamento = MdGerenciamento::create([
        'md_tabela_id' => $tabela->id, 'md_formulario_id' => $formulario->id, 'nome' => 'cliente', 'titulo' => 'Cliente',
        'nome_fisico' => 'ZZ_CLIENTE', 'nome_arquivo' => 'ClienteResource',
    ]);

    expect(class_exists($gerenciamento->resourceFqcn(), false))->toBeFalse();

    $resource = Registro::resource($gerenciamento->fresh('tabela.modulo'));

    expect($resource)->toBe($gerenciamento->resourceFqcn())
        ->and(is_subclass_of($resource, BaseResource::class))->toBeTrue()
        ->and(array_keys($resource::getPages()))->toBe(['index', 'create', 'view', 'edit'])
        ->and($resource::getModel())->toBe($tabela->modelFqcn());
});

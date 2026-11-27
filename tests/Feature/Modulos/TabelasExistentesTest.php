<?php

use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdStatus;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Models\User;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Geradores\Gerador;
use App\Sistema\Registro;
use App\Sistema\Runtime\Acesso;
use App\Sistema\Schema\Incorporador;
use Illuminate\Support\Facades\File;

/**
 * Tabelas feitas à mão entram no dicionário como se criadas pela aba Tabelas (módulo, campos, FKs, foto da
 * estrutura), mas marcadas como existentes: o dicionário nunca gera migration nem arquivos por cima delas.
 */
beforeEach(fn () => compartilharConexaoCentral());

function moduloDeEstrutura(): Modulo
{
    return Modulo::create(['nome' => 'Estrutura', 'prefixo' => 'est', 'pasta' => 'Estrutura', 'banco' => Banco::Tenant, 'padrao' => true]);
}

it('cadastra a tabela existente com campos, tipos, FKs e a foto da estrutura', function () {
    $empresa = empresaComBanco('exist1');

    try {
        $modulo = moduloDeEstrutura();

        Incorporador::incorporar($modulo, 'licencas', ['nome' => 'Licenças', 'classe_model' => 'App\\Models\\Licenca'], $empresa);
        Incorporador::incorporar($modulo, 'filiais', ['nome' => 'Filiais', 'classe_model' => 'App\\Models\\Filial'], $empresa);
        $usuarios = Incorporador::incorporar($modulo, 'users', ['nome' => 'Usuários', 'classe_model' => 'App\\Models\\User'], $empresa);

        $campos = $usuarios->campos->keyBy('nome_fisico');

        expect($usuarios->existente)->toBeTrue()
            ->and($usuarios->classe_model)->toBe('App\\Models\\User')
            ->and($usuarios->isGerada())->toBeTrue()
            ->and($usuarios->banco)->toBe(Banco::Tenant)
            ->and($campos->keys()->all())->toContain('name', 'email', 'password', 'licenca_id', 'filial_id')
            ->and($campos->keys()->all())->not->toContain('id', 'created_at', 'updated_at')
            ->and($campos['email']->unico)->toBeTrue()
            ->and($campos['email']->obrigatorio)->toBeTrue()
            ->and($campos['email']->tipo)->toBe(TipoCampo::String)
            ->and($campos['licenca_id']->tipo)->toBe(TipoCampo::ForeignId)
            ->and($campos['licenca_id']->fkTabela->nome_fisico)->toBe('licencas')
            ->and($campos['licenca_id']->obrigatorio)->toBeFalse()
            // a situação (MD_STATUS_ID) é o recurso do dicionário, não um campo comum: entra na foto, não nos campos
            ->and($usuarios->controla_situacao)->toBeTrue()
            ->and($campos->keys()->all())->not->toContain('MD_STATUS_ID')
            ->and($usuarios->situacoes()->count())->toBe(2)
            ->and($usuarios->situacaoPadrao())->toBe(MdStatus::where('nome_fisico', 'ATIVO')->value('id'))
            ->and($usuarios->generated_schema['fields'])->toHaveCount($usuarios->campos->count() + 1)
            ->and($usuarios->formularios()->first()->componentes()->where('campo', 'situacao')->exists())->toBeTrue();
    } finally {
        $empresa->delete();
    }
});

it('cadastra também o formulário e o gerenciamento, sem registrar tela nova no painel', function () {
    $empresa = empresaComBanco('exist6');

    try {
        $modulo = moduloDeEstrutura();
        $tabela = Incorporador::incorporar($modulo, 'licencas', ['nome' => 'Licenças', 'classe_model' => 'App\\Models\\Licenca'], $empresa);

        $formulario = $tabela->formularios()->first();
        $gerenciamento = $tabela->gerenciamentos()->first();

        expect($formulario)->not->toBeNull()
            // um componente por campo, mais o da situação
            ->and($formulario->componentes()->count())->toBe($tabela->campos()->count() + 1)
            ->and($gerenciamento)->not->toBeNull()
            ->and($gerenciamento->md_formulario_id)->toBe($formulario->id)
            // a tela de verdade é a escrita à mão: o dicionário não cria Resource por cima
            ->and(Registro::resource($gerenciamento->load('tabela.modulo')))->toBeNull();

        // de novo: nada duplica
        Incorporador::incorporar($modulo, 'licencas', [], $empresa);

        expect(MdFormulario::where('md_tabela_id', $tabela->id)->count())->toBe(1)
            ->and(MdGerenciamento::where('md_tabela_id', $tabela->id)->count())->toBe(1)
            ->and($formulario->componentes()->count())->toBe($tabela->campos()->count() + 1);
    } finally {
        $empresa->delete();
    }
});

it('incorporar de novo não duplica campos', function () {
    $empresa = empresaComBanco('exist2');

    try {
        $modulo = moduloDeEstrutura();

        $primeira = Incorporador::incorporar($modulo, 'licencas', [], $empresa);
        $total = $primeira->campos()->count();
        $segunda = Incorporador::incorporar($modulo, 'licencas', [], $empresa);

        expect($segunda->id)->toBe($primeira->id)
            ->and($segunda->campos()->count())->toBe($total)
            ->and(MdTabela::where('nome_fisico', 'licencas')->count())->toBe(1);
    } finally {
        $empresa->delete();
    }
});

it('o dicionário nunca gera migration nem arquivos para a tabela existente', function () {
    $empresa = empresaComBanco('exist3');

    try {
        $modulo = moduloDeEstrutura();
        $tabela = Incorporador::incorporar($modulo, 'licencas', ['classe_model' => 'App\\Models\\Licenca'], $empresa);

        $antes = glob(database_path('migrations/tenant/*.php'));

        expect(fn () => Gerador::publicarTabela($tabela))->toThrow(RuntimeException::class, 'já existe no sistema')
            ->and(fn () => Gerador::excluirTabela($tabela))->toThrow(RuntimeException::class, 'já existe no sistema')
            ->and(glob(database_path('migrations/tenant/*.php')))->toBe($antes)
            ->and(File::isDirectory(app_path('Modulos/Estrutura')))->toBeFalse();
    } finally {
        $empresa->delete();
    }
});

it('o model da tabela existente é o que já existe, não o gerado', function () {
    $tabela = new MdTabela(['nome_fisico' => 'users', 'classe_model' => User::class]);

    expect($tabela->modelFqcn())->toBe(User::class);
});

it('uma tabela de módulo comum pode referenciar a do módulo padrão (Estrutura)', function () {
    $empresa = empresaComBanco('exist4');

    try {
        $est = moduloDeEstrutura();
        $ws = Modulo::create(['nome' => 'Webservice', 'prefixo' => 'ws', 'pasta' => 'Webservice', 'banco' => Banco::Tenant]);

        Incorporador::incorporar($est, 'licencas', [], $empresa);

        $tabelaWs = MdTabela::create(['modulo_id' => $ws->id, 'nome' => 'X', 'nome_fisico' => 'ws_x', 'banco' => Banco::Tenant, 'tipo' => 'cadastro']);
        $licencas = MdTabela::where('nome_fisico', 'licencas')->first();

        expect(MdCampo::create(['md_tabela_id' => $tabelaWs->id, 'nome' => 'L', 'nome_fisico' => 'LICENCA_ID', 'tipo' => TipoCampo::ForeignId, 'fk_tabela_id' => $licencas->id, 'fk_on_delete' => 'restrict']))
            ->toBeInstanceOf(MdCampo::class);
    } finally {
        $empresa->delete();
    }
});

it('na base pai o módulo não restringe: o que decide é a permissão do usuário', function () {
    $ws = Modulo::create(['nome' => 'Webservice', 'prefixo' => 'ws', 'pasta' => 'Webservice', 'banco' => Banco::Tenant]);

    expect(Acesso::liberado($ws))->toBeTrue();
});

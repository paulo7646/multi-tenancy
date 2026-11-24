<?php

use App\Filament\Resources\Dicionario\MdFormularioResource\Pages\EditMdFormulario;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\EditMdTabela;
use App\Filament\Resources\Dicionario\ModuloResource\Pages\EditModulo;
use App\Livewire\Dicionario\TabelasDoModulo;
use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Salvar no dicionário só grava o cadastro; os arquivos saem no botão Criar
 * arquivos. Este teste gera de verdade (módulo, migration, model) e desfaz tudo no fim.
 */
beforeEach(function () {
    Filament::setCurrentPanel('admin');

    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $this->actingAs(tap(User::factory()->create())->assignRole($papel));

    $this->sufixo = strtoupper(Str::random(4));
    $this->modulos = file_get_contents(base_path('database/md/modulos.json'));
    $this->migrationsAntes = glob(database_path('migrations/*.php'));

    $this->modulo = Modulo::create([
        'nome' => 'Teste '.$this->sufixo, 'prefixo' => 'z'.strtolower(Str::random(3)),
        'pasta' => 'Teste'.$this->sufixo, 'banco' => 'central', 'roles' => [],
    ]);
    $this->tabela = MdTabela::create([
        'modulo_id' => $this->modulo->id, 'nome' => 'Cliente', 'nome_fisico' => "{$this->modulo->prefixo}_CLIENTE", 'banco' => 'central', 'tipo' => 'cadastro',
    ]);
    MdCampo::create(['uid' => (string) Str::uuid(), 'md_tabela_id' => $this->tabela->id, 'nome' => 'Nome', 'nome_fisico' => 'NOME', 'tipo' => 'string', 'tamanho' => 60, 'ordem' => 1]);
    $this->tabela->load('modulo');
});

afterEach(function () {
    Schema::dropIfExists($this->tabela->nome_fisico);

    File::deleteDirectory(app_path("Modulos/{$this->modulo->pasta}"));
    File::delete(base_path("database/md/tabelas/central_{$this->tabela->nome_fisico}.json"));
    @rmdir(base_path('database/md/tabelas'));
    file_put_contents(base_path('database/md/modulos.json'), $this->modulos);

    foreach (array_diff(glob(database_path('migrations/*.php')), $this->migrationsAntes) as $novo) {
        File::delete($novo);
    }

    $this->tabela->delete();
    $this->modulo->delete();
});

it('salvar a tabela não gera arquivo nem migration', function () {
    Livewire::test(EditMdTabela::class, ['record' => $this->tabela->id])->call('save');

    expect(is_file($this->tabela->caminho($this->tabela->classeModel().'.php')))->toBeFalse()
        ->and(Schema::hasTable($this->tabela->nome_fisico))->toBeFalse()
        ->and(array_diff(glob(database_path('migrations/*.php')), $this->migrationsAntes))->toBe([]);
});

it('o botão pergunta e mostra o que vai ser feito antes de gerar', function () {
    $pagina = Livewire::test(EditMdTabela::class, ['record' => $this->tabela->id]);
    $pagina->mountAction('criarArquivos')->assertActionMounted('criarArquivos');

    $descricao = $pagina->instance()->descricaoDosArquivos()->toHtml();

    expect($descricao)->toContain("Migration de criação da tabela {$this->tabela->nome_fisico}")
        ->toContain('Model')
        ->toContain('salvo');

    expect(Schema::hasTable($this->tabela->nome_fisico))->toBeFalse();
});

it('Criar arquivos gera a migration, aplica no banco e cria o model e o controller', function () {
    Livewire::test(EditMdTabela::class, ['record' => $this->tabela->id])->callAction('criarArquivos');

    $this->tabela->refresh();

    expect(Schema::hasTable($this->tabela->nome_fisico))->toBeTrue()
        ->and($this->tabela->isGerada())->toBeTrue()
        ->and(is_file($this->tabela->caminho($this->tabela->classeModel().'.php')))->toBeTrue()
        ->and(is_file($this->tabela->caminho($this->tabela->classeController().'.php')))->toBeTrue()
        ->and(array_diff(glob(database_path('migrations/*.php')), $this->migrationsAntes))->toHaveCount(1);
});

it('o módulo só cria a pasta no botão', function () {
    expect(is_dir(app_path("Modulos/{$this->modulo->pasta}")))->toBeFalse();

    Livewire::test(EditModulo::class, ['record' => $this->modulo->id])->callAction('criarArquivos');

    expect(is_dir(app_path("Modulos/{$this->modulo->pasta}")))->toBeTrue();
});

it('formulário e tela só geram depois que a tabela tem arquivos', function () {
    $formulario = MdFormulario::create([
        'md_tabela_id' => $this->tabela->id, 'nome' => 'Cliente', 'titulo' => 'Cliente', 'nome_fisico' => $this->tabela->nome_fisico,
        'nome_arquivo' => 'View'.$this->tabela->nomeClasse(), 'paginado' => false, 'colunas' => 2,
    ]);

    $pagina = Livewire::test(EditMdFormulario::class, ['record' => $formulario->id]);

    expect($pagina->instance()->descricaoDosArquivos()->toHtml())->toContain('Crie primeiro os arquivos da tabela');

    $pagina->callAction('criarArquivos');

    expect(is_file($this->tabela->caminho($this->tabela->classeModel().'.php')))->toBeFalse();
});

it('o módulo lista as tabelas numa aba e a tabela tem o grupo de arquivos', function () {
    Livewire::test(EditModulo::class, ['record' => $this->modulo->getRouteKey()])
        ->assertSee('Tabelas')
        ->assertSee('Cliente');

    Livewire::test(EditMdTabela::class, ['record' => $this->tabela->getRouteKey()])
        ->assertSee('Abrir arquivo model')
        ->assertSee('Abrir arquivo controller');
});

it('a aba Tabelas do módulo é uma tabela com abrir, nova tabela e excluir', function () {
    Livewire::test(TabelasDoModulo::class, ['moduloId' => $this->modulo->id])
        ->assertCanSeeTableRecords([$this->tabela])
        ->assertTableActionExists('edit', record: $this->tabela)
        ->assertTableActionExists('delete', record: $this->tabela)
        ->assertSee('Nova tabela');
});

<?php

use App\Models\User;
use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Models\Arquivo;
use App\Plataforma\Models\Assinatura;
use App\Plataforma\Pages\ConfiguracoesDaEmpresa;
use App\Plataforma\RelationManagers\AssinantesRelationManager;
use App\Plataforma\RelationManagers\EventosDaAssinaturaRelationManager;
use App\Plataforma\RelationManagers\VersoesRelationManager;
use App\Plataforma\Resources\AssinaturaResource\Pages\ListAssinaturas;
use App\Plataforma\Resources\AssinaturaResource\Pages\ViewAssinatura;
use App\Plataforma\Resources\DocumentoResource\Pages\EditDocumento;
use App\Plataforma\Resources\DocumentoResource\Pages\ListDocumentos;
use App\Plataforma\Resources\ModeloImpressaoResource\Pages\ManageModelosImpressao;
use App\Plataforma\Resources\PastaResource\Pages\ManagePastas;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();

    Filament::setCurrentPanel('admin');
    $usuario = User::factory()->create();
    // o super_admin do Shield recebe todas as permissões (as das migrations da plataforma incluídas)
    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $usuario->assignRole($papel);
    $this->actingAs($usuario);
});

function pdfEmArquivo(): Arquivo
{
    return Armazenamento::criar(['nome' => 'Contrato'], ['conteudo' => Pdf::loadHTML('<p>x</p>')->output(), 'nome' => 'contrato.pdf']);
}

it('as telas da plataforma carregam', function (string $pagina) {
    Livewire::test($pagina)->assertOk();
})->with([
    'documentos' => ListDocumentos::class,
    'pastas' => ManagePastas::class,
    'assinaturas' => ListAssinaturas::class,
    'modelos de impressão' => ManageModelosImpressao::class,
    'dados da empresa' => ConfiguracoesDaEmpresa::class,
]);

it('a tela do documento mostra as versões', function () {
    $arquivo = pdfEmArquivo();

    Livewire::test(EditDocumento::class, ['record' => $arquivo->id])->assertOk();
    Livewire::test(VersoesRelationManager::class, ['ownerRecord' => $arquivo, 'pageClass' => EditDocumento::class])
        ->assertOk()
        ->assertCanSeeTableRecords($arquivo->versoes);
});

it('cria o pedido de assinatura pelo documento e abre a tela do pedido', function () {
    $arquivo = pdfEmArquivo();

    Livewire::test(ListDocumentos::class)
        ->callAction(
            TestAction::make('assinar')->table($arquivo),
            ['titulo' => 'Contrato X', 'assinantes' => [['nome' => 'Ana Souza', 'email' => 'ana@teste.com']], 'ordem_obrigatoria' => true, 'exigir_codigo' => true, 'enviar_agora' => true],
        )
        ->assertHasNoFormErrors();

    $pedido = Assinatura::firstWhere('titulo', 'Contrato X');

    expect($pedido)->not->toBeNull()
        ->and($pedido->status)->toBe('em_andamento');

    Livewire::test(ViewAssinatura::class, ['record' => $pedido->id])->assertOk();
    Livewire::test(AssinantesRelationManager::class, ['ownerRecord' => $pedido, 'pageClass' => ViewAssinatura::class])->assertOk();
    Livewire::test(EventosDaAssinaturaRelationManager::class, ['ownerRecord' => $pedido, 'pageClass' => ViewAssinatura::class])->assertOk();
});

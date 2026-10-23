<?php
uses(Tests\TestCase::class);
it('página do formulário renderiza', function () {
    $this->actingAs(App\Models\User::role('super_admin')->firstOrFail());
    foreach (App\Models\Md\MdFormulario::all() as $f) {
        $this->get("http://multi-tenancy.test/admin/dicionario/formularios/{$f->id}/edit")->assertOk()
            ->assertSee('Componentes')->assertSee('Parâmetros')->assertSee('Tipo do componente')->assertSee('Tradução')->assertSee('Máscara')->assertDontSee('Pré-visualizar');
    }
    $this->get('http://multi-tenancy.test/admin/dicionario/formularios/create')->assertOk();
    $this->get('http://multi-tenancy.test/admin/dicionario/formularios')->assertOk()->assertSee('Componentes');
});

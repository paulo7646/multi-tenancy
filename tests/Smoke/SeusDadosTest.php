<?php
uses(Tests\TestCase::class);
it('abre as telas com os dados atuais', function () {
    $this->actingAs(App\Models\User::role('super_admin')->firstOrFail());
    $t = App\Models\Md\MdTabela::first(); $f = App\Models\Md\MdFormulario::first(); $g = App\Models\Md\MdGerenciamento::first();
    $falhas = [];
    foreach (["admin/dicionario/tabelas/{$t->id}/edit", "admin/dicionario/tabelas/{$t->id}", 'admin/dicionario/tabelas/create', "admin/dicionario/formularios/{$f->id}/edit", 'admin/dicionario/formularios/create', "admin/dicionario/gerenciamentos/{$g->id}/edit", 'admin/dicionario/modulos', 'admin/navegacao?painel=client'] as $p) {
        $r = $this->get("http://multi-tenancy.test/{$p}");
        if ($r->getStatusCode() !== 200) $falhas[] = $r->getStatusCode() . " {$p} " . ($r->exception ? get_class($r->exception) . ': ' . str($r->exception->getMessage())->limit(400) : '');
    }
    tenancy()->initialize(App\Models\Tenant::first());
    foreach (['admin', 'admin/m/OP_PRODUTO', 'admin/m/OP_PRODUTO/r/novo', 'admin/m/modulo/op'] as $p) {
        $r = $this->get("http://linear.test/{$p}");
        if ($r->getStatusCode() !== 200) $falhas[] = $r->getStatusCode() . " {$p} " . ($r->exception ? get_class($r->exception) . ': ' . str($r->exception->getMessage())->limit(400) : '');
    }
    expect($falhas)->toBe([]);
});

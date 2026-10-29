<?php

use App\Webservice\Models\Agendamento;

function novoAgendamento(array $dados): Agendamento
{
    return Agendamento::create(['nome' => 'Teste', 'tipo' => 'codigo_fonte', ...$dados]);
}

it('monta o cron de cada frequência', function (array $dados, string $cron) {
    expect(novoAgendamento($dados)->cron)->toBe($cron);
})->with([
    'minutos' => [['frequencia' => 'minutos', 'intervalo' => 5], '*/5 * * * *'],
    'horas' => [['frequencia' => 'horas', 'intervalo' => 2], '0 */2 * * *'],
    'diário' => [['frequencia' => 'diario', 'hora' => '23:15'], '15 23 * * *'],
    'semanal' => [['frequencia' => 'semanal', 'hora' => '08:30', 'dia_semana' => 1], '30 8 * * 1'],
    'cron livre' => [['frequencia' => 'cron', 'cron' => '0 6 * * 1-5'], '0 6 * * 1-5'],
]);

it('calcula a próxima execução ao salvar', function () {
    $agendamento = novoAgendamento(['frequencia' => 'minutos', 'intervalo' => 10]);

    expect($agendamento->proxima_execucao)->not->toBeNull()
        ->and($agendamento->proxima_execucao->isFuture())->toBeTrue();
});

it('agendamento inativo não tem próxima execução', function () {
    $agendamento = novoAgendamento(['frequencia' => 'minutos', 'intervalo' => 10, 'ativo' => false]);

    expect($agendamento->proxima_execucao)->toBeNull();
});

it('sabe se expirou', function () {
    expect(novoAgendamento(['frequencia' => 'minutos', 'intervalo' => 1, 'expira_em' => now()->subDay()])->expirado())->toBeTrue()
        ->and(novoAgendamento(['frequencia' => 'minutos', 'intervalo' => 1, 'expira_em' => now()->addDay()])->expirado())->toBeFalse();
});

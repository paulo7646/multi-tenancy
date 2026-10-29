<?php

namespace App\Webservice\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Execução automática: uma função do controller de uma tabela ou um layout de EDI.
 */
class Agendamento extends Model
{
    public const TIPOS = ['codigo_fonte' => 'Código-fonte (função do controller)', 'layout_edi' => 'Layout de EDI (operação)'];

    public const FREQUENCIAS = [
        'minutos' => 'A cada N minutos',
        'horas' => 'A cada N horas',
        'diario' => 'Todo dia',
        'semanal' => 'Toda semana',
        'cron' => 'Expressão cron',
    ];

    public const DIAS = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];

    protected $table = 'ws_agendamentos';

    protected $attributes = ['frequencia' => 'minutos', 'ativo' => true];

    protected $fillable = [
        'nome', 'tipo', 'tabela', 'funcao', 'operacao_id', 'parametros', 'frequencia', 'intervalo', 'hora', 'dia_semana', 'cron',
        'expira_em', 'ativo', 'ultima_execucao', 'proxima_execucao', 'ultimo_status', 'ultimo_erro',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'parametros' => 'array',
            'expira_em' => 'datetime',
            'ultima_execucao' => 'datetime',
            'proxima_execucao' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // a expressão cron e a próxima execução acompanham a frequência escolhida
        static::saving(function (self $agendamento) {
            if ($agendamento->frequencia !== 'cron' || blank($agendamento->cron)) {
                $agendamento->cron = $agendamento->montarCron();
            }

            if ($agendamento->isDirty(['cron', 'ativo']) || $agendamento->proxima_execucao === null) {
                $agendamento->proxima_execucao = $agendamento->calcularProxima();
            }
        });
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    public function expirado(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }

    public function montarCron(): string
    {
        [$hora, $minuto] = array_map('intval', array_pad(explode(':', (string) ($this->hora ?: '00:00')), 2, 0));
        $n = max(1, (int) ($this->intervalo ?: 1));

        return match ($this->frequencia) {
            'minutos' => "*/{$n} * * * *",
            'horas' => "0 */{$n} * * *",
            'diario' => "{$minuto} {$hora} * * *",
            'semanal' => "{$minuto} {$hora} * * " . (int) $this->dia_semana,
            default => (string) $this->cron,
        };
    }

    public function calcularProxima(?Carbon $a_partir_de = null): ?Carbon
    {
        if (! $this->ativo || blank($this->cron) || ! CronExpression::isValidExpression($this->cron)) {
            return null;
        }

        return Carbon::instance((new CronExpression($this->cron))->getNextRunDate($a_partir_de ?? now()));
    }
}

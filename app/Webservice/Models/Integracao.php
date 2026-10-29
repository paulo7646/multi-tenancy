<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Uma execução de um layout: o arquivo buscado (ou recebido), o resultado por lote.
 */
class Integracao extends Model
{
    public const STATUS = [
        'pendente' => 'Pendente',
        'processando' => 'Processando',
        'concluida' => 'Concluída',
        'parcial' => 'Parcial (há lotes com erro)',
        'erro' => 'Erro',
    ];

    public const ORIGENS = ['manual' => 'Manual', 'agendamento' => 'Agendamento', 'recebida' => 'Recebida'];

    protected $table = 'ws_integracoes';

    protected $fillable = [
        'operacao_id', 'versao_id', 'agendamento_id', 'origem', 'status', 'formato', 'arquivo', 'parametros',
        'erro', 'lotes_total', 'lotes_erro', 'iniciada_em', 'finalizada_em',
    ];

    protected function casts(): array
    {
        return ['parametros' => 'array', 'iniciada_em' => 'datetime', 'finalizada_em' => 'datetime'];
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    public function versao(): BelongsTo
    {
        return $this->belongsTo(Versao::class, 'versao_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class, 'integracao_id')->orderBy('numero');
    }

    public function conteudo(): ?string
    {
        return $this->arquivo && Storage::disk('local')->exists($this->arquivo)
            ? Storage::disk('local')->get($this->arquivo)
            : null;
    }

    public function guardarArquivo(string $conteudo): void
    {
        $caminho = "webservice/integracoes/{$this->getKey()}." . ($this->formato === 'xml' ? 'xml' : 'json');

        Storage::disk('local')->put($caminho, $conteudo);
        $this->update(['arquivo' => $caminho]);
    }
}

<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de impressão: papel, orientação, título e, para registros, um corpo
 * com marcadores {CAMPO}. Sem corpo, vale o layout automático.
 */
class ModeloImpressao extends Model
{
    public const TIPOS = ['lista' => 'Lista (várias linhas)', 'registro' => 'Registro (uma ficha)'];

    public const PAPEIS = ['a4' => 'A4', 'a5' => 'A5', 'letter' => 'Carta', 'legal' => 'Ofício'];

    public const ORIENTACOES = ['portrait' => 'Retrato', 'landscape' => 'Paisagem'];

    /** Marcas HTML aceitas no corpo; o resto é removido */
    public const HTML_PERMITIDO = '<p><br><b><strong><i><em><u><h1><h2><h3><h4><table><thead><tbody><tr><th><td><ul><ol><li><div><span><hr><small>';

    protected $table = 'imp_modelos';

    protected $attributes = ['papel' => 'a4', 'orientacao' => 'portrait', 'mostrar_cabecalho' => true, 'ativo' => true, 'padrao' => false];

    protected $fillable = ['nome', 'tipo', 'tabela', 'padrao', 'papel', 'orientacao', 'titulo', 'corpo', 'mostrar_cabecalho', 'rodape', 'ativo'];

    protected function casts(): array
    {
        return ['padrao' => 'boolean', 'mostrar_cabecalho' => 'boolean', 'ativo' => 'boolean'];
    }

    /**
     * Modelos que valem para uma tabela (os dela e os de qualquer tabela).
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeDaTabela(Builder $consulta, string $tipo, string $tabela): void
    {
        $consulta->where('ativo', true)->where('tipo', $tipo)->where(fn ($q) => $q->whereNull('tabela')->orWhere('tabela', $tabela));
    }

    /**
     * O modelo padrão da tabela (o dela primeiro), se houver.
     */
    public static function padraoDa(string $tipo, string $tabela): ?self
    {
        return static::daTabela($tipo, $tabela)->where('padrao', true)->orderByRaw('tabela is null')->first();
    }
}

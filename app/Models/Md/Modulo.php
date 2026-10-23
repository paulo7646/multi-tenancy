<?php

namespace App\Models\Md;

use App\Sistema\Enums\Banco;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
{
    protected $connection = 'central';

    protected $table = 'modulos';

    protected $fillable = ['nome', 'prefixo', 'pasta', 'banco', 'descricao', 'ordem', 'roles', 'roles_gerados'];

    protected function casts(): array
    {
        return [
            'banco' => Banco::class,
            'roles' => 'array',
            'roles_gerados' => 'array',
            'ordem' => 'integer',
        ];
    }

    public function tabelas(): HasMany
    {
        return $this->hasMany(MdTabela::class);
    }

    /**
     * Permissão de acesso ao módulo (criada no central e em cada cliente).
     */
    public function permissao(): string
    {
        return static::permissaoDo($this->prefixo);
    }

    public static function permissaoDo(string $prefixo): string
    {
        return "modulo_{$prefixo}";
    }

    public function namespace(): string
    {
        return "App\\Modulos\\{$this->pasta}";
    }

    public function caminho(string $arquivo = ''): string
    {
        return app_path(rtrim("Modulos/{$this->pasta}/{$arquivo}", '/'));
    }
}

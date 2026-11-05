<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações da empresa (ou da base pai), em chave e valor: nome, logo,
 * endereço, rodapé de impressão... Cada banco tem as suas.
 */
class Configuracao extends Model
{
    protected $table = 'plt_configuracoes';

    protected $fillable = ['chave', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }

    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        if (! Schema::hasTable('plt_configuracoes')) {
            return $padrao;
        }

        $valor = static::where('chave', $chave)->value('valor');

        return $valor === null ? $padrao : ($valor['v'] ?? $padrao);
    }

    public static function definir(string $chave, mixed $valor): void
    {
        static::updateOrCreate(['chave' => $chave], ['valor' => ['v' => $valor]]);
    }

    /**
     * Dados da empresa usados no cabeçalho de impressões e nos certificados.
     *
     * @return array{nome: string, documento: ?string, endereco: ?string, contato: ?string, rodape: ?string, logo: ?string}
     */
    public static function empresa(): array
    {
        return [
            'nome' => (string) static::obter('empresa.nome', config('app.name')),
            'documento' => static::obter('empresa.documento'),
            'endereco' => static::obter('empresa.endereco'),
            'contato' => static::obter('empresa.contato'),
            'rodape' => static::obter('empresa.rodape'),
            'logo' => static::obter('empresa.logo'),
        ];
    }
}

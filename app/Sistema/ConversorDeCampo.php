<?php

namespace App\Sistema;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Exceptions\EventoException;
use App\Sistema\Rules\Cnpj;
use App\Sistema\Rules\Cpf;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Valida e converte valores pelo tipo do campo no dicionário (texto, número, data, lista...).
 * Usado pelo VQueryTabela: o set converte para gravar, o get converte para o tipo do PHP.
 */
class ConversorDeCampo
{
    /** Apelidos aceitos nos nomes dos métodos (setSmallInt = setSmallInteger) */
    protected const APELIDOS = [
        'int' => TipoCampo::Integer,
        'tinyint' => TipoCampo::TinyInteger,
        'smallint' => TipoCampo::SmallInteger,
        'mediumint' => TipoCampo::MediumInteger,
        'bigint' => TipoCampo::BigInteger,
        'bool' => TipoCampo::Boolean,
    ];

    /** Faixa dos inteiros no PostgreSQL (tinyInteger vira smallint) */
    protected const FAIXAS = [
        'tinyInteger' => [-32768, 32767],
        'smallInteger' => [-32768, 32767],
        'integer' => [-2147483648, 2147483647],
        'mediumInteger' => [-2147483648, 2147483647],
        'bigInteger' => [PHP_INT_MIN, PHP_INT_MAX],
        'unsignedTinyInteger' => [0, 32767],
        'unsignedSmallInteger' => [0, 32767],
        'unsignedInteger' => [0, 2147483647],
        'unsignedMediumInteger' => [0, 2147483647],
        'unsignedBigInteger' => [0, PHP_INT_MAX],
        'foreignId' => [1, PHP_INT_MAX],
        'year' => [1901, 2155],
    ];

    /** @var Collection<string, MdCampo> campos por nome físico */
    protected Collection $campos;

    public function __construct(protected MdTabela $tabela)
    {
        $tabela->loadMissing('campos');

        $this->campos = $tabela->campos->keyBy('nome_fisico');
    }

    public static function tipoDoMetodo(string $sufixo): ?TipoCampo
    {
        $chave = strtolower($sufixo);

        if (isset(static::APELIDOS[$chave])) {
            return static::APELIDOS[$chave];
        }

        foreach (TipoCampo::cases() as $tipo) {
            if (strtolower($tipo->value) === $chave) {
                return $tipo;
            }
        }

        return null;
    }

    public function paraGravar(TipoCampo $tipo, string $campo, mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $md = $this->campos->get($campo);
        $erro = fn (string $esperado) => new EventoException("{$this->tabela->nome_fisico}.{$campo}: valor ".var_export($valor, true)." inválido ({$esperado}).");

        if ($tipo->isInteger() || in_array($tipo, [TipoCampo::Year, TipoCampo::ForeignId], true)) {
            if (! is_int($valor) && ! (is_string($valor) && preg_match('/^-?\d+$/', trim($valor))) && ! (is_float($valor) && floor($valor) === $valor)) {
                throw $erro('número inteiro');
            }

            $inteiro = (int) $valor;
            [$min, $max] = static::FAIXAS[$tipo->value] ?? [PHP_INT_MIN, PHP_INT_MAX];

            if ($inteiro < $min || $inteiro > $max) {
                throw $erro("entre {$min} e {$max}");
            }

            return $inteiro;
        }

        return match (true) {
            in_array($tipo, [TipoCampo::Decimal, TipoCampo::Float, TipoCampo::Double], true) => $this->numero($tipo, $md, $valor, $erro),
            $tipo === TipoCampo::Boolean => $this->logico($valor, $erro),
            $tipo->isDate() => $this->data($valor, $erro)->format('Y-m-d'),
            $tipo->isDateTime() => $this->data($valor, $erro)->format('Y-m-d H:i:s'),
            $tipo->isTime() => $this->hora($valor, $erro),
            $tipo->isJson() => $this->lista($valor, $erro),
            $tipo === TipoCampo::MultiSelect => $this->multipla($md, $valor, $erro),
            $tipo === TipoCampo::Enum => $this->opcao($campo, $md, $valor, $erro),
            default => $this->texto($tipo, $md, $valor, $erro),
        };
    }

    public function paraLer(TipoCampo $tipo, mixed $valor): mixed
    {
        if ($valor === null) {
            return null;
        }

        return match (true) {
            $tipo->isInteger(), $tipo === TipoCampo::Year, $tipo === TipoCampo::ForeignId => (int) $valor,
            in_array($tipo, [TipoCampo::Decimal, TipoCampo::Float, TipoCampo::Double], true) => (float) $valor,
            $tipo === TipoCampo::Boolean => (bool) $valor,
            $tipo->isDate(), $tipo->isDateTime() => $valor instanceof DateTimeInterface ? Carbon::instance($valor) : Carbon::parse($valor),
            $tipo->isTime() => $valor instanceof DateTimeInterface ? $valor->format('H:i:s') : (string) $valor,
            $tipo->isJson(), $tipo === TipoCampo::MultiSelect => is_array($valor) ? $valor : (json_decode((string) $valor, true) ?? []),
            default => (string) $valor,
        };
    }

    protected function numero(TipoCampo $tipo, ?MdCampo $md, mixed $valor, \Closure $erro): string|float
    {
        if (is_string($valor)) {
            $texto = trim($valor);
            // formato brasileiro: 1.234,56
            if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $texto) || preg_match('/^-?\d+,\d+$/', $texto)) {
                $texto = str_replace(['.', ','], ['', '.'], $texto);
            }
            $valor = $texto;
        }

        if (! is_numeric($valor)) {
            throw $erro('número');
        }

        if ($tipo !== TipoCampo::Decimal) {
            return (float) $valor;
        }

        $precisao = $md?->precisao ?: 10;
        $escala = $md?->escala ?? 2;
        $formatado = number_format((float) $valor, $escala, '.', '');
        $digitosInteiros = strlen(ltrim(explode('.', ltrim($formatado, '-'))[0], '0'));

        if ($digitosInteiros > $precisao - $escala) {
            throw $erro('no máximo '.($precisao - $escala)." dígitos antes da vírgula e {$escala} casas decimais");
        }

        return $formatado;
    }

    protected function logico(mixed $valor, \Closure $erro): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        return match (mb_strtolower(trim((string) $valor))) {
            '1', 'true', 's', 'sim', 'y', 'yes' => true,
            '0', 'false', 'n', 'nao', 'não', 'no' => false,
            default => throw $erro('verdadeiro/falso'),
        };
    }

    protected function data(mixed $valor, \Closure $erro): Carbon
    {
        if ($valor instanceof DateTimeInterface) {
            return Carbon::instance($valor);
        }

        $texto = trim((string) $valor);

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $formato) {
            try {
                $data = Carbon::createFromFormat("!{$formato}", $texto);
            } catch (Throwable) {
                continue;
            }

            if ($data && $data->format($formato) === $texto) {
                return $data;
            }
        }

        try {
            return Carbon::parse($texto);
        } catch (Throwable) {
            throw $erro('data: Y-m-d ou d/m/Y');
        }
    }

    protected function hora(mixed $valor, \Closure $erro): string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('H:i:s');
        }

        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', trim((string) $valor), $m)) {
            throw $erro('hora: HH:MM ou HH:MM:SS');
        }

        return "{$m[1]}:{$m[2]}:".($m[4] ?? '00');
    }

    protected function lista(mixed $valor, \Closure $erro): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        $decodificado = json_decode((string) $valor, true);

        return is_array($decodificado) ? $decodificado : throw $erro('JSON');
    }

    protected function multipla(?MdCampo $md, mixed $valor, \Closure $erro): array
    {
        $valores = array_values(array_map('strval', $this->lista($valor, $erro)));
        $fora = array_diff($valores, array_map('strval', $md?->opcoes ?? []));

        if ($fora !== []) {
            throw $erro('opções: '.implode(', ', $md?->opcoes ?? []));
        }

        return $valores;
    }

    protected function opcao(string $campo, ?MdCampo $md, mixed $valor, \Closure $erro): string
    {
        $opcoes = array_map('strval', $md?->opcoes ?? []);

        if (! in_array((string) $valor, $opcoes, true)) {
            throw $erro('opções: '.implode(', ', $opcoes));
        }

        return (string) $valor;
    }

    protected function texto(TipoCampo $tipo, ?MdCampo $md, mixed $valor, \Closure $erro): string
    {
        if (! is_scalar($valor) && ! $valor instanceof \Stringable) {
            throw $erro('texto');
        }

        $texto = (string) $valor;

        $valido = match ($tipo) {
            TipoCampo::Email => filter_var($texto, FILTER_VALIDATE_EMAIL) !== false,
            TipoCampo::Url => filter_var($texto, FILTER_VALIDATE_URL) !== false,
            TipoCampo::Uuid => Str::isUuid($texto),
            TipoCampo::Ulid => Str::isUlid($texto),
            TipoCampo::IpAddress => filter_var($texto, FILTER_VALIDATE_IP) !== false,
            TipoCampo::MacAddress => filter_var($texto, FILTER_VALIDATE_MAC) !== false,
            TipoCampo::Cep => (bool) preg_match('/^\d{5}-?\d{3}$/', $texto),
            TipoCampo::Phone => (bool) preg_match('/^\d{10,11}$/', preg_replace('/\D/', '', $texto)),
            TipoCampo::Color => (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $texto),
            TipoCampo::Cpf => $this->regraPassa(new Cpf, $texto),
            TipoCampo::Cnpj => $this->regraPassa(new Cnpj, $texto),
            default => true,
        };

        if (! $valido) {
            throw $erro($tipo->label());
        }

        $limite = $tipo->storageType()->hasLength() ? ($md?->tamanho ?: $tipo->defaultLength()) : null;

        if ($limite !== null && mb_strlen($texto) > $limite) {
            throw $erro("no máximo {$limite} caracteres");
        }

        return $texto;
    }

    protected function regraPassa(ValidationRule $regra, string $valor): bool
    {
        $passou = true;
        $regra->validate('valor', $valor, function () use (&$passou) {
            $passou = false;
        });

        return $passou;
    }
}

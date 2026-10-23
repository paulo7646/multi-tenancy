<?php

namespace App\Sistema;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Base\BaseController;
use App\Sistema\Base\BaseModel;
use App\Sistema\Base\Concerns\Utilidades;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Exceptions\EventoException;
use App\Sistema\Rules\Cnpj;
use App\Sistema\Rules\Cpf;
use BadMethodCallException;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Um registro de qualquer tabela do dicionário, com leitura e gravação por tipo.
 *
 *   $produto = new ModelProperty('op', 'produto', 1);   // carrega o registro 1
 *   $produto->setGetAllChildrens();                      // traz todas as tabelas filhas
 *   $produto->setGetChildren('op_preco');                // ou só algumas
 *
 *   $produto->getString('descricao');                    // 'Suco'
 *   $produto->setDecimal('preco', 9.9)->update();
 *
 *   $novo = new ModelProperty('op', 'produto');
 *   $novo->setString('descricao', 'Água')->setDecimal('preco', 3)->insert();
 *   $novo->getId();
 *
 * Cada tipo do dicionário tem o seu par set/get (setSmallInteger/getSmallInteger,
 * setDate/getDate, setCpf/getCpf...). O set valida e converte o valor para o
 * tipo do campo; o get devolve o valor no tipo do PHP. setCampo/getCampo são os
 * genéricos (o get devolve string).
 *
 * insert(), update() e delete() rodam em transação (erro = rollback) e disparam
 * os eventos do model (beforePost, afterPost...). Para agrupar várias operações:
 * iniciaTransacao() ... transacaoCommit() / transacaoRollBack(), ou
 * finalizaTransacao() (commit se tudo deu certo, rollback se alguma operação falhou).
 * Os nomes com acento também funcionam: iniciaTransação(), finalizaTransação(),
 * transaçãoCommit(), transaçãoRollBack().
 *
 * @method $this setString(string $campo, ?string $valor)
 * @method ?string getString(string $campo)
 * @method $this setChar(string $campo, ?string $valor)
 * @method ?string getChar(string $campo)
 * @method $this setText(string $campo, ?string $valor)
 * @method ?string getText(string $campo)
 * @method $this setTinyText(string $campo, ?string $valor)
 * @method ?string getTinyText(string $campo)
 * @method $this setMediumText(string $campo, ?string $valor)
 * @method ?string getMediumText(string $campo)
 * @method $this setLongText(string $campo, ?string $valor)
 * @method ?string getLongText(string $campo)
 * @method $this setInteger(string $campo, int|string|null $valor)
 * @method ?int getInteger(string $campo)
 * @method $this setInt(string $campo, int|string|null $valor)
 * @method ?int getInt(string $campo)
 * @method $this setTinyInteger(string $campo, int|string|null $valor)
 * @method ?int getTinyInteger(string $campo)
 * @method $this setTinyInt(string $campo, int|string|null $valor)
 * @method ?int getTinyInt(string $campo)
 * @method $this setSmallInteger(string $campo, int|string|null $valor)
 * @method ?int getSmallInteger(string $campo)
 * @method $this setSmallInt(string $campo, int|string|null $valor)
 * @method ?int getSmallInt(string $campo)
 * @method $this setMediumInteger(string $campo, int|string|null $valor)
 * @method ?int getMediumInteger(string $campo)
 * @method $this setMediumInt(string $campo, int|string|null $valor)
 * @method ?int getMediumInt(string $campo)
 * @method $this setBigInteger(string $campo, int|string|null $valor)
 * @method ?int getBigInteger(string $campo)
 * @method $this setBigInt(string $campo, int|string|null $valor)
 * @method ?int getBigInt(string $campo)
 * @method $this setUnsignedInteger(string $campo, int|string|null $valor)
 * @method ?int getUnsignedInteger(string $campo)
 * @method $this setUnsignedTinyInteger(string $campo, int|string|null $valor)
 * @method ?int getUnsignedTinyInteger(string $campo)
 * @method $this setUnsignedSmallInteger(string $campo, int|string|null $valor)
 * @method ?int getUnsignedSmallInteger(string $campo)
 * @method $this setUnsignedMediumInteger(string $campo, int|string|null $valor)
 * @method ?int getUnsignedMediumInteger(string $campo)
 * @method $this setUnsignedBigInteger(string $campo, int|string|null $valor)
 * @method ?int getUnsignedBigInteger(string $campo)
 * @method $this setDecimal(string $campo, int|float|string|null $valor)
 * @method ?float getDecimal(string $campo)
 * @method $this setFloat(string $campo, int|float|string|null $valor)
 * @method ?float getFloat(string $campo)
 * @method $this setDouble(string $campo, int|float|string|null $valor)
 * @method ?float getDouble(string $campo)
 * @method $this setBoolean(string $campo, bool|int|string|null $valor)
 * @method ?bool getBoolean(string $campo)
 * @method $this setBool(string $campo, bool|int|string|null $valor)
 * @method ?bool getBool(string $campo)
 * @method $this setDate(string $campo, DateTimeInterface|string|null $valor)
 * @method ?Carbon getDate(string $campo)
 * @method $this setDateTime(string $campo, DateTimeInterface|string|null $valor)
 * @method ?Carbon getDateTime(string $campo)
 * @method $this setDateTimeTz(string $campo, DateTimeInterface|string|null $valor)
 * @method ?Carbon getDateTimeTz(string $campo)
 * @method $this setTimestamp(string $campo, DateTimeInterface|string|null $valor)
 * @method ?Carbon getTimestamp(string $campo)
 * @method $this setTimestampTz(string $campo, DateTimeInterface|string|null $valor)
 * @method ?Carbon getTimestampTz(string $campo)
 * @method $this setTime(string $campo, DateTimeInterface|string|null $valor)
 * @method ?string getTime(string $campo)
 * @method $this setTimeTz(string $campo, DateTimeInterface|string|null $valor)
 * @method ?string getTimeTz(string $campo)
 * @method $this setYear(string $campo, int|string|null $valor)
 * @method ?int getYear(string $campo)
 * @method $this setJson(string $campo, array|string|null $valor)
 * @method ?array getJson(string $campo)
 * @method $this setJsonb(string $campo, array|string|null $valor)
 * @method ?array getJsonb(string $campo)
 * @method $this setEnum(string $campo, ?string $valor)
 * @method ?string getEnum(string $campo)
 * @method $this setUuid(string $campo, ?string $valor)
 * @method ?string getUuid(string $campo)
 * @method $this setUlid(string $campo, ?string $valor)
 * @method ?string getUlid(string $campo)
 * @method $this setIpAddress(string $campo, ?string $valor)
 * @method ?string getIpAddress(string $campo)
 * @method $this setMacAddress(string $campo, ?string $valor)
 * @method ?string getMacAddress(string $campo)
 * @method $this setForeignId(string $campo, int|string|null $valor)
 * @method ?int getForeignId(string $campo)
 * @method $this setEmail(string $campo, ?string $valor)
 * @method ?string getEmail(string $campo)
 * @method $this setPhone(string $campo, ?string $valor)
 * @method ?string getPhone(string $campo)
 * @method $this setCpf(string $campo, ?string $valor)
 * @method ?string getCpf(string $campo)
 * @method $this setCnpj(string $campo, ?string $valor)
 * @method ?string getCnpj(string $campo)
 * @method $this setCep(string $campo, ?string $valor)
 * @method ?string getCep(string $campo)
 * @method $this setColor(string $campo, ?string $valor)
 * @method ?string getColor(string $campo)
 * @method $this setUrl(string $campo, ?string $valor)
 * @method ?string getUrl(string $campo)
 * @method $this setPassword(string $campo, ?string $valor)
 * @method ?string getPassword(string $campo)
 * @method $this setFile(string $campo, ?string $valor)
 * @method ?string getFile(string $campo)
 * @method $this setImage(string $campo, ?string $valor)
 * @method ?string getImage(string $campo)
 * @method $this setMultiSelect(string $campo, array|string|null $valor)
 * @method ?array getMultiSelect(string $campo)
 * @method $this iniciaTransação()
 * @method bool finalizaTransação()
 * @method $this transaçãoCommit()
 * @method $this transaçãoRollBack()
 */
class ModelProperty
{
    use Utilidades;

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

    protected MdTabela $tabela;

    protected BaseModel $model;

    /** @var Collection<string, MdCampo> campos por nome físico */
    protected Collection $campos;

    protected bool $todosFilhos = false;

    /** @var list<string> */
    protected array $filhos = [];

    /** Transações abertas por este objeto (iniciaTransacao) */
    protected int $transacoes = 0;

    /** Alguma operação falhou dentro da transação aberta? */
    protected ?Throwable $erro = null;

    /**
     * @param  string  $modulo  prefixo (op), nome (Operacional) ou pasta do módulo
     * @param  string  $tabela  nome físico com ou sem prefixo (op_produto ou produto)
     * @param  mixed  $id  carrega o registro na hora
     */
    public function __construct(string $modulo, string $tabela, mixed $id = null)
    {
        $this->iniciar(Registro::tabela($modulo, $tabela), Registro::model(Registro::tabela($modulo, $tabela)));

        if ($id !== null) {
            $this->setId($id);
        }
    }

    /**
     * Envolve um model já carregado (usado nas tabelas filhas).
     */
    public static function deModel(MdTabela $tabela, BaseModel $model): static
    {
        $instancia = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $instancia->iniciar($tabela, $model);

        return $instancia;
    }

    protected function iniciar(MdTabela $tabela, BaseModel $model): void
    {
        $tabela->loadMissing(['campos', 'modulo', 'situacoes.status']);

        $this->tabela = $tabela;
        $this->model = $model;
        $this->campos = $tabela->campos->keyBy('nome_fisico');
    }

    // ------------------------------------------------------------------
    // Registro
    // ------------------------------------------------------------------

    /**
     * Informa a chave e carrega o registro do banco (com as filhas escolhidas).
     */
    public function setId(mixed $id): static
    {
        $this->model->setPrimaryKey($id)->refresh();
        $this->carregarFilhos();

        return $this;
    }

    public function getId(): mixed
    {
        return $this->model->getKey();
    }

    public function setPrimaryKey(mixed $id): static
    {
        return $this->setId($id);
    }

    public function getPrimaryKey(): mixed
    {
        return $this->getId();
    }

    /**
     * O registro existe no banco (foi carregado ou inserido)?
     */
    public function existe(): bool
    {
        return $this->model->exists;
    }

    /**
     * Recarrega do banco (descarta o que foi setado e não gravado).
     */
    public function recarregar(): static
    {
        return $this->setId($this->getId() ?? throw new EventoException('Informe o id antes de recarregar.'));
    }

    /**
     * Sem argumentos: o model deste registro. Com módulo e nome: o model de outra tabela (como em Utilidades).
     */
    public function getModel(?string $modulo = null, ?string $nome = null): BaseModel
    {
        return $modulo === null ? $this->model : Registro::model(Registro::tabela($modulo, (string) $nome));
    }

    /**
     * Sem argumentos: o controller desta tabela. Com módulo e nome: o de outra tabela.
     */
    public function getController(?string $modulo = null, ?string $nome = null): BaseController
    {
        return $modulo === null ? Registro::controller($this->tabela) : Registro::controller(Registro::tabela($modulo, (string) $nome));
    }

    public function getTabela(?string $modulo = null, ?string $nome = null): MdTabela
    {
        return $modulo === null ? $this->tabela : Registro::tabela($modulo, (string) $nome);
    }

    public function toArray(): array
    {
        return $this->model->attributesToArray();
    }

    // ------------------------------------------------------------------
    // Tabelas filhas
    // ------------------------------------------------------------------

    /**
     * Traz todas as tabelas filhas (as que têm FK para esta).
     */
    public function setGetAllChildrens(bool $todas = true): static
    {
        $this->todosFilhos = $todas;

        return $this->carregarFilhos();
    }

    /**
     * Acrescenta tabelas filhas (nome físico) às que serão trazidas.
     *
     * @param  string|list<string>  $filhas
     */
    public function setGetChildren(string|array $filhas): static
    {
        $this->filhos = array_values(array_unique([...$this->filhos, ...array_map('mb_strtoupper', (array) $filhas)]));

        return $this->carregarFilhos();
    }

    /**
     * Registros de uma tabela filha, ou todas: ['op_preco' => [ModelProperty, ...]].
     *
     * @return list<ModelProperty>|array<string, list<ModelProperty>>
     */
    public function getChildren(?string $filha = null): array
    {
        $filha = $filha === null ? null : mb_strtoupper($filha);
        $filhas = $this->model->tabelasFilhas();
        $carregadas = collect($this->model->getRelations())->only(array_keys($filhas));

        if ($filha !== null) {
            if (! isset($filhas[$filha])) {
                throw new EventoException("{$filha} não é filha de {$this->tabela->nome_fisico}. Filhas: " . (implode(', ', array_keys($filhas)) ?: 'nenhuma') . '.');
            }

            if (! $carregadas->has($filha)) {
                throw new EventoException("A filha {$filha} não foi carregada: use setGetChildren('{$filha}') ou setGetAllChildrens().");
            }

            return array_map(fn (BaseModel $m) => static::deModel($filhas[$filha][0], $m), $carregadas->get($filha));
        }

        return $carregadas->map(fn (array $registros, string $nome) => array_map(fn (BaseModel $m) => static::deModel($filhas[$nome][0], $m), $registros))->all();
    }

    public function getChildrens(): array
    {
        return $this->getChildren();
    }

    protected function carregarFilhos(): static
    {
        if ($this->model->exists && ($this->todosFilhos || $this->filhos !== [])) {
            $this->model->preencherFilhos($this->todosFilhos ? null : $this->filhos);
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Campos: genéricos
    // ------------------------------------------------------------------

    /**
     * Seta o campo validando pelo tipo dele no dicionário.
     */
    public function setCampo(string $campo, mixed $valor): static
    {
        $campo = $this->nomeDoCampo($campo);
        $this->model->setAttribute($campo, $this->paraGravar($this->tipoDo($campo), $campo, $valor));

        return $this;
    }

    /**
     * Valor do campo em texto (datas em Y-m-d / Y-m-d H:i:s, listas em JSON, lógico 1/0).
     */
    public function getCampo(string $campo): ?string
    {
        $campo = $this->nomeDoCampo($campo);
        $tipo = $this->tipoDo($campo);
        $valor = $this->paraLer($tipo, $campo);

        return match (true) {
            $valor === null => null,
            is_bool($valor) => $valor ? '1' : '0',
            is_array($valor) => json_encode($valor, JSON_UNESCAPED_UNICODE),
            $valor instanceof DateTimeInterface => $tipo->isDate() ? $valor->format('Y-m-d') : $valor->format('Y-m-d H:i:s'),
            default => (string) $valor,
        };
    }

    // ------------------------------------------------------------------
    // Gravação
    // ------------------------------------------------------------------

    /**
     * Insere o registro (transação: erro = rollback). Os eventos do model rodam.
     */
    public function insert(): static
    {
        if ($this->model->exists) {
            throw new EventoException("O registro {$this->getId()} já existe em {$this->tabela->nome_fisico}: use update().");
        }

        return $this->gravar(function () {
            if ($this->model->save() === false) {
                throw new EventoException('A inclusão foi cancelada pelas regras da tabela.');
            }
        });
    }

    /**
     * Atualiza o registro do id setado (transação: erro = rollback).
     */
    public function update(): static
    {
        if (! $this->model->exists) {
            throw new EventoException("Informe o id do registro de {$this->tabela->nome_fisico} antes do update() (ou use insert()).");
        }

        return $this->gravar(function () {
            if ($this->model->isDirty() && $this->model->save() === false) {
                throw new EventoException('A alteração foi cancelada pelas regras da tabela.');
            }
        });
    }

    /**
     * Exclui o registro do id setado (transação: erro = rollback).
     */
    public function delete(): static
    {
        if (! $this->model->exists) {
            throw new EventoException("Informe o id do registro de {$this->tabela->nome_fisico} antes do delete().");
        }

        return $this->gravar(function () {
            if ($this->model->delete() === false) {
                throw new EventoException('A exclusão foi cancelada pelas regras da tabela.');
            }
        });
    }

    protected function gravar(callable $operacao): static
    {
        try {
            // dentro de iniciaTransacao() vira um savepoint
            $this->conexao()->transaction($operacao);
        } catch (Throwable $e) {
            $this->erro = $e;

            throw $e;
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Transação manual
    // ------------------------------------------------------------------

    public function iniciaTransacao(): static
    {
        $this->conexao()->beginTransaction();
        $this->transacoes++;
        $this->erro = null;

        return $this;
    }

    public function transacaoCommit(): static
    {
        $this->exigirTransacao('transacaoCommit');
        $this->conexao()->commit();
        $this->transacoes--;

        return $this;
    }

    /**
     * Desfaz tudo desde o iniciaTransacao(). O objeto continua com os valores
     * setados; use recarregar() para voltar ao que está no banco.
     */
    public function transacaoRollBack(): static
    {
        $this->exigirTransacao('transacaoRollBack');
        $this->conexao()->rollBack();
        $this->transacoes--;

        return $this;
    }

    /**
     * Encerra as transações abertas por este objeto: commit se tudo deu
     * certo, rollback se alguma operação falhou. Retorna true no commit.
     */
    public function finalizaTransacao(): bool
    {
        $commit = $this->erro === null;

        while ($this->transacoes > 0) {
            $commit ? $this->transacaoCommit() : $this->transacaoRollBack();
        }

        $this->erro = null;

        return $commit;
    }

    public function emTransacao(): bool
    {
        return $this->transacoes > 0;
    }

    /**
     * Erro da última operação que falhou dentro da transação aberta.
     */
    public function getErro(): ?Throwable
    {
        return $this->erro;
    }

    protected function exigirTransacao(string $metodo): void
    {
        if ($this->transacoes === 0) {
            throw new EventoException("{$metodo}(): não há transação aberta. Chame iniciaTransacao() antes.");
        }
    }

    protected function conexao(): ConnectionInterface
    {
        return $this->model->getConnection();
    }

    /**
     * Transação esquecida aberta é desfeita (nada fica pendurado na conexão).
     */
    public function __destruct()
    {
        try {
            while ($this->transacoes > 0) {
                $this->conexao()->rollBack();
                $this->transacoes--;
            }
        } catch (Throwable) {
            // conexão já encerrada
        }
    }

    // ------------------------------------------------------------------
    // set{Tipo} / get{Tipo} e nomes com acento
    // ------------------------------------------------------------------

    public function __call(string $metodo, array $argumentos): mixed
    {
        // iniciaTransação() -> iniciaTransacao()
        $semAcento = Str::ascii($metodo);
        if ($semAcento !== $metodo && method_exists($this, $semAcento)) {
            return $this->{$semAcento}(...$argumentos);
        }

        if (preg_match('/^(set|get)(.+)$/i', $metodo, $m) && ($tipo = static::tipoDoMetodo($m[2]))) {
            $campo = $this->nomeDoCampo($argumentos[0] ?? throw new BadMethodCallException("{$metodo}(): informe o nome do campo."));
            $this->conferirTipo($tipo, $campo, $metodo);

            if (strtolower($m[1]) === 'get') {
                return $this->paraLer($tipo, $campo);
            }

            if (! array_key_exists(1, $argumentos)) {
                throw new BadMethodCallException("{$metodo}('{$campo}', valor): informe o valor.");
            }

            $this->model->setAttribute($campo, $this->paraGravar($tipo, $campo, $argumentos[1]));

            return $this;
        }

        throw new BadMethodCallException('Método ' . static::class . "::{$metodo}() não existe. Use set/get + o tipo do campo (setString, getDecimal...) ou setCampo/getCampo.");
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

    /**
     * Tipo do campo no dicionário (situacao = enum; id = bigInteger).
     */
    /**
     * Nomes físicos são em maiúscula: aceita 'descricao' ou 'DESCRICAO' (id e datas do Laravel ficam como são).
     */
    protected function nomeDoCampo(string $campo): string
    {
        return in_array(mb_strtolower($campo), [$this->model->getKeyName(), 'created_at', 'updated_at'], true) ? mb_strtolower($campo) : mb_strtoupper($campo);
    }

    protected function tipoDo(string $campo): TipoCampo
    {
        if ($campo === $this->model->getKeyName()) {
            return TipoCampo::UnsignedBigInteger;
        }

        if ($campo === MdTabela::COLUNA_SITUACAO && $this->tabela->controla_situacao) {
            return TipoCampo::Enum;
        }

        if (in_array($campo, ['created_at', 'updated_at'], true)) {
            return TipoCampo::Timestamp;
        }

        return $this->campos->get($campo)?->tipo
            ?? throw new EventoException("O campo {$campo} não existe em {$this->tabela->nome_fisico}. Campos: " . $this->campos->keys()->join(', ') . '.');
    }

    protected function conferirTipo(TipoCampo $tipo, string $campo, string $metodo): void
    {
        $doCampo = $this->tipoDo($campo);

        // aceita o tipo do campo ou o tipo de coluna em que ele é gravado (email -> string)
        // na chave primária vale qualquer inteiro (setInt('id', 1))
        $chave = $campo === $this->model->getKeyName() && $tipo->isInteger();

        if ($tipo === $doCampo || $tipo === $doCampo->storageType() || $chave) {
            return;
        }

        $sugestao = preg_replace('/^(set|get).*/i', '$1', $metodo) . ucfirst($doCampo->value);

        throw new EventoException("O campo {$campo} é do tipo {$doCampo->label()}: use {$sugestao}() (ou setCampo/getCampo).");
    }

    // ------------------------------------------------------------------
    // Conversões
    // ------------------------------------------------------------------

    protected function paraGravar(TipoCampo $tipo, string $campo, mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $md = $this->campos->get($campo);
        $erro = fn (string $esperado) => new EventoException("{$this->tabela->nome_fisico}.{$campo}: valor " . var_export($valor, true) . " inválido ({$esperado}).");

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

    protected function paraLer(TipoCampo $tipo, string $campo): mixed
    {
        $valor = $this->model->getAttribute($campo);

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
            throw $erro('no máximo ' . ($precisao - $escala) . " dígitos antes da vírgula e {$escala} casas decimais");
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

        return "{$m[1]}:{$m[2]}:" . ($m[4] ?? '00');
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
            throw $erro('opções: ' . implode(', ', $md?->opcoes ?? []));
        }

        return $valores;
    }

    protected function opcao(string $campo, ?MdCampo $md, mixed $valor, \Closure $erro): string
    {
        $opcoes = $campo === MdTabela::COLUNA_SITUACAO && ! $md
            ? $this->tabela->situacoes->map(fn ($s) => $s->status?->nome_fisico)->filter()->values()->all()
            : array_map('strval', $md?->opcoes ?? []);

        if (! in_array((string) $valor, $opcoes, true)) {
            throw $erro('opções: ' . implode(', ', $opcoes));
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

    protected function regraPassa(\Illuminate\Contracts\Validation\ValidationRule $regra, string $valor): bool
    {
        $passou = true;
        $regra->validate('valor', $valor, function () use (&$passou) {
            $passou = false;
        });

        return $passou;
    }

}

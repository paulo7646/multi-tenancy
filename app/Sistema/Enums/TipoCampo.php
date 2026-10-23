<?php

namespace App\Sistema\Enums;

/**
 * Tipos de campo do dicionário de dados (md_campos).
 * O valor é o método do Blueprint usado na migration.
 *
 * Ficam de fora os tipos que não fazem sentido como campo de tela
 * (id/increments, morphs, timestamps, softDeletes, rememberToken),
 * binary e os que o PostgreSQL não suporta sem extensão (set, geometry,
 * geography, vector).
 */
enum TipoCampo: string
{
    // Texto
    case String = 'string';
    case Char = 'char';
    case Text = 'text';
    case TinyText = 'tinyText';
    case MediumText = 'mediumText';
    case LongText = 'longText';

    // Números inteiros
    case Integer = 'integer';
    case TinyInteger = 'tinyInteger';
    case SmallInteger = 'smallInteger';
    case MediumInteger = 'mediumInteger';
    case BigInteger = 'bigInteger';
    case UnsignedInteger = 'unsignedInteger';
    case UnsignedTinyInteger = 'unsignedTinyInteger';
    case UnsignedSmallInteger = 'unsignedSmallInteger';
    case UnsignedMediumInteger = 'unsignedMediumInteger';
    case UnsignedBigInteger = 'unsignedBigInteger';

    // Números decimais
    case Decimal = 'decimal';
    case Float = 'float';
    case Double = 'double';

    // Lógico
    case Boolean = 'boolean';

    // Data e hora
    case Date = 'date';
    case DateTime = 'dateTime';
    case DateTimeTz = 'dateTimeTz';
    case Time = 'time';
    case TimeTz = 'timeTz';
    case Timestamp = 'timestamp';
    case TimestampTz = 'timestampTz';
    case Year = 'year';

    // Estruturados
    case Json = 'json';
    case Jsonb = 'jsonb';
    case Enum = 'enum';

    // Identificadores e rede
    case Uuid = 'uuid';
    case Ulid = 'ulid';
    case IpAddress = 'ipAddress';
    case MacAddress = 'macAddress';

    // Relacionamento
    case ForeignId = 'foreignId';

    // Campos especiais: tipos de tela gravados num tipo de coluna comum (storageType)
    case Email = 'email';
    case Phone = 'phone';
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case Cep = 'cep';
    case Color = 'color';
    case Url = 'url';
    case Password = 'password';
    case File = 'file';
    case Image = 'image';
    case MultiSelect = 'multiSelect';

    public function label(): string
    {
        return match ($this) {
            self::String => 'Texto curto (string)',
            self::Char => 'Texto fixo (char)',
            self::Text => 'Texto longo (text)',
            self::TinyText => 'Texto pequeno (tinyText)',
            self::MediumText => 'Texto médio (mediumText)',
            self::LongText => 'Texto muito longo / editor (longText)',
            self::Integer => 'Inteiro (integer)',
            self::TinyInteger => 'Inteiro mínimo (tinyInteger)',
            self::SmallInteger => 'Inteiro pequeno (smallInteger)',
            self::MediumInteger => 'Inteiro médio (mediumInteger)',
            self::BigInteger => 'Inteiro grande (bigInteger)',
            self::UnsignedInteger => 'Inteiro sem sinal (unsignedInteger)',
            self::UnsignedTinyInteger => 'Inteiro mínimo sem sinal (unsignedTinyInteger)',
            self::UnsignedSmallInteger => 'Inteiro pequeno sem sinal (unsignedSmallInteger)',
            self::UnsignedMediumInteger => 'Inteiro médio sem sinal (unsignedMediumInteger)',
            self::UnsignedBigInteger => 'Inteiro grande sem sinal (unsignedBigInteger)',
            self::Decimal => 'Decimal / dinheiro (decimal)',
            self::Float => 'Ponto flutuante (float)',
            self::Double => 'Ponto flutuante duplo (double)',
            self::Boolean => 'Sim/Não (boolean)',
            self::Date => 'Data (date)',
            self::DateTime => 'Data e hora (dateTime)',
            self::DateTimeTz => 'Data e hora com fuso (dateTimeTz)',
            self::Time => 'Hora (time)',
            self::TimeTz => 'Hora com fuso (timeTz)',
            self::Timestamp => 'Timestamp (timestamp)',
            self::TimestampTz => 'Timestamp com fuso (timestampTz)',
            self::Year => 'Ano (year)',
            self::Json => 'JSON / chave-valor (json)',
            self::Jsonb => 'JSON binário / chave-valor (jsonb)',
            self::Enum => 'Lista de opções (enum)',
            self::Uuid => 'UUID (uuid)',
            self::Ulid => 'ULID (ulid)',
            self::IpAddress => 'Endereço IP (ipAddress)',
            self::MacAddress => 'Endereço MAC (macAddress)',
            self::ForeignId => 'Chave estrangeira / lookup (foreignId)',
            self::Email => 'E-mail',
            self::Phone => 'Telefone (com máscara)',
            self::Cpf => 'CPF (com máscara e validação)',
            self::Cnpj => 'CNPJ (com máscara e validação)',
            self::Cep => 'CEP (com máscara)',
            self::Color => 'Cor (seletor)',
            self::Url => 'Endereço web (URL)',
            self::Password => 'Senha (gravada criptografada)',
            self::File => 'Arquivo (upload)',
            self::Image => 'Imagem (upload)',
            self::MultiSelect => 'Múltipla escolha (várias opções)',
        };
    }

    /**
     * Tipo de coluna usado no banco. Os tipos especiais são gravados num tipo comum.
     */
    public function storageType(): self
    {
        return match ($this) {
            self::Email, self::Url, self::Password, self::File, self::Image => self::String,
            self::Phone, self::Cpf, self::Cnpj, self::Cep, self::Color => self::String,
            self::MultiSelect => self::Jsonb,
            default => $this,
        };
    }

    /**
     * Tamanho padrão da coluna para os tipos de texto.
     */
    public function defaultLength(): int
    {
        return match ($this) {
            self::Phone => 20,
            self::Cpf => 14,
            self::Cnpj => 18,
            self::Cep => 9,
            self::Color => 9,
            default => 255,
        };
    }

    /**
     * Tipo do PostgreSQL usado para converter dados ao trocar o tipo da coluna (USING col::tipo).
     */
    public function postgresCast(?int $length = null, ?int $precision = null, ?int $scale = null): string
    {
        $type = $this->storageType();

        return match (true) {
            $type === self::String, $type === self::Enum => 'varchar(' . ($length ?: 255) . ')',
            $type === self::Char => 'char(' . ($length ?: 255) . ')',
            in_array($type, [self::Text, self::TinyText, self::MediumText, self::LongText], true) => 'text',
            in_array($type, [self::TinyInteger, self::SmallInteger, self::UnsignedTinyInteger, self::UnsignedSmallInteger], true) => 'smallint',
            in_array($type, [self::BigInteger, self::UnsignedBigInteger, self::ForeignId], true) => 'bigint',
            $type->isInteger(), $type === self::Year => 'integer',
            $type === self::Decimal => 'decimal(' . ($precision ?: 10) . ', ' . ($scale ?? 2) . ')',
            $type === self::Float, $type === self::Double => 'double precision',
            $type === self::Boolean => 'boolean',
            $type === self::Date => 'date',
            in_array($type, [self::DateTime, self::Timestamp], true) => 'timestamp(0) without time zone',
            in_array($type, [self::DateTimeTz, self::TimestampTz], true) => 'timestamp(0) with time zone',
            $type === self::Time => 'time(0) without time zone',
            $type === self::TimeTz => 'time(0) with time zone',
            $type === self::Json => 'json',
            $type === self::Jsonb => 'jsonb',
            $type === self::Uuid => 'uuid',
            $type === self::Ulid => 'char(26)',
            $type === self::IpAddress => 'inet',
            $type === self::MacAddress => 'macaddr',
            default => 'text',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::String, self::Char, self::Text, self::TinyText, self::MediumText, self::LongText => 'Texto',
            self::Integer, self::TinyInteger, self::SmallInteger, self::MediumInteger, self::BigInteger,
            self::UnsignedInteger, self::UnsignedTinyInteger, self::UnsignedSmallInteger,
            self::UnsignedMediumInteger, self::UnsignedBigInteger => 'Números inteiros',
            self::Decimal, self::Float, self::Double => 'Números decimais',
            self::Boolean => 'Lógico',
            self::Date, self::DateTime, self::DateTimeTz, self::Time, self::TimeTz,
            self::Timestamp, self::TimestampTz, self::Year => 'Data e hora',
            self::Json, self::Jsonb, self::Enum => 'Estruturados',
            self::Uuid, self::Ulid, self::IpAddress, self::MacAddress => 'Identificadores e rede',
            self::ForeignId => 'Relacionamento',
            self::Email, self::Phone, self::Cpf, self::Cnpj, self::Cep, self::Color, self::Url,
            self::Password, self::File, self::Image, self::MultiSelect => 'Campos especiais',
        };
    }

    /**
     * Opções agrupadas para o combo de tipos.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $type) => $type->group())
            ->map(fn ($types) => $types->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all())
            ->all();
    }

    public function hasLength(): bool
    {
        return in_array($this, [self::String, self::Char], true);
    }

    /**
     * Opções definidas no cadastro (lista do enum ou da múltipla escolha).
     */
    public function hasChoices(): bool
    {
        return in_array($this, [self::Enum, self::MultiSelect], true);
    }

    public function isUpload(): bool
    {
        return in_array($this, [self::File, self::Image], true);
    }

    public function hasPrecision(): bool
    {
        return in_array($this, [self::Decimal, self::Float], true);
    }

    public function hasScale(): bool
    {
        return $this === self::Decimal;
    }

    public function hasOptions(): bool
    {
        return $this === self::Enum;
    }

    public function isForeign(): bool
    {
        return $this === self::ForeignId;
    }

    public function isInteger(): bool
    {
        return $this->group() === 'Números inteiros';
    }

    public function isUnsigned(): bool
    {
        return str_starts_with($this->value, 'unsigned') || $this === self::ForeignId;
    }

    public function isNumeric(): bool
    {
        return $this->isInteger() || in_array($this, [self::Decimal, self::Float, self::Double, self::Year], true);
    }

    public function isDate(): bool
    {
        return $this === self::Date;
    }

    public function isDateTime(): bool
    {
        return in_array($this, [self::DateTime, self::DateTimeTz, self::Timestamp, self::TimestampTz], true);
    }

    public function isTime(): bool
    {
        return in_array($this, [self::Time, self::TimeTz], true);
    }

    public function isJson(): bool
    {
        return in_array($this, [self::Json, self::Jsonb], true);
    }

    /**
     * Cast do Eloquent para o model gerado.
     */
    public function cast(?int $scale = null): ?string
    {
        return match (true) {
            $this === self::Password => 'hashed',
            $this === self::MultiSelect => 'array',
            $this === self::Boolean => 'boolean',
            $this->isDate() => 'date',
            $this->isDateTime() => 'datetime',
            $this->isJson() => 'array',
            $this === self::Decimal => 'decimal:' . ($scale ?? 2),
            in_array($this, [self::Float, self::Double], true) => 'float',
            $this->isInteger(), $this === self::Year, $this === self::ForeignId => 'integer',
            default => null,
        };
    }
}

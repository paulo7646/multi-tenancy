<?php

namespace App\Sistema\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Componentes do Filament que um campo pode usar no formulário, e os grupos
 * (componentes de layout). Cada tipo de campo aceita alguns: o primeiro da
 * lista é o padrão. "Oculto" (Hidden) está sempre disponível.
 */
enum TipoComponente: string implements HasLabel
{
    // campos
    case Texto = 'texto';
    case Numero = 'numero';
    case Senha = 'senha';
    case Memo = 'memo';
    case Editor = 'editor';
    case Markdown = 'markdown';
    case Codigo = 'codigo';
    case Combo = 'combo';
    case Radio = 'radio';
    case Botoes = 'botoes';
    case Multipla = 'multipla';
    case Checkboxes = 'checkboxes';
    case Tags = 'tags';
    case Toggle = 'toggle';
    case Checkbox = 'checkbox';
    case Slider = 'slider';
    case Data = 'data';
    case DataHora = 'data_hora';
    case DataHoraSegundo = 'data_hora_segundo';
    case Hora = 'hora';
    case HoraSegundo = 'hora_segundo';
    case Cor = 'cor';
    case Arquivo = 'arquivo';
    case Imagem = 'imagem';
    case ChaveValor = 'chave_valor';
    case Lookup = 'lookup';
    case Oculto = 'oculto';

    // grupos
    case Secao = 'secao';
    case Moldura = 'moldura';
    case Grade = 'grade';

    public function getLabel(): string
    {
        return match ($this) {
            self::Texto => 'Campo de texto (TextInput)',
            self::Numero => 'Número (TextInput numérico)',
            self::Senha => 'Senha (TextInput de senha)',
            self::Memo => 'Área de texto (Textarea)',
            self::Editor => 'Editor rico (RichEditor)',
            self::Markdown => 'Editor Markdown (MarkdownEditor)',
            self::Codigo => 'Editor de código (CodeEditor)',
            self::Combo => 'Combo (Select)',
            self::Radio => 'Opções (Radio)',
            self::Botoes => 'Botões de opção (ToggleButtons)',
            self::Multipla => 'Combo de várias opções (Select múltiplo)',
            self::Checkboxes => 'Lista de caixas (CheckboxList)',
            self::Tags => 'Etiquetas (TagsInput)',
            self::Toggle => 'Liga/desliga (Toggle)',
            self::Checkbox => 'Caixa de marcar (Checkbox)',
            self::Slider => 'Controle deslizante (Slider)',
            self::Data => 'Data (DatePicker)',
            self::DataHora => 'Data e hora: hora e minuto (DateTimePicker)',
            self::DataHoraSegundo => 'Data e hora com segundos (DateTimePicker)',
            self::Hora => 'Hora: hora e minuto (TimePicker)',
            self::HoraSegundo => 'Hora com segundos (TimePicker)',
            self::Cor => 'Seletor de cor (ColorPicker)',
            self::Arquivo => 'Arquivo (FileUpload)',
            self::Imagem => 'Imagem (FileUpload de imagem)',
            self::ChaveValor => 'Chave e valor (KeyValue)',
            self::Lookup => 'Tabela (Select da FK)',
            self::Oculto => 'Oculto (Hidden)',
            self::Secao => 'Seção (Section)',
            self::Moldura => 'Moldura (Fieldset)',
            self::Grade => 'Grade sem moldura (Grid)',
        };
    }

    /**
     * Aceita máscara (TextInput)?
     */
    public function aceitaMascara(): bool
    {
        return in_array($this, [self::Texto, self::Numero], true);
    }

    /**
     * Componentes que o tipo de campo aceita; o primeiro é o padrão.
     *
     * @return list<self>
     */
    public static function para(TipoCampo $tipo): array
    {
        $lista = match (true) {
            $tipo->isForeign() => [self::Lookup],
            $tipo === TipoCampo::Enum => [self::Combo, self::Radio, self::Botoes],
            $tipo === TipoCampo::MultiSelect => [self::Multipla, self::Checkboxes],
            $tipo === TipoCampo::Boolean => [self::Toggle, self::Checkbox],
            $tipo->isDate() => [self::Data],
            $tipo->isDateTime() => [self::DataHora, self::DataHoraSegundo, self::Data],
            $tipo->isTime() => [self::Hora, self::HoraSegundo],
            $tipo->isJson() => [self::ChaveValor, self::Tags],
            $tipo === TipoCampo::Color => [self::Cor, self::Texto],
            $tipo === TipoCampo::File => [self::Arquivo],
            $tipo === TipoCampo::Image => [self::Imagem, self::Arquivo],
            $tipo === TipoCampo::Password => [self::Senha],
            $tipo->isNumeric() => [self::Numero, self::Slider, self::Texto],
            $tipo === TipoCampo::LongText => [self::Editor, self::Markdown, self::Memo, self::Codigo, self::Texto],
            in_array($tipo, [TipoCampo::Text, TipoCampo::TinyText, TipoCampo::MediumText], true) => [self::Memo, self::Texto, self::Editor, self::Markdown, self::Codigo],
            in_array($tipo, [TipoCampo::Cpf, TipoCampo::Cnpj, TipoCampo::Cep, TipoCampo::Phone, TipoCampo::Email, TipoCampo::Url,
                TipoCampo::Uuid, TipoCampo::Ulid, TipoCampo::IpAddress, TipoCampo::MacAddress], true) => [self::Texto],
            default => [self::Texto, self::Memo, self::Editor, self::Markdown],
        };

        return [...$lista, self::Oculto];
    }

    /**
     * Componentes da situação (coluna SITUACAO das tabelas que controlam situação).
     *
     * @return list<self>
     */
    public static function paraSituacao(): array
    {
        return [self::Botoes, self::Combo, self::Radio];
    }

    /**
     * Componentes de grupo (layout).
     *
     * @return list<self>
     */
    public static function grupos(): array
    {
        return [self::Secao, self::Moldura, self::Grade];
    }

    public static function padrao(TipoCampo $tipo): self
    {
        return static::para($tipo)[0];
    }

    /**
     * O componente escolhido, se o tipo de campo aceitar; senão o padrão.
     * Sem tipo de campo: a situação.
     */
    public static function resolver(?string $componente, ?TipoCampo $tipo): self
    {
        $aceitos = $tipo ? static::para($tipo) : static::paraSituacao();
        $escolhido = static::tryFrom((string) $componente);

        return $escolhido && in_array($escolhido, $aceitos, true) ? $escolhido : $aceitos[0];
    }

    public static function resolverGrupo(?string $componente): self
    {
        $escolhido = static::tryFrom((string) $componente);

        return $escolhido && in_array($escolhido, static::grupos(), true) ? $escolhido : self::Secao;
    }

    /**
     * @param  list<self>  $componentes
     * @return array<string, string>
     */
    public static function opcoes(array $componentes): array
    {
        return collect($componentes)->mapWithKeys(fn (self $c) => [$c->value => $c->getLabel()])->all();
    }

    /**
     * Máscaras prontas (formato do Filament: 9 = número, a = letra, * = qualquer).
     *
     * @return array<string, string>
     */
    public static function mascaras(): array
    {
        return [
            '999.999.999-99' => 'CPF',
            '99.999.999/9999-99' => 'CNPJ',
            '(99) 99999-9999' => 'Celular',
            '(99) 9999-9999' => 'Telefone fixo',
            '99999-999' => 'CEP',
            '99/99/9999' => 'Data',
            '99:99' => 'Hora',
            'aaa-9*99' => 'Placa de veículo',
            '$dinheiro' => 'Dinheiro (1.234,56)',
        ];
    }
}

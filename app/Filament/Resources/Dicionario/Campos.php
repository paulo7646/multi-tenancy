<?php

namespace App\Filament\Resources\Dicionario;

use App\Models\Md\MdFormularioBotao;
use App\Models\Md\MdGerenciamentoBotao;
use App\Models\Md\MdTabela;
use App\Navigation\NavigationCatalog;
use App\Sistema\Enums\Cor;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Component;
use Illuminate\Support\Str;

/**
 * Campos reaproveitados nas telas do dicionário.
 */
class Campos
{
    /** Tabelas do próprio sistema que o dicionário não pode usar */
    public const TABELAS_RESERVADAS = [
        'audits', 'cache', 'cache_locks', 'domains', 'failed_jobs', 'filiais', 'job_batches', 'jobs', 'licencas',
        'md_campos', 'md_formulario_botoes', 'md_formularios', 'md_gerenciamento_botoes', 'md_gerenciamentos',
        'md_status', 'md_tabela_status', 'md_tabelas', 'migrations', 'model_has_permissions', 'model_has_roles',
        'modulos', 'navigation_groups', 'navigation_items', 'notifications', 'password_reset_tokens', 'permissions',
        'role_has_permissions', 'roles', 'sessions', 'tenants', 'user_licenses', 'users',
    ];

    public const COLUNAS_RESERVADAS = ['ID', 'CREATED_AT', 'UPDATED_AT', 'SITUACAO'];

    /** Métodos das classes base que um botão não pode usar como nome físico */
    public const METODOS_RESERVADOS = ['getmodel', 'gettabela', 'abortar', 'notificar', 'usuario', 'tenantatual', '__construct'];

    public static function icone(string $nome = 'icone', string $label = 'Ícone'): Select
    {
        return Select::make($nome)
            ->label($label)
            ->searchable()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search) => NavigationCatalog::searchIcons($search))
            ->getOptionLabelUsing(fn (?string $value) => $value ? NavigationCatalog::iconLabel($value) : null)
            ->placeholder('Pesquise: user, cart, home...');
    }

    public static function cor(string $nome = 'cor', string $padrao = 'primary'): Select
    {
        return Select::make($nome)
            ->label('Cor')
            ->options(collect(Cor::cases())->mapWithKeys(fn (Cor $cor) => [$cor->value => static::amostraCor($cor)])->all())
            ->allowHtml()
            ->native(false)
            ->default($padrao)
            ->required();
    }

    protected static function amostraCor(Cor $cor): string
    {
        $tom = $cor->getColor();
        $css = is_array($tom) ? ($tom[500] ?? reset($tom)) : "var(--{$tom}-500)";

        return '<span style="display:inline-flex;align-items:center;gap:.5rem"><span style="width:.9rem;height:.9rem;border-radius:9999px;background:' . e($css) . '"></span>' . e($cor->getLabel()) . '</span>';
    }

    /**
     * Nome físico: minúsculas, números e _ começando por letra.
     */
    public static function nomeFisico(string $nome = 'nome_fisico', string $label = 'Nome físico', int $max = 60): TextInput
    {
        return TextInput::make($nome)
            ->label($label)
            ->required()
            ->maxLength($max)
            // nomes físicos sempre em maiúscula: aparece, valida e grava assim
            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
            ->mutateStateForValidationUsing(fn ($state) => is_string($state) ? mb_strtoupper(trim($state)) : $state)
            ->dehydrateStateUsing(fn ($state) => is_string($state) ? mb_strtoupper(trim($state)) : $state)
            ->regex('/^[A-Z][A-Z0-9_]*$/')
            ->validationMessages(['regex' => 'Use letras, números e _ (começando por letra). Fica em maiúscula.']);
    }

    /**
     * Nome físico sugerido a partir de um nome: "Preço de venda" -> PRECO_DE_VENDA.
     */
    public static function snake(?string $texto): string
    {
        return Str::of(Str::ascii((string) $texto))->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->toString();
    }

    /**
     * Nome do arquivo/classe (ModelProduto, ViewProduto...).
     */
    public static function nomeArquivo(string $label = 'Nome do arquivo', string $nome = 'nome_arquivo'): TextInput
    {
        return TextInput::make($nome)
            ->label($label)
            ->required()
            ->maxLength(80)
            ->regex('/^[A-Z][A-Za-z0-9_]*$/')
            ->validationMessages(['regex' => 'Nome de classe: começa com maiúscula, letras, números e _ (ex.: ViewProduto).'])
            ->suffix('.php');
    }

    /**
     * Classes já usadas na pasta da tabela, fora o próprio registro.
     *
     * @param  string  $tipo  view | gerenciamento | botoes | controller
     * @return list<string>
     */
    public static function classesEmUso(MdTabela $tabela, string $tipo, ?int $ignorar = null): array
    {
        $botoes = [
            ...MdFormularioBotao::whereIn('md_formulario_id', $tabela->formularios()->select('id'))->pluck('nome_arquivo'),
            ...MdGerenciamentoBotao::whereIn('md_gerenciamento_id', $tabela->gerenciamentos()->select('id'))->pluck('nome_arquivo'),
        ];

        return array_values(array_unique(array_filter([
            $tabela->classeModel(),
            ...($tipo === 'controller' ? [] : [$tabela->classeController()]),
            ...$tabela->formularios()->when($tipo === 'view' && $ignorar, fn ($q) => $q->whereKeyNot($ignorar))->pluck('nome_arquivo'),
            ...$tabela->gerenciamentos()->when($tipo === 'gerenciamento' && $ignorar, fn ($q) => $q->whereKeyNot($ignorar))->pluck('nome_arquivo'),
            // botões com o mesmo nome de arquivo dividem a classe: só conflitam com os outros tipos
            ...($tipo === 'botoes' ? [] : $botoes),
        ])));
    }

    /**
     * Regra: o nome do arquivo não pode ser de outra classe da mesma tabela.
     *
     * @param  Closure(Get): ?MdTabela  $tabela
     * @param  Closure(Component): ?int|null  $ignorar  id do próprio registro (na edição)
     */
    public static function regraClasseLivre(Closure $tabela, string $tipo, ?Closure $ignorar = null): Closure
    {
        return fn (Get $get, Component $livewire) => function (string $attribute, $value, Closure $fail) use ($tabela, $tipo, $ignorar, $get, $livewire) {
            $md = $tabela($get);

            if ($md && in_array($value, static::classesEmUso($md, $tipo, $ignorar ? $ignorar($livewire) : null), true)) {
                $fail("A classe {$value} já existe na pasta da tabela {$md->nome_fisico}.");
            }
        };
    }

    /**
     * Regra: o método do botão (nome físico) não pode se repetir na mesma
     * classe de botões, que é compartilhada por formulários e gerenciamentos.
     *
     * @param  Closure(Get, Component): list<string>  $outros  "Classe::metodo" dos outros botões da tabela
     */
    public static function regraBotaoUnico(Closure $outros): Closure
    {
        return fn (Get $get, Component $livewire) => function (string $attribute, $value, Closure $fail) use ($outros, $get, $livewire) {
            if (in_array(mb_strtolower((string) $value), static::METODOS_RESERVADOS, true)) {
                $fail("\"{$value}\" é um método reservado das classes do sistema.");

                return;
            }

            $classe = $get('nome_arquivo');

            if (in_array(mb_strtolower("{$classe}::{$value}"), array_map('mb_strtolower', $outros($get, $livewire)), true)) {
                $fail("A classe {$classe} já tem um botão \"{$value}\" (em outro formulário/gerenciamento).");
            }
        };
    }

    /**
     * "Classe::metodo" dos botões da tabela, fora os do formulário/gerenciamento informado.
     *
     * @return list<string>
     */
    public static function botoesDaTabela(?MdTabela $tabela, ?int $ignorarFormulario = null, ?int $ignorarGerenciamento = null): array
    {
        if (! $tabela) {
            return [];
        }

        return [
            ...MdFormularioBotao::whereIn('md_formulario_id', $tabela->formularios()->when($ignorarFormulario, fn ($q) => $q->whereKeyNot($ignorarFormulario))->select('id'))
                ->get()->map(fn ($b) => "{$b->nome_arquivo}::{$b->nome_fisico}"),
            ...MdGerenciamentoBotao::whereIn('md_gerenciamento_id', $tabela->gerenciamentos()->when($ignorarGerenciamento, fn ($q) => $q->whereKeyNot($ignorarGerenciamento))->select('id'))
                ->get()->map(fn ($b) => "{$b->nome_arquivo}::{$b->nome_fisico}"),
        ];
    }
}

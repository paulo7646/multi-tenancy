<?php

namespace App\Filament\Resources\Dicionario\Concerns;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Sistema\Geradores\ClassesWriter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Botões "Abrir arquivo" das telas do dicionário: abrem o arquivo gerado no VS Code (protocolo vscode://,
 * numa aba nova do editor). Sem o arquivo gerado o botão fica desabilitado: a tela usa o padrão do dicionário.
 */
trait AbreArquivosNoEditor
{
    /**
     * Endereço vscode:// do arquivo (null se ele não existe).
     */
    public static function urlNoEditor(string $caminho): ?string
    {
        if (! is_file($caminho)) {
            return null;
        }

        $caminho = str_replace('\\', '/', (string) realpath($caminho));

        return 'vscode://file/'.implode('/', array_map('rawurlencode', explode('/', $caminho)));
    }

    protected static function acaoAbrirArquivo(string $nome, string $rotulo, string $caminho): Action
    {
        $url = static::urlNoEditor($caminho);

        return Action::make($nome)
            ->label($rotulo)
            ->icon('heroicon-o-code-bracket')
            ->color('gray')
            ->url($url)
            ->disabled($url === null)
            ->tooltip($url === null ? 'Arquivo ainda não gerado: a tela usa o padrão do dicionário (Criar arquivos).' : 'Abre no VS Code');
    }

    /**
     * Na tabela: um botão com setinha que abre a lista (model, controller, formulários e gerenciamentos).
     */
    public static function grupoDeArquivosDaTabela(MdTabela $tabela): ActionGroup
    {
        $tabela->loadMissing(['modulo', 'formularios', 'gerenciamentos']);

        return ActionGroup::make([
            static::acaoAbrirArquivo('abrirModel', 'Abrir arquivo model', $tabela->caminho($tabela->classeModel().'.php')),
            static::acaoAbrirArquivo('abrirController', 'Abrir arquivo controller', ClassesWriter::controllerPath($tabela)),
            ...$tabela->formularios->map(fn (MdFormulario $f) => static::acaoAbrirArquivo("abrirFormulario{$f->id}", "Abrir arquivo formulário: {$f->titulo}", static::caminhoDoFormulario($f->setRelation('tabela', $tabela))))->all(),
            ...$tabela->gerenciamentos->map(fn (MdGerenciamento $g) => static::acaoAbrirArquivo("abrirGerenciamento{$g->id}", "Abrir arquivo gerenciamento: {$g->titulo}", ClassesWriter::resourcePath($g->setRelation('tabela', $tabela))))->all(),
        ])
            ->label('Arquivos')
            ->icon('heroicon-o-document-text')
            ->button()
            ->color('gray');
    }

    public static function caminhoDoFormulario(MdFormulario $formulario): string
    {
        return $formulario->tabela->caminho(($formulario->nome_arquivo ?: 'View').'.php');
    }

    public static function acaoAbrirArquivoDoFormulario(MdFormulario $formulario): Action
    {
        return static::acaoAbrirArquivo('abrirArquivo', 'Abrir arquivo', static::caminhoDoFormulario($formulario->loadMissing('tabela.modulo')));
    }

    public static function acaoAbrirArquivoDoGerenciamento(MdGerenciamento $gerenciamento): Action
    {
        return static::acaoAbrirArquivo('abrirArquivo', 'Abrir arquivo', ClassesWriter::resourcePath($gerenciamento->loadMissing('tabela.modulo')));
    }
}

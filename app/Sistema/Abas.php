<?php

namespace App\Sistema;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;

/**
 * Padrão de tela do projeto: um form só, com Tabs dentro dele. A primeira aba é sempre "Geral"
 * (informações gerais) e tudo que é registro filho ou assunto separado vira outra aba do mesmo form,
 * no lugar do relation manager solto embaixo do formulário.
 */
class Abas
{
    /**
     * Aba do form com a tabela de um registro filho (um RelationManager) dentro dela.
     * Só aparece com o registro já salvo (no "criar" não há a quem ligar) e se o usuário pode ver a relação.
     *
     * @param  class-string<RelationManager>  $relationManager
     */
    public static function filha(string $titulo, string $relationManager, string $icone): Tab
    {
        return Tab::make($titulo)
            ->icon($icone)
            ->visible(fn (?Model $record, $livewire): bool => (bool) $record?->exists
                && $relationManager::canViewForRecord($record, $livewire::class))
            ->schema([
                Livewire::make(
                    $relationManager,
                    fn (Model $record, $livewire): array => ['ownerRecord' => $record, 'pageClass' => $livewire::class],
                )->key($relationManager),
            ]);
    }
}

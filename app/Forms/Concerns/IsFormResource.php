<?php

namespace App\Forms\Concerns;

use App\Forms\FormPermissions;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use UnitEnum;

/**
 * Comportamento comum dos resources gerados pelo construtor de formulários.
 * A classe precisa declarar: protected static string $formTable.
 */
trait IsFormResource
{
    /**
     * Permissões do formulário (view_any_form_{tabela} etc.), concedidas ao super_admin e aos papéis do cadastro.
     */
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof \BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);
        $permission = FormPermissions::forAction(static::$formTable, $action);

        return $permission && auth()->user()?->can($permission) ? Response::allow() : Response::deny();
    }

    /**
     * Executa o método do controller de um botão. Se o método retornar uma
     * URL, redireciona; senão mostra "concluído".
     */
    protected static function runButton(Component $livewire, string $label, Closure $callback): void
    {
        $redirect = $callback();

        if (is_string($redirect) && $redirect !== '') {
            $livewire->redirect($redirect);

            return;
        }

        Notification::make()->title("{$label}: concluído")->success()->send();
    }
}

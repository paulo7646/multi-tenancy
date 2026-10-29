<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Operacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class OperacaoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_operacao');
    }

    public function view(AuthUser $authUser, Operacao $operacao): bool
    {
        return $authUser->can('view_operacao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_operacao');
    }

    public function update(AuthUser $authUser, Operacao $operacao): bool
    {
        return $authUser->can('update_operacao');
    }

    public function delete(AuthUser $authUser, Operacao $operacao): bool
    {
        return $authUser->can('delete_operacao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_operacao');
    }

    public function restore(AuthUser $authUser, Operacao $operacao): bool
    {
        return $authUser->can('restore_operacao');
    }

    public function forceDelete(AuthUser $authUser, Operacao $operacao): bool
    {
        return $authUser->can('force_delete_operacao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_operacao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_operacao');
    }

    public function replicate(AuthUser $authUser, Operacao $operacao): bool
    {
        return $authUser->can('replicate_operacao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_operacao');
    }

}
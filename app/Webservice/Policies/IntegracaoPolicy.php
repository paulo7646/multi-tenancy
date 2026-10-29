<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Integracao;
use Illuminate\Auth\Access\HandlesAuthorization;

class IntegracaoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_integracao');
    }

    public function view(AuthUser $authUser, Integracao $integracao): bool
    {
        return $authUser->can('view_integracao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_integracao');
    }

    public function update(AuthUser $authUser, Integracao $integracao): bool
    {
        return $authUser->can('update_integracao');
    }

    public function delete(AuthUser $authUser, Integracao $integracao): bool
    {
        return $authUser->can('delete_integracao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_integracao');
    }

    public function restore(AuthUser $authUser, Integracao $integracao): bool
    {
        return $authUser->can('restore_integracao');
    }

    public function forceDelete(AuthUser $authUser, Integracao $integracao): bool
    {
        return $authUser->can('force_delete_integracao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_integracao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_integracao');
    }

    public function replicate(AuthUser $authUser, Integracao $integracao): bool
    {
        return $authUser->can('replicate_integracao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_integracao');
    }

}
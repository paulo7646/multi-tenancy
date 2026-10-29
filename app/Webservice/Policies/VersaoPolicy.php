<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Versao;
use Illuminate\Auth\Access\HandlesAuthorization;

class VersaoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_versao');
    }

    public function view(AuthUser $authUser, Versao $versao): bool
    {
        return $authUser->can('view_versao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_versao');
    }

    public function update(AuthUser $authUser, Versao $versao): bool
    {
        return $authUser->can('update_versao');
    }

    public function delete(AuthUser $authUser, Versao $versao): bool
    {
        return $authUser->can('delete_versao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_versao');
    }

    public function restore(AuthUser $authUser, Versao $versao): bool
    {
        return $authUser->can('restore_versao');
    }

    public function forceDelete(AuthUser $authUser, Versao $versao): bool
    {
        return $authUser->can('force_delete_versao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_versao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_versao');
    }

    public function replicate(AuthUser $authUser, Versao $versao): bool
    {
        return $authUser->can('replicate_versao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_versao');
    }

}
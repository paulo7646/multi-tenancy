<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Objeto;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObjetoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_objeto');
    }

    public function view(AuthUser $authUser, Objeto $objeto): bool
    {
        return $authUser->can('view_objeto');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_objeto');
    }

    public function update(AuthUser $authUser, Objeto $objeto): bool
    {
        return $authUser->can('update_objeto');
    }

    public function delete(AuthUser $authUser, Objeto $objeto): bool
    {
        return $authUser->can('delete_objeto');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_objeto');
    }

    public function restore(AuthUser $authUser, Objeto $objeto): bool
    {
        return $authUser->can('restore_objeto');
    }

    public function forceDelete(AuthUser $authUser, Objeto $objeto): bool
    {
        return $authUser->can('force_delete_objeto');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_objeto');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_objeto');
    }

    public function replicate(AuthUser $authUser, Objeto $objeto): bool
    {
        return $authUser->can('replicate_objeto');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_objeto');
    }

}
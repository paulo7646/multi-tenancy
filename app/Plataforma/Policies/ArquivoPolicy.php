<?php

declare(strict_types=1);

namespace App\Plataforma\Policies;

use App\Plataforma\Models\Arquivo;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ArquivoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_documento');
    }

    public function view(AuthUser $authUser, Arquivo $arquivo): bool
    {
        return $authUser->can('view_documento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_documento');
    }

    public function update(AuthUser $authUser, Arquivo $arquivo): bool
    {
        return $authUser->can('update_documento');
    }

    public function delete(AuthUser $authUser, Arquivo $arquivo): bool
    {
        return $authUser->can('delete_documento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_documento');
    }

    public function restore(AuthUser $authUser, Arquivo $arquivo): bool
    {
        return $authUser->can('restore_documento');
    }

    public function forceDelete(AuthUser $authUser, Arquivo $arquivo): bool
    {
        return $authUser->can('force_delete_documento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_documento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_documento');
    }

    public function replicate(AuthUser $authUser, Arquivo $arquivo): bool
    {
        return $authUser->can('replicate_documento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_documento');
    }
}

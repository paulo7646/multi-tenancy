<?php

declare(strict_types=1);

namespace App\Plataforma\Policies;

use App\Plataforma\Models\Assinatura;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AssinaturaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_assinatura');
    }

    public function view(AuthUser $authUser, Assinatura $assinatura): bool
    {
        return $authUser->can('view_assinatura');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_assinatura');
    }

    public function update(AuthUser $authUser, Assinatura $assinatura): bool
    {
        return $authUser->can('update_assinatura');
    }

    public function delete(AuthUser $authUser, Assinatura $assinatura): bool
    {
        return $authUser->can('delete_assinatura');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_assinatura');
    }

    public function restore(AuthUser $authUser, Assinatura $assinatura): bool
    {
        return $authUser->can('restore_assinatura');
    }

    public function forceDelete(AuthUser $authUser, Assinatura $assinatura): bool
    {
        return $authUser->can('force_delete_assinatura');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_assinatura');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_assinatura');
    }

    public function replicate(AuthUser $authUser, Assinatura $assinatura): bool
    {
        return $authUser->can('replicate_assinatura');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_assinatura');
    }
}

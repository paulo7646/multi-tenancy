<?php

declare(strict_types=1);

namespace App\Plataforma\Policies;

use App\Plataforma\Models\ModeloImpressao;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ModeloImpressaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_modelo::impressao');
    }

    public function view(AuthUser $authUser, ModeloImpressao $modeloImpressao): bool
    {
        return $authUser->can('view_modelo::impressao');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_modelo::impressao');
    }

    public function update(AuthUser $authUser, ModeloImpressao $modeloImpressao): bool
    {
        return $authUser->can('update_modelo::impressao');
    }

    public function delete(AuthUser $authUser, ModeloImpressao $modeloImpressao): bool
    {
        return $authUser->can('delete_modelo::impressao');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_modelo::impressao');
    }

    public function restore(AuthUser $authUser, ModeloImpressao $modeloImpressao): bool
    {
        return $authUser->can('restore_modelo::impressao');
    }

    public function forceDelete(AuthUser $authUser, ModeloImpressao $modeloImpressao): bool
    {
        return $authUser->can('force_delete_modelo::impressao');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_modelo::impressao');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_modelo::impressao');
    }

    public function replicate(AuthUser $authUser, ModeloImpressao $modeloImpressao): bool
    {
        return $authUser->can('replicate_modelo::impressao');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_modelo::impressao');
    }
}

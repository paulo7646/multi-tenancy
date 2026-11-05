<?php

declare(strict_types=1);

namespace App\Plataforma\Policies;

use App\Plataforma\Models\Pasta;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PastaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_pasta');
    }

    public function view(AuthUser $authUser, Pasta $pasta): bool
    {
        return $authUser->can('view_pasta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_pasta');
    }

    public function update(AuthUser $authUser, Pasta $pasta): bool
    {
        return $authUser->can('update_pasta');
    }

    public function delete(AuthUser $authUser, Pasta $pasta): bool
    {
        return $authUser->can('delete_pasta');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_pasta');
    }

    public function restore(AuthUser $authUser, Pasta $pasta): bool
    {
        return $authUser->can('restore_pasta');
    }

    public function forceDelete(AuthUser $authUser, Pasta $pasta): bool
    {
        return $authUser->can('force_delete_pasta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_pasta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_pasta');
    }

    public function replicate(AuthUser $authUser, Pasta $pasta): bool
    {
        return $authUser->can('replicate_pasta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_pasta');
    }
}

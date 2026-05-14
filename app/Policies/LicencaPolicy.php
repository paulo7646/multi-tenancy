<?php

namespace App\Policies;

use App\Models\Licenca;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LicencaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_licenca');
    }

    public function view(User $user, Licenca $licenca): bool
    {
        return $user->can('view_licenca');
    }

    public function create(User $user): bool
    {
        return $user->can('create_licenca');
    }

    public function update(User $user, Licenca $licenca): bool
    {
        return $user->can('update_licenca');
    }

    public function delete(User $user, Licenca $licenca): bool
    {
        return $user->can('delete_licenca');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_licenca');
    }

    public function forceDelete(User $user, Licenca $licenca): bool
    {
        return $user->can('force_delete_licenca');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_licenca');
    }

    public function restore(User $user, Licenca $licenca): bool
    {
        return $user->can('restore_licenca');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_licenca');
    }

    public function replicate(User $user, Licenca $licenca): bool
    {
        return $user->can('replicate_licenca');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_licenca');
    }
}

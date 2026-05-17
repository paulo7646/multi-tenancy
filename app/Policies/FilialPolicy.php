<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Filial;
use Illuminate\Auth\Access\HandlesAuthorization;

class FilialPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_filial');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Filial $filial): bool
    {
        return $user->can('view_filial');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_filial');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Filial $filial): bool
    {
        return $user->can('update_filial');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Filial $filial): bool
    {
        return $user->can('delete_filial');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_filial');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Filial $filial): bool
    {
        return $user->can('force_delete_filial');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_filial');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Filial $filial): bool
    {
        return $user->can('restore_filial');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_filial');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Filial $filial): bool
    {
        return $user->can('replicate_filial');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_filial');
    }
}

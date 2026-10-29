<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Evento;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_evento');
    }

    public function view(AuthUser $authUser, Evento $evento): bool
    {
        return $authUser->can('view_evento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_evento');
    }

    public function update(AuthUser $authUser, Evento $evento): bool
    {
        return $authUser->can('update_evento');
    }

    public function delete(AuthUser $authUser, Evento $evento): bool
    {
        return $authUser->can('delete_evento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_evento');
    }

    public function restore(AuthUser $authUser, Evento $evento): bool
    {
        return $authUser->can('restore_evento');
    }

    public function forceDelete(AuthUser $authUser, Evento $evento): bool
    {
        return $authUser->can('force_delete_evento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_evento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_evento');
    }

    public function replicate(AuthUser $authUser, Evento $evento): bool
    {
        return $authUser->can('replicate_evento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_evento');
    }

}
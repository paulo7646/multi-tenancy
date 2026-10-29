<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Agendamento;
use Illuminate\Auth\Access\HandlesAuthorization;

class AgendamentoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_agendamento');
    }

    public function view(AuthUser $authUser, Agendamento $agendamento): bool
    {
        return $authUser->can('view_agendamento');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_agendamento');
    }

    public function update(AuthUser $authUser, Agendamento $agendamento): bool
    {
        return $authUser->can('update_agendamento');
    }

    public function delete(AuthUser $authUser, Agendamento $agendamento): bool
    {
        return $authUser->can('delete_agendamento');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_agendamento');
    }

    public function restore(AuthUser $authUser, Agendamento $agendamento): bool
    {
        return $authUser->can('restore_agendamento');
    }

    public function forceDelete(AuthUser $authUser, Agendamento $agendamento): bool
    {
        return $authUser->can('force_delete_agendamento');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_agendamento');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_agendamento');
    }

    public function replicate(AuthUser $authUser, Agendamento $agendamento): bool
    {
        return $authUser->can('replicate_agendamento');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_agendamento');
    }

}
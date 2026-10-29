<?php

declare(strict_types=1);

namespace App\Webservice\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Webservice\Models\Webservice;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebservicePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_webservice');
    }

    public function view(AuthUser $authUser, Webservice $webservice): bool
    {
        return $authUser->can('view_webservice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_webservice');
    }

    public function update(AuthUser $authUser, Webservice $webservice): bool
    {
        return $authUser->can('update_webservice');
    }

    public function delete(AuthUser $authUser, Webservice $webservice): bool
    {
        return $authUser->can('delete_webservice');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_webservice');
    }

    public function restore(AuthUser $authUser, Webservice $webservice): bool
    {
        return $authUser->can('restore_webservice');
    }

    public function forceDelete(AuthUser $authUser, Webservice $webservice): bool
    {
        return $authUser->can('force_delete_webservice');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_webservice');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_webservice');
    }

    public function replicate(AuthUser $authUser, Webservice $webservice): bool
    {
        return $authUser->can('replicate_webservice');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_webservice');
    }

}
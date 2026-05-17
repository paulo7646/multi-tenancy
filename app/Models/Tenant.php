<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

use OwenIt\Auditing\Contracts\Auditable;

class Tenant extends BaseTenant implements TenantWithDatabase, Auditable
{
    use HasDatabase, HasDomains, \OwenIt\Auditing\Auditable;

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function userLicenses(): HasMany
    {
        return $this->hasMany(UserLicense::class);
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'password',
            'is_active',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];


    public function setPasswordAttribute($value)
    {
        return $this->attributes['password'] = Hash::Make($value);
    }
}

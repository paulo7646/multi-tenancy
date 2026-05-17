<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLicense extends Model
{

    protected $connection = 'central';

    protected $fillable = [
        'user_email',
        'tenant_id',
        'license_key',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public static function findActive(string $email, string $tenantId): ?static
    {
        $base = static::where('user_email', $email)
            ->where('status', 'active')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        // licença específica ao tenant tem prioridade sobre a global
        return (clone $base)->where('tenant_id', $tenantId)->first()
            ?? (clone $base)->whereNull('tenant_id')->first();
    }
}

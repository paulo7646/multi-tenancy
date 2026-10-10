<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

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
        'status' => LicenseStatus::class,
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Trava final: nenhuma licença é ativada além do limite da empresa,
        // venha de onde vier (formulário, ação da tabela, tinker...).
        static::saving(function (UserLicense $license) {
            if ($license->status !== LicenseStatus::Active || ! $license->isDirty(['status', 'tenant_id'])) {
                return;
            }

            if ($license->tenant?->licenseLimitReached(except: $license->getKey())) {
                throw ValidationException::withMessages([
                    'status' => $license->tenant->licenseLimitMessage(),
                ]);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === LicenseStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Licenças ativas e não vencidas.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LicenseStatus::Active)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public static function findActive(string $email, string $tenantId): ?static
    {
        $base = static::active()->where('user_email', $email);

        // licença específica ao tenant tem prioridade sobre a global
        return (clone $base)->where('tenant_id', $tenantId)->first()
            ?? (clone $base)->whereNull('tenant_id')->first();
    }
}

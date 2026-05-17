<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Session;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Traits\HasFilialScope;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles, \OwenIt\Auditing\Auditable, HasFilialScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'licenca_id',
        'filial_id',
    ];

    public function licenca(): BelongsTo
    {
        return $this->belongsTo(Licenca::class);
    }

    /**
     * Retorna o ID da filial ativa para este usuário.
     * Se o usuário tem filial_id fixo, retorna ele.
     * Se não tem, retorna o que está na sessão (ou null = todas as filiais).
     */
    public function getActiveFilialId(): ?int
    {
        if ($this->filial_id !== null) {
            return $this->filial_id;
        }

        return Session::get('filial_ativa');
    }

    public function hasFilial(): bool
    {
        return $this->filial_id !== null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}

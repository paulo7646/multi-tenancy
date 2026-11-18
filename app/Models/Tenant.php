<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

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
            'slug',
            'name',
            'email',
            'password',
            'is_active',
            'qtd_licencas',
        ];
    }

    /**
     * Nome para exibição (o "name" do tenant é sempre o usuário admin).
     */
    public function displayName(): string
    {
        return $this->slug ?? $this->domains->first()?->domain ?? $this->getTenantKey();
    }

    public function activeLicensesCount(?int $except = null): int
    {
        return $this->userLicenses()
            ->active()
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->count();
    }

    /**
     * Limite de licenças ATIVAS da empresa (qtd_licencas). null = sem limite.
     * $except ignora a própria licença que está sendo ativada.
     */
    public function licenseLimitReached(?int $except = null): bool
    {
        return $this->qtd_licencas !== null
            && $this->activeLicensesCount($except) >= $this->qtd_licencas;
    }

    public function licenseLimitMessage(): string
    {
        return "A empresa {$this->displayName()} já tem {$this->activeLicensesCount()} de {$this->qtd_licencas} licenças ativas. "
            .'Aumente a quantidade de licenças da empresa para ativar esta.';
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Define uma senha nova para o administrador da empresa (o usuário do banco dela com o e-mail
     * da empresa) e a mantém igual na linha da empresa. Entregue a senha em texto: o hash é
     * aplicado uma vez só (cast do User e mutator deste model).
     *
     * @throws \RuntimeException se o administrador não existe no banco da empresa
     */
    public function redefinirSenhaDoAdministrador(string $senha): void
    {
        $achou = $this->executar(function () use ($senha) {
            $admin = User::withoutGlobalScopes()->where('email', $this->email)->first();

            $admin?->forceFill(['password' => $senha])->save();

            return $admin !== null;
        });

        if (! $achou) {
            throw new \RuntimeException("O administrador {$this->email} não existe no banco desta empresa.");
        }

        $this->forceFill(['password' => $senha])->save();
    }

    /**
     * Como run(), mas devolve a conexão ao estado anterior mesmo se o callback lançar exceção.
     * O run() do Stancl não tem try/finally: uma exceção deixaria a empresa inicializada e a
     * conexão padrão apontando para o banco dela (até a sessão, que usa o banco, seria gravada lá).
     */
    public function executar(callable $callback): mixed
    {
        $original = tenant();

        try {
            return $this->run($callback);
        } finally {
            if (tenant()?->getTenantKey() !== $original?->getTenantKey()) {
                $original ? tenancy()->initialize($original) : tenancy()->end();
            }
        }
    }

    public function setPasswordAttribute($value)
    {
        return $this->attributes['password'] = Hash::Make($value);
    }
}

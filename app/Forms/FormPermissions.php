<?php

namespace App\Forms;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Permissões das telas geradas, no padrão já usado pelo Shield no projeto
 * (ex.: view_any_form_clientes). Criadas pelas próprias migrations geradas,
 * no banco onde a migration roda (central ou cada empresa), e concedidas aos
 * papéis escolhidos no cadastro (o super_admin recebe sempre).
 */
class FormPermissions
{
    /** ação do Filament => prefixo da permissão */
    public const ACTIONS = [
        'viewAny' => 'view_any',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'deleteAny' => 'delete_any',
    ];

    /**
     * @return list<string>
     */
    public static function names(string $table): array
    {
        return array_map(fn (string $prefix) => "{$prefix}_form_{$table}", array_values(static::ACTIONS));
    }

    public static function forAction(string $table, string $action): ?string
    {
        $prefix = static::ACTIONS[$action] ?? null;

        return $prefix ? "{$prefix}_form_{$table}" : null;
    }

    /**
     * Cria as permissões e concede aos papéis (os que existirem neste banco).
     * O super_admin sempre recebe: no projeto ele não passa pelo Gate
     * automaticamente (define_via_gate = false), tem as permissões explícitas.
     */
    public static function install(array $permissions, array $roles = []): void
    {
        $registrar = app(PermissionRegistrar::class);

        foreach ($permissions as $permission) {
            $registrar->getPermissionClass()::findOrCreate($permission, 'web');
        }

        $roles = array_unique([...$roles, config('filament-shield.super_admin.name', 'super_admin')]);

        $registrar->getRoleClass()::whereIn('name', $roles)->get()
            ->each(fn ($role) => $role->givePermissionTo($permissions));

        static::forgetCache();
    }

    public static function uninstall(array $permissions): void
    {
        app(PermissionRegistrar::class)->getPermissionClass()::whereIn('name', $permissions)->delete();

        static::forgetCache();
    }

    /**
     * O PermissionRegistrar guarda o cache do primeiro banco em que foi usado:
     * no tenants:migrate (várias empresas no mesmo processo) ele limparia o
     * cache da empresa errada. Por isso a chave também é apagada direto na
     * tabela de cache do banco atual.
     */
    protected static function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $store = config('permission.cache.store') === 'default' ? config('cache.default') : config('permission.cache.store');

        if (config("cache.stores.{$store}.driver") !== 'database') {
            return;
        }

        try {
            DB::table(config("cache.stores.{$store}.table", 'cache'))
                ->where('key', 'like', '%' . config('permission.cache.key') . '%')
                ->delete();
        } catch (Throwable) {
            // banco sem tabela de cache: nada a limpar
        }
    }

    /**
     * Nomes de papéis existentes (central + empresas), para sugerir no cadastro.
     *
     * @return list<string>
     */
    public static function roleSuggestions(): array
    {
        $roleClass = app(PermissionRegistrar::class)->getRoleClass();
        $names = $roleClass::pluck('name');

        foreach (Tenant::all() as $tenant) {
            $names = $names->merge($tenant->run(fn () => $roleClass::pluck('name')));
        }

        return $names->unique()->sort()->values()->all();
    }
}

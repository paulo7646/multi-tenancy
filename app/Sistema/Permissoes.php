<?php

namespace App\Sistema;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Permissões de acesso aos módulos (modulo_{prefixo}). São criadas pelas
 * migrations geradas, no banco onde a migration roda (central ou cada
 * cliente), e concedidas aos papéis escolhidos no cadastro do módulo.
 */
class Permissoes
{
    public static function superAdmin(): string
    {
        return config('filament-shield.super_admin.name', 'super_admin');
    }

    /**
     * Cria a permissão e deixa exatamente estes papéis (mais o super_admin)
     * com ela, entre os papéis que existem neste banco.
     */
    public static function sincronizar(string $permissao, array $roles): void
    {
        // Consulta direto no banco atual: o PermissionRegistrar guarda as
        // permissões em memória e, no mesmo processo (tenants:migrate, central
        // depois de um cliente), devolveria a permissão de outro banco.
        $registrar = app(PermissionRegistrar::class);
        $permission = $registrar->getPermissionClass()::query()->firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        $roles = array_unique([...$roles, static::superAdmin()]);

        foreach ($registrar->getRoleClass()::query()->get() as $role) {
            in_array($role->name, $roles, true)
                ? $role->permissions()->syncWithoutDetaching([$permission->getKey()])
                : $role->permissions()->detach($permission->getKey());
        }

        static::esquecerCache();
    }

    /**
     * Cria as permissões (se não existirem) e concede a estes papéis (mais o
     * super_admin), sem tirar de quem já tem: depois disso quem manda é o Shield.
     */
    public static function criar(array $permissoes, array $roles): void
    {
        $registrar = app(PermissionRegistrar::class);
        $roles = array_unique([...$roles, static::superAdmin()]);
        $ids = collect($permissoes)
            ->map(fn (string $nome) => $registrar->getPermissionClass()::query()->firstOrCreate(['name' => $nome, 'guard_name' => 'web'])->getKey())
            ->all();

        foreach ($registrar->getRoleClass()::query()->whereIn('name', $roles)->get() as $role) {
            $role->permissions()->syncWithoutDetaching($ids);
        }

        static::esquecerCache();
    }

    public static function remover(string ...$permissoes): void
    {
        app(PermissionRegistrar::class)->getPermissionClass()::query()->whereIn('name', $permissoes)->delete();

        static::esquecerCache();
    }

    /**
     * Pode a ação? O super_admin pode tudo.
     */
    public static function pode(string $permissao): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->hasRole(static::superAdmin()) || $user->can($permissao));
    }

    /**
     * O PermissionRegistrar guarda o cache do primeiro banco em que foi usado:
     * no tenants:migrate (vários clientes no mesmo processo) ele limparia o
     * cache do cliente errado. Por isso a chave também é apagada direto na
     * tabela de cache do banco atual.
     */
    protected static function esquecerCache(): void
    {
        app(PermissionRegistrar::class)->clearPermissionsCollection();

        $store = config('permission.cache.store') === 'default' ? config('cache.default') : config('permission.cache.store');

        if (config("cache.stores.{$store}.driver") !== 'database') {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

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
     * Nomes de papéis existentes (central + clientes), para sugerir no cadastro.
     *
     * @return array<string, string>
     */
    public static function sugestoesDePapeis(): array
    {
        $roleClass = app(PermissionRegistrar::class)->getRoleClass();
        $names = $roleClass::pluck('name');

        foreach (Tenant::all() as $tenant) {
            $names = $names->merge($tenant->run(fn () => $roleClass::pluck('name')));
        }

        return $names->unique()->reject(fn ($name) => $name === static::superAdmin())
            ->sort()->values()->mapWithKeys(fn ($name) => [$name => $name])->all();
    }
}

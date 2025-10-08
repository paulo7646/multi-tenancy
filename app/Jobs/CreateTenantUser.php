<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateTenantUser implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Tenant $tenant) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Pega o role do central
        $centralRole = Role::where('name', 'super_admin')->first();
        $permissions = $centralRole->permissions;

        $this->tenant->run(function () use ($permissions) {

            // Cria o usuário no tenant
            $user = User::create([
                'name' => $this->tenant->name,
                'email' => $this->tenant->email,
                'password' => $this->tenant->password,
            ]);

            // Cria o role no tenant
            $tenantRole = Role::firstOrCreate(['name' => 'super_admin']);

            // Cria todas as permissões no tenant, se não existirem
            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission->name]);
            }

            // Sincroniza permissões no role
            $tenantRole->syncPermissions(Permission::all());

            // Atribui o papel e permissões ao usuário
            $user->assignRole($tenantRole);
        });
    }
}

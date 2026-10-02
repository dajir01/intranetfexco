<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class AssignPermissionManagementToSistemasSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = Usuario::find(2);
        $permission = Permission::query()->where('name', 'usuarios.gestionar_permisos')->first();

        if (! $usuario || ! $permission) {
            $this->command?->warn('No se pudo habilitar la gestión de permisos para Sistemas.');

            return;
        }

        $usuario->permissions()->syncWithoutDetaching([$permission->id]);
        $this->command?->info('Gestión de permisos habilitada para Sistemas (ID: 2).');
    }
}

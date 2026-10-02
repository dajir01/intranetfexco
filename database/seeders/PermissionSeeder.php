<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('acl.permissions', []) as $permission) {
            $module = (string) ($permission['module'] ?? '');
            $action = (string) ($permission['action'] ?? '');

            if ($module === '' || $action === '') {
                continue;
            }

            Permission::updateOrCreate(
                ['name' => "{$module}.{$action}"],
                [
                    'module' => $module,
                    'action' => $action,
                ]
            );
        }
    }
}

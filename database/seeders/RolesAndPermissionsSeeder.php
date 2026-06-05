<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view dashboard',
            'manage watchlist',
            'manage strategies',
            'manage trades',
            'manage risk',
            'manage backtests',
            'access admin',
            'manage users',
            'view webhook logs',
            'view mcp audit',
            'mcp.read',
            'mcp.write',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Viewer: read-only.
        $viewer = Role::findOrCreate('Viewer', 'web');
        $viewer->syncPermissions(['view dashboard', 'mcp.read']);

        // Trader: full self-service trading workflow.
        $trader = Role::findOrCreate('Trader', 'web');
        $trader->syncPermissions([
            'view dashboard', 'manage watchlist', 'manage strategies',
            'manage trades', 'manage risk', 'manage backtests',
            'mcp.read', 'mcp.write',
        ]);

        // Admin: everything.
        $admin = Role::findOrCreate('Admin', 'web');
        $admin->syncPermissions(Permission::all());
    }
}

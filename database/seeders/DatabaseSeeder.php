<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles/permissions and the system default strategy first.
        $this->call([
            RolesAndPermissionsSeeder::class,
            DefaultStrategySeeder::class,
            ConfluenceStrategySeeder::class,
            VssCeStrategySeeder::class,
        ]);

        // Demo accounts, one per role.
        $demo = [
            ['Admin',  'Admin User',  'admin@optionsignal.local'],
            ['Trader', 'Trader User', 'trader@optionsignal.local'],
            ['Viewer', 'Viewer User', 'viewer@optionsignal.local'],
        ];

        foreach ($demo as [$role, $name, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$role]);

            $user->riskSetting()->firstOrCreate([], config('risk.defaults'));
        }

        // Seed watchlists for the demo users.
        $this->call(WatchlistSeeder::class);
    }
}

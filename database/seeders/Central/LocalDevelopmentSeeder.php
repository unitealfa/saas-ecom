<?php

namespace Database\Seeders\Central;

use App\Models\Central\Countries\Country;
use App\Models\Central\Roles\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Compte de démonstration ignoré hors environnement local ou testing.');

            return;
        }

        if (tenancy()->initialized) {
            throw new LogicException('Central context required.');
        }

        $admin = [
            'first_name' => 'Admin',
            'last_name' => 'Essai',
            'email' => 'admin@example.test',
            'password' => 'LocalTest!2026-Owner',
        ];

        $role = Role::where('is_super_admin', true)
            ->where('guard_name', 'central')
            ->firstOrFail();

        $user = User::unguarded(fn () => User::withTrashed()->firstOrCreate(
            ['email' => $admin['email']],
            [
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'password' => Hash::make($admin['password']),
                'country_id' => Country::where('code', 'DZ')->firstOrFail()->id,
                'email_verified_at' => now(),
            ]
        ));

        if ($user->wasRecentlyCreated) {
            $user->assignRole($role);
        }

        $this->command->info('Super Admin initialisé.');
    }
}

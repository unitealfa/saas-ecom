<?php

namespace Database\Seeders\Central;

use App\Models\Central\Activity;
use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\User;
use Database\Seeders\LocalFixtureSeeder;
use LogicException;

class LocalAuthenticationSeeder extends LocalFixtureSeeder
{
    public function run(): void
    {
        $this->assertLocalEnvironment();
        if (tenancy()->initialized) {
            throw new LogicException('Run authentication fixtures in the central context.');
        }
        $this->call([CountrySeeder::class, AuthorizationSeeder::class]);
        $connection = (new User)->getConnection();
        $connection->transaction(function (): void {
            Role::where('is_super_admin', true)->lockForUpdate()->firstOrFail();
            foreach ([
                'user-manager' => ['Gestionnaire', 'Essai', 'manager@example.test'],
                'user-reader' => ['Lecteur', 'Essai', 'reader@example.test'],
            ] as $roleName => [$firstName, $lastName, $email]) {
                $marker = 'local:central-auth:'.$roleName.':v1';
                $activity = Activity::where('operation_key', $marker)->first();
                if ($activity !== null) {
                    if (! User::withTrashed()->where('uuid', $activity->properties['user_uuid'])->exists()) {
                        throw new LogicException('A marked authentication fixture is missing; refusing to recreate it.');
                    }

                    continue;
                }
                if (User::withTrashed()->where('email', $email)->exists()) {
                    throw new LogicException('Refusing to adopt an existing unmarked account: '.$email);
                }
                $user = new User(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'password' => 'LocalTest!2026-Staff']);
                $user->country_id = Country::where('code', 'DZ')->where('is_active', true)->firstOrFail()->id;
                $user->email_verified_at = now();
                $user->save();
                $role = Role::where('name', $roleName)->where('guard_name', 'central')->firstOrFail();
                $user->assignRole($role);
                activity('local-fixtures')->performedOn($user)->event('local_authentication_seeded')
                    ->withProperties(['user_uuid' => $user->uuid, 'role_uuid' => $role->getAttribute('uuid')])
                    ->tap(function (Activity $activity) use ($marker): void {
                        $activity->operation_key = $marker;
                    })->log('Compte central fictif pour la démonstration.');
            }
        });
        $this->command->info('Central test accounts: manager@example.test and reader@example.test; local password: LocalTest!2026-Staff. Existing accounts are preserved.');
    }
}

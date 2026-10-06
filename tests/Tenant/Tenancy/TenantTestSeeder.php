<?php

namespace Tests\Tenant\Tenancy;

use App\Models\Central\Country;
use App\Models\Tenant;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use App\Services\Central\TenantProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class TenantTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('Example accounts and shops are limited to tests.');
        }

        if (tenancy()->initialized) {
            throw new LogicException('The test seeder must run in the central context.');
        }

        $country = Country::where('code', 'DZ')->firstOrFail();

        foreach ([1, 2] as $number) {
            $email = "boutique{$number}@test.com";
            $legacyEmail = "boutique_{$number}@gmail.com";
            $user = User::where('email', $email)->first()
                ?? User::where('email', $legacyEmail)->first()
                ?? new User;

            if (! $user->exists) {
                $user->first_name = 'Owner';
                $user->last_name = "Boutique {$number}";
                $user->email = $email;
                $user->password = 'password';
                $user->country_id = $country->id;
                $user->save();
            } elseif ($user->email === $legacyEmail) {
                $user->update(['first_name' => 'Owner', 'last_name' => "Boutique {$number}", 'email' => $email]);
            }

            $attributes = [
                'user_id' => $user->id,
                'internal_label' => "Boutique {$number}",
                'shop_name' => "Boutique {$number}",
                'slug' => "boutique{$number}",
                'document_prefix' => "BOUTIQUE{$number}",
                'creation_key' => "demo-boutique-{$number}",
            ];
            $hash = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));

            $tenant = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($user, $attributes, $hash): Tenant {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $tenant = Tenant::where('user_id', $user->id)->where('creation_key', $attributes['creation_key'])->first();

                if ($tenant !== null) {
                    if (! hash_equals($tenant->creation_hash, $hash)) {
                        throw new LogicException('The existing demo tenant has a different creation request.');
                    }

                    return $tenant;
                }

                return Tenant::create([...$attributes, 'creation_hash' => $hash]);
            }, attempts: 3);

            $domainName = $tenant->slug.'.'.config('tenancy.saas_base_domain');
            $primaryDomain = $tenant->domains()->where('is_primary', true)->first();

            if ($primaryDomain === null) {
                $tenant->domains()->create([
                    'domain' => $domainName,
                    'type' => 1,
                    'is_primary' => true,
                    'verification_status' => 2,
                    'verified_at' => now(),
                ]);
            } elseif ($primaryDomain->domain !== $domainName) {
                $primaryDomain->update(['domain' => $domainName]);
            }

            if (! $tenant->wasRecentlyCreated) {
                $database = $tenant->database();

                if (! $database->manager()->databaseExists($database->getName())) {
                    $tenant->setInternal('db_name', null);
                    $tenant->setInternal('db_name', $tenant->database()->getName());
                    $tenant->save();
                }

                app(TenantProvisioner::class)->provision($tenant);
            }

            $tenant->run(function () use ($user, $legacyEmail, $email): void {
                $localOwner = ShopUser::where('central_user_uuid', $user->uuid)->firstOrFail();

                if ($localOwner->email === $legacyEmail) {
                    $localOwner->update(['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $email]);
                }
            });
        }
    }
}

<?php

namespace Database\Seeders\Central;

use App\Models\Central\Country;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Central\TenantProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demonstration credentials are limited to development and tests.');
        }

        if (tenancy()->initialized) {
            throw new LogicException('The demo seeder must run in the central context.');
        }

        $country = Country::where('code', 'DZ')->firstOrFail();

        foreach ([1, 2] as $number) {
            $user = User::firstOrNew(['email' => "boutique_{$number}@gmail.com"]);

            if (! $user->exists) {
                $user->name = "user_{$number}";
                $user->password = 'password';
                $user->country_id = $country->id;
                $user->save();
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

            if (! $tenant->domains()->where('domain', "boutique{$number}.aydra.localhost")->exists()) {
                $tenant->domains()->create([
                    'domain' => "boutique{$number}.aydra.localhost",
                    'type' => 1,
                    'is_primary' => true,
                    'verification_status' => 2,
                    'verified_at' => now(),
                ]);
            }

            if (! $tenant->wasRecentlyCreated) {
                app(TenantProvisioner::class)->provision($tenant);
            }
        }
    }
}

<?php

use App\Enums\Central\Deployments\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Tenant\Shop;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

test('the durable central queue provisions two isolated MySQL shops with real owner links and retries safely', function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create();
    $databases = [];

    try {
        foreach (['nour', 'amine'] as $slug) {
            $this->actingAs($admin, 'central')->post(route('central.users.tenants.store', $owner), [
                'shop_name' => ucfirst($slug), 'slug' => $slug, 'creation_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $tenant = Tenant::where('slug', $slug)->firstOrFail();
            $databases[] = $tenant->database()->getName();
        }
        expect(DB::table('jobs')->count())->toBe(2);
        expect(TenantSchemaDeployment::where('status', StatusEnum::PENDING)->count())->toBe(2);
        foreach ($databases as $database) {
            expect(DB::selectOne('SELECT COUNT(*) AS count FROM information_schema.schemata WHERE schema_name = ?', [$database])->count)->toBe(0);
        }

        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--stop-when-empty' => true, '--no-interaction' => true]);
        expect(DB::table('jobs')->count())->toBe(0);
        expect(TenantSchemaDeployment::where('status', StatusEnum::SUCCEEDED)->count())->toBe(2);
        expect(DB::table('failed_jobs')->count())->toBe(0);
        foreach (Tenant::where('user_id', $owner->id)->get() as $tenant) {
            expect($tenant->schema_version)->not->toBeNull();
            $tenant->run(function () use ($tenant, $owner): void {
                expect(Shop::count())->toBe(1);
                expect(Shop::firstOrFail()->tenant_uuid)->toBe($tenant->uuid);
                expect(Shop::firstOrFail()->shop_name)->toBe($tenant->shop_name);
                expect(ShopUser::firstOrFail()->central_user_uuid)->toBe($owner->uuid);
                expect(ShopUser::firstOrFail()->password)->not->toBe($owner->password);
                expect(Shop::firstOrFail()->business_type)->toBe('OTHER');
                expect(Shop::firstOrFail()->cart_lifetime_days)->toBe(7);
            });
        }
        $deployment = TenantSchemaDeployment::firstOrFail();
        app()->call([new ProvisionTenantDatabase($deployment->id), 'handle']);
        expect($deployment->fresh()->attempt_number)->toBe(1);
    } finally {
        tenancy()->end();
        foreach ($databases as $database) {
            TestCase::dropIsolatedMysqlDatabase($database);
        }
    }
});

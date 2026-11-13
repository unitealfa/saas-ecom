<?php

use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Users\User as LocalUser;
use App\Models\User;
use App\Services\Tenant\Auth\OwnerLoginService;
use Database\Factories\Central\DomainFactory;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('verified owners enter their shop once through a browser-bound signed grant', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(FreePlanSeeder::class);
    $admin = User::factory()->create();
    $owner = User::factory()->create(['legal_verified_by_id' => $admin->id, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_profile_version' => 1]);
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $tenant->forceFill(['status' => TenantStatus::ACTIVE])->save();
    $domain = DomainFactory::new()->create(['tenant_id' => $tenant->id,
        'domain' => $tenant->slug.'.aydra.localhost', 'is_primary' => true,
        'verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now()]);
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $base = rtrim($domain->publicUrl(), '/');
    $this->get($base)->assertOk()->assertSee($tenant->shop_name);
    $cookieName = 'aydra_tenant_'.$tenant->id;
    $show = $this->get($base.'/login')->assertOk()->assertSee('Continuer avec mon compte Aydra');
    $access = $show->viewData('ownerAccessUrl');
    $cookie = $show->getCookie($cookieName, decrypt: false);
    expect($cookie)->not->toBeNull();
    $this->withUnencryptedCookie($cookieName, $cookie->getValue());
    $grant = $this->actingAs($owner, 'central')->get($access)->assertRedirect();
    $target = $grant->headers->get('Location');
    expect(str_starts_with($target, $base.'/auth/owner?'))->toBeTrue();
    expect(URL::hasValidSignature(Request::create($target), absolute: false))->toBeTrue();
    $this->withoutExceptionHandling();
    $accepted = $this->get($target)->assertRedirect($base.'/admin');
    $this->withUnencryptedCookie($cookieName, $accepted->getCookie($cookieName, decrypt: false)->getValue());
    $this->withExceptionHandling();
    $this->get($base.'/admin')->assertOk()->assertSee('Profil de la boutique');
    $this->get($base.'/admin/shop/profile')->assertOk();
    $this->get($target)->assertForbidden();
    $tenant->run(function () use ($owner): void {
        $local = LocalUser::where('central_user_uuid', $owner->uuid)->sole();
        expect($local->can('shop.profile.update'))->toBeTrue()
            ->and($local->joined_at)->not->toBeNull();
        $local->revokePermissionTo('shop.profile.update');
        $service = app(OwnerLoginService::class);
        $service->accept($owner->uuid, tenant()->uuid, hash('sha256', $owner->email));
        expect($local->fresh()->can('shop.profile.update'))->toBeFalse();
    });
    $other = User::factory()->create(['legal_verified_by_id' => $admin->id, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_profile_version' => 1]);
    $this->actingAs($other, 'central')->get($access)->assertForbidden();
    $this->travel(2)->minutes();
    expect(URL::hasValidSignature(Request::create($target), absolute: false))->toBeFalse();
    $this->get($target)->assertForbidden();
});

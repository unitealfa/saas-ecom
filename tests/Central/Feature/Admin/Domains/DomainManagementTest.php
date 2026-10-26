<?php

use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\Central\Domains\TypeEnum;
use App\Enums\VerificationStatusEnum;
use App\Http\Controllers\Storefront\StorefrontController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Models\Central\Activity;
use App\Models\Central\Domain;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\Central\Domains\DomainDnsInspector;
use App\Services\Central\Domains\DomainService;
use App\Services\Central\Roles\CentralRoleCatalog;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\DomainPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

function domainAdmin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    return $actor;
}

function domainActor(string $permission): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('domain-'.Str::random(10), [$permission => 7]));

    return $actor;
}

function domainShop(): Tenant
{
    Event::fake([TenantCreated::class]);
    $shop = Tenant::factory()->create();
    Event::assertDispatched(TenantCreated::class);

    return $shop;
}

function domainAddress(Tenant $shop, string $host, bool $primary = false): Domain
{
    $domain = $shop->domains()->make(['domain' => $host, 'type' => TypeEnum::CUSTOM, 'is_primary' => $primary]);
    $domain->forceFill(['verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now(),
        'certificate_status' => CertificateStatusEnum::ACTIVE])->save();

    return $domain->refresh();
}

test('domain permissions remain independent and protect all HTTP entry points', function (): void {
    domainAdmin();
    $domain = domainAddress(domainShop(), 'permissions.example.com', true);
    $abilities = ['saas.domains.view' => 'viewAny', 'saas.domains.create' => 'create', 'saas.domains.update' => 'update',
        'saas.domains.verify' => 'verify', 'saas.domains.primary.update' => 'setPrimary', 'saas.domains.archive' => 'archive'];
    foreach ($abilities as $permission => $ability) {
        $actor = domainActor($permission);
        foreach ($abilities as $otherPermission => $otherAbility) {
            expect(Gate::forUser($actor)->allows($otherAbility, in_array($otherAbility, ['viewAny', 'create'], true) ? Domain::class : $domain))
                ->toBe($permission === $otherPermission);
        }
    }
    $reader = domainActor('saas.users.view');
    $this->actingAs($reader, 'central');
    foreach (['index', 'create', 'edit'] as $action) {
        $this->get(route('central.domains.'.$action, $action === 'edit' ? $domain : []))->assertForbidden();
    }
    $this->post(route('central.domains.store'), [])->assertForbidden();
    $this->put(route('central.domains.update', $domain), [])->assertForbidden();
    $this->post(route('central.domains.verify', $domain), [])->assertForbidden();
    $this->patch(route('central.domains.primary', $domain), [])->assertForbidden();
    $this->delete(route('central.domains.destroy', $domain), [])->assertForbidden();
});

test('domain readers can search and see DNS instructions without editing actions or internal identifiers', function (): void {
    domainAdmin();
    $shop = domainShop();
    $domain = domainAddress($shop, 'reader.example.com', true);
    $reader = domainActor('saas.domains.view');
    $this->actingAs($reader, 'central')->get(route('central.domains.index', ['search' => 'reader.example.com']))
        ->assertOk()->assertSee('reader.example.com')->assertSee($shop->shop_name)->assertDontSee('Ajouter un domaine')
        ->assertDontSee('Définir comme principal')->assertDontSee('name="tenant_id"', false);
    $this->get(route('central.domains.index', ['search' => 'missing-host']))->assertViewHas('domains', fn ($domains) => $domains->total() === 0);
    $this->get(route('central.domains.edit', $domain))->assertForbidden();
    $this->get(route('central.domains.edit', ['domain' => $domain->id]))->assertNotFound();
});

test('a creator adds a normalized IDNA address pending verification without mutating another shop', function (): void {
    domainAdmin();
    $creator = domainActor('saas.domains.create');
    $shop = domainShop();
    $otherShop = domainShop();
    $this->actingAs($creator, 'central')->get(route('central.domains.create'))->assertOk()->assertSee($shop->uuid);
    $this->post(route('central.domains.store'), ['tenant_uuid' => $shop->uuid, 'domain' => '  CAFÉ.Example.com  '])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $domain = Domain::where('domain', 'xn--caf-dma.example.com')->firstOrFail();
    expect($domain->tenant_id)->toBe($shop->id)
        ->and($domain->verification_status)->toBe(VerificationStatusEnum::PENDING)
        ->and($domain->verified_at)->toBeNull()->and($domain->is_primary)->toBeFalse();
    expect($otherShop->domains()->exists())->toBeFalse();
    expect(Activity::forSubject($domain)->where('event', 'domain_created')->count())->toBe(1);
});

test('domains reject invalid reserved and foreign platform names', function (string $host): void {
    $admin = domainAdmin();
    $shop = domainShop();
    if ($host === 'another-shop.aydra.localhost') {
        Tenant::factory()->create(['slug' => 'another-shop']);
    }
    $this->actingAs($admin, 'central')->post(route('central.domains.store'), ['tenant_uuid' => $shop->uuid, 'domain' => $host])
        ->assertSessionHasErrors('domain');
    expect($shop->domains()->exists())->toBeFalse();
})->with(['https://shop.example.com/path', 'shop.example.com:443', '*.example.com', '127.0.0.1',
    'aydra.localhost', 'www.aydra.localhost', 'another-shop.aydra.localhost']);

test('creation rejects forged verification and ownership fields', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $this->actingAs($admin, 'central')->post(route('central.domains.store'), [
        'tenant_uuid' => $shop->uuid, 'domain' => 'forged.example.com',
        'tenant_id' => $shop->id, 'verification_status' => VerificationStatusEnum::VERIFIED->value, 'is_primary' => true,
    ])->assertSessionHasErrors(['tenant_id', 'verification_status', 'is_primary']);
    expect($shop->domains()->exists())->toBeFalse();
});

test('updates reset address proofs but reject stale forms and primary renaming', function (): void {
    $admin = domainAdmin();
    $domain = domainAddress(domainShop(), 'before.example.com');
    $token = $domain->managementState();
    $this->actingAs($admin, 'central')->get(route('central.domains.edit', $domain))->assertOk()->assertSee('_aydra-verification.before.example.com');
    $this->put(route('central.domains.update', $domain), ['domain' => 'after.example.com', 'expected_state' => $token])->assertSessionHasNoErrors();
    $domain->refresh();
    expect($domain->domain)->toBe('after.example.com')
        ->and($domain->verification_status)->toBe(VerificationStatusEnum::PENDING)
        ->and($domain->verified_at)->toBeNull()->and($domain->certificate_status)->toBeNull();
    $this->put(route('central.domains.update', $domain), ['domain' => 'stale.example.com', 'expected_state' => $token])->assertSessionHasErrors('domain');
    $domain->forceFill(['is_primary' => true])->save();
    $this->put(route('central.domains.update', $domain), ['domain' => 'unsafe.example.com', 'expected_state' => $domain->refresh()->managementState()])
        ->assertSessionHasErrors('domain');
    expect($domain->refresh()->domain)->toBe('after.example.com');
});

test('switching primary is atomic and rejects a stale current-primary snapshot', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $first = domainAddress($shop, 'first.example.com', true);
    $second = domainAddress($shop, 'second.example.com');
    $third = domainAddress($shop, 'third.example.com');
    $this->actingAs($admin, 'central')->patch(route('central.domains.primary', $second), [
        'expected_state' => $second->managementState(), 'expected_primary_uuid' => $first->uuid,
    ])->assertSessionHasNoErrors();
    expect($first->refresh()->is_primary)->toBeFalse()->and($second->refresh()->is_primary)->toBeTrue();
    $this->patch(route('central.domains.primary', $third), [
        'expected_state' => $third->managementState(), 'expected_primary_uuid' => $first->uuid,
    ])->assertSessionHasErrors('domain');
    expect($shop->domains()->where('is_primary', true)->count())->toBe(1);
    expect(Activity::forSubject($second)->where('event', 'domain_primary_changed')->count())->toBe(1);
});

test('unverified addresses and invalid certificates cannot become primary', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $domain = domainAddress($shop, 'not-ready.example.com');
    foreach ([
        ['verification_status' => VerificationStatusEnum::PENDING, 'verified_at' => null],
        ['verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now(), 'certificate_status' => CertificateStatusEnum::ERROR],
    ] as $attributes) {
        $domain->forceFill($attributes)->save();
        $this->actingAs($admin, 'central')->patch(route('central.domains.primary', $domain), [
            'expected_state' => $domain->refresh()->managementState(), 'expected_primary_uuid' => null,
        ])->assertSessionHasErrors('domain');
        expect($domain->refresh()->is_primary)->toBeFalse();
    }
});

test('archival blocks resolution keeps the reservation and preserves the primary domain', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $primary = domainAddress($shop, 'main.example.com', true);
    $domain = domainAddress($shop, 'archive.example.com');
    $this->actingAs($admin, 'central')->delete(route('central.domains.destroy', $primary), ['expected_state' => $primary->managementState()])
        ->assertSessionHasErrors('domain');
    $this->delete(route('central.domains.destroy', $domain), ['expected_state' => $domain->managementState()])->assertSessionHasNoErrors();
    expect(Domain::find($domain->id))->toBeNull()->and(Domain::withTrashed()->findOrFail($domain->id)->trashed())->toBeTrue();
    expect(fn () => app(DomainTenantResolver::class)->resolve($domain->domain))->toThrow(TenantCouldNotBeIdentifiedOnDomainException::class);
    $this->get('http://'.$domain->domain.'/')->assertNotFound();
    expect(tenancy()->initialized)->toBeFalse();
    $this->get(route('central.domains.index', ['archived' => 1]))->assertOk()->assertSee('archive.example.com');
    $this->post(route('central.domains.store'), ['tenant_uuid' => $shop->uuid, 'domain' => ' ARCHIVE.Example.com '])->assertSessionHasErrors('domain');
});

test('verification persists real inspector results and refuses stale snapshots after the external check', function (): void {
    $admin = domainAdmin();
    $domain = domainAddress(domainShop(), 'verify.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial();
    $inspector->shouldReceive('inspect')->once()->andReturn(['verified' => true, 'certificate' => CertificateStatusEnum::ACTIVE]);
    $this->app->instance(DomainDnsInspector::class, $inspector);
    $this->actingAs($admin, 'central')->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'Domaine vérifié.');
    expect(Activity::forSubject($domain)->where('event', 'domain_verification_checked')->count())->toBe(1);
    $domain->refresh();
    $inspector->shouldReceive('inspect')->once()->andReturnUsing(function (Domain $target): array {
        $target->forceFill(['domain' => 'concurrent.example.com'])->save();

        return ['verified' => true, 'certificate' => CertificateStatusEnum::ACTIVE];
    });
    $this->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertSessionHasErrors('domain');
    expect(Activity::forSubject($domain)->where('event', 'domain_verification_checked')->count())->toBe(1);
});

test('failed DNS ownership checks never report success and keep the host unavailable', function (): void {
    $admin = domainAdmin();
    $domain = domainAddress(domainShop(), 'failure.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial();
    $inspector->shouldReceive('inspect')->once()->andReturn(['verified' => false, 'certificate' => null]);
    $this->app->instance(DomainDnsInspector::class, $inspector);
    $this->actingAs($admin, 'central')->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertSessionHasErrors('domain')->assertSessionMissing('status');
    expect($domain->refresh()->verification_status)->toBe(VerificationStatusEnum::FAILED)->and($domain->verified_at)->toBeNull();
    $this->get('http://'.$domain->domain.'/')->assertNotFound();
});

test('DNS proof requires the exact token and valid platform routing', function (bool $tokenMatches, bool $cnameMatches): void {
    $domain = domainAddress(domainShop(), 'dns.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $token = $inspector->token($domain);
    $inspector->shouldReceive('records')->with('_aydra-verification.dns.example.com', DNS_TXT)
        ->once()->andReturn([['txt' => $tokenMatches ? $token : 'invalid']]);
    if ($tokenMatches) {
        $inspector->shouldReceive('records')->with('dns.example.com', DNS_CNAME)
            ->once()->andReturn([['target' => $cnameMatches ? config('tenancy.saas_base_domain').'.' : 'attacker.example.com']]);
        if (! $cnameMatches) {
            $inspector->shouldReceive('records')->with('dns.example.com', DNS_A | DNS_AAAA)->andReturn([['ip' => '1.1.1.1']]);
            $inspector->shouldReceive('records')->with(config('tenancy.saas_base_domain'), DNS_A | DNS_AAAA)->andReturn([['ip' => '8.8.8.8']]);
        }
    }
    if ($tokenMatches && $cnameMatches) {
        $inspector->shouldReceive('certificate')->with('dns.example.com')->once()->andReturn(CertificateStatusEnum::ACTIVE);
    }
    $result = $inspector->inspect($domain);
    expect($result['verified'])->toBe($tokenMatches && $cnameMatches);
})->with([[true, true], [true, false], [false, true]]);

test('TLS inspection refuses private and loopback addresses before opening a socket', function (string $address): void {
    $domain = domainAddress(domainShop(), 'private.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $inspector->shouldReceive('records')->with('_aydra-verification.private.example.com', DNS_TXT)
        ->andReturn([['txt' => $inspector->token($domain)]]);
    $inspector->shouldReceive('records')->with('private.example.com', DNS_CNAME)->andReturn([['target' => config('tenancy.saas_base_domain')]]);
    $inspector->shouldReceive('records')->with('private.example.com', DNS_A | DNS_AAAA)->andReturn([['ip' => $address]]);
    expect($inspector->inspect($domain)['certificate'])->toBe(CertificateStatusEnum::ERROR);
})->with(['127.0.0.1', '10.1.2.3', '169.254.169.254', '::1', '::ffff:127.0.0.1']);

test('the dedicated seeder adds only six permissions and preserves existing identities and assignments', function (): void {
    domainAdmin();
    $actor = domainActor('saas.users.view');
    $before = $actor->permissions()->pluck('permissions.uuid', 'permissions.name')->all();
    Permission::whereIn('name', array_keys(CentralRoleCatalog::DOMAIN_PERMISSIONS))->delete();
    $ids = Permission::pluck('uuid', 'name')->all();
    $this->seed(DomainPermissionSeeder::class);
    $after = Permission::pluck('uuid', 'name')->all();
    expect(array_diff_key($after, array_flip(array_keys(CentralRoleCatalog::DOMAIN_PERMISSIONS))))->toBe($ids);
    $this->seed(DomainPermissionSeeder::class);
    expect(Permission::pluck('uuid', 'name')->all())->toBe($after);
    expect($actor->permissions()->pluck('permissions.uuid', 'permissions.name')->all())->toBe($before);
    expect(Permission::whereIn('name', array_keys(CentralRoleCatalog::DOMAIN_PERMISSIONS))->count())->toBe(6);
});

test('domain mutations reject an archived shop even for the super administrator', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $domain = domainAddress($shop, 'archived-owner.example.com');
    $shop->delete();
    $this->actingAs($admin, 'central')->get(route('central.domains.edit', $domain))->assertForbidden();
    $this->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])->assertForbidden();
});

test('a permission revoked during DNS verification prevents its persistence', function (): void {
    domainAdmin();
    $actor = domainActor('saas.domains.verify');
    $domain = domainAddress(domainShop(), 'revoked.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial();
    $inspector->shouldReceive('inspect')->once()->andReturnUsing(function () use ($actor): array {
        $actor->revokePermissionTo('saas.domains.verify');

        return ['verified' => true, 'certificate' => CertificateStatusEnum::ACTIVE];
    });
    $this->app->instance(DomainDnsInspector::class, $inspector);
    $this->actingAs($actor, 'central')->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertForbidden();
    expect(Activity::forSubject($domain)->where('event', 'domain_verification_checked')->exists())->toBeFalse();
});

test('an audit failure rolls back the domain reservation', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    Event::listen('eloquent.creating: '.Activity::class, function (): void {
        throw new RuntimeException('Isolated audit failure.');
    });
    expect(fn () => app(DomainService::class)->create($admin, $shop->uuid, 'rollback.example.com'))
        ->toThrow(RuntimeException::class, 'Isolated audit failure.');
    expect(Domain::where('domain', 'rollback.example.com')->exists())->toBeFalse();
});

test('service mutations enforce the same permission when called outside a controller', function (): void {
    domainAdmin();
    $actor = domainActor('saas.domains.view');
    $domain = domainAddress(domainShop(), 'service.example.com');
    expect(fn () => app(DomainService::class)->archive($actor, $domain, $domain->managementState()))
        ->toThrow(AuthorizationException::class);
    expect($domain->refresh()->trashed())->toBeFalse();
});

test('central alias domains preserve verification primary selection and archive authorization', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $first = domainAddress($shop, 'alias-first.example.com', true);
    $second = domainAddress($shop, 'alias-second.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial();
    $inspector->shouldReceive('inspect')->once()->andReturn(['verified' => true, 'certificate' => CertificateStatusEnum::ACTIVE]);
    $this->app->instance(DomainDnsInspector::class, $inspector);
    $this->actingAs($admin, 'central')->post('http://localhost/admin/domains/'.$second->uuid.'/verification', [
        'expected_state' => $second->managementState(),
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.domains.index'));
    $this->patch('http://localhost/admin/domains/'.$second->uuid.'/primary', [
        'expected_state' => $second->refresh()->managementState(), 'expected_primary_uuid' => $first->uuid,
    ])->assertSessionHasNoErrors();
    $this->delete('http://localhost/admin/domains/'.$first->uuid, ['expected_state' => $first->refresh()->managementState()])
        ->assertSessionHasNoErrors();
    expect($second->refresh()->is_primary)->toBeTrue();
    expect(Domain::withTrashed()->findOrFail($first->id)->trashed())->toBeTrue();
});

test('temporary DNS failures preserve the existing proofs and availability', function (): void {
    $admin = domainAdmin();
    $domain = domainAddress(domainShop(), 'outage.example.com');
    $before = $domain->only(['verification_status', 'verified_at', 'certificate_status']);
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial();
    $inspector->shouldReceive('inspect')->once()->andReturn(['verified' => null, 'certificate' => null]);
    $this->app->instance(DomainDnsInspector::class, $inspector);
    $this->actingAs($admin, 'central')->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertSessionHasErrors(['domain' => 'La vérification DNS est impossible pour le moment. Les preuves et l’accès existants sont conservés. Vérifiez la configuration ou réessayez après propagation DNS.'])
        ->assertSessionMissing('status');
    expect($domain->refresh()->only(array_keys($before)))->toEqual($before);
    $this->get('http://'.$domain->domain.'/')->assertServiceUnavailable();
    expect(Activity::forSubject($domain)->where('event', 'domain_verification_checked')->firstOrFail()->getProperty('dns_check'))->toBeNull();
});

test('DNS success and HTTPS failure are reported separately without claiming complete success', function (?CertificateStatusEnum $certificate): void {
    $admin = domainAdmin();
    $domain = domainAddress(domainShop(), 'https-state.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial();
    $inspector->shouldReceive('inspect')->once()->andReturn(['verified' => true, 'certificate' => $certificate]);
    $this->app->instance(DomainDnsInspector::class, $inspector);
    $this->actingAs($admin, 'central')->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertSessionHasErrors('domain')->assertSessionMissing('status');
    expect($domain->refresh()->verification_status)->toBe(VerificationStatusEnum::VERIFIED)
        ->and($domain->certificate_status)->toBe($certificate ?? CertificateStatusEnum::ACTIVE);
})->with([null, CertificateStatusEnum::ERROR]);

test('apex and flattened DNS addresses can verify through matching public A and AAAA records', function (string $host, array $actual, array $expected, bool $matches): void {
    $domain = domainAddress(domainShop(), $host);
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $inspector->shouldReceive('records')->with('_aydra-verification.'.$host, DNS_TXT)->andReturn([['txt' => $inspector->token($domain)]]);
    $inspector->shouldReceive('records')->with($host, DNS_CNAME)->andReturn([]);
    $inspector->shouldReceive('records')->with($host, DNS_A | DNS_AAAA)->andReturn($actual);
    $inspector->shouldReceive('records')->with(config('tenancy.saas_base_domain'), DNS_A | DNS_AAAA)->andReturn($expected);
    if ($matches) {
        $inspector->shouldReceive('certificate')->with($host)->once()->andReturn(CertificateStatusEnum::ACTIVE);
    }
    expect($inspector->inspect($domain)['verified'])->toBe($matches);
})->with([
    ['example.com', [['ip' => '8.8.8.8']], [['ip' => '8.8.8.8']], true],
    ['flattened.example.com', [['ipv6' => '2606:4700:4700::1111']], [['ipv6' => '2606:4700:4700:0:0:0:0:1111']], true],
    ['wrong.example.com', [['ip' => '1.1.1.1']], [['ip' => '8.8.8.8']], false],
    ['mixed.example.com', [['ip' => '8.8.8.8'], ['ip' => '1.1.1.1']], [['ip' => '8.8.8.8']], false],
]);

test('DNS transport errors stay indeterminate instead of becoming negative ownership proofs', function (bool $txtFailure): void {
    $domain = domainAddress(domainShop(), 'unknown.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $inspector->shouldReceive('records')->with('_aydra-verification.unknown.example.com', DNS_TXT)
        ->andReturn($txtFailure ? null : [['txt' => $inspector->token($domain)]]);
    if (! $txtFailure) {
        $inspector->shouldReceive('records')->with('unknown.example.com', DNS_CNAME)->andReturn([]);
        $inspector->shouldReceive('records')->with('unknown.example.com', DNS_A | DNS_AAAA)->andReturn(null);
        $inspector->shouldReceive('records')->with(config('tenancy.saas_base_domain'), DNS_A | DNS_AAAA)->andReturn([['ip' => '8.8.8.8']]);
    }
    expect($inspector->inspect($domain))->toBe(['verified' => null, 'certificate' => null]);
})->with([true, false]);

test('review accepts available platform addresses without accepting central aliases', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $host = 'review-local.'.config('tenancy.saas_base_domain');
    $this->actingAs($admin, 'central')->post(route('central.domains.store'), ['tenant_uuid' => $shop->uuid, 'domain' => $host])
        ->assertSessionHasNoErrors();
    expect(Domain::where('domain', $host)->firstOrFail()->tenant_id)->toBe($shop->id);
    foreach (['aydra.localhost', 'localhost', 'child.localhost'] as $reserved) {
        $this->post(route('central.domains.store'), ['tenant_uuid' => $shop->uuid, 'domain' => $reserved])
            ->assertSessionHasErrors('domain');
    }
});

test('review rejects malformed domain values with validation errors rather than a server error', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $this->actingAs($admin, 'central')->postJson(route('central.domains.store'), [
        'tenant_uuid' => $shop->uuid, 'domain' => ['invalid' => 'host.example.com'],
    ])->assertUnprocessable()->assertJsonValidationErrors('domain');
    expect($shop->domains()->exists())->toBeFalse();
});

test('review preserves the no-self-redirect rule with a stale primary flag', function (): void {
    $domain = domainAddress(domainShop(), 'self-redirect.example.com', true);
    $snapshot = clone $domain;
    $snapshot->forceFill(['is_primary' => false]);
    $previous = DomainTenantResolver::$currentDomain;
    DomainTenantResolver::$currentDomain = $snapshot;
    try {
        $request = Request::create('https://self-redirect.example.com/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']);
        $controller = app(StorefrontController::class);
        $response = $controller($request, app(EnsureLocalDiagnostics::class));
        expect($response->getStatusCode())->toBe(503);
        expect($response->headers->has('Location'))->toBeFalse();
    } finally {
        DomainTenantResolver::$currentDomain = $previous;
    }
});

test('TLS fallback tries a second public address while retaining its retry bound', function (?CertificateStatusEnum $first, ?CertificateStatusEnum $second, ?CertificateStatusEnum $expected): void {
    $host = 'retry-tls.example.com';
    $domain = domainAddress(domainShop(), $host);
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $inspector->shouldReceive('records')->with('_aydra-verification.'.$host, DNS_TXT)->andReturn([['txt' => $inspector->token($domain)]]);
    $inspector->shouldReceive('records')->with($host, DNS_CNAME)->andReturn([['target' => config('tenancy.saas_base_domain')]]);
    $inspector->shouldReceive('records')->with($host, DNS_A | DNS_AAAA)->andReturn([
        ['ip' => '8.8.8.8'], ['ip' => '1.1.1.1'], ['ip' => '9.9.9.9'],
    ]);
    $inspector->shouldReceive('handshake')->with($host, '8.8.8.8')->once()->andReturn($first);
    $inspector->shouldReceive('handshake')->with($host, '1.1.1.1')->once()->andReturn($second);
    $inspector->shouldReceive('handshake')->with($host, '9.9.9.9')->never();
    expect($inspector->inspect($domain))->toBe(['verified' => true, 'certificate' => $expected]);
})->with([
    [null, CertificateStatusEnum::ACTIVE, CertificateStatusEnum::ACTIVE],
    [CertificateStatusEnum::ERROR, CertificateStatusEnum::ACTIVE, CertificateStatusEnum::ACTIVE],
    [CertificateStatusEnum::ERROR, null, CertificateStatusEnum::ERROR],
    [null, null, null],
]);

test('archived addresses can be restored only under creation permission and require new verification', function (): void {
    domainAdmin();
    $creator = domainActor('saas.domains.create');
    $archiver = domainActor('saas.domains.archive');
    $shop = domainShop();
    $primary = domainAddress($shop, 'restore-main.example.com', true);
    $domain = domainAddress($shop, 'restore.example.com');
    $domain->delete();
    $domain->refresh();
    $payload = ['expected_state' => $domain->managementState()];
    $this->actingAs($archiver, 'central')->post(route('central.domains.restore', $domain), $payload)->assertForbidden();
    $this->actingAs($creator, 'central')->post(route('central.domains.restore', $domain), $payload)
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($domain->refresh()->trashed())->toBeFalse()->and($domain->tenant_id)->toBe($shop->id)
        ->and($domain->verification_status)->toBe(VerificationStatusEnum::PENDING)
        ->and($domain->verified_at)->toBeNull()->and($domain->certificate_status)->toBeNull()
        ->and($domain->is_primary)->toBeFalse();
    expect($primary->refresh()->is_primary)->toBeTrue();
    expect(Activity::forSubject($domain)->where('event', 'domain_restored')->count())->toBe(1);
    $this->get('http://'.$domain->domain.'/')->assertNotFound();
});

test('archived-shop domains cannot be restored and stale archive forms are rejected', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $domain = domainAddress($shop, 'stale-restore.example.com');
    $domain->delete();
    $stale = $domain->managementState();
    $domain->forceFill(['certificate_status' => CertificateStatusEnum::ERROR])->save();
    $this->actingAs($admin, 'central')->post(route('central.domains.restore', $domain), ['expected_state' => $stale])
        ->assertSessionHasErrors('domain');
    $shop->delete();
    $this->post(route('central.domains.restore', $domain), ['expected_state' => $domain->refresh()->managementState()])
        ->assertForbidden();
    expect($domain->refresh()->trashed())->toBeTrue();
});

test('secondary platform addresses can be renamed without changing the shop slug or database identity', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $primary = domainAddress($shop, 'platform-main.example.com', true);
    $initial = $shop->slug.'.'.config('tenancy.saas_base_domain');
    $domain = domainAddress($shop, $initial);
    $domain->forceFill(['type' => TypeEnum::SUBDOMAIN])->save();
    $database = $shop->getInternal('db_name');
    $slug = $shop->slug;
    $host = 'new-address.'.config('tenancy.saas_base_domain');
    $this->actingAs($admin, 'central')->put(route('central.domains.update', $domain), [
        'domain' => $host, 'expected_state' => $domain->refresh()->managementState(),
    ])->assertSessionHasNoErrors();
    expect($domain->refresh()->domain)->toBe($host)->and($domain->type)->toBe(TypeEnum::SUBDOMAIN)
        ->and($domain->verification_status)->toBe(VerificationStatusEnum::PENDING);
    expect($shop->refresh()->slug)->toBe($slug)->and($shop->getInternal('db_name'))->toBe($database);
    $this->post(route('central.domains.verify', $domain), ['expected_state' => $domain->managementState()])
        ->assertSessionHasNoErrors();
    expect($domain->refresh()->verification_status)->toBe(VerificationStatusEnum::VERIFIED);
    expect($primary->refresh()->is_primary)->toBeTrue();
});

test('the primary edit page guides replacement and preselects its own shop', function (): void {
    $admin = domainAdmin();
    $shop = domainShop();
    $domain = domainAddress($shop, 'replace.example.com', true);
    $this->actingAs($admin, 'central')->get(route('central.domains.edit', $domain))->assertOk()
        ->assertSee('Préparer une adresse de remplacement')->assertDontSee('name="domain"', false);
    $this->get(route('central.domains.create', ['tenant_uuid' => $shop->uuid]))->assertOk()
        ->assertViewHas('shops', fn ($shops): bool => $shops->total() === 1 && $shops->first()->uuid === $shop->uuid);
});

test('empty DNS answers remain inconclusive instead of revoking a verified host', function (bool $emptyTxt): void {
    $domain = domainAddress(domainShop(), 'propagation.example.com');
    $inspector = Mockery::mock(DomainDnsInspector::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $inspector->shouldReceive('records')->with('_aydra-verification.propagation.example.com', DNS_TXT)
        ->andReturn($emptyTxt ? [] : [['txt' => $inspector->token($domain)]]);
    if (! $emptyTxt) {
        $inspector->shouldReceive('records')->with('propagation.example.com', DNS_CNAME)->andReturn([]);
        $inspector->shouldReceive('records')->with('propagation.example.com', DNS_A | DNS_AAAA)->andReturn([]);
        $inspector->shouldReceive('records')->with(config('tenancy.saas_base_domain'), DNS_A | DNS_AAAA)->andReturn([['ip' => '8.8.8.8']]);
    }
    expect($inspector->inspect($domain))->toBe(['verified' => null, 'certificate' => null]);
})->with([true, false]);

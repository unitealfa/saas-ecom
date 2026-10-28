<?php

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Plans\FeatureCatalogService;
use App\Services\Central\Plans\FreePlanCatalog;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FeaturePermissionSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function commercialFeatureAdmin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('root', 'central'));

    return $user;
}

function commercialFeatureActor(string $permission): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::createWithPermissions('feature-'.Str::random(10), [$permission => 7]));

    return $user;
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function commercialFeaturePayload(?Feature $feature = null, array $changes = []): array
{
    return array_replace([
        'code' => $feature?->code ?? 'catalog.products', 'name' => $feature?->name ?? 'Nombre de produits',
        'value_type' => $feature?->value_type->value ?? ValueTypeEnum::QUANTITY->value,
        'unit' => $feature !== null ? $feature->unit : 'produits',
        'quota_scope' => $feature?->quota_scope->value ?? QuotaScopeEnum::TENANT->value,
        'period' => $feature?->period->value ?? PeriodEnum::LIFETIME->value,
        ...($feature !== null ? ['expected_state' => $feature->managementState()] : []),
    ], $changes);
}

test('commercial feature readers see the catalogue and details without mutation controls', function (): void {
    commercialFeatureAdmin();
    $feature = Feature::factory()->create(['name' => 'Catalogue produits', 'code' => 'local.products']);
    $reader = commercialFeatureActor('saas.features.view');
    $this->actingAs($reader, 'central')->get(route('central.features.index'))
        ->assertOk()->assertSee('Catalogue produits')->assertSee('local.products')
        ->assertDontSee('Créer une fonctionnalité')->assertDontSee('>Modifier<', false);
    $this->get(route('central.features.show', $feature))->assertOk()
        ->assertSee('local.products')->assertDontSee('>Archiver<', false)->assertDontSee('>Désactiver<', false);
    $this->get(route('central.features.show', $feature->id))->assertNotFound();
    $this->get(route('central.features.edit', $feature))->assertForbidden();
});

test('every commercial feature HTTP action rejects staff without the required permission', function (): void {
    commercialFeatureAdmin();
    $feature = Feature::factory()->create();
    $reader = commercialFeatureActor('saas.users.view');
    $this->actingAs($reader, 'central');
    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('central.features.'.$action, in_array($action, ['show', 'edit'], true) ? $feature : []))->assertForbidden();
    }
    $this->post(route('central.features.store'), [])->assertForbidden();
    $this->put(route('central.features.update', $feature), [])->assertForbidden();
    $this->patch(route('central.features.activate', $feature), [])->assertForbidden();
    $this->delete(route('central.features.archive', $feature), [])->assertForbidden();
    expect(Feature::count())->toBe(1);
});

test('commercial feature permissions remain independent', function (string $permission, string $ability): void {
    commercialFeatureAdmin();
    $feature = Feature::factory()->create();
    $actor = commercialFeatureActor($permission);
    foreach (['viewAny', 'create', 'update', 'activate', 'archive'] as $other) {
        expect(Gate::forUser($actor)->allows($other, in_array($other, ['viewAny', 'create'], true) ? Feature::class : $feature))
            ->toBe($ability === $other);
    }
})->with([
    ['saas.features.view', 'viewAny'], ['saas.features.create', 'create'],
    ['saas.features.update', 'update'], ['saas.features.activate', 'activate'], ['saas.features.archive', 'archive'],
]);

test('feature creators normalize the code and create an inactive feature without listing rights', function (): void {
    commercialFeatureAdmin();
    $creator = commercialFeatureActor('saas.features.create');
    $this->actingAs($creator, 'central')->get(route('central.features.create'))->assertOk();
    $this->post(route('central.features.store'), commercialFeaturePayload(null, ['code' => ' LOCAL.Products ', 'unit' => ' produits ']))
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $feature = Feature::where('code', 'local.products')->firstOrFail();
    expect($feature->is_active)->toBeFalse();
    expect($feature->unit)->toBe('produits');
    expect($feature->value_type)->toBe(ValueTypeEnum::QUANTITY);
    expect(Activity::forSubject($feature)->where('event', 'feature_created')->count())->toBe(1);
    expect(Tenant::count())->toBe(0);
    $this->post(route('central.features.store'), commercialFeaturePayload(null, ['code' => 'activate.bypass', 'is_active' => true]))
        ->assertSessionHasErrors('is_active');
    expect(Feature::where('code', 'activate.bypass')->exists())->toBeFalse();
});

test('invalid feature enum values and malformed codes produce validation errors', function (string $field, mixed $value): void {
    $admin = commercialFeatureAdmin();
    $this->actingAs($admin, 'central')->postJson(route('central.features.store'), commercialFeaturePayload(null, [$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Feature::count())->toBe(0);
})->with([['value_type', 99], ['quota_scope', 0], ['period', 99], ['code', ['invalid']], ['code', 'bad code']]);

test('unused feature configuration is editable but its permanent code cannot change', function (): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create();
    $this->actingAs($admin, 'central')->get(route('central.features.edit', $feature))->assertOk();
    $this->put(route('central.features.update', $feature), commercialFeaturePayload($feature, [
        'name' => 'Accès aux promotions', 'value_type' => ValueTypeEnum::BOOLEAN->value, 'unit' => null,
        'quota_scope' => QuotaScopeEnum::TENANT->value, 'period' => PeriodEnum::MONTHLY->value,
    ]))->assertSessionHasNoErrors();
    $feature->refresh();
    expect($feature->value_type)->toBe(ValueTypeEnum::BOOLEAN);
    expect($feature->period)->toBe(PeriodEnum::MONTHLY);
    expect($feature->is_active)->toBeTrue();
    $old = $feature->code;
    $this->put(route('central.features.update', $feature), commercialFeaturePayload($feature, ['code' => 'other.code']))
        ->assertSessionHasErrors('code');
    expect($feature->refresh()->code)->toBe($old);
});

test('used feature semantics and availability remain protected while its name can change', function (): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create();
    $grant = PlanFeature::factory()->create(['feature_id' => $feature->id]);
    $this->actingAs($admin, 'central')->get(route('central.features.edit', $feature))->assertOk()->assertSee('seul son nom');
    $this->put(route('central.features.update', $feature), commercialFeaturePayload($feature, ['name' => 'Nouveau libellé']))
        ->assertSessionHasNoErrors();
    $feature->refresh();
    expect($feature->name)->toBe('Nouveau libellé');
    $this->put(route('central.features.update', $feature), commercialFeaturePayload($feature, ['period' => PeriodEnum::MONTHLY->value]))
        ->assertSessionHasErrors('feature');
    $this->patch(route('central.features.activate', $feature), ['expected_state' => $feature->managementState(), 'is_active' => false])
        ->assertSessionHasErrors('feature');
    $this->delete(route('central.features.archive', $feature), ['expected_state' => $feature->managementState()])
        ->assertSessionHasErrors('feature');
    expect($feature->refresh()->period)->toBe(PeriodEnum::LIFETIME);
    expect($feature->is_active)->toBeTrue();
    expect($feature->trashed())->toBeFalse();
    expect($grant->refresh()->limit)->toBe(2);
    $this->get(route('central.features.show', $feature))->assertOk()->assertDontSee('>Désactiver<', false)->assertDontSee('>Archiver<', false);
});

test('the shops foundation cannot be created renamed disabled reinterpreted or archived by administrators', function (): void {
    $admin = commercialFeatureAdmin();
    $this->actingAs($admin, 'central')->post(route('central.features.store'), commercialFeaturePayload(null, ['code' => 'shops']))
        ->assertSessionHasErrors('code');
    $plan = app(FreePlanCatalog::class)->current();
    $shops = Feature::where('code', Feature::SHOPS_CODE)->firstOrFail();
    $this->patch(route('central.features.activate', $shops), ['expected_state' => $shops->managementState(), 'is_active' => false])->assertForbidden();
    $this->delete(route('central.features.archive', $shops), ['expected_state' => $shops->managementState()])->assertForbidden();
    $this->put(route('central.features.update', $shops), commercialFeaturePayload($shops, ['quota_scope' => QuotaScopeEnum::TENANT->value]))
        ->assertSessionHasErrors('feature');
    $this->put(route('central.features.update', $shops), commercialFeaturePayload($shops, ['name' => 'Nombre de boutiques autorisées']))
        ->assertSessionHasNoErrors();
    expect(app(FreePlanCatalog::class)->current()->id)->toBe($plan->id);
    expect($shops->refresh()->is_active)->toBeTrue();
    expect($plan->features()->sole()->limit)->toBe(2);
});

test('initializing the free catalogue preserves mass assignment protection on other models', function (): void {
    $status = null;
    Feature::creating(function () use (&$status): void {
        $status = (new User(['status' => StatusEnum::SUSPENDED]))->status;
    });

    $plan = app(FreePlanCatalog::class)->current();

    expect($status)->toBe(StatusEnum::ACTIVE);
    expect($plan->features()->sole()->limit)->toBe(2);
    expect(Feature::where('code', Feature::SHOPS_CODE)->sole()->is_active)->toBeTrue();
});

test('the free catalogue refuses an existing inactive or incompatible shops definition', function (array $attributes): void {
    $feature = Feature::factory()->create(['code' => Feature::SHOPS_CODE, ...$attributes]);
    $before = $feature->only(['is_active', 'value_type', 'quota_scope', 'period']);

    expect(fn () => app(FreePlanCatalog::class)->current())
        ->toThrow(LogicException::class, 'The shops feature must be an active owner quota.');

    expect($feature->refresh()->only(array_keys($before)))->toBe($before);
    expect($feature->planFeatures()->exists())->toBeFalse();
})->with([
    'inactive' => [['is_active' => false]],
    'boolean' => [['value_type' => ValueTypeEnum::BOOLEAN]],
    'tenant scope' => [['quota_scope' => QuotaScopeEnum::TENANT]],
    'monthly period' => [['period' => PeriodEnum::MONTHLY]],
]);

test('only activation permission changes an unused feature state and stale requests are rejected', function (): void {
    commercialFeatureAdmin();
    $feature = Feature::factory()->create(['is_active' => false]);
    $activator = commercialFeatureActor('saas.features.activate');
    $state = $feature->managementState();
    $this->actingAs($activator, 'central')->patch(route('central.features.activate', $feature), ['expected_state' => $state, 'is_active' => true])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($feature->refresh()->is_active)->toBeTrue();
    $this->patch(route('central.features.activate', $feature), ['expected_state' => $state, 'is_active' => false])->assertSessionHasErrors('feature');
    expect($feature->refresh()->is_active)->toBeTrue();
    expect(Activity::forSubject($feature)->where('event', 'feature_activation_updated')->count())->toBe(1);
});

test('archival retains the record and reserves the code while all mutations become unavailable', function (): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create(['code' => 'reserved.feature']);
    $this->actingAs($admin, 'central')->delete(route('central.features.archive', $feature), ['expected_state' => $feature->managementState()])
        ->assertSessionHasNoErrors();
    $feature->refresh();
    expect($feature->trashed())->toBeTrue();
    expect($feature->is_active)->toBeFalse();
    $this->get(route('central.features.show', $feature))->assertOk()->assertSee('Archivée');
    $this->get(route('central.features.edit', $feature))->assertNotFound();
    $this->patch(route('central.features.activate', $feature), ['expected_state' => $feature->managementState(), 'is_active' => true])->assertNotFound();
    $this->post(route('central.features.store'), commercialFeaturePayload(null, ['code' => 'RESERVED.FEATURE']))->assertSessionHasErrors('code');
    $this->get(route('central.features.index', ['archived' => 1]))->assertOk()->assertSee('reserved.feature');
    expect(Activity::forSubject($feature)->where('event', 'feature_archived')->count())->toBe(1);
});

test('historical usage or permission metadata prevents archival and semantic changes', function (string $reference): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create();
    if ($reference === 'permission') {
        Permission::create(['name' => 'fixture.feature-reference', 'label' => 'Référence de test', 'feature_code' => $feature->code]);
    } else {
        DB::table('feature_usage')->insert([
            'uuid' => (string) Str::uuid(), 'user_id' => $admin->id, 'feature_id' => $feature->id,
            'period_starts_at' => now()->startOfDay(), 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $this->actingAs($admin, 'central')->delete(route('central.features.archive', $feature), ['expected_state' => $feature->managementState()])
        ->assertSessionHasErrors('feature');
    $this->put(route('central.features.update', $feature), commercialFeaturePayload($feature, ['unit' => 'different unit']))
        ->assertSessionHasErrors('feature');
    expect($feature->refresh()->trashed())->toBeFalse();
})->with(['permission', 'usage']);

test('feature mutation SQL and audit roll back together', function (): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create(['is_active' => false]);
    expect(function () use ($admin, $feature): void {
        DB::transaction(function () use ($admin, $feature): void {
            app(FeatureCatalogService::class)->setActive($admin, $feature, true, $feature->managementState());
            throw new LogicException('abort-feature-test');
        });
    })->toThrow(LogicException::class, 'abort-feature-test');
    expect($feature->refresh()->is_active)->toBeFalse();
    expect(Activity::forSubject($feature)->where('event', 'feature_activation_updated')->exists())->toBeFalse();
});

test('SQL uniqueness remains enforced when callers bypass the HTTP duplicate check', function (): void {
    $admin = commercialFeatureAdmin();
    $service = app(FeatureCatalogService::class);
    $service->create($admin, commercialFeaturePayload());
    expect(fn () => $service->create($admin, commercialFeaturePayload()))->toThrow(ValidationException::class);
    expect(Feature::where('code', 'catalog.products')->count())->toBe(1);
});

test('commercial feature listing supports literal searches status filters and pagination', function (): void {
    $admin = commercialFeatureAdmin();
    Feature::factory()->count(22)->create();
    $feature = Feature::factory()->create(['name' => 'Cible commerciale', 'code' => 'literal.feature', 'is_active' => false]);
    $this->actingAs($admin, 'central')->get(route('central.features.index'))->assertOk()
        ->assertViewHas('features', fn ($items) => $items->total() === 23 && $items->count() === 20);
    $this->get(route('central.features.index', ['search' => 'literal.feature']))->assertOk()->assertSee('Cible commerciale')
        ->assertViewHas('features', fn ($items) => $items->total() === 1);
    $this->get(route('central.features.index', ['search' => '%']))->assertOk()->assertViewHas('features', fn ($items) => $items->total() === 0);
    $this->get(route('central.features.index', ['is_active' => 0]))->assertOk()->assertSee($feature->code)
        ->assertViewHas('features', fn ($items) => $items->total() === 1);
});

test('a second stale feature edit cannot overwrite the first saved value', function (): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create();
    $payload = commercialFeaturePayload($feature, ['name' => 'Première modification']);
    $this->actingAs($admin, 'central')->put(route('central.features.update', $feature), $payload)->assertSessionHasNoErrors();
    $this->put(route('central.features.update', $feature), array_replace($payload, ['name' => 'Modification obsolète']))->assertSessionHasErrors('feature');
    expect($feature->refresh()->name)->toBe('Première modification');
});

test('feature activation cannot be mass assigned and permanent codes remain immutable', function (): void {
    $strict = Model::preventsSilentlyDiscardingAttributes();
    Model::preventSilentlyDiscardingAttributes();
    try {
        expect(fn () => (new Feature)->fill(['is_active' => true]))->toThrow(MassAssignmentException::class);
    } finally {
        Model::preventSilentlyDiscardingAttributes($strict);
    }
    $feature = Feature::factory()->create();
    expect(fn () => $feature->forceFill(['code' => 'new.code'])->save())->toThrow(LogicException::class);
});

test('feature writers can perform successive authorized actions without an admin throttle', function (): void {
    $admin = commercialFeatureAdmin();
    $feature = Feature::factory()->create(['is_active' => false]);
    $this->actingAs($admin, 'central');
    for ($i = 0; $i < 8; $i++) {
        $feature->refresh();
        $this->patch(route('central.features.activate', $feature), ['expected_state' => $feature->managementState(), 'is_active' => ! $feature->is_active])
            ->assertSessionHasNoErrors()->assertRedirect();
    }
    expect(Activity::forSubject($feature)->where('event', 'feature_activation_updated')->count())->toBe(8);
});

test('adding feature permissions preserves existing role and permission assignments', function (): void {
    commercialFeatureAdmin();
    $roles = DB::table('model_has_roles')->get()->toJson();
    $direct = DB::table('model_has_permissions')->get()->toJson();
    $this->seed(FeaturePermissionSeeder::class);
    $this->seed(FeaturePermissionSeeder::class);
    expect(Permission::where('name', 'like', 'saas.features.%')->count())->toBe(5);
    expect(DB::table('model_has_roles')->get()->toJson())->toBe($roles);
    expect(DB::table('model_has_permissions')->get()->toJson())->toBe($direct);
});

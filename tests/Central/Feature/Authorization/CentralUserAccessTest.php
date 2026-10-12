<?php

use App\Models\Central\Activity;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\LocalAuthenticationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Stancl\Tenancy\Events\TenantCreated;

test('central catalogue seeds the three agreed roles without creating accounts', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->seed(AuthorizationSeeder::class);

    expect(Role::orderBy('id')->pluck('name')->all())->toBe(['root', 'user-manager', 'user-reader']);
    expect(User::count())->toBe(0);
    expect(Role::findByName('user-manager')->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['saas.users.create', 'saas.users.update', 'saas.users.view']);
    expect(Role::findByName('user-reader')->permissions()->pluck('name')->all())->toBe(['saas.users.view']);
});

test('agreed staff roles can view the central user list', function (string $name): void {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findByName($name, 'central'));

    expect(Gate::forUser($user)->allows('viewAny', User::class))->toBeTrue();
    $this->actingAs($user, 'central')->get(route('central.users.index'))
        ->assertOk()->assertSee($user->email);
    $this->get(route('dashboard'))->assertOk()->assertSee('Consulter les utilisateurs');
})->with(['root', 'user-manager', 'user-reader']);

test('merchant dashboard shows only the owners shops and refuses the administration URL', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Benameur']);
    $other = User::factory()->create();
    Event::fake([TenantCreated::class]);
    Tenant::factory()->create(['user_id' => $owner->id, 'shop_name' => 'Boutique de Karim']);
    Tenant::factory()->create(['user_id' => $other->id, 'shop_name' => 'Boutique privée de Nadia']);
    Event::assertDispatched(TenantCreated::class, 2);

    $this->actingAs($owner, 'central')->get(route('dashboard'))
        ->assertOk()->assertSee('Bonjour, Karim Benameur')->assertSee('Boutique de Karim')
        ->assertSee('La gestion des boutiques est en préparation.')
        ->assertDontSee('Boutique privée de Nadia')->assertDontSee('Consulter les utilisateurs');
    $this->get(route('central.users.index'))->assertForbidden();
});

test('central administration requires login and verified email', function (): void {
    $this->get(route('central.users.index'))->assertRedirect(route('login'));
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->unverified()->create();
    $user->assignRole(Role::findByName('root'));
    $this->actingAs($user, 'central')->get(route('central.users.index'))
        ->assertRedirect(route('verification.notice'));
});

test('an expired permission removes the menu and refuses direct and Livewire access', function (): void {
    $this->freezeTime();
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $role = Role::createWithPermissions('temporary-reader', ['saas.users.view' => 1]);
    $user->assignRole($role);
    $component = Livewire::actingAs($user, 'central')->test('central::pages.admin.users.index')->assertOk();

    $this->travel(1)->days();
    expect(Gate::forUser($user)->allows('viewAny', User::class))->toBeFalse();
    $this->actingAs($user, 'central')->get(route('dashboard'))->assertDontSee('Consulter les utilisateurs');
    $this->get(route('central.users.index'))->assertForbidden();
    $component->set('search', 'Essai')->assertForbidden();
});

test('central consultation applies globally to users', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader'));
    $first = User::factory()->create(['email' => 'first@example.test']);
    $second = User::factory()->create(['email' => 'second@example.test']);
    expect(Gate::forUser($reader)->allows('view', $first))->toBeTrue();
    expect(Gate::forUser($reader)->allows('view', $second))->toBeTrue();
    $this->actingAs($reader, 'central')->get(route('central.users.index'))
        ->assertOk()->assertSee($first->email)->assertSee($second->email);
});
test('user list search escapes names and binds query input', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader'));
    User::factory()->create(['name' => '<script>alert(1)</script>', 'email' => 'escaped@example.test']);

    Livewire::actingAs($reader, 'central')->test('central::pages.admin.users.index')
        ->set('search', 'escaped@example.test')->assertSee('escaped@example.test')
        ->assertSee('&lt;script&gt;', escape: false)->assertDontSee('<script>alert(1)</script>', escape: false)
        ->set('search', "' OR 1=1 --")->assertDontSee('escaped@example.test');
});

test('local authentication seeding adds staff without changing existing owner or admin accounts', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->unverified()->create(['email' => 'existing-admin@example.test']);
    $admin->assignRole(Role::findByName('root'));
    $snapshot = $admin->fresh()->getAttributes();

    $this->seed(LocalAuthenticationSeeder::class);
    $manager = User::where('email', 'manager@example.test')->firstOrFail();
    $firstAssignment = $manager->roles()->firstOrFail()->pivot->assigned_at;
    $this->seed(LocalAuthenticationSeeder::class);

    expect($admin->fresh()->getAttributes())->toBe($snapshot);
    expect(User::count())->toBe(3);
    expect($manager->roles()->firstOrFail()->pivot->assigned_at)->toEqual($firstAssignment);
    expect(Activity::where('event', 'local_authentication_seeded')->count())->toBe(2);
    expect($manager->can('saas.users.create'))->toBeTrue();
    expect(User::where('email', 'reader@example.test')->firstOrFail()->can('saas.users.create'))->toBeFalse();
});

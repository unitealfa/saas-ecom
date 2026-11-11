<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Roles\Role;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Billing\SaasInvoicePdfService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\OperationsPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AuthorizationSeeder::class);
});

function operationsViewer(array $permissions): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('operations-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

test('technical operations require their own central permission', function (): void {
    $this->get(route('central.operations.index'))->assertRedirect(route('login'));
    $this->actingAs(operationsViewer(['saas.users.view']), 'central')->get(route('central.operations.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('central.operations.index'));
    $this->actingAs(User::factory()->create(), 'central')->get(route('central.operations.index'))->assertForbidden();
    $this->actingAs(operationsViewer(['saas.operations.view']), 'central')->get(route('central.operations.index'))
        ->assertOk()->assertSee('Déploiements de boutiques')->assertSee('Envois de documents')
        ->assertSee(route('central.operations.index'));
});

test('the additive seeder preserves existing grants and the permission identity', function (): void {
    $actor = operationsViewer(['saas.users.view']);
    Permission::where('name', 'saas.operations.view')->where('guard_name', 'central')->firstOrFail()->delete();
    $grants = DB::table('model_has_permissions')->count();
    $this->seed(OperationsPermissionSeeder::class);
    $permission = Permission::where('name', 'saas.operations.view')->where('guard_name', 'central')->firstOrFail();
    $this->seed(OperationsPermissionSeeder::class);

    expect(Permission::where('name', 'saas.operations.view')->where('guard_name', 'central')->sole()->id)->toBe($permission->id)
        ->and(DB::table('model_has_permissions')->count())->toBe($grants);
    $this->actingAs($actor, 'central')->get(route('central.operations.index'))->assertForbidden();
});

test('deployments are paginated without hiding later operations', function (): void {
    Event::fake([TenantCreated::class]);
    $viewer = operationsViewer(['saas.operations.view']);
    $tenant = Tenant::factory()->create();
    TenantSchemaDeployment::factory()->count(16)->create(['tenant_id' => $tenant->id]);

    $this->actingAs($viewer, 'central')->get(route('central.operations.index'))
        ->assertOk()->assertViewHas('deployments', fn ($page): bool => $page->total() === 16 && $page->count() === 15);
    $this->get(route('central.operations.index', ['deployments_page' => 2]))
        ->assertOk()->assertViewHas('deployments', fn ($page): bool => $page->currentPage() === 2 && $page->count() === 1);
});

test('operations list exposes only safe summaries of recorded deployments and deliveries', function (): void {
    Event::fake([TenantCreated::class]);
    $viewer = operationsViewer(['saas.operations.view']);
    $tenant = Tenant::factory()->create();
    $deployment = TenantSchemaDeployment::factory()->create(['tenant_id' => $tenant->id, 'status' => 4,
        'error_code' => 'PRIVATE-DIAGNOSTIC', 'sanitized_error' => 'PRIVATE-DETAIL']);
    $this->partialMock(SaasInvoicePdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturnUsing(function (SaasInvoice $invoice): array {
            $bytes = "%PDF-1.4\n%%EOF";
            $key = (new SaasInvoicePdfService)->storageKey($invoice);
            Storage::disk('local')->put($key, $bytes);

            return ['storage_key' => $key, 'file_hash' => hash('sha256', $bytes), 'size_bytes' => strlen($bytes)];
        });
    });
    $fixture = manualPaymentFixture();
    $delivery = \App\Models\Central\Billing\SaasDocumentDelivery::where('document_id', $fixture['invoice']->id)->sole();

    $this->actingAs($viewer, 'central')->get(route('central.operations.index'))
        ->assertOk()->assertSee($deployment->uuid)->assertSee($tenant->uuid)->assertSee($delivery->uuid)
        ->assertSee('Échec')->assertSee('En attente')
        ->assertDontSee('PRIVATE-DIAGNOSTIC')->assertDontSee('PRIVATE-DETAIL')
        ->assertDontSee($fixture['owner']->email)->assertDontSee($fixture['invoice']->number);
});

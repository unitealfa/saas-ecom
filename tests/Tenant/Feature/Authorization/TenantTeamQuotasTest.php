<?php

use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Users\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\Tenant\Authorization\TenantQuotaService;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('member quota counts each boutique independently and does not delete existing members after a reduction', function (): void {
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $owner = CentralUser::factory()->create();
    $plan = Plan::factory()->create();
    $feature = Feature::where('code', Feature::TEAM_MEMBERS_CODE)->firstOrFail();
    // Fixture uniquement : une limite choisie dans le test, jamais un chiffre fixé pour une offre réelle.
    PlanFeature::factory()->create([
        'plan_id' => $plan->id,
        'feature_id' => $feature->id,
        'is_active' => true,
        'limit' => 1,
    ]);
    Subscription::factory()->create([
        'user_id' => $owner->id,
        'plan_id' => $plan->id,
        'period_starts_at' => now()->subDay(),
        'period_ends_at' => now()->addMonth(),
    ]);
    $one = Tenant::factory()->create(['user_id' => $owner->id]);
    $two = Tenant::factory()->create(['user_id' => $owner->id]);

    $one->run(function (): void {
        $member = TenantUser::factory()->create(['email' => 'member-one@example.test']);
        expect(fn () => app(TenantQuotaService::class)->assertCanReserveSeat('member-two@example.test'))
            ->toThrow(ValidationException::class, 'Limite de l’offre atteinte : 1 membre(s) maximum.');
        expect(TenantUser::whereKey($member->id)->exists())->toBeTrue();
    });
    $two->run(function (): void {
        expect(fn () => app(TenantQuotaService::class)->assertCanReserveSeat('member-two@example.test'))
            ->not->toThrow(ValidationException::class);
    });
});

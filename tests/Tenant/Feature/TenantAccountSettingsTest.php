<?php

use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Users\User as Member;
use App\Models\User;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Tenant\Auth\LoginService;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('a password alone does not authenticate an employee who enabled two factor authentication', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(FreePlanSeeder::class);
    Plan::where('code', 'free')->update(['is_default' => true]);
    $owner = User::factory()->create(['legal_verified_by_id' => User::factory()->create()->id,
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_profile_version' => 1]);
    app(DefaultSubscription::class)->ensure($owner);
    $shop = Tenant::factory()->create(['user_id' => $owner->id]);
    $shop->forceFill(['status' => TenantStatus::ACTIVE])->save();

    $shop->run(function (): void {
        $member = Member::factory()->create([
            'password' => 'Password-1234!', 'status' => StatusEnum::ACTIVE,
            'membership_status' => MembershipStatusEnum::ACTIVE, 'joined_at' => now(), 'email_verified_at' => now(),
        ]);
        $member->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('ABCDEFGHIJKLMNOP'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $result = app(LoginService::class)->login($member->email, 'Password-1234!', false);

        expect($result->is($member))->toBeTrue()
            ->and(Auth::guard('tenant')->check())->toBeFalse();
    });
});

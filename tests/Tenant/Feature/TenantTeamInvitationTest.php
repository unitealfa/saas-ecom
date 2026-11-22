<?php

use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\TeamInvitation;
use App\Models\Tenant\Users\User as Member;
use App\Models\User;
use App\Notifications\Tenant\TeamInvitationNotification;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Tenant\Auth\OwnerLoginService;
use App\Services\Tenant\Users\TeamInvitationService;
use Database\Factories\Central\DomainFactory;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;
use Illuminate\Cookie\CookieJar;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('a new invitee completes the account form and enters the shop with the invited role', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([FreePlanSeeder::class, TenantTeamQuotaFeatureSeeder::class]);
    Notification::fake();
    config(['mail.default' => 'array', 'session.driver' => 'database']);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    $offer = Plan::factory()->create(['is_active' => true, 'monthly_price' => '0.00', 'annual_price' => '0.00']);
    foreach ([Feature::SHOPS_CODE, Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE] as $code) {
        PlanFeature::factory()->create(['plan_id' => $offer->id, 'feature_id' => Feature::where('code', $code)->sole()->id, 'limit' => 2]);
    }
    $offer->forceFill(['is_default' => true])->save();
    $owner = User::factory()->create(['legal_verified_by_id' => User::factory()->create()->id,
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_profile_version' => 1]);
    app(DefaultSubscription::class)->ensure($owner);
    $shop = Tenant::factory()->create(['user_id' => $owner->id, 'shop_name' => 'Boutique des invitations']);
    $shop->forceFill(['status' => StatusEnum::ACTIVE])->save();
    $domain = DomainFactory::new()->create(['tenant_id' => $shop->id, 'domain' => $shop->slug.'.aydra.localhost',
        'is_primary' => true, 'verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now()]);
    $base = rtrim($domain->publicUrl(), '/');

    $invitation = $shop->run(function () use ($owner, $shop): TeamInvitation {
        $actor = app(OwnerLoginService::class)->accept($owner->uuid, $shop->uuid, hash('sha256', $owner->email));
        $role = Role::createWithPermissions('profile-reader', ['shop.profile.view' => 30]);

        return app(TeamInvitationService::class)->send($actor, 'member@example.test', $role);
    });
    $url = '';
    Notification::assertSentOnDemand(TeamInvitationNotification::class, function ($notification, $channels, $recipient) use (&$url): bool {
        $url = $notification->toMail($recipient)->actionUrl;

        return true;
    });
    expect($url)->toStartWith($base.'/invitations/');
    $setup = $url.'&compte=1';
    $this->get($url)->assertOk()->assertSee('Accepter l’invitation')
        ->assertSee('&amp;compte=1', false)->assertDontSee('name="last_name"', false);
    $form = $this->get($setup)->assertOk()->assertSee('Créer mon compte')
        ->assertSee('Boutique des invitations')->assertSee('member@example.test')
        ->assertSee('name="password_confirmation"', false)->assertDontSee('name="email"', false);
    $cookieName = 'aydra_tenant_'.$shop->id;
    $this->withUnencryptedCookie($cookieName, $form->getCookie($cookieName, decrypt: false)->getValue());
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $endpoint = $base.route('tenant.invitations.accept', $invitation, false);
    $invalid = $this->from($setup)->post($endpoint, ['token' => $query['token']])
        ->assertRedirect($setup);
    $this->withUnencryptedCookie($cookieName, $invalid->getCookie($cookieName, decrypt: false)->getValue());
    $this->get($setup)->assertOk()->assertSee('Indiquez votre nom.')->assertSee('Choisissez un mot de passe.');

    $accepted = $this->post($endpoint, [
        'token' => $query['token'], 'last_name' => 'Membre',
        'password' => 'Choisir-UnMotDePasse8!', 'password_confirmation' => 'Choisir-UnMotDePasse8!',
    ])->assertRedirect($base.'/admin');
    $this->withUnencryptedCookie($cookieName, $accepted->getCookie($cookieName, decrypt: false)->getValue());
    $this->get($base.'/admin')->assertOk()->assertSee('Membre')->assertSee('Invitation acceptée.')
        ->assertSee('Profil de la boutique');
    $this->get($base.'/admin/shop/profile')->assertOk();
    $this->get($base.'/admin/roles')->assertForbidden();
    $shop->run(function () use ($invitation): void {
        $member = Member::where('email', 'member@example.test')->sole();
        expect($invitation->refresh()->accepted_at)->not->toBeNull()
            ->and($member->hasVerifiedEmail())->toBeTrue()
            ->and($member->can('shop.profile.view'))->toBeTrue();
    });

    config(['session.domain' => '.aydra.localhost']);
    app(CookieJar::class)->setDefaultPathAndDomain('/', '.aydra.localhost');
    $recallerName = auth('tenant')->getRecallerName();
    $this->post($base.'/logout')->assertRedirect($base.'/login');
    $this->withUnencryptedCookie($cookieName, '');
    $this->get($base.'/login')->assertOk()->assertSee('Rester connecté');

    $credentials = ['email' => 'member@example.test', 'password' => 'Choisir-UnMotDePasse8!'];
    $login = $this->post($base.'/login', $credentials)->assertRedirect($base.'/admin');
    $recaller = $login->getCookie($recallerName, decrypt: false);
    expect($recaller === null || $recaller->isCleared())->toBeTrue();
    $this->withUnencryptedCookie($cookieName, $login->getCookie($cookieName, decrypt: false)->getValue());
    $this->get($base.'/admin')->assertOk()->assertSee('Membre');

    $this->post($base.'/logout')->assertRedirect($base.'/login');
    $this->withUnencryptedCookie($cookieName, '');
    $remembered = $this->post($base.'/login', $credentials + ['remember' => '1'])->assertRedirect($base.'/admin');
    $recaller = $remembered->getCookie($recallerName, decrypt: false);
    expect($recaller)->not->toBeNull()
        ->and($recaller->getDomain())->toBeNull()
        ->and($recaller->isHttpOnly())->toBeTrue()
        ->and($recaller->getExpiresTime())->toBeGreaterThan(time());
    $this->withUnencryptedCookie($recallerName, $recaller->getValue());
    $this->get($base.'/admin')->assertOk()->assertSee('Membre');
});

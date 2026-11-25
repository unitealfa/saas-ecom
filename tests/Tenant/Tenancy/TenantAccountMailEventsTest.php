<?php

use App\Events\Tenant\Users\TeamInvitationMailRequested;
use App\Events\Tenant\Users\WelcomeEmployeeMailRequested;
use App\Listeners\Tenant\Users\SendTeamInvitation;
use App\Models\Central\Users\User as CentralUser;
use App\Models\Tenant;
use App\Models\Tenant\Users\User as ShopUser;
use App\Notifications\Central\Auth\AccountMailFailureNotification;
use App\Notifications\Tenant\TeamInvitationNotification;
use App\Notifications\Tenant\WelcomeEmployeeNotification;
use App\Services\AccountMailEvents;
use App\Services\Tenant\Users\TenantInvitationUrl;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Tenant\Tenancy\TenantTestSeeder;
use Tests\TestCase;


beforeEach(function (): void {
    config(['tenancy.saas_base_domain' => 'aydra.localhost', 'queue.account_mail_connection' => 'sync']);
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('the tenant mail event boundary refuses dispatch inside the tenant transaction', function (): void {
    Event::fake([WelcomeEmployeeMailRequested::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $tenant->run(function () use ($tenant): void {
        $member = ShopUser::sole();
        $build = fn () => new WelcomeEmployeeMailRequested($tenant->uuid, $member->uuid, $member->email, $tenant->shop_name, app(TenantInvitationUrl::class)->make('tenant.login'));
        expect(fn () => DB::connection('tenant')->transaction(function () use ($build): void {
            expect(AccountMailEvents::dispatch('tenant_welcome', $build, tenant: true))->toBeFalse();
            throw new LogicException('rollback');
        }))->toThrow(LogicException::class, 'rollback');
        Event::assertNotDispatched(WelcomeEmployeeMailRequested::class);
        expect(AccountMailEvents::dispatch('tenant_welcome', $build, tenant: true))->toBeTrue();
    });
    Event::assertDispatchedTimes(WelcomeEmployeeMailRequested::class, 1);
});

test('database account mail payloads are encrypted and carry the correct tenant id for two shops', function (): void {
    // Queue::fake is deliberately not used here: Stancl bypasses its payload hook with QueueFake.
    config(['queue.account_mail_connection' => 'database', 'queue.connections.database.connection' => config('tenancy.database.central_connection')]);
    Notification::fake();
    $expected = [];
    foreach (Tenant::orderBy('id')->get() as $tenant) {
        $tenant->run(function () use ($tenant, &$expected): void {
            $member = ShopUser::sole();
            $url = app(TenantInvitationUrl::class)->make('tenant.login');
            expect(AccountMailEvents::dispatch('tenant_welcome', fn () => new WelcomeEmployeeMailRequested(
                $tenant->uuid, $member->uuid, $member->email, $tenant->shop_name, $url,
            ), tenant: true))->toBeTrue();
            $expected[] = ['tenant_id' => $tenant->getTenantKey(), 'id' => $member->id, 'uuid' => $member->uuid, 'email' => $member->email, 'url' => $url];
        });
    }
    expect($expected)->toHaveCount(2);
    expect($expected[0]['id'])->toBe($expected[1]['id']);
    $jobs = DB::connection(config('tenancy.database.central_connection'))->table('jobs')->where('queue', 'default')->orderBy('id')->get();
    expect($jobs)->toHaveCount(2);
    foreach ($jobs as $index => $job) {
        $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
        expect((string) $payload['tenant_id'])->toBe((string) $expected[$index]['tenant_id']);
        expect($payload['data']['command'])->not->toContain($expected[$index]['url']);
    }
    Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'default', '--stop-when-empty' => true, '--sleep' => 0, '--no-interaction' => true]);
    expect(tenancy()->initialized)->toBeFalse();
    expect(DB::connection(config('tenancy.database.central_connection'))->table('jobs')->where('queue', 'default')->count())->toBe(0);
    foreach ($expected as $entry) {
        $tenant = Tenant::findOrFail($entry['tenant_id']);
        $tenant->run(function () use ($entry): void {
            $member = ShopUser::where('uuid', $entry['uuid'])->firstOrFail();
            Notification::assertSentTo($member, WelcomeEmployeeNotification::class, function ($notification, $channels, $notifiable) use ($entry): bool {
                return $notifiable->email === $entry['email'] && $notification->toMail($notifiable)->actionUrl === $entry['url'];
            });
        });
    }
});

test('a tenant invitation failure is reported to the central owner and never a tenant numeric id', function (): void {
    Notification::fake();
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $owner = CentralUser::where('uuid', $tenant->owner->uuid)->firstOrFail();
    $owner->forceFill(['email_verified_at' => now()])->save();
    $tenant->run(function () use ($tenant): void {
        $event = new TeamInvitationMailRequested($tenant->uuid, (string) Str::uuid(), 'invite@example.test', 'https://example.test/secret', 'Shop', now()->addHour()->getTimestamp(), null, AccountMailEvents::tenantOwnerUuid());
        (new SendTeamInvitation)->failed($event, new RuntimeException('secret'));
    });
    Notification::assertSentTo($owner, AccountMailFailureNotification::class);
});

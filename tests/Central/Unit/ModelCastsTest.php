<?php

use App\Enums\ActivityOriginEnum;
use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\Central\Domains\TypeEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatusEnum;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Activity as CentralActivity;
use App\Models\Central\Domain;
use App\Models\Central\Role as CentralRole;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\PermissionAssignment;
use App\Models\Tenant\Activity as TenantActivity;
use App\Models\Tenant\Role as TenantRole;
use App\Models\Tenant\User as TenantUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('documented statuses hydrate as enums and serialize as stable numeric codes', function (string $modelClass, string $field, int $code, BackedEnum $status): void {
    /** @var Model $model */
    $model = new $modelClass;
    $model->setRawAttributes([$field => $code]);

    expect($model->getAttribute($field))->toBe($status);
    expect($model->toArray()[$field])->toBe($code);
    expect(fn () => $model->setAttribute($field, 0))->toThrow(ValueError::class);
})->with([
    'central account' => [CentralUser::class, 'status', 3, StatusEnum::SUSPENDED],
    'shop account' => [TenantUser::class, 'status', 2, StatusEnum::INACTIVE],
    'shop membership' => [TenantUser::class, 'membership_status', 4, MembershipStatusEnum::REVOKED],
    'tenant provisioning' => [Tenant::class, 'status', 6, TenantStatusEnum::PROVISIONING_FAILED],
]);

test('password casts hash clear text and preserve an existing password hash', function (string $modelClass): void {
    $user = new $modelClass;
    $user->setAttribute('password', 'a-valid-private-password');
    $user->syncOriginal();
    $hash = $user->getRawOriginal('password');
    $user->setAttribute('password', $hash);

    expect(Hash::check('a-valid-private-password', $user->getAttribute('password')))->toBeTrue();
    expect($user->getAttribute('password'))->toBe($hash);
    expect($user->toArray())->not->toHaveKey('password');
})->with([CentralUser::class, TenantUser::class]);

test('role metadata uses booleans and an integer version in both contexts', function (string $modelClass): void {
    $role = new $modelClass;
    $role->setRawAttributes(['is_system' => 1, 'is_protected' => 0, 'is_super_admin' => 0, 'permission_version' => '2']);

    expect($role->getAttribute('is_system'))->toBeTrue();
    expect($role->getAttribute('is_protected'))->toBeFalse();
    expect($role->getAttribute('is_super_admin'))->toBeFalse();
    expect($role->getAttribute('permission_version'))->toBe(2);
})->with([CentralRole::class, TenantRole::class]);

test('activity origin casts preserve the collections supplied by Spatie', function (string $modelClass): void {
    $activity = new $modelClass;
    $activity->setRawAttributes(['origin' => 4, 'properties' => '{"schema_version":1}', 'attribute_changes' => '{"attributes":{"status":1}}']);

    expect($activity->getAttribute('origin'))->toBe(ActivityOriginEnum::JOB);
    expect($activity->getAttribute('properties'))->toBeInstanceOf(Collection::class);
    expect($activity->getAttribute('properties')->get('schema_version'))->toBe(1);
    expect($activity->getAttribute('attribute_changes'))->toBeInstanceOf(Collection::class);
})->with([CentralActivity::class, TenantActivity::class]);

test('domain enums preserve optional certificate states', function (): void {
    $domain = new Domain;
    $domain->setRawAttributes(['type' => 1, 'verification_status' => 2, 'certificate_status' => null, 'is_primary' => 1]);

    expect($domain->getAttribute('type'))->toBe(TypeEnum::SUBDOMAIN);
    expect($domain->getAttribute('verification_status'))->toBe(VerificationStatusEnum::VERIFIED);
    expect($domain->getAttribute('certificate_status'))->toBeNull();
    $domain->setAttribute('certificate_status', CertificateStatusEnum::ACTIVE);
    expect($domain->toArray()['certificate_status'])->toBe(2);
});

test('business dates hydrate as immutable dates without adding microseconds', function (string $modelClass, string $field): void {
    config(['database.connections.tenant' => config('database.connections.mysql')]);
    $model = new $modelClass;
    $model->setRawAttributes([$field => '2026-10-08 12:34:56']);

    $date = $model->getAttribute($field);

    expect($date)->toBeInstanceOf(CarbonImmutable::class);
    expect($date->format('Y-m-d H:i:s.u'))->toBe('2026-10-08 12:34:56.000000');
    $date->addDay();
    expect($date->toDateString())->toBe('2026-10-08');
})->with([
    'central email verification' => [CentralUser::class, 'email_verified_at'],
    'central two factor confirmation' => [CentralUser::class, 'two_factor_confirmed_at'],
    'shop membership activation' => [TenantUser::class, 'joined_at'],
    'domain verification' => [Domain::class, 'verified_at'],
    'tenant provisioning' => [Tenant::class, 'provisioned_at'],
    'permission assignment' => [PermissionAssignment::class, 'assigned_at'],
    'permission expiration' => [PermissionAssignment::class, 'expires_at'],
]);

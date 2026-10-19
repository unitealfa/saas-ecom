<?php

namespace App\Services\Central\Tenants;

use App\DTOs\Central\Tenants\TenantData;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Feature;
use App\Models\Central\PlanFeature;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Illuminate\Database\UniqueConstraintViolationException;

class TenantCreator
{
    public function __construct(private AuthorizationMutation $mutations, private DefaultSubscription $subscriptions) {}

    public function create(User $actor, User $owner, TenantData $data): Tenant
    {
        return $this->persist($actor, $owner, $data, false);
    }

    public function createFirst(User $owner, TenantData $data): Tenant
    {
        return $this->persist($owner, $owner, $data, true);
    }

    public function createOwned(User $owner, TenantData $data): Tenant
    {
        return $this->persist($owner, $owner, $data, false, true);
    }

    private function persist(User $actor, User $owner, TenantData $data, bool $first, bool $owned = false): Tenant
    {
        return $this->mutations->run(function () use ($actor, $owner, $data, $first, $owned): Tenant {
            $owner = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($first || $owned ? 'createOwned' : 'create', [Tenant::class, $owner]);
            if ($owner->status !== StatusEnum::ACTIVE) {
                throw ValidationException::withMessages(['shop_name' => 'Le compte propriétaire doit être actif.']);
            }
            $fingerprint = $data->fingerprint($owner->uuid);
            $existing = Tenant::withTrashed()->where('user_id', $owner->id)
                ->where('creation_key', $data->creationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->creation_hash !== $fingerprint || $existing->trashed()) {
                    throw ValidationException::withMessages(['creation_key' => 'Cette demande de création a déjà été utilisée autrement.']);
                }

                return $existing;
            }

            if ($first && Tenant::where('user_id', $owner->id)->exists()) {
                throw ValidationException::withMessages(['shop_name' => 'Votre première boutique est déjà configurée.']);
            }
            if ($owned && ! Tenant::where('user_id', $owner->id)->exists()) {
                throw ValidationException::withMessages(['shop_name' => 'Configurez d’abord votre première boutique.']);
            }

            $subscription = $this->subscriptions->ensure($owner, $actor);
            $quotaFeature = Feature::where('code', 'shops')->where('is_active', true)->firstOrFail();
            $quota = PlanFeature::where('plan_id', $subscription->plan_id)->where('feature_id', $quotaFeature->id)
                ->lockForUpdate()->first();
            if ($quota === null || ! $quota->is_active) {
                throw ValidationException::withMessages(['shop_name' => 'L’offre actuelle ne permet pas de créer de boutique.']);
            }
            $count = Tenant::where('user_id', $owner->id)->orderBy('id')->lockForUpdate()->get(['id'])->count();
            if ($quota->limit !== null && $count >= $quota->limit) {
                throw ValidationException::withMessages(['shop_name' => 'Le nombre maximum de boutiques de cette offre est atteint.']);
            }

            $domain = $data->slug.'.'.config('tenancy.saas_base_domain');
            if (strlen($data->slug) > 55 || strlen($domain) > 253) {
                throw ValidationException::withMessages(['slug' => 'Ce slug dépasse la limite technique du nom de base ou du domaine.']);
            }
            $queueConnection = config('queue.connections.central-provisioning.connection');
            if ($queueConnection !== $owner->getConnectionName()) {
                throw new LogicException('The provisioning queue must use the same central database connection.');
            }
            $versions = glob(database_path('migrations/tenant/*.php'));
            if ($versions === false || $versions === []) {
                throw new LogicException('Tenant migrations are missing.');
            }
            sort($versions);
            $uuid = (string) Str::uuid();
            $tenant = new Tenant([
                'shop_name' => $data->shopName, 'slug' => $data->slug, 'internal_label' => $data->shopName,
            ]);
            $tenant->forceFill([
                'uuid' => $uuid, 'user_id' => $owner->id, 'document_prefix' => strtoupper(str_replace('-', '', $uuid)),
                'creation_key' => $data->creationKey, 'creation_hash' => $fingerprint,
            ]);
            $tenant->configureShopForProvisioning($data->settings->toArray());
            try {
                $tenant->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages(['slug' => 'Cette adresse de boutique est déjà réservée.']);
            } catch (LogicException $exception) {
                throw ValidationException::withMessages(['slug' => 'Ce slug ou ce nom de base est réservé ou indisponible.']);
            }
            try {
                $tenant->domains()->create([
                    'domain' => $domain, 'type' => 1, 'is_primary' => true,
                    'verification_status' => 2, 'verified_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException|\Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException) {
                throw ValidationException::withMessages(['slug' => 'Ce domaine de boutique est déjà utilisé.']);
            }
            $deployment = TenantSchemaDeployment::create([
                'tenant_id' => $tenant->id, 'target_version' => pathinfo(end($versions), PATHINFO_FILENAME),
                'operation' => OperationEnum::PROVISIONING, 'status' => DeploymentStatus::PENDING, 'attempt_number' => 1,
                'operation_key' => 'provision:'.$tenant->uuid, 'correlation_id' => (string) Str::uuid(),
                'runtime_versions' => ['php' => PHP_VERSION, 'laravel' => app()->version()],
            ]);
            ProvisionTenantDatabase::dispatch($deployment->id)->beforeCommit();
            activity()->causedBy($actor)->performedOn($tenant)->event('provisioning_requested')
                ->withProperties(['owner_uuid' => $owner->uuid, 'deployment_uuid' => $deployment->uuid])
                ->log('Boutique réservée et création de sa base demandée.');

            return $tenant;
        });
    }
}

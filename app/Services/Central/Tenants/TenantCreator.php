<?php

namespace App\Services\Central\Tenants;

use App\DTOs\Central\Tenants\TenantData;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

class TenantCreator
{
    private const string EVENT_REQUESTED = 'provisioning_requested';

    private const string DEPLOYMENT_KEY_PREFIX = 'provision:';

    private const int PRIMARY_DOMAIN_TYPE = 1;

    private const int MAX_SLUG_LENGTH = 55;

    private const int MAX_DOMAIN_LENGTH = 253;

    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly DefaultSubscription $subscriptions,
        private readonly TenantSchemaVersions $versions,
    ) {}

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
        return $this->mutations->run(
            actor: $actor,
            ability: $first || $owned ? 'createOwned' : 'create',
            loadTarget: fn () => [Tenant::class, User::whereKey($owner->id)->lockForUpdate()->firstOrFail()],
            mutation: function (array $arguments) use ($actor, $data, $first, $owned): Tenant {
                [, $owner] = $arguments;

                if ($owner->status !== StatusEnum::ACTIVE) {
                    throw ValidationException::withMessages(['shop_name' => 'Le compte propriétaire doit être actif.']);
                }

                $fingerprint = $data->fingerprint($owner->uuid);

                if ($existing = $this->replayOf($owner, $data, $fingerprint)) {
                    return $existing;
                }

                $this->assertPosition($owner, $first, $owned);
                $this->assertQuota($owner, $actor);

                $domain = $this->domainFor($data);
                $targetVersion = $this->targetSchemaVersion($owner);

                $tenant = $this->reserve($owner, $data, $fingerprint);
                $this->attachDomain($tenant, $domain);
                $deployment = $this->requestProvisioning($tenant, $targetVersion);

                activity()->causedBy($actor)->performedOn($tenant)->event(self::EVENT_REQUESTED)
                    ->withProperties(['owner_uuid' => $owner->uuid, 'deployment_uuid' => $deployment->uuid])
                    ->log('Boutique réservée et création de sa base demandée.');

                return $tenant;
            },
        );
    }

    /** Une demande déjà traitée avec la même empreinte est rejouée ; avec une empreinte différente, elle est refusée. */
    private function replayOf(User $owner, TenantData $data, string $fingerprint): ?Tenant
    {
        $existing = Tenant::withTrashed()->where('user_id', $owner->id)
            ->where('creation_key', $data->creationKey)->lockForUpdate()->first();

        if ($existing === null) {
            return null;
        }

        if ($existing->creation_hash !== $fingerprint || $existing->trashed()) {
            throw ValidationException::withMessages(['creation_key' => 'Cette demande de création a déjà été utilisée autrement.']);
        }

        return $existing;
    }

    private function assertPosition(User $owner, bool $first, bool $owned): void
    {
        $hasShop = Tenant::where('user_id', $owner->id)->exists();

        if ($first && $hasShop) {
            throw ValidationException::withMessages(['shop_name' => 'Votre première boutique est déjà configurée.']);
        }

        if ($owned && ! $hasShop) {
            throw ValidationException::withMessages(['shop_name' => 'Configurez d’abord votre première boutique.']);
        }
    }

    private function assertQuota(User $owner, User $actor): void
    {
        $subscription = $this->subscriptions->ensure($owner, $actor);
        $feature = Feature::where('code', Feature::SHOPS_CODE)->active()->firstOrFail();
        $quota = PlanFeature::where('plan_id', $subscription->plan_id)->where('feature_id', $feature->id)
            ->lockForUpdate()->first();

        if ($quota === null || ! $quota->is_active) {
            throw ValidationException::withMessages(['shop_name' => 'L’offre actuelle ne permet pas de créer de boutique.']);
        }

        // get() et non count() SQL : le verrou doit porter sur les lignes.
        $shops = Tenant::where('user_id', $owner->id)->orderBy('id')->lockForUpdate()->get(['id']);

        if ($quota->limit !== null && $shops->count() >= $quota->limit) {
            throw ValidationException::withMessages(['shop_name' => 'Le nombre maximum de boutiques de cette offre est atteint.']);
        }
    }

    private function domainFor(TenantData $data): string
    {
        $domain = $data->slug.'.'.config('tenancy.saas_base_domain');

        if (strlen($data->slug) > self::MAX_SLUG_LENGTH || strlen($domain) > self::MAX_DOMAIN_LENGTH) {
            throw ValidationException::withMessages(['slug' => 'Ce slug dépasse la limite technique du nom de base ou du domaine.']);
        }

        return $domain;
    }

    /** Vérifie que la création de base est possible avant toute écriture et retourne la version cible du schéma. */
    private function targetSchemaVersion(User $owner): string
    {
        if (config('queue.connections.central-provisioning.connection') !== $owner->getConnectionName()) {
            throw new LogicException('The provisioning queue must use the same central database connection.');
        }

        $files = $this->versions->files();

        if ($files === []) {
            throw new LogicException('Tenant migrations are missing.');
        }

        return pathinfo($files[array_key_last($files)], PATHINFO_FILENAME);
    }

    private function reserve(User $owner, TenantData $data, string $fingerprint): Tenant
    {
        $uuid = (string) Str::uuid();
        $tenant = new Tenant([
            'shop_name' => $data->shopName,
            'slug' => $data->slug,
            'internal_label' => $data->shopName,
        ]);
        $tenant->forceFill([
            'uuid' => $uuid,
            'user_id' => $owner->id,
            'document_prefix' => strtoupper(str_replace('-', '', $uuid)),
            'creation_key' => $data->creationKey,
            'creation_hash' => $fingerprint,
        ]);
        $tenant->configureShopForProvisioning($data->settings->toArray());

        try {
            $tenant->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Cette adresse de boutique est déjà réservée.']);
        } catch (LogicException) {
            throw ValidationException::withMessages(['slug' => 'Ce slug ou ce nom de base est réservé ou indisponible.']);
        }

        return $tenant;
    }

    private function attachDomain(Tenant $tenant, string $domain): void
    {
        try {
            $tenant->domains()->make([
                'domain' => $domain,
                'type' => self::PRIMARY_DOMAIN_TYPE,
                'is_primary' => true,
            ])->forceFill([
                'verification_status' => VerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException|DomainOccupiedByOtherTenantException) {
            throw ValidationException::withMessages(['slug' => 'Ce domaine de boutique est déjà utilisé.']);
        }
    }

    private function requestProvisioning(Tenant $tenant, string $targetVersion): TenantSchemaDeployment
    {
        $deployment = TenantSchemaDeployment::create([
            'tenant_id' => $tenant->id,
            'target_version' => $targetVersion,
            'operation' => OperationEnum::PROVISIONING,
            'status' => DeploymentStatus::PENDING,
            'attempt_number' => 1,
            'operation_key' => self::DEPLOYMENT_KEY_PREFIX.$tenant->uuid,
            'correlation_id' => (string) Str::uuid(),
            'runtime_versions' => $this->versions->runtime(),
        ]);

        ProvisionTenantDatabase::dispatch($deployment->id)->beforeCommit();

        return $deployment;
    }
}
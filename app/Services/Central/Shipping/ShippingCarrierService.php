<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShippingCarrierService
{
    private const int PER_PAGE = 20;

    private const string EVENT_CREATED = 'carrier_created';

    private const string EVENT_UPDATED = 'carrier_updated';

    private const string EVENT_ACTIVATED = 'carrier_activation_updated';

    private const string EVENT_ARCHIVED = 'carrier_archived';

    private const array AUDITED = ['code', 'name', 'is_active', 'reference_version', 'deleted_at'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ShippingCarrier>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        $this->assertCentralContext();
        Gate::forUser($actor)->authorize('viewAny', ShippingCarrier::class);

        $search = trim((string) ($filters['search'] ?? ''));
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return ShippingCarrier::query()
            ->when($filters['archived'] ?? false, fn (Builder $query) => $query->onlyTrashed())
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('code', 'like', $pattern),
            ))
            ->orderBy('name')->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function create(User $actor, string $code, string $name): ShippingCarrier
    {
        $this->assertCentralContext();

        return $this->mutations->run($actor, 'create', fn () => ShippingCarrier::class,
            function () use ($actor, $code, $name): ShippingCarrier {
                $isYalidine = $code === ShippingCarrier::YALIDINE;

                $carrier = new ShippingCarrier(['name' => $name]);
                $carrier->forceFill([
                    'code' => $code,
                    'adapter' => $isYalidine ? ShippingCarrier::YALIDINE : ShippingCarrier::UNCONFIGURED_ADAPTER,
                    'default_api_url' => null,
                    'reference_source' => $isYalidine ? ShippingCarrier::YALIDINE_SOURCE : ShippingCarrier::MANUAL_SOURCE,
                ]);

                try {
                    $carrier->save();
                } catch (UniqueConstraintViolationException) {
                    $this->fail('code', 'Ce transporteur existe déjà, y compris dans les archives.');
                }

                $this->audit($actor, $carrier, self::EVENT_CREATED, null);

                return $carrier;
            });
    }

    public function update(User $actor, ShippingCarrier $carrier, string $name, string $expected): void
    {
        $this->mutate($actor, $carrier, 'update', $expected, function (ShippingCarrier $carrier) use ($actor, $name): void {
            if ($carrier->name === $name) {
                return;
            }

            $before = $this->revise($carrier, ['name' => $name]);
            $this->audit($actor, $carrier, self::EVENT_UPDATED, $before);
        });
    }

    public function setActive(User $actor, ShippingCarrier $carrier, bool $active, string $expected): void
    {
        $this->mutate($actor, $carrier, 'activate', $expected, function (ShippingCarrier $carrier) use ($actor, $active): void {
            if ($carrier->is_active === $active) {
                return;
            }

            $before = $this->revise($carrier, ['is_active' => $active]);
            $this->audit($actor, $carrier, self::EVENT_ACTIVATED, $before);
        });
    }

    public function archive(User $actor, ShippingCarrier $carrier, string $expected): void
    {
        $this->mutate($actor, $carrier, 'archive', $expected, function (ShippingCarrier $carrier) use ($actor): void {
            if ($this->isReferenced($carrier)) {
                $this->fail('carrier', 'Ce transporteur est utilisé. Désactivez-le plutôt que de l’archiver.');
            }

            $before = $this->revise($carrier, ['is_active' => false]);
            $carrier->delete();
            $this->audit($actor, $carrier, self::EVENT_ARCHIVED, $before);
        });
    }

    /**
     * Exécute une modification sur le transporteur verrouillé, après contrôle de l'état attendu.
     *
     * @param  Closure(ShippingCarrier): void  $mutation
     */
    private function mutate(User $actor, ShippingCarrier $carrier, string $ability, string $expected, Closure $mutation): void
    {
        $this->assertCentralContext();

        $this->mutations->run($actor, $ability, fn () => $this->lock($carrier, $expected), $mutation);
    }

    private function lock(ShippingCarrier $carrier, string $expected): ShippingCarrier
    {
        $current = ShippingCarrier::query()->whereKey($carrier->id)->lockForUpdate()->firstOrFail();

        if (! hash_equals($current->managementState(), $expected)) {
            $this->fail('conflict', 'Ce transporteur a changé. Rechargez la page avant de recommencer.');
        }

        return $current;
    }

    /**
     * Applique les changements, incrémente la version de référence et retourne l'état audité d'avant.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function revise(ShippingCarrier $carrier, array $changes): array
    {
        $before = $carrier->only(self::AUDITED);

        $carrier->forceFill($changes);
        $carrier->reference_version++;
        $carrier->save();

        return $before;
    }

    private function isReferenced(ShippingCarrier $carrier): bool
    {
        return $this->hasCentralReferences($carrier) || $this->hasTenantReferences($carrier);
    }

    private function hasCentralReferences(ShippingCarrier $carrier): bool
    {
        $central = $carrier->getConnection();

        return $central->table('carrier_geo_mappings')->where('carrier_id', $carrier->id)->exists()
            || $central->table('pickup_points')->where('carrier_id', $carrier->id)->exists();
    }

    /** Parcourt toutes les boutiques préparées : si l'une est illisible, l'archivage est refusé par prudence. */
    private function hasTenantReferences(ShippingCarrier $carrier): bool
    {
        foreach (Tenant::query()->withTrashed()->whereNotNull('provisioned_at')->cursor() as $tenant) {
            /** @var Tenant $tenant */
            try {
                tenancy()->initialize($tenant);
                $referenced = DB::connection('tenant')->table('carrier_accounts')
                    ->where('carrier_uuid', $carrier->uuid)->exists();
            } catch (Throwable $exception) {
                report($exception);

                $this->fail('carrier', 'Les références des boutiques sont indisponibles. Archivage refusé.');
            } finally {
                tenancy()->end();
            }

            if ($referenced) {
                return true;
            }
        }

        return false;
    }

    private function assertCentralContext(): void
    {
        abort_if(tenancy()->initialized, 403);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, ShippingCarrier $carrier, string $event, ?array $before): void
    {
        activity()->causedBy($actor)->performedOn($carrier)->event($event)
            ->withProperties(['before' => $before, 'after' => $carrier->only(self::AUDITED)])
            ->log('Catalogue des transporteurs modifié.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

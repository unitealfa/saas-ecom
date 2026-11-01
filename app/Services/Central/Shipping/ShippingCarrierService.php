<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
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

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, ShippingCarrier>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('viewAny', ShippingCarrier::class);
        $pattern = '%'.addcslashes(trim((string) ($filters['search'] ?? '')), '\\%_').'%';

        return ShippingCarrier::query()
            ->when($filters['archived'] ?? false, fn (Builder $query) => $query->onlyTrashed())
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->where(fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('code', 'like', $pattern))
            ->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function create(User $actor, string $code, string $name): ShippingCarrier
    {
        abort_if(tenancy()->initialized, 403);

        return $this->mutations->run($actor, 'create', fn () => ShippingCarrier::class,
            function () use ($actor, $code, $name): ShippingCarrier {
                $carrier = new ShippingCarrier(['name' => $name]);
                $carrier->forceFill([
                    'code' => $code,
                    'adapter' => $code === ShippingCarrier::YALIDINE ? ShippingCarrier::YALIDINE : ShippingCarrier::UNCONFIGURED_ADAPTER,
                    'default_api_url' => null,
                    'reference_source' => $code === ShippingCarrier::YALIDINE ? ShippingCarrier::YALIDINE_SOURCE : ShippingCarrier::MANUAL_SOURCE,
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
        abort_if(tenancy()->initialized, 403);

        $this->mutations->run($actor, 'update', fn () => $this->lock($carrier, $expected),
            function (ShippingCarrier $carrier) use ($actor, $name): void {
                if ($carrier->name === $name) {
                    return;
                }
                $before = $carrier->only(self::AUDITED);
                $carrier->name = $name;
                $this->saveRevision($carrier);
                $this->audit($actor, $carrier, self::EVENT_UPDATED, $before);
            });
    }

    public function setActive(User $actor, ShippingCarrier $carrier, bool $active, string $expected): void
    {
        abort_if(tenancy()->initialized, 403);

        $this->mutations->run($actor, 'activate', fn () => $this->lock($carrier, $expected),
            function (ShippingCarrier $carrier) use ($actor, $active): void {
                if ($carrier->is_active === $active) {
                    return;
                }
                $before = $carrier->only(self::AUDITED);
                $carrier->forceFill(['is_active' => $active]);
                $this->saveRevision($carrier);
                $this->audit($actor, $carrier, self::EVENT_ACTIVATED, $before);
            });
    }

    public function archive(User $actor, ShippingCarrier $carrier, string $expected): void
    {
        abort_if(tenancy()->initialized, 403);

        $this->mutations->run($actor, 'archive', fn () => $this->lock($carrier, $expected),
            function (ShippingCarrier $carrier) use ($actor): void {
                if ($this->isReferenced($carrier)) {
                    $this->fail('carrier', 'Ce transporteur est utilisé. Désactivez-le plutôt que de l’archiver.');
                }
                $before = $carrier->only(self::AUDITED);
                $carrier->forceFill(['is_active' => false]);
                $this->saveRevision($carrier);
                $carrier->delete();
                $this->audit($actor, $carrier, self::EVENT_ARCHIVED, $before);
            });
    }

    private function lock(ShippingCarrier $carrier, string $expected): ShippingCarrier
    {
        $current = ShippingCarrier::query()->whereKey($carrier->id)->lockForUpdate()->firstOrFail();
        if (! hash_equals($current->managementState(), $expected)) {
            $this->fail('conflict', 'Ce transporteur a changé. Rechargez la page avant de recommencer.');
        }

        return $current;
    }

    private function saveRevision(ShippingCarrier $carrier): void
    {
        $carrier->reference_version++;
        $carrier->save();
    }

    private function isReferenced(ShippingCarrier $carrier): bool
    {
        $central = $carrier->getConnection();
        if ($central->table('carrier_geo_mappings')->where('carrier_id', $carrier->id)->exists()
            || $central->table('pickup_points')->where('carrier_id', $carrier->id)->exists()) {
            return true;
        }
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

<?php

declare(strict_types=1);

namespace App\Services\Central\Domains;

use App\Enums\Central\Domains\TypeEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;

class DomainService
{
    private const int PER_PAGE = 20;

    private const string EVENT_CREATED = 'domain_created';

    private const string EVENT_UPDATED = 'domain_updated';

    private const string EVENT_PRIMARY_CHANGED = 'domain_primary_changed';

    private const string EVENT_ARCHIVED = 'domain_archived';

    private const string EVENT_RESTORED = 'domain_restored';

    private const array AUDITED = ['domain', 'type', 'is_primary', 'verification_status', 'verified_at', 'certificate_status', 'deleted_at'];

    public function __construct(private AuthorizationMutation $mutations) {}

    /** @return LengthAwarePaginator<int, Domain> */
    public function search(User $actor, ?string $search, bool $archived): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Domain::class);

        $query = Domain::with(['tenant' => fn ($query) => $query->withTrashed()]);

        if ($archived) {
            $query->onlyTrashed();
        }

        if (filled($search)) {
            $pattern = $this->likePattern($search);

            $query->where(fn ($query) => $query->where('domain', 'like', $pattern)
                ->orWhereHas('tenant', fn ($query) => $query->withTrashed()->where('shop_name', 'like', $pattern)));
        }

        return $query->orderBy('domain')->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @return LengthAwarePaginator<int, Tenant> */
    public function shops(User $actor, ?string $search, ?string $tenantUuid = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('create', Domain::class);

        $query = Tenant::query();

        if ($tenantUuid !== null) {
            $query->where('uuid', $tenantUuid);
        }

        if (filled($search)) {
            $query->where('shop_name', 'like', $this->likePattern($search));
        }

        return $query->orderBy('shop_name')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function create(User $actor, string $tenantUuid, string $host): Domain
    {
        return $this->mutations->run($actor, 'create', fn () => Domain::class, function () use ($actor, $tenantUuid, $host): Domain {
            $tenant = Tenant::where('uuid', $tenantUuid)->lockForUpdate()->firstOrFail();
            $host = $this->normalize($host);

            $domain = new Domain(['domain' => $host, 'type' => $this->typeFor($tenant, $host), 'is_primary' => false]);
            $domain->tenant()->associate($tenant);
            $domain->forceFill(['verification_status' => VerificationStatusEnum::PENDING]);

            $this->save($domain);
            $this->audit($actor, $domain, self::EVENT_CREATED, null);

            return $domain;
        });
    }

    public function update(User $actor, Domain $domain, string $host, string $expectedState): void
    {
        $this->mutations->run($actor, 'update', fn () => $this->lock($domain, $expectedState), function (Domain $domain) use ($actor, $host): void {
            $host = $this->normalize($host);

            if ($domain->domain === $host) {
                return;
            }

            if ($domain->is_primary) {
                throw ValidationException::withMessages(['domain' => 'Préparez et vérifiez une adresse de remplacement avant de remplacer le domaine principal.']);
            }

            $before = $domain->only(self::AUDITED);

            // Une adresse modifiée doit être vérifiée à nouveau.
            $domain->forceFill([
                'domain' => $host,
                'type' => $this->typeFor($domain->tenant, $host),
                ...$this->unverified(),
            ]);

            $this->save($domain);
            $this->audit($actor, $domain, self::EVENT_UPDATED, $before);
        });
    }

    public function setPrimary(User $actor, Domain $domain, string $expectedState, ?string $expectedPrimary): void
    {
        $this->mutations->run($actor, 'setPrimary', fn () => $this->lock($domain, $expectedState), function (Domain $domain) use ($actor, $expectedPrimary): void {
            $current = Domain::where('tenant_id', $domain->tenant_id)->where('is_primary', true)->lockForUpdate()->first();

            if ($current?->uuid !== $expectedPrimary) {
                throw ValidationException::withMessages(['domain' => 'Le domaine principal a changé. Rechargez la page.']);
            }

            if (! $domain->isReadyAsPrimary()) {
                throw ValidationException::withMessages(['domain' => 'Vérifiez le domaine et son certificat HTTPS avant de le définir comme principal.']);
            }

            if ($domain->is_primary) {
                return;
            }

            // L'ancien principal est désactivé avant l'activation du nouveau (un seul principal par boutique).
            $current?->forceFill(['is_primary' => false])->save();

            $before = $domain->only(self::AUDITED);
            $domain->forceFill(['is_primary' => true])->save();

            $this->audit($actor, $domain, self::EVENT_PRIMARY_CHANGED, $before, ['previous_domain_uuid' => $current?->uuid]);
        });
    }

    public function archive(User $actor, Domain $domain, string $expectedState): void
    {
        $this->mutations->run($actor, 'archive', fn () => $this->lock($domain, $expectedState), function (Domain $domain) use ($actor): void {
            $hasOtherDomain = Domain::where('tenant_id', $domain->tenant_id)->whereKeyNot($domain->id)->exists();

            if ($domain->is_primary || ! $hasOtherDomain) {
                throw ValidationException::withMessages(['domain' => 'Conservez une adresse principale : ajoutez, vérifiez et choisissez un autre domaine avant cet archivage.']);
            }

            $before = $domain->only(self::AUDITED);
            $domain->delete();

            $this->audit($actor, $domain, self::EVENT_ARCHIVED, $before);
        });
    }

    public function restore(User $actor, Domain $domain, string $expectedState): void
    {
        $this->mutations->run($actor, 'restore', fn () => $this->lock($domain, $expectedState, true), function (Domain $domain) use ($actor): void {
            $before = $domain->only(self::AUDITED);

            // Un domaine restauré n'est ni principal ni vérifié : il repasse par la vérification.
            $domain->forceFill(['is_primary' => false, ...$this->unverified()]);
            $domain->restore();

            $this->audit($actor, $domain, self::EVENT_RESTORED, $before);
        });
    }

    public function lock(Domain $domain, string $expectedState, bool $archived = false): Domain
    {
        // Ordre de verrouillage constant : boutique, puis domaine.
        Tenant::whereKey($domain->tenant_id)->lockForUpdate()->firstOrFail();

        $query = $archived ? Domain::onlyTrashed() : Domain::query();
        $current = $query->whereKey($domain->id)->lockForUpdate()->firstOrFail();

        if (! hash_equals($current->managementState(), $expectedState)) {
            throw ValidationException::withMessages(['domain' => 'Ce domaine a changé. Rechargez la page avant de recommencer.']);
        }

        return $current;
    }

    public function normalize(string $host): string
    {
        $host = idn_to_ascii(rtrim(mb_strtolower(trim($host)), '.'), IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        if (! is_string($host) || strlen($host) > 253 || ! str_contains($host, '.')
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw ValidationException::withMessages(['domain' => 'Saisissez un nom de domaine valide, sans protocole, port, chemin ni caractère générique.']);
        }

        if ($this->isReservedHost($host)) {
            throw ValidationException::withMessages(['domain' => 'Cette adresse est réservée à la plateforme centrale.']);
        }

        return $host;
    }

    /** Domaines de la plateforme, et leurs sous-domaines sauf ceux qui sont des boutiques (sous le domaine de base). */
    private function isReservedHost(string $host): bool
    {
        $base = $this->platformHost();
        $central = $this->centralHosts();

        if (in_array($host, [...$central, $base], true)) {
            return true;
        }

        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            return false;
        }

        foreach ($central as $centralHost) {
            if (str_ends_with($host, '.'.$centralHost)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function centralHosts(): array
    {
        $hosts = [...(array) config('tenancy.central_domains', []), parse_url((string) config('app.url'), PHP_URL_HOST)];

        return array_values(array_filter(array_map(
            fn (mixed $host): string => strtolower(trim((string) $host)),
            $hosts,
        )));
    }

    private function platformHost(): string
    {
        return strtolower(rtrim((string) config('tenancy.saas_base_domain'), '.'));
    }

    private function typeFor(Tenant $tenant, string $host): TypeEnum
    {
        $base = $this->platformHost();

        if ($base === '' || ! str_ends_with($host, '.'.$base)) {
            return TypeEnum::CUSTOM;
        }

        $label = substr($host, 0, -strlen('.'.$base));

        if (! preg_match(Tenant::SLUG_PATTERN, $label)
            || in_array($label, Tenant::RESERVED_SLUGS, true)
            || Tenant::withTrashed()->where('slug', $label)->whereKeyNot($tenant->id)->exists()) {
            throw ValidationException::withMessages(['domain' => 'Ce sous-domaine est invalide, réservé ou appartient à une autre boutique.']);
        }

        return TypeEnum::SUBDOMAIN;
    }

    /** @return array<string, mixed> */
    private function unverified(): array
    {
        return [
            'verification_status' => VerificationStatusEnum::PENDING,
            'verified_at' => null,
            'certificate_status' => null,
        ];
    }

    private function likePattern(string $search): string
    {
        return '%'.addcslashes(trim($search), '\\%_').'%';
    }

    private function save(Domain $domain): void
    {
        try {
            $domain->save();
        } catch (UniqueConstraintViolationException|DomainOccupiedByOtherTenantException) {
            throw ValidationException::withMessages(['domain' => 'Ce domaine est déjà réservé, y compris s’il a été archivé.']);
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $extra
     */
    private function audit(User $actor, Domain $domain, string $event, ?array $before, array $extra = []): void
    {
        activity()->causedBy($actor)->performedOn($domain)->event($event)
            ->withProperties(['tenant_uuid' => $domain->tenant->uuid, 'before' => $before, 'after' => $domain->only(self::AUDITED), ...$extra])
            ->log('Gestion administrative du domaine.');
    }
}

<?php

namespace App\Policies\Central;

use App\Enums\Central\Tenants\StatusEnum;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class DomainPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.domains.view');
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.domains.create');
    }

    public function createOwned(User $actor, Tenant $tenant): bool
    {
        return ! tenancy()->initialized && ! $tenant->trashed() && $tenant->user_id === $actor->id && $tenant->status === StatusEnum::ACTIVE;
    }

    public function update(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && $this->access->allows($actor, 'saas.domains.update');
    }

    public function verify(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && $this->access->isStaff($actor)
            && $this->access->allows($actor, 'saas.domains.verify');
    }

    public function setPrimary(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && ($this->access->allows($actor, 'saas.domains.primary.update') || $this->createOwned($actor, $domain->tenant));
    }

    public function archive(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && ($this->access->allows($actor, 'saas.domains.archive') || $this->createOwned($actor, $domain->tenant));
    }

    /** Restaurer un domaine archivé revient à le réserver à nouveau : même permission que la création. */
    public function restore(User $actor, Domain $domain): bool
    {
        return $domain->trashed()
            && $this->hasActiveTenant($domain)
            && $this->access->allows($actor, 'saas.domains.create');
    }

    /** Un domaine archivé, ou dont la boutique est archivée, n'est plus modifiable. */
    private function isEditable(Domain $domain): bool
    {
        return ! $domain->trashed() && $this->hasActiveTenant($domain);
    }

    private function hasActiveTenant(Domain $domain): bool
    {
        return $domain->tenant !== null && ! $domain->tenant->trashed();
    }
}

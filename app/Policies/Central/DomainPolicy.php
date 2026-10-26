<?php

namespace App\Policies\Central;

use App\Models\Central\Domain;
use App\Models\Central\User;
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

    public function update(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && $this->access->allows($actor, 'saas.domains.update');
    }

    public function verify(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && $this->access->allows($actor, 'saas.domains.verify');
    }

    public function setPrimary(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && $this->access->allows($actor, 'saas.domains.primary.update');
    }

    public function archive(User $actor, Domain $domain): bool
    {
        return $this->isEditable($domain) && $this->access->allows($actor, 'saas.domains.archive');
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

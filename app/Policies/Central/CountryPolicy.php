<?php

namespace App\Policies\Central;

use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class CountryPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.countries.view', listing: true);
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.countries.create');
    }

    public function update(User $actor, Country $country): bool
    {
        return $this->access->allows($actor, 'saas.countries.update');
    }

    public function activate(User $actor, Country $country): bool
    {
        return $this->access->allows($actor, 'saas.countries.activate');
    }

    public function delete(User $actor, Country $country): bool
    {
        return $this->access->allows($actor, 'saas.countries.delete');
    }
}

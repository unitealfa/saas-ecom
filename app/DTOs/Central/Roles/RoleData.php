<?php

namespace App\DTOs\Central\Roles;

final readonly class RoleData
{
    /**
     * @param  array<string, int|null>|null  $permissionDurations  UUID => jours ; null conserve une durée existante.
     * @param  array<string, int>  $durations  Modifications de durées sans remplacement de composition.
     */
    public function __construct(public ?string $name, public ?string $label, public ?array $permissionDurations, public array $durations = []) {}
}

<?php

namespace App\DTOs\Central\Roles;

final readonly class RoleData
{
    /** @param array<string, int> $permissionDurations UUID => jours */
    public function __construct(public string $name, public string $label, public array $permissionDurations) {}
}

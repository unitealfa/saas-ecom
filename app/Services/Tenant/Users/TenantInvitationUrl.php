<?php

namespace App\Services\Tenant\Users;

use App\Enums\VerificationStatusEnum;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Tenants\Tenant;
use LogicException;

final class TenantInvitationUrl
{
    public function make(string $route, array $params = []): string
    {
        $tenant = tenancy()->tenant;
        if (! tenancy()->initialized || ! $tenant instanceof Tenant) {
            throw new LogicException('Contexte boutique absent.');
        }
        $domain = Domain::where('tenant_id', $tenant->id)->where('is_primary', true)
            ->where('verification_status', VerificationStatusEnum::VERIFIED->value)->first();
        if ($domain === null || ! $domain->isReadyAsPrimary()) {
            throw new LogicException('Aucun domaine principal validé pour cette boutique.');
        }
        $path = route($route, $params, false);
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new LogicException('Route relative invalide.');
        }
        return rtrim($domain->publicUrl(), '/').$path;
    }
}

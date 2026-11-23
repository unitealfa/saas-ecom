<?php

namespace App\Models\Tenant\Users;

use App\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

final class Passkey extends \Laravel\Passkeys\Passkey
{
    use HasPublicUuid, TenantConnection;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

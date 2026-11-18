<?php

namespace App\Models\Tenant\Users;

use App\Concerns\HasPublicUuid;
use App\Models\Tenant\Roles\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

#[Fillable([])]
final class TeamInvitation extends Model
{
    use HasPublicUuid, TenantConnection;

    protected $table = 'team_invitations';

    protected $hidden = ['id', 'token_hash', 'invited_by_id', 'initial_role_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime'];
    }

    public function initialRole(): BelongsTo { return $this->belongsTo(Role::class, 'initial_role_id'); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by_id'); }
}

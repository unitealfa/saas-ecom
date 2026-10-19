<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class UserContact extends Model
{
    use CentralConnection, HasPublicUuid;

    protected $guarded = ['id', 'primary_slot'];

    protected $hidden = ['id', 'user_id', 'country_id', 'primary_slot'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class)->withTrashed();
    }
}

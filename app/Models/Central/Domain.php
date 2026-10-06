<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Database\Factories\Central\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

#[Fillable(['domain', 'type', 'is_primary', 'verification_status', 'verified_at', 'certificate_status'])]
#[Hidden(['id', 'tenant_id', 'primary_slot'])]
class Domain extends BaseDomain
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verification_status' => 'integer', 'verified_at' => 'immutable_datetime'];
    }
}

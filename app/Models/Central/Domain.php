<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\Central\Domains\TypeEnum;
use App\Enums\VerificationStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

#[Fillable(['domain', 'type', 'is_primary'])]
#[Hidden(['id', 'tenant_id', 'primary_slot'])]
class Domain extends BaseDomain
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'type' => TypeEnum::class,
            'is_primary' => 'boolean',
            'verification_status' => VerificationStatusEnum::class,
            'verified_at' => 'immutable_datetime',
            'certificate_status' => CertificateStatusEnum::class,
        ];
    }

    protected function domain(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::lower(trim($value)));
    }
}

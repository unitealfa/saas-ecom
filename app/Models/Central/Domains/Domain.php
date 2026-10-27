<?php

namespace App\Models\Central\Domains;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\Central\Domains\TypeEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Tenants\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * @property VerificationStatusEnum $verification_status
 * @property TypeEnum $type
 * @property CertificateStatusEnum|null $certificate_status
 * @property CarbonImmutable|null $verified_at
 * @property-read Tenant|null $tenant
 */
#[Fillable(['domain', 'type', 'is_primary'])]
#[Hidden(['id', 'tenant_id', 'primary_slot'])]
class Domain extends BaseDomain
{
    use HasPublicUuid, SoftDeletes;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return parent::tenant();
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode(array_intersect_key(
            $this->getRawOriginal(),
            array_flip(['uuid', 'tenant_id', 'domain', 'type', 'is_primary', 'verification_status', 'verified_at', 'certificate_status', 'updated_at', 'deleted_at']),
        ), JSON_THROW_ON_ERROR));
    }

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

    public function usesLocalHttp(): bool
    {
        return $this->type === TypeEnum::SUBDOMAIN && app()->environment(['local', 'testing'])
            && parse_url(config('app.url'), PHP_URL_SCHEME) === 'http';
    }

    public function isReadyAsPrimary(): bool
    {
        return ! $this->trashed() && $this->verification_status === VerificationStatusEnum::VERIFIED
            && $this->verified_at !== null
            && ($this->usesLocalHttp() || $this->certificate_status === CertificateStatusEnum::ACTIVE);
    }

    public function publicUrl(): string
    {
        $port = $this->usesLocalHttp() ? parse_url(config('app.url'), PHP_URL_PORT) : null;

        return ($this->usesLocalHttp() ? 'http' : 'https').'://'.$this->domain.($port !== null ? ':'.$port : '').'/';
    }

    /** @return Attribute<string, string> */
    protected function domain(): Attribute
    {
        return Attribute::set(fn (string $value): string => Str::lower(trim($value)));
    }
}

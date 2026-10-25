<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Services\Central\Countries\CountryCatalog;
use App\Services\Central\Users\PhoneNumberNormalizer;
use Database\Factories\Central\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['code', 'name_fr', 'name_en', 'name_ar', 'is_active'])]
#[Hidden(['id'])]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    protected static function booted(): void
    {
        $invalidate = static function (self $country): void {
            $country->getConnection()->afterCommit(
                static fn () => app(CountryCatalog::class)->invalidate()
            );
        };

        static::saved($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getCallingCodeAttribute(): ?string
    {
        return app(PhoneNumberNormalizer::class)->callingCode($this->code);
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }
}

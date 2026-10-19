<?php

namespace App\Models\Central;

use App\Concerns\HasProfilePhoto;
use App\Concerns\HasPublicUuid;
use App\Concerns\HasTimedPermissions;
use App\Concerns\LogsSafeActivity;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use Carbon\CarbonImmutable;
use Database\Factories\Central\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property int $country_id
 * @property int|null $phone_country_id
 * @property Country|null $phoneCountry
 * @property string $last_name
 * @property string|null $first_name
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $legal_form
 * @property string|null $activity_nature
 * @property string|null $legal_address
 * @property int|null $legal_profile_version
 * @property VerificationStatusEnum|null $legal_verification_status
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property StatusEnum $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'last_name', 'first_name', 'email', 'password'])]
#[Hidden(['id', 'country_id', 'phone_country_id', 'legal_verified_by_id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    protected $attributes = ['locale' => 'fr', 'status' => 1];

    /** @use HasFactory<UserFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    use HasProfilePhoto, HasTimedPermissions, LogsSafeActivity;

    protected string $guard_name = 'central';

    public function getMorphClass(): string
    {
        return 'central_user';
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return Attribute<string, string> */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => trim(($attributes['first_name'] ?? '').' '.($attributes['last_name'] ?? '')),
            set: fn (string $value): array => ['last_name' => $value, 'first_name' => null],
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->email = Str::lower(trim($user->email));
            if (! $user->exists || $user->isDirty(['phone', 'phone_country_id'])) {
                $user->normalizePhone();
            }
        });
    }

    private function normalizePhone(): void
    {
        if (! $this->getConnection()->getSchemaBuilder()->hasColumn('users', 'phone_country_id')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'La mise à jour des coordonnées est temporairement indisponible. Contactez l’administration.']);
        }
        if ($this->phone === null || trim($this->phone) === '') {
            $this->phone = null;
            $this->phone_country_id = null;
        } else {
            $countryId = $this->phone_country_id ?? $this->country_id;
            $sameCountry = $this->exists && (int) $this->getRawOriginal('phone_country_id') === (int) $countryId;
            $country = Country::withTrashed()->find($countryId);
            if ($country === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour le téléphone.']);
            }
            $this->phone = app(\App\Services\Central\Users\PhoneNumberNormalizer::class)->normalize($this->phone, $country->code);
            if ((! $country->is_active || $country->trashed()) && (! $sameCountry || $this->phone !== $this->getRawOriginal('phone'))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour un nouveau numéro.']);
            }
            $this->phone_country_id = $country->id;
            if (self::withTrashed()->where('phone', $this->phone)->when($this->exists, fn ($query) => $query->whereKeyNot($this->id))->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['phone' => 'Ce numéro est déjà attribué à un compte.']);
            }
        }
        if ($this->exists && $this->isDirty('phone')) {
            $this->phone_verified_at = null;
            $this->whatsapp_verified_at = null;
        }
    }

    public function save(array $options = []): bool
    {
        try {
            return $this->getConnection()->transaction(function () use ($options): bool {
                if ($this->exists) {
                    self::withTrashed()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                }
                return parent::save($options);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            foreach (['phone', 'email'] as $field) {
                if (str_contains($exception->getMessage(), 'users_'.$field.'_unique')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => $field === 'email'
                        ? 'Cette adresse e-mail est déjà utilisée.' : 'Ce numéro de téléphone est déjà associé à un compte.']);
                }
            }
            throw $exception;
        }
    }

    /** @return BelongsTo<Country, $this> */
    public function phoneCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'phone_country_id')->withTrashed();
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'user_id');
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function legalVerifier(): BelongsTo
    {
        return $this->belongsTo(self::class, 'legal_verified_by_id')->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'status' => StatusEnum::class,
            'country_id' => 'integer',
            'phone_country_id' => 'integer',
            'legal_profile_version' => 'integer',
            'legal_verification_status' => VerificationStatusEnum::class,
            'legal_verified_by_id' => 'integer',
            'phone_verified_at' => 'immutable_datetime',
            'whatsapp_verified_at' => 'immutable_datetime',
            'legal_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'share_capital' => 'decimal:2',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}

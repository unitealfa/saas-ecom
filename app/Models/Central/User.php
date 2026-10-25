<?php

namespace App\Models\Central;

use App\Concerns\HasProfilePhoto;
use App\Concerns\HasPublicUuid;
use App\Concerns\HasTimedPermissions;
use App\Concerns\LogsSafeActivity;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Services\Central\Users\PhoneNumberNormalizer;
use Carbon\CarbonImmutable;
use Database\Factories\Central\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property StatusEnum $status
 * @property VerificationStatusEnum|null $legal_verification_status
 * @property int|null $legal_profile_version
 * @property int|null $country_id
 * @property int|null $phone_country_id
 * @property CarbonImmutable|null $legal_verified_at
 * @property string|null $remember_token
 * @property string $name
 * @property string|null $legal_form
 * @property string|null $activity_nature
 * @property string|null $legal_address
 * @property CarbonImmutable|null $phone_verified_at
 * @property CarbonImmutable|null $whatsapp_verified_at
 * @property-read Country|null $country
 * @property-read Country|null $phoneCountry
 */
#[Fillable(['name', 'last_name', 'first_name', 'email', 'password'])]
#[Hidden(['id', 'country_id', 'phone_country_id', 'legal_verified_by_id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /** @use HasProfilePhoto<Media> */
    use HasProfilePhoto;

    /** @use HasTimedPermissions<Role, Permission> */
    use HasTimedPermissions;

    use LogsSafeActivity;

    /** Message de validation par champ unique (index users_<champ>_unique). */
    private const array UNIQUE_MESSAGES = [
        'phone' => 'Ce numéro de téléphone est déjà associé à un compte.',
        'email' => 'Cette adresse e-mail est déjà utilisée.',
    ];

    protected string $guard_name = 'central';

    protected $attributes = ['locale' => 'fr', 'status' => StatusEnum::ACTIVE->value];

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

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->email = Str::lower(trim($user->email));

            if (! $user->exists || $user->isDirty(['phone', 'phone_country_id'])) {
                $user->normalizePhone();
            }
        });
    }

    /** Doit rester défini ici : App\Models\User en hérite, et ses factories passent par ce modèle. */
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

    public function getMorphClass(): string
    {
        return 'central_user';
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeWithStatus(Builder $query, StatusEnum $status): Builder
    {
        return $query->where('status', $status->value);
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
        } catch (UniqueConstraintViolationException $exception) {
            foreach (self::UNIQUE_MESSAGES as $field => $message) {
                if (str_contains($exception->getMessage(), "users_{$field}_unique")
                    || str_contains($exception->getMessage(), "users.{$field}")) {
                    throw ValidationException::withMessages([$field => $message]);
                }
            }

            throw $exception;
        }
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function phoneCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'phone_country_id')->withTrashed();
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return BelongsTo<self, $this> */
    public function legalVerifier(): BelongsTo
    {
        return $this->belongsTo(self::class, 'legal_verified_by_id')->withTrashed();
    }

    /** Get the user's initials. */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    private function normalizePhone(): void
    {
        $this->assertPhoneColumnsExist();

        if (blank($this->phone)) {
            $this->phone = null;
            $this->phone_country_id = null;
        } else {
            $this->normalizeFilledPhone();
        }

        if ($this->exists && $this->isDirty('phone')) {
            $this->phone_verified_at = null;
            $this->whatsapp_verified_at = null;
        }
    }

    private function assertPhoneColumnsExist(): void
    {
        if (! $this->getConnection()->getSchemaBuilder()->hasColumn('users', 'phone_country_id')) {
            throw ValidationException::withMessages([
                'phone' => 'La mise à jour des coordonnées est temporairement indisponible. Contactez l’administration.',
            ]);
        }
    }

    private function normalizeFilledPhone(): void
    {
        $countryId = $this->phone_country_id ?? $this->country_id;
        $sameCountry = $this->exists && (int) $this->getRawOriginal('phone_country_id') === (int) $countryId;

        $country = Country::withTrashed()->find($countryId)
            ?? throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour le téléphone.']);

        $this->phone = app(PhoneNumberNormalizer::class)->normalize($this->phone, $country->code);

        // Un pays inactif ou archivé reste toléré seulement pour un numéro déjà enregistré, inchangé.
        $countryIsUsable = $country->is_active && ! $country->trashed();
        $numberUnchanged = $sameCountry && $this->phone === $this->getRawOriginal('phone');

        if (! $countryIsUsable && ! $numberUnchanged) {
            throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour un nouveau numéro.']);
        }

        $this->phone_country_id = $country->id;
        $this->assertPhoneIsUnique();
    }

    private function assertPhoneIsUnique(): void
    {
        $taken = self::withTrashed()
            ->where('phone', $this->phone)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['phone' => 'Ce numéro est déjà attribué à un compte.']);
        }
    }
}

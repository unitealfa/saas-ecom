<?php

namespace App\Concerns;

use App\Models\Central\Media as CentralMedia;
use App\Models\Tenant\Media as TenantMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/** @template TMedia of CentralMedia|TenantMedia */
trait HasProfilePhoto
{
    /** @return MorphOne<TMedia, $this> */
    public function profilePhoto(): MorphOne
    {
        /** @var class-string<TMedia> $model */
        $model = $this->getMorphClass() === 'central_user' ? CentralMedia::class : TenantMedia::class;

        return $this->morphOne($model, 'model', 'model_type', 'model_id')
            ->where('collection_name', 'profile_photo')->where('is_primary', true);
    }

    /** @return Attribute<string, never> */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $default = asset('default-avatar.svg');
            $central = $this->getMorphClass() === 'central_user';
            $route = $central ? 'central.profile-photo.show' : 'tenant.profile-photo.show';
            if (! $this->exists || $central === tenancy()->initialized || ! Route::has($route)) {
                return $default;
            }
            $photo = $this->profilePhoto;

            return $photo === null ? $default : URL::signedRoute($route, ['user' => $this, 'v' => $photo->uuid], absolute: false);
        });
    }
}

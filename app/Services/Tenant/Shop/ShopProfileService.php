<?php

namespace App\Services\Tenant\Shop;

use App\DTOs\Tenant\Shop\ShopSettings;
use App\Models\Tenant\Media\Media;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ShopProfileService
{
    private const array EDITABLE = [
        'description', 'about', 'business_type', 'contact_email', 'contact_phone',
        'contact_whatsapp', 'locale', 'timezone', 'theme_code', 'colors', 'cart_lifetime_days',
    ];

    public function __construct(private ShopProfileCatalog $catalog) {}

    public function shop(): Shop
    {
        abort_unless(tenancy()->initialized, 403);
        $shop = Shop::query()->where('singleton', 1)->sole();
        abort_unless($shop->tenant_uuid === tenant()->uuid, 403);

        return $shop;
    }

    public function token(Shop $shop): string
    {
        return hash('sha256', json_encode($shop->only([...self::EDITABLE, 'logo_media_id', 'favicon_media_id']), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, array $data, ?UploadedFile $logo, ?UploadedFile $favicon): void
    {
        $written = [];
        try {
            DB::connection('tenant')->transaction(function () use ($actor, $data, $logo, $favicon, &$written): void {
                $shop = Shop::query()->where('singleton', 1)->lockForUpdate()->firstOrFail();
                abort_unless($shop->tenant_uuid === tenant()->uuid, 403);
                Gate::forUser($actor)->authorize('update', $shop);

                if (! hash_equals($this->token($shop), (string) $data['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'Profil modifié entre-temps. Rechargez la page.']);
                }
                $settings = ShopSettings::fromArray(array_intersect_key($data, array_flip([
                    'business_type', 'theme_code', 'colors', 'cart_lifetime_days',
                ])))->toArray();
                $values = array_intersect_key($data, array_flip(self::EDITABLE));
                $values = array_replace($values, $settings);

                if ($logo !== null) {
                    Gate::forUser($actor)->authorize('branding', $shop);
                    $values['logo_media_id'] = $this->storeBranding($shop, $actor, $logo, 'shop-logo', $written);
                }
                if ($favicon !== null) {
                    Gate::forUser($actor)->authorize('branding', $shop);
                    $values['favicon_media_id'] = $this->storeBranding($shop, $actor, $favicon, 'shop-favicon', $written);
                }
                $shop->forceFill($values)->save();
                activity()->causedBy($actor)->performedOn($shop)->event('shop_profile_updated')
                    ->withProperties(['changed_fields' => array_keys($values)])->log('Profil de la boutique modifié.');
                $this->catalog->invalidateAfterCommit($shop);
            }, 3);
        } catch (Throwable $exception) {
            foreach ($written as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    /** @param list<string> $written */
    private function storeBranding(Shop $shop, User $actor, UploadedFile $file, string $collection, array &$written): int
    {
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw ValidationException::withMessages([$collection => 'Format non autorisé.']),
        };
        $size = getimagesize($file->getRealPath());
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] > 5000 || $size[1] > 5000) {
            throw ValidationException::withMessages([$collection => 'Dimensions de l’image invalides.']);
        }
        $folder = 'shops/'.tenant()->uuid.'/branding';
        $path = $file->storeAs($folder, (string) Str::uuid().'.'.$extension, 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages([$collection => 'Enregistrement impossible.']);
        }
        $written[] = $path;

        // Preserve old records and files for history; retire their primary flag first.
        Media::query()->where('model_type', $shop->getMorphClass())->where('model_id', $shop->id)
            ->where('collection_name', $collection)->where('is_primary', true)->update(['is_primary' => false]);

        $media = new Media;
        $media->forceFill([
            'created_by_id' => $actor->id,
            'storage_key' => $path,
            'model_id' => $shop->id,
            'model_type' => $shop->getMorphClass(),
            'collection_name' => $collection,
            'disk' => 'local',
            'mime_type' => $mime,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
            'size_bytes' => $file->getSize(),
            'width' => $size[0],
            'height' => $size[1],
            'visibility' => 1,
            'position' => 0,
            'is_primary' => true,
            'file_hash' => hash_file('sha256', $file->getRealPath()),
        ])->save();

        return $media->id;
    }

    public function branding(string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, ['logo', 'favicon'], true), 404);
        $shop = $this->shop();
        $media = $kind === 'logo' ? $shop->logoMedia : $shop->faviconMedia;
        $collection = $kind === 'logo' ? 'shop-logo' : 'shop-favicon';
        abort_unless($media instanceof Media && ! $media->trashed()
            && $media->model_type === $shop->getMorphClass() && (int) $media->model_id === $shop->id
            && $media->collection_name === $collection && $media->disk === 'local'
            && in_array($media->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true)
            && Storage::disk('local')->exists($media->storage_key), 404);

        return Storage::disk('local')->response($media->storage_key, $kind, [
            'Content-Type' => $media->mime_type,
            'Content-Security-Policy' => "default-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}

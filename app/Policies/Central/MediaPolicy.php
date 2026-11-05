<?php

namespace App\Policies\Central;

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Billing\SaasInvoicePdfService;
use Illuminate\Support\Facades\Gate;

class MediaPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, Media $media): bool
    {
        return $this->viewAny($actor);
    }

    public function upload(User $actor): bool
    {
        return $this->has($actor, 'upload');
    }

    public function download(User $actor, Media $media): bool
    {
        if ($media->collection_name === SaasInvoicePdfService::COLLECTION) {
            $invoice = $media->model_type === 'saas_invoice' ? SaasInvoice::find($media->model_id) : null;

            return ! tenancy()->initialized && ! $media->trashed() && $media->visibility === MediaVisibilityEnum::PRIVATE
                && $invoice !== null && $invoice->getAttribute('document_media_id') === $media->id
                && Gate::forUser($actor)->allows('download', $invoice);
        }
        if ($media->trashed() || $media->visibility !== MediaVisibilityEnum::PRIVATE || ! $this->has($actor, 'download')) {
            return false;
        }

        if ($media->collection_name !== 'profile_photo') {
            return true;
        }

        if ($media->model_type !== 'central_user') {
            return false;
        }
        $owner = User::withTrashed()->find($media->model_id);

        return $owner !== null && Gate::forUser($actor)->allows('viewProfilePhoto', $owner);
    }

    public function archive(User $actor, Media $media): bool
    {
        return ! $media->trashed() && $media->isAdministrativeDocument() && $this->has($actor, 'archive');
    }

    private function has(User $actor, string $ability): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, "saas.media.$ability");
    }
}

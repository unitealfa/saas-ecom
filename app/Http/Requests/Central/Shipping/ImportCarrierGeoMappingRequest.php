<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportCarrierGeoMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('import', CarrierGeoMapping::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->route()?->getActionMethod() === 'confirm'
            ? ['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'file' => ['prohibited']]
            : ['carrier_uuid' => ['required', 'uuid'], 'file' => ['required', 'file', 'extensions:csv', 'max:1024']];
    }

    protected function getRedirectUrl(): string
    {
        return route('central.carrier-mappings.import');
    }
}

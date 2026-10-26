<?php

namespace App\Http\Requests\Central\Domains;

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Central\Domains\DomainService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreDomainRequest extends FormRequest
{
    /** Nom de domaine complet : étiquettes de 1 à 63 caractères, au moins un point, extension alphabétique ou punycode. */
    private const string HOST_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/D';

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $domain = $this->route('domain');

        return $actor instanceof User
            && Gate::forUser($actor)->allows($domain instanceof Domain ? 'update' : 'create', $domain ?? Domain::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $domain = $this->route('domain');
        $updating = $domain instanceof Domain;

        return [
            'domain' => [
                'bail', 'required', 'string', 'max:253',
                'regex:'.self::HOST_PATTERN,
                $this->validateHost(...),
                Rule::unique(Domain::class, 'domain')->ignore($domain),
            ],
            'tenant_uuid' => $updating
                ? ['prohibited']
                : ['required', 'uuid', Rule::exists(Tenant::class, 'uuid')->whereNull('deleted_at')],
            'expected_state' => $updating ? ['required', 'string', 'regex:/^[a-f0-9]{64}$/'] : ['prohibited'],
            'type' => ['prohibited'],
            'is_primary' => ['prohibited'],
            'tenant_id' => ['prohibited'],
            'verification_status' => ['prohibited'],
            'verified_at' => ['prohibited'],
            'certificate_status' => ['prohibited'],
            'uuid' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('domain');

        if (! is_string($value)) {
            return;
        }

        $host = rtrim(mb_strtolower(trim($value)), '.');

        if (function_exists('idn_to_ascii')) {
            $host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }

        $this->merge(['domain' => $host]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'domain.unique' => 'Ce domaine est déjà réservé, y compris s’il a été archivé.',
            'domain.required' => 'Saisissez le domaine à ajouter.',
            'domain.regex' => 'Saisissez un nom de domaine valide, par exemple boutique.exemple.com.',
            'tenant_uuid.required' => 'Choisissez une boutique.',
            'tenant_uuid.exists' => 'Cette boutique n’est plus disponible.',
            'expected_state.required' => 'Rechargez le formulaire avant de recommencer.',
        ];
    }

    /** Réutiliser les règles du service : les alias de boutiques restent autorisés sous la base SaaS. */
    private function validateHost(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            app(DomainService::class)->normalize($value);
        } catch (ValidationException $exception) {
            $fail($exception->errors()['domain'][0]);
        }
    }
}

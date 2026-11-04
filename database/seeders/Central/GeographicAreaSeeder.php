<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

class GeographicAreaSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Geographic references require the central context.');
        }

        $payload = $this->loadLocal();
        Validator::make($payload, [
            'wilayas' => ['required', 'array', 'list'],
            'wilayas.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'wilayas.*.code' => ['required', 'integer', 'min:1', 'distinct'],
            'wilayas.*.name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'wilayas.*.ar_name' => ['nullable', 'string', 'max:255'],
            'communes' => ['present', 'array', 'list'],
            'communes.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'communes.*.wilaya_id' => ['required', 'integer', 'min:1'],
            'communes.*.name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'communes.*.ar_name' => ['nullable', 'string', 'max:255'],
            'communes.*.post_code' => ['required', 'string', 'regex:/^[0-9]{4,5}$/D'],
            'metadata.source' => ['required', 'string', 'max:255'],
            'metadata.version' => ['required', 'string', 'max:255'],
            'metadata.effective_at' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        DB::transaction(function () use ($payload): void {
            $country = Country::where('code', 'DZ')->lockForUpdate()->firstOrFail();
            $existing = GeographicArea::withTrashed()->where('country_id', $country->id)
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn (GeographicArea $area): string => $area->type->value.':'.($area->parent_id ?? 0).':'.$area->code);
            $provinces = [];
            $seen = [];
            foreach ($payload['wilayas'] as $row) {
                $provinces[(int) $row['id']] = $this->storeArea($country, null, GeoZoneTypeEnum::PROVINCE, (string) $row['code'], $row, $payload['metadata'], $existing, $seen);
            }
            foreach ($payload['communes'] as $row) {
                $parent = $provinces[(int) $row['wilaya_id']] ?? null;
                if ($parent === null) {
                    throw ValidationException::withMessages(['import' => 'La wilaya d’une commune est introuvable.']);
                }
                $this->storeArea($country, $parent, GeoZoneTypeEnum::MUNICIPALITY, 'dz-'.$row['post_code'], $row, $payload['metadata'], $existing, $seen);
            }
            $created = count(array_diff_key($seen, $existing->all()));
            activity()->performedOn($country)->event('geography_imported')->withProperties([
                'created' => $created, 'ignored' => count($seen) - $created, 'conflicts' => [],
                'counts' => ['wilayas' => count($payload['wilayas']), 'communes' => count($payload['communes'])],
            ])->log('Référentiel géographique chargé par le seeder.');
        }, 3);
    }

    /** @return array<string, mixed> */
    public function loadLocal(): array
    {
        $payload = ['metadata' => config('central.geography.reference')];
        $hash = hash_init('sha256');
        foreach (['wilayas' => 'Wilaya_Of_Algeria.json', 'communes' => 'Commune_Of_Algeria.json'] as $name => $file) {
            $contents = @file_get_contents(database_path('data/geography/dz/'.$file));
            if ($contents === false) {
                throw ValidationException::withMessages(['import' => 'Impossible de lire les fichiers géographiques locaux.']);
            }
            hash_update($hash, $contents);
            try {
                $payload[$name] = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['import' => "Le fichier {$file} est invalide."]);
            }
        }
        $payload['metadata']['version'] = 'sha256:'.hash_final($hash);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{source: string, version: string, effective_at: ?string}  $metadata
     * @param  Collection<string, GeographicArea>  $existing
     * @param  array<string, true>  $seen
     */
    private function storeArea(Country $country, ?GeographicArea $parent, GeoZoneTypeEnum $type, string $code, array $row, array $metadata, Collection $existing, array &$seen): GeographicArea
    {
        $key = $type->value.':'.($parent?->id ?? 0).':'.$code;
        if (isset($seen[$key])) {
            throw ValidationException::withMessages(['import' => "Code géographique dupliqué : {$code}."]);
        }
        $seen[$key] = true;
        $values = [
            'name_fr' => trim($row['name']),
            'name_ar' => filled($row['ar_name'] ?? null) ? trim($row['ar_name']) : null,
            'reference_source' => $metadata['source'],
            'reference_version' => $metadata['version'],
            'effective_at' => $metadata['effective_at'],
        ];
        $area = $existing->get($key);
        if ($area !== null) {
            foreach ($values as $field => $value) {
                $current = $field === 'effective_at' ? $area->effective_at?->format('Y-m-d') : $area->getAttribute($field);
                if ($current !== $value) {
                    throw ValidationException::withMessages(['import' => "Code, nom, parent ou version en conflit : {$code}."]);
                }
            }

            return $area;
        }
        if ($parent?->trashed()) {
            throw ValidationException::withMessages(['import' => "La wilaya parente est archivée : {$code}."]);
        }
        $area = new GeographicArea;
        $area->forceFill([...$values, 'country_id' => $country->id, 'parent_id' => $parent?->id,
            'type' => $type, 'code' => $code, 'is_active' => $parent?->is_active ?? true])->save();

        return $area;
    }
}

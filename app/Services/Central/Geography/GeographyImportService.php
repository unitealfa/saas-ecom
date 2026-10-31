<?php

namespace App\Services\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

class GeographyImportService
{
    private const string EVENT_IMPORTED = 'geography_imported';

    public function __construct(private Factory $validator) {}

    /** @return array<string, mixed> */
    public function loadLocal(): array
    {
        $payload = ['metadata' => config('geography.reference')];
        $hash = hash_init('sha256');
        foreach (['wilayas' => 'Wilaya_Of_Algeria.json', 'communes' => 'Commune_Of_Algeria.json'] as $name => $file) {
            $contents = file_get_contents(database_path("data/geography/dz/{$file}"));
            if ($contents === false) {
                $this->fail('Impossible de lire les fichiers géographiques locaux.');
            }
            hash_update($hash, $contents);
            try {
                $payload[$name] = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->fail("Le fichier {$file} est invalide.");
            }
        }
        $payload['metadata']['source'] = 'local-json:database/data/geography/dz';
        $payload['metadata']['version'] = 'sha256:'.hash_final($hash);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{metadata: array{source: string, version: string, effective_at: ?string}, wilayas: list<array<string, mixed>>, communes: list<array<string, mixed>>}
     */
    public function validatePayload(array $payload): array
    {
        if (! isset($payload['metadata'])) {
            $payload['metadata'] = [];
        }
        if (is_array($payload['metadata'])) {
            $payload['metadata'] += ['source' => 'seed-json',
                'version' => 'sha256:'.hash('sha256', json_encode([$payload['wilayas'] ?? null, $payload['communes'] ?? null], JSON_THROW_ON_ERROR)),
                'effective_at' => null];
        }
        $data = $this->validator->make($payload, [
            'metadata' => ['required', 'array:source,version,effective_at'],
            'metadata.source' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'metadata.version' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'metadata.effective_at' => ['nullable', 'date_format:Y-m-d'],
            'wilayas' => ['required', 'array', 'list', 'min:1', 'max:10000'],
            'wilayas.*' => ['required', 'array'],
            'wilayas.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'wilayas.*.code' => ['required', 'integer', 'min:1'],
            'wilayas.*.name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'wilayas.*.ar_name' => ['nullable', 'string', 'max:255'],
            'communes' => ['present', 'array', 'list', 'max:100000'],
            'communes.*' => ['required', 'array'],
            'communes.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'communes.*.wilaya_id' => ['required', 'integer', 'min:1'],
            'communes.*.name' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'communes.*.ar_name' => ['nullable', 'string', 'max:255'],
            'communes.*.post_code' => ['required', 'string', 'regex:/^[0-9]{4,5}$/D'],
        ])->validate();

        $wilayas = [];
        $communes = [];
        $codes = [];
        foreach ($data['wilayas'] as $row) {
            $code = trim((string) $row['code']);
            $name = trim($row['name']);
            $key = mb_strtolower($code);
            if (isset($codes[$key])) {
                $this->fail("Wilaya dupliquée : {$code}.");
            }
            $codes[$key] = true;
            $wilayas[(int) $row['id']] = ['id' => (int) $row['id'], 'code' => $code, 'name' => $name, 'name_ar' => $this->arabic($row)];
        }
        $codes = [];
        foreach ($data['communes'] as $row) {
            $parent = (int) $row['wilaya_id'];
            if (! isset($wilayas[$parent])) {
                $this->fail("Wilaya source introuvable pour la commune {$row['id']}.");
            }
            $name = trim($row['name']);
            $code = 'dz-'.$row['post_code'];
            $key = $parent.':'.$code;
            if (isset($codes[$key])) {
                $this->fail("Code postal de commune dupliqué dans la même wilaya : {$code}.");
            }
            $codes[$key] = true;
            $communes[] = ['id' => (int) $row['id'], 'code' => $code, 'wilaya_id' => $parent, 'name' => $name, 'name_ar' => $this->arabic($row)];
        }

        return ['metadata' => ['source' => trim($data['metadata']['source']), 'version' => trim($data['metadata']['version']), 'effective_at' => $data['metadata']['effective_at'] ?? null], 'wilayas' => array_values($wilayas), 'communes' => $communes];
    }

    /** @param array<string, mixed> $payload
     * @return array{created: int, ignored: int, conflicts: list<string>, counts: array{wilayas: int, communes: int}}
     */
    public function import(array $payload): array
    {
        $this->ensureCentralContext();
        $data = $this->validatePayload($payload);

        return (new GeographicArea)->getConnection()->transaction(function () use ($data): array {
            $country = Country::where('code', 'DZ')->lockForUpdate()->first();
            if ($country === null) {
                $this->fail('Le pays DZ doit être créé par CountrySeeder avant l’import.');
            }
            $plan = $this->plan($data, $country);
            if ($plan['report']['conflicts'] !== []) {
                $this->fail(implode(' ', $plan['report']['conflicts']));
            }
            $parents = [];
            foreach (['wilayas', 'communes'] as $group) {
                foreach ($data[$group] as $row) {
                    $type = $group === 'wilayas' ? GeoZoneTypeEnum::PROVINCE : GeoZoneTypeEnum::MUNICIPALITY;
                    $parent = $group === 'communes' ? $parents[$row['wilaya_id']] : null;
                    $area = $plan['existing'][$type->value.':'.($parent->id ?? 0).':'.$row['code']] ?? null;
                    if ($area === null) {
                        $area = new GeographicArea;
                        $area->fill(['code' => $row['code'], 'name_fr' => $row['name'], 'name_ar' => $row['name_ar'],
                            'reference_source' => $data['metadata']['source'], 'reference_version' => $data['metadata']['version'], 'effective_at' => $data['metadata']['effective_at']]);
                        $area->forceFill(['country_id' => $country->id, 'type' => $type, 'parent_id' => $parent?->id, 'is_active' => $parent === null ? true : $parent->is_active])->save();
                    }
                    if ($group === 'wilayas') {
                        $parents[$row['id']] = $area;
                    }
                }
            }
            activity()->performedOn($country)->event(self::EVENT_IMPORTED)->withProperties($plan['report'])
                ->log('Référentiel géographique chargé par le seeder.');

            return $plan['report'];
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array{report: array{created: int, ignored: int, conflicts: list<string>, counts: array{wilayas: int, communes: int}}, existing: array<string, GeographicArea>}
     */
    private function plan(array $data, Country $country): array
    {
        $areas = GeographicArea::withTrashed()->where('country_id', $country->id)->orderBy('id')->lockForUpdate()->get();
        $existing = [];
        $conflicts = [];
        foreach ($areas as $area) {
            $key = $area->type->value.':'.($area->parent_id ?? 0).':'.$area->code;
            if (isset($existing[$key])) {
                $conflicts[] = "Code existant ambigu : {$area->code}.";
            }
            $existing[$key] = $area;
        }
        $report = ['created' => 0, 'ignored' => 0, 'conflicts' => $conflicts,
            'counts' => ['wilayas' => count($data['wilayas']), 'communes' => count($data['communes'])]];
        $parents = array_column($data['wilayas'], 'code', 'id');
        foreach (['wilayas', 'communes'] as $group) {
            foreach ($data[$group] as $row) {
                $type = $group === 'wilayas' ? GeoZoneTypeEnum::PROVINCE : GeoZoneTypeEnum::MUNICIPALITY;
                $parent = $group === 'communes' ? ($existing[GeoZoneTypeEnum::PROVINCE->value.':0:'.$parents[$row['wilaya_id']]] ?? null) : null;
                $area = $existing[$type->value.':'.($parent->id ?? 0).':'.$row['code']] ?? null;
                if ($area === null) {
                    if ($parent?->trashed()) {
                        $report['conflicts'][] = "Nom ou parent existant incompatible : {$row['code']}.";
                    } else {
                        $report['created']++;
                    }

                    continue;
                }
                $matches = $area->name_fr === $row['name'] && $area->name_ar === $row['name_ar']
                    && $area->parent_id === $parent?->id
                    && $area->reference_source === $data['metadata']['source']
                    && $area->reference_version === $data['metadata']['version']
                    && $area->effective_at?->format('Y-m-d') === $data['metadata']['effective_at'];
                if (! $matches) {
                    $report['conflicts'][] = "Code, nom, parent ou version en conflit : {$row['code']}.";
                } else {
                    $report['ignored']++;
                }
            }
        }

        return ['report' => $report, 'existing' => $existing];
    }

    /** @param array<string, mixed> $row */
    private function arabic(array $row): ?string
    {
        $name = trim($row['ar_name'] ?? '');

        return $name === '' ? null : $name;
    }

    private function fail(string $message): never
    {
        $validator = $this->validator->make([], []);
        $validator->errors()->add('import', $message);

        throw new ValidationException($validator);
    }

    private function ensureCentralContext(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('L’import géographique doit être exécuté dans le contexte central.');
        }
    }
}

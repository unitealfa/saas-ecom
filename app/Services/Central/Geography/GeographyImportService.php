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

    private const string COUNTRY_CODE = 'DZ';

    private const int ATTEMPTS = 3;

    private const string LOCAL_DIRECTORY = 'data/geography/dz';

    private const array LOCAL_FILES = ['wilayas' => 'Wilaya_Of_Algeria.json', 'communes' => 'Commune_Of_Algeria.json'];

    public function __construct(private readonly Factory $validator) {}

    /** @return array<string, mixed> */
    public function loadLocal(): array
    {
        $payload = ['metadata' => config('geography.reference')];
        $hash = hash_init('sha256');

        foreach (self::LOCAL_FILES as $name => $file) {
            $contents = @file_get_contents(database_path(self::LOCAL_DIRECTORY.'/'.$file));

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

        // La version est l'empreinte des fichiers : elle change dès qu'une donnée change.
        $payload['metadata']['version'] = 'sha256:'.hash_final($hash);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{metadata: array{source: string, version: string, effective_at: ?string}, wilayas: list<array<string, mixed>>, communes: list<array<string, mixed>>}
     */
    public function validatePayload(array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        if (is_array($metadata)) {
            $payload['metadata'] = $metadata + [
                'source' => 'seed-json',
                'version' => 'sha256:'.hash('sha256', json_encode([$payload['wilayas'] ?? null, $payload['communes'] ?? null], JSON_THROW_ON_ERROR)),
                'effective_at' => null,
            ];
        }

        $data = $this->validator->make($payload, $this->rules())->validate();

        $wilayas = $this->normalizeWilayas($data['wilayas']);

        return [
            'metadata' => [
                'source' => trim($data['metadata']['source']),
                'version' => trim($data['metadata']['version']),
                'effective_at' => $data['metadata']['effective_at'] ?? null,
            ],
            'wilayas' => array_values($wilayas),
            'communes' => $this->normalizeCommunes($data['communes'], $wilayas),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: int, ignored: int, conflicts: list<string>, counts: array{wilayas: int, communes: int}}
     */
    public function import(array $payload): array
    {
        $this->ensureCentralContext();
        $data = $this->validatePayload($payload);

        return (new GeographicArea)->getConnection()->transaction(function () use ($data): array {
            $country = Country::where('code', self::COUNTRY_CODE)->lockForUpdate()->first()
                ?? $this->fail('Le pays DZ doit être créé par CountrySeeder avant l’import.');

            ['report' => $report, 'existing' => $existing] = $this->plan($data, $country);

            if ($report['conflicts'] !== []) {
                $this->fail(implode(' ', $report['conflicts']));
            }

            $this->createMissing($data, $country, $existing);

            activity()->performedOn($country)->event(self::EVENT_IMPORTED)->withProperties($report)
                ->log('Référentiel géographique chargé par le seeder.');

            return $report;
        }, self::ATTEMPTS);
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
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
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array{id: int, code: string, name: string, name_ar: ?string}> Indexé par id source.
     */
    private function normalizeWilayas(array $rows): array
    {
        $wilayas = [];
        $seen = [];

        foreach ($rows as $row) {
            $code = trim((string) $row['code']);
            $key = mb_strtolower($code);

            if (isset($seen[$key])) {
                $this->fail("Wilaya dupliquée : {$code}.");
            }

            $seen[$key] = true;
            $wilayas[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'code' => $code,
                'name' => trim($row['name']),
                'name_ar' => $this->arabic($row),
            ];
        }

        return $wilayas;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $wilayas
     * @return list<array{id: int, code: string, wilaya_id: int, name: string, name_ar: ?string}>
     */
    private function normalizeCommunes(array $rows, array $wilayas): array
    {
        $communes = [];
        $seen = [];

        foreach ($rows as $row) {
            $parent = (int) $row['wilaya_id'];

            if (! isset($wilayas[$parent])) {
                $this->fail("Wilaya source introuvable pour la commune {$row['id']}.");
            }

            $code = 'dz-'.$row['post_code'];
            $key = $parent.':'.$code;

            if (isset($seen[$key])) {
                $this->fail("Code postal de commune dupliqué dans la même wilaya : {$code}.");
            }

            $seen[$key] = true;
            $communes[] = [
                'id' => (int) $row['id'],
                'code' => $code,
                'wilaya_id' => $parent,
                'name' => trim($row['name']),
                'name_ar' => $this->arabic($row),
            ];
        }

        return $communes;
    }

    /**
     * Compare le fichier à la base sans rien écrire : compte les créations, les lignes déjà à jour et les conflits.
     *
     * @param  array<string, mixed>  $data
     * @return array{report: array{created: int, ignored: int, conflicts: list<string>, counts: array{wilayas: int, communes: int}}, existing: array<string, GeographicArea>}
     */
    private function plan(array $data, Country $country): array
    {
        $existing = [];
        $conflicts = [];

        foreach (GeographicArea::withTrashed()->where('country_id', $country->id)->orderBy('id')->lockForUpdate()->get() as $area) {
            $key = $this->key($area->type, $area->parent_id, $area->code);

            if (isset($existing[$key])) {
                $conflicts[] = "Code existant ambigu : {$area->code}.";
            }

            $existing[$key] = $area;
        }

        $report = [
            'created' => 0,
            'ignored' => 0,
            'conflicts' => $conflicts,
            'counts' => ['wilayas' => count($data['wilayas']), 'communes' => count($data['communes'])],
        ];

        $provinceCodes = array_column($data['wilayas'], 'code', 'id');

        foreach ($this->rows($data) as [$type, $row]) {
            $parent = $type === GeoZoneTypeEnum::MUNICIPALITY
                ? ($existing[$this->key(GeoZoneTypeEnum::PROVINCE, null, $provinceCodes[$row['wilaya_id']])] ?? null)
                : null;

            $area = $existing[$this->key($type, $parent?->id, $row['code'])] ?? null;

            if ($area === null) {
                if ($parent?->trashed()) {
                    $report['conflicts'][] = "Nom ou parent existant incompatible : {$row['code']}.";
                } else {
                    $report['created']++;
                }
            } elseif ($this->matches($area, $row, $parent, $data['metadata'])) {
                $report['ignored']++;
            } else {
                $report['conflicts'][] = "Code, nom, parent ou version en conflit : {$row['code']}.";
            }
        }

        return ['report' => $report, 'existing' => $existing];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, GeographicArea>  $existing
     */
    private function createMissing(array $data, Country $country, array $existing): void
    {
        $provinces = []; // id source de la wilaya => zone en base

        foreach ($this->rows($data) as [$type, $row]) {
            $parent = $type === GeoZoneTypeEnum::MUNICIPALITY ? $provinces[$row['wilaya_id']] : null;

            $area = $existing[$this->key($type, $parent?->id, $row['code'])]
                ?? $this->createArea($data['metadata'], $country, $type, $parent, $row);

            if ($type === GeoZoneTypeEnum::PROVINCE) {
                $provinces[$row['id']] = $area;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $row
     */
    private function createArea(array $metadata, Country $country, GeoZoneTypeEnum $type, ?GeographicArea $parent, array $row): GeographicArea
    {
        $area = new GeographicArea;
        $area->fill([
            'code' => $row['code'],
            'name_fr' => $row['name'],
            'name_ar' => $row['name_ar'],
            'reference_source' => $metadata['source'],
            'reference_version' => $metadata['version'],
            'effective_at' => $metadata['effective_at'],
        ]);
        $area->forceFill([
            'country_id' => $country->id,
            'type' => $type,
            'parent_id' => $parent?->id,
            'is_active' => $parent?->is_active ?? true,
        ])->save();

        return $area;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $metadata
     */
    private function matches(GeographicArea $area, array $row, ?GeographicArea $parent, array $metadata): bool
    {
        return $area->name_fr === $row['name']
            && $area->name_ar === $row['name_ar']
            && $area->parent_id === $parent?->id
            && $area->reference_source === $metadata['source']
            && $area->reference_version === $metadata['version']
            && $area->effective_at?->format('Y-m-d') === $metadata['effective_at'];
    }

    /**
     * Wilayas d'abord, puis communes : une commune est toujours traitée après sa wilaya.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{GeoZoneTypeEnum, array<string, mixed>}>
     */
    private function rows(array $data): array
    {
        return [
            ...array_map(fn (array $row): array => [GeoZoneTypeEnum::PROVINCE, $row], $data['wilayas']),
            ...array_map(fn (array $row): array => [GeoZoneTypeEnum::MUNICIPALITY, $row], $data['communes']),
        ];
    }

    private function key(GeoZoneTypeEnum $type, ?int $parentId, string $code): string
    {
        return $type->value.':'.($parentId ?? 0).':'.$code;
    }

    /** @param array<string, mixed> $row */
    private function arabic(array $row): ?string
    {
        $name = trim($row['ar_name'] ?? '');

        return $name === '' ? null : $name;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['import' => $message]);
    }

    private function ensureCentralContext(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('L’import géographique doit être exécuté dans le contexte central.');
        }
    }
}
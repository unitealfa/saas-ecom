<?php

namespace Database\Seeders\Central;

use App\Services\Central\Geography\GeographyImportService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;
use JsonException;

class GeographicAreaSeeder extends Seeder
{
    private const string LOCAL_DIRECTORY = 'data/geography/dz';

    private const array LOCAL_FILES = [
        'wilayas' => 'Wilaya_Of_Algeria.json',
        'communes' => 'Commune_Of_Algeria.json',
    ];

    public function run(GeographyImportService $importer): void
    {
        $importer->import($this->loadLocal());
    }

    private function loadLocal(): array
    {
        $payload = [
            'metadata' => config('central.geography.reference'),
        ];

        $hash = hash_init('sha256');

        foreach (self::LOCAL_FILES as $name => $file) {
            $contents = @file_get_contents(
                database_path(self::LOCAL_DIRECTORY.'/'.$file)
            );

            if ($contents === false) {
                throw ValidationException::withMessages([
                    'import' => 'Impossible de lire les fichiers géographiques locaux.',
                ]);
            }

            hash_update($hash, $contents);

            try {
                $payload[$name] = json_decode(
                    $contents,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    'import' => "Le fichier {$file} est invalide.",
                ]);
            }
        }

        $payload['metadata']['version'] = 'sha256:'.hash_final($hash);

        return $payload;
    }
}
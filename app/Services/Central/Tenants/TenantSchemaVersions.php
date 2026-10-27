<?php

namespace App\Services\Central\Tenants;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class TenantSchemaVersions
{
    /** @return list<string> */
    public function files(): array
    {
        $files = glob(database_path('migrations/tenant/*.php')) ?: [];
        sort($files);

        return $files;
    }

    public function latest(): string
    {
        $files = $this->files();

        return $files === [] ? throw new LogicException('Tenant migrations are missing.')
            : pathinfo($files[array_key_last($files)], PATHINFO_FILENAME);
    }

    /** @return list<string> */
    public function through(string $version): array
    {
        $files = $this->files();
        $names = array_map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), $files);
        $position = array_search($version, $names, true);

        if ($position === false) {
            throw ValidationException::withMessages(['target_version' => 'Cette version n’est pas prévue dans le code déployé.']);
        }

        return array_slice($files, 0, $position + 1);
    }

    /** @return array<string, string> */
    public function runtime(): array
    {
        return [
            'php' => PHP_VERSION, 'laravel' => app()->version(),
            'tenancy' => InstalledVersions::getPrettyVersion('stancl/tenancy') ?? 'unknown',
            'mysql' => (string) DB::selectOne('SELECT VERSION() AS version')->version,
            'application' => $this->latest(),
        ];
    }
}

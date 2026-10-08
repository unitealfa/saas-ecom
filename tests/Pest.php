<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/DocumentedConstraintAssertions.php';

function isolateLocalFixtureStorage(): void
{
    $original = app()->storagePath();
    $path = $original.'/framework/testing/local-fixtures-'.Str::uuid();
    config(['testing.local_fixture_storage' => ['original' => $original, 'path' => $path]]);
    app()->useStoragePath($path);
    config(['filesystems.disks.local.root' => $path.'/app/private']);
    Storage::forgetDisk('local');
}

function cleanLocalFixtureStorage(): void
{
    $paths = config('testing.local_fixture_storage');
    app()->useStoragePath($paths['original']);
    $resolved = realpath($paths['path']);
    $parent = realpath($paths['original'].'/framework/testing');
    if ($resolved !== false) {
        if ($parent === false || ! str_starts_with(strtolower($resolved), strtolower($parent).DIRECTORY_SEPARATOR.'local-fixtures-')) {
            throw new LogicException('Refusing to delete storage outside the isolated fixture test directory.');
        }
        File::deleteDirectory($resolved);
    }
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Central/Feature');

pest()->extend(TestCase::class)->in('Tenant');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * @return array<string, array<string, array{type: string, nullable: bool, keys: list<string>, note: string}>>
 */
function documentedDatabaseTables(string $context, bool $mainSchema = false): array
{
    $directory = base_path('documentation et recherche');
    if ($mainSchema) {
        $document = file_get_contents($directory.'/Schema-BDD-SaaS-Ecommerce-UUID.md');
        $boundary = strpos($document, '## 5.');
        $document = $context === 'central' ? substr($document, 0, $boundary) : substr($document, $boundary);
    } else {
        $filename = $context === 'central' ? 'Diagramme-BDD-Centrale-Complet.md' : 'Diagramme-BDD-Boutique-Complet.md';
        $document = file_get_contents($directory.'/'.$filename);
    }

    preg_match_all('/```mermaid\s*\r?\n(.*?)```/s', $document, $blocks);
    $tables = [];
    foreach ($blocks[1] as $block) {
        preg_match_all('/^\s*([a-z][a-z0-9_]*)\s*\{\s*\r?\n(.*?)^\s*\}/ms', $block, $definitions, PREG_SET_ORDER);
        foreach ($definitions as $definition) {
            $columns = [];
            foreach (preg_split('/\r?\n/', trim($definition[2])) as $line) {
                if (! preg_match('/^(\S+)\s+(\w+)(?:\s+((?:PK|FK|UK)(?:,\s*(?:PK|FK|UK))?))?(?:\s+"(.*)")?$/', trim($line), $field)) {
                    throw new LogicException('Invalid documented field: '.$line);
                }

                $note = $field[4] ?? '';
                $columns[$field[2]] = [
                    'type' => strtr($field[1], ['bigint_unsigned' => 'u64', 'tinyint_unsigned' => 'u8', 'smallint_unsigned' => 'u16']),
                    'nullable' => $mainSchema ? str_contains($note, 'nullable') || str_contains($note, 'sinon NULL') : str_contains($note, '?'),
                    'keys' => isset($field[3]) && $field[3] !== '' ? preg_split('/,\s*/', $field[3]) : [],
                    'note' => $note,
                ];
            }
            $tables[$definition[1]] = $columns;
        }
    }

    return $tables;
}

function assertDocumentedDatabaseSchema(string $context): void
{
    $tables = documentedDatabaseTables($context);
    $main = documentedDatabaseTables($context, true);
    $schema = Schema::getFacadeRoot();
    $driver = $schema->getConnection()->getDriverName();
    $technical = ['migrations', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
    $technical = array_merge($technical, $context === 'central' ? ['passkeys'] : ['tenants', 'domains']);
    $databaseSchema = $driver === 'mysql' ? $schema->getConnection()->getDatabaseName() : null;
    $actualTables = array_column($schema->getTables($databaseSchema), 'name');
    $expectedTables = array_merge(array_keys($tables), $technical);
    sort($actualTables);
    sort($expectedTables);
    expect($actualTables)->toBe($expectedTables);

    foreach ($tables as $table => $fields) {
        $expectedColumns = array_keys($fields);
        $mainColumns = array_keys($main[$table]);
        sort($expectedColumns);
        sort($mainColumns);
        expect($mainColumns)->toBe($expectedColumns);

        $actual = array_column($schema->getColumns($table), null, 'name');
        $extras = $table === 'users' && $context === 'central' ? ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] : [];
        if ($table === 'domains' && $context === 'central') {
            $extras[] = 'primary_slot';
        }
        $expectedColumns = array_merge($expectedColumns, $extras);
        $actualColumns = array_keys($actual);
        sort($expectedColumns);
        sort($actualColumns);
        expect($actualColumns)->toBe($expectedColumns);

        $indexes = $schema->getIndexes($table);
        foreach ($fields as $column => $field) {
            expect($field['type'])->toBe($main[$table][$column]['type']);
            expect($field['nullable'])->toBe($main[$table][$column]['nullable']);
            expect($actual[$column]['nullable'])->toBe($field['nullable'], $context.'.'.$table.'.'.$column.' nullable');

            if ($driver === 'mysql' && in_array('FK', $field['keys'], true)) {
                expect($actual[$column]['auto_increment'])->toBeFalse($context.'.'.$table.'.'.$column.' must reference an existing ID');
            }
            if ($driver === 'mysql' && $column === 'id' && in_array('PK', $field['keys'], true)) {
                expect($actual[$column]['auto_increment'])->toBeTrue($context.'.'.$table.'.id generates the record identity');
            }

            if (in_array('UK', $field['keys'], true) && ! ($table === 'roles' && $column === 'permission_signature')) {
                expect(collect($indexes)->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === [$column]))->toBeTrue();
            }

            if (str_contains($main[$table][$column]['note'], 'generated')) {
                expect($actual[$column]['generation'])->not->toBeNull();
            }

            if ($driver === 'mysql') {
                $type = $actual[$column]['type'];
                $expectedType = match ($field['type']) {
                    'u64' => 'bigint unsigned',
                    'u16' => 'smallint unsigned',
                    'u8' => 'tinyint unsigned',
                    'uuid' => 'char(36)',
                    'boolean' => 'tinyint(1)',
                    'datetime' => 'datetime',
                    'int' => 'int',
                    'bigint' => 'bigint',
                    'tinyint' => 'tinyint',
                    'varchar' => 'varchar(255)',
                    'decimal' => preg_match('/^(weight_kg|length_cm|width_cm|height_cm|content_quantity)$/', $column) ? 'decimal(14,3)' : 'decimal(14,2)',
                    default => $field['type'],
                };
                expect($type)->toBe($expectedType);
                if ($field['type'] === 'uuid') {
                    expect($actual[$column]['collation'])->toBe('ascii_bin');
                }
            }
        }

        $primary = collect($fields)->filter(fn (array $field): bool => in_array('PK', $field['keys'], true))->keys()->all();
        if ($table === 'model_has_roles') {
            $primary = ['role_id', 'model_id', 'model_type'];
        } elseif ($table === 'model_has_permissions') {
            $primary = ['permission_id', 'model_id', 'model_type'];
        }
        expect(collect($indexes)->firstWhere('primary', true)['columns'])->toBe($primary);
    }

    $filename = $context === 'central' ? 'Diagramme-BDD-Centrale-Complet.md' : 'Diagramme-BDD-Boutique-Complet.md';
    preg_match_all('/^(\w+)\s+\S+\s+(\w+)\s*:\s*"FK (\w+)"/m', file_get_contents(base_path('documentation et recherche/'.$filename)), $relations, PREG_SET_ORDER);
    foreach ($relations as $relation) {
        $foreignKeys = $schema->getForeignKeys($relation[2]);
        $foreign = collect($foreignKeys)->first(fn (array $foreign): bool => $foreign['columns'] === [$relation[3]] && $foreign['foreign_table'] === $relation[1] && $foreign['foreign_columns'] === ['id']);
        expect($foreign)->not->toBeNull($context.'.'.$relation[2].'.'.$relation[3].' must reference '.$relation[1].'.id');
        expect($foreign['on_delete'])->toBe('restrict');
        expect($foreign['on_update'])->toBe('restrict');
    }

    $document = file_get_contents(base_path('documentation et recherche/Schema-BDD-SaaS-Ecommerce-UUID.md'));
    $sections = $context === 'central' ? ['6.3', '6.7'] : ['6.1', '6.2'];
    foreach ($sections as $section) {
        preg_match('/^### '.preg_quote($section, '/').'[^\r\n]*\R(.*?)(?=^### |\z)/ms', $document, $content);
        foreach (preg_split('/\R/', $content[1]) as $line) {
            if (! str_starts_with($line, '|')) {
                continue;
            }
            $cells = explode('|', str_replace('`', '', $line));
            $parentCell = $section === '6.1' ? $cells[2] : $cells[1];
            $childCell = $section === '6.1' ? $cells[1] : ($cells[2] ?? '');
            if (! preg_match('/([a-z_]+)\(([^)]+)\)/', $parentCell, $parent)) {
                continue;
            }
            preg_match_all('/([a-z_]+)\(([^)]+)\)/', $childCell, $children, PREG_SET_ORDER);
            foreach ($children as $child) {
                if (! isset($tables[$parent[1]], $tables[$child[1]])) {
                    continue;
                }
                $parentColumns = explode(',', str_replace(' ', '', $parent[2]));
                $childColumns = array_map(function (string $column): string {
                    preg_match('/^\s*([a-z_]+)/', $column, $name);

                    return $name[1];
                }, explode(',', $child[2]));
                expect(collect($schema->getIndexes($parent[1]))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === $parentColumns))->toBeTrue();
                expect(collect($schema->getForeignKeys($child[1]))->contains(fn (array $foreign): bool => $foreign['columns'] === $childColumns && $foreign['foreign_table'] === $parent[1] && $foreign['foreign_columns'] === $parentColumns))->toBeTrue();
            }
        }
    }
}

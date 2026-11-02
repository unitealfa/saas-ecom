<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PickupPointImportService
{
    public const array HEADERS = ['external_code', 'name', 'province_uuid', 'municipality_uuid', 'address', 'phone', 'map_url', 'reference_source'];

    private const int MAX_ROWS = 500;

    private const int TTL_SECONDS = 900;

    /** La ligne 1 du fichier est l'en-tête : les données commencent à la ligne 2. */
    private const int FIRST_DATA_LINE = 2;

    private const array DELIMITERS = [',', ';'];

    public function __construct(private readonly PickupPointService $points) {}

    /** @return array<string, mixed> */
    public function preview(User $actor, string $carrierUuid, UploadedFile $file): array
    {
        $this->authorize($actor);

        $carrier = ShippingCarrier::query()->where('uuid', $carrierUuid)->first()
            ?? $this->fail('Sélectionnez un transporteur non archivé.');

        $rows = $this->read($file, $carrierUuid);
        $preview = $this->points->reviewRows($actor, $carrier, $rows);

        $token = bin2hex(random_bytes(32));

        Cache::put(
            $this->key($token),
            ['actor_id' => $actor->id, 'carrier_uuid' => $carrier->uuid, 'rows' => $preview],
            self::TTL_SECONDS,
        );

        return ['carrier' => $carrier, 'rows' => $preview, 'token' => $token];
    }

    /** @return array{created: int, updated: int, unchanged: int} */
    public function confirm(User $actor, string $token): array
    {
        $this->authorize($actor);

        $payload = $this->payloadFor($actor, $token)
            ?? $this->fail('Cet aperçu est expiré, déjà utilisé ou appartient à un autre compte. Recommencez l’import.');

        $result = $this->points->import($actor, $payload['carrier_uuid'], $payload['rows']);

        // L'aperçu est à usage unique : il n'est supprimé qu'une fois l'import validé.
        DB::afterCommit(fn () => Cache::forget($this->key($token)));

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function review(User $actor, string $token): ?array
    {
        $this->authorize($actor);

        $payload = $this->payloadFor($actor, $token);

        if ($payload === null) {
            return null;
        }

        $carrier = ShippingCarrier::query()->where('uuid', $payload['carrier_uuid'])->first();

        return $carrier === null ? null : ['carrier' => $carrier, 'rows' => $payload['rows'], 'token' => $token];
    }

    /** @return list<array<string, mixed>> */
    private function read(UploadedFile $file, string $carrierUuid): array
    {
        $handle = fopen($file->getPathname(), 'rb');

        if ($handle === false) {
            $this->fail('Le fichier ne peut pas être lu.');
        }

        try {
            $delimiter = $this->delimiter($handle);
            $rows = [];

            while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                if ($cells === [null]) {
                    continue; // ligne vide
                }

                if (count($rows) >= self::MAX_ROWS || count($cells) !== count(self::HEADERS)) {
                    $this->fail(sprintf('Fichier limité à %d lignes, avec exactement huit colonnes par ligne.', self::MAX_ROWS));
                }

                $rows[] = $this->validateRow($cells, $carrierUuid, count($rows) + self::FIRST_DATA_LINE);
            }

            if ($rows === []) {
                $this->fail('Le fichier ne contient aucun bureau.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lit l'en-tête et retourne le séparateur (virgule ou point-virgule) qui le reconnaît.
     *
     * @param  resource  $handle
     */
    private function delimiter($handle): string
    {
        $line = fgets($handle);

        if ($line === false) {
            $this->fail('Le fichier est vide.');
        }

        $header = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line)); // retire le BOM UTF-8

        foreach (self::DELIMITERS as $candidate) {
            if (str_getcsv($header, $candidate, '"', '') === self::HEADERS) {
                return $candidate;
            }
        }

        $this->fail('En-tête CSV invalide. Utilisez les colonnes indiquées, dans le même ordre.');
    }

    /**
     * @param  list<string|null>  $cells
     * @return array<string, mixed>
     */
    private function validateRow(array $cells, string $carrierUuid, int $line): array
    {
        $data = array_combine(self::HEADERS, array_map(static fn (?string $cell): string => trim($cell ?? ''), $cells));

        if (! mb_check_encoding($data, 'UTF-8')) {
            $this->fail('Le fichier doit utiliser l’encodage UTF-8.');
        }

        $validation = Validator::make([...$data, 'carrier_uuid' => $carrierUuid], PickupPointService::dataRules());

        if ($validation->fails()) {
            $this->fail("Ligne {$line} : {$validation->errors()->first()}");
        }

        $validated = $validation->validated();
        foreach (['carrier_uuid', 'province_uuid', 'municipality_uuid'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null) ? strtolower($validated[$field]) : null;
        }
        foreach (['municipality_uuid', 'phone', 'map_url'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null) ? $validated[$field] : null;
        }

        return $validated;
    }

    /** @return array<string, mixed>|null L'aperçu mis en cache, s'il existe et appartient à cet acteur. */
    private function payloadFor(User $actor, string $token): ?array
    {
        $payload = Cache::get($this->key($token));

        return is_array($payload) && ($payload['actor_id'] ?? null) === $actor->id ? $payload : null;
    }

    private function authorize(User $actor): void
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('import', PickupPoint::class);
    }

    /** La clé inclut l'identité de la base pour ne jamais mélanger deux environnements. */
    private function key(string $token): string
    {
        $connection = (new PickupPoint)->getConnection();

        return 'pickup-point-import:'.hash('sha256', json_encode([
            $connection->getConfig('host'),
            $connection->getConfig('port'),
            $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR)).':'.$token;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}

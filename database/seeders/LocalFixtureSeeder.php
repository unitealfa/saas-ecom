<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

abstract class LocalFixtureSeeder extends Seeder
{
    protected function assertLocalEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Local fixtures cannot run outside local or testing.');
        }
    }

    /** @param array<string, mixed> $attributes */
    protected function insert(Connection $connection, string $table, array $attributes, bool $updatedAt = true): int
    {
        $timestamps = ['created_at' => now()->format('Y-m-d H:i:s.u')];

        if ($updatedAt) {
            $timestamps['updated_at'] = $timestamps['created_at'];
        }

        return (int) $connection->table($table)->insertGetId([
            'uuid' => (string) Str::uuid(), ...$timestamps, ...$attributes,
        ]);
    }

    /** @param array<mixed> $value */
    protected function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    protected function pdf(Connection $connection, string $modelType, int $modelId, int $authorId, string $label): int
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $label);
        $stream = "BT /F1 14 Tf 40 780 Td (LOCAL TEST FIXTURE - NOT A REAL RECEIPT) Tj 0 -30 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        $key = 'local-fixtures/'.Str::uuid().'.pdf';

        if (! Storage::disk('local')->put($key, $pdf)) {
            throw new LogicException('Could not save the local PDF fixture.');
        }

        return $this->insert($connection, 'media', [
            'created_by_id' => $authorId, 'model_type' => $modelType, 'model_id' => $modelId,
            'storage_key' => $key, 'collection_name' => 'local-fixtures', 'disk' => 'local',
            'mime_type' => 'application/pdf', 'original_name' => 'local-test-document.pdf',
            'size_bytes' => strlen($pdf), 'visibility' => 2, 'position' => 0, 'is_primary' => false,
            'file_hash' => hash('sha256', $pdf),
        ]);
    }

    /** @return array<string, mixed> */
    protected function taxSnapshot(int $amount): array
    {
        return [
            'net_amount' => $amount.'.00', 'tax_amount' => '0.00', 'total_amount' => $amount.'.00',
            'taxes' => [[
                'code' => 'LOCAL_TEST_ZERO', 'nature' => 'fixture_only', 'base_ht' => $amount.'.00',
                'taux' => '0.00', 'montant' => '0.00', 'motif_exoneration' => 'Simulation locale, aucune valeur fiscale réelle.',
            ]],
        ];
    }
}

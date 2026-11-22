<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DDL MySQL is not transactional. Run with the application stopped and a backup.
        if (! Schema::hasTable('saas_payment_proof_claims')) {
            Schema::create('saas_payment_proof_claims', function (Blueprint $table): void {
                $table->char('proof_hash', 64)->charset('ascii')->collation('ascii_bin')->primary();
                $table->foreignId('payment_id')->constrained('saas_transfers')->restrictOnDelete();
            });
        }

        // Preserve historical rows. Claim both media hashes (original and readable PDF).
        // In case of historical duplicates, the first claim suffices to block new reuse.
        DB::statement("INSERT INTO saas_payment_proof_claims (proof_hash, payment_id)
            SELECT m.file_hash, MIN(p.id) FROM saas_transfers p JOIN media m
              ON (m.id = p.proof_media_id OR m.id = p.source_proof_media_id)
            WHERE p.record_type = 1 AND p.transfer_status IN (3,7)
              AND p.transfer_method <> 5 AND p.reversal_of_id IS NULL
              AND m.file_hash REGEXP '^[0-9a-f]{64}$'
            GROUP BY m.file_hash ON DUPLICATE KEY UPDATE payment_id = saas_payment_proof_claims.payment_id");

        // Old request images were sometimes converted to PDF before payment creation.
        // Only associate the initial image when the retained PDF matches the payment PDF.
        DB::statement("INSERT INTO saas_payment_proof_claims (proof_hash, payment_id)
            SELECT original.file_hash, MIN(p.id) FROM saas_transfers p
            JOIN subscription_requests r ON r.current_payment_id = p.id
            JOIN media payment_pdf ON payment_pdf.id = p.proof_media_id
            JOIN media request_pdf ON request_pdf.model_type = 'subscription_request'
              AND request_pdf.model_id = r.id AND request_pdf.collection_name = 'payment_proof'
              AND request_pdf.file_hash = payment_pdf.file_hash
            JOIN media original ON original.model_type = 'subscription_request'
              AND original.model_id = r.id AND original.collection_name = 'payment_source'
            WHERE p.record_type = 1 AND p.transfer_status IN (3,7)
              AND p.transfer_method <> 5 AND p.reversal_of_id IS NULL
              AND original.file_hash REGEXP '^[0-9a-f]{64}$'
            GROUP BY original.file_hash ON DUPLICATE KEY UPDATE payment_id = saas_payment_proof_claims.payment_id");

        // Replace the CHECK in a single ALTER. Keep the stronger rule for cash/refunds.
        DB::statement('ALTER TABLE saas_transfers DROP CHECK ck_saas_transfers_b78f7cba93, ADD CONSTRAINT ck_saas_transfers_verified_proof_v2 CHECK (transfer_status <> 3 OR (proof_media_id IS NOT NULL AND transaction_fingerprint IS NOT NULL AND occurred_at IS NOT NULL AND validated_by_id IS NOT NULL AND validated_at IS NOT NULL AND ((record_type = 1 AND transfer_method <> 5) OR (transfer_reference IS NOT NULL AND financial_account_key IS NOT NULL))))');
    }

    public function down(): void
    {
        // No fabricated historical bank data. Refuse an unsafe rollback.
        if (DB::table('saas_transfers')->where('transfer_status', 3)->where(function ($q): void {
            $q->whereNull('transfer_reference')->orWhereNull('financial_account_key');
        })->exists()) {
            throw new RuntimeException('Retour arrière interdit : des versements confirmés ne possèdent plus les anciennes données bancaires. Restaurer une sauvegarde cohérente.');
        }
        DB::statement('ALTER TABLE saas_transfers DROP CHECK ck_saas_transfers_verified_proof_v2, ADD CONSTRAINT ck_saas_transfers_b78f7cba93 CHECK (transfer_status <> 3 OR (proof_media_id IS NOT NULL AND transfer_reference IS NOT NULL AND financial_account_key IS NOT NULL AND transaction_fingerprint IS NOT NULL AND occurred_at IS NOT NULL AND validated_by_id IS NOT NULL AND validated_at IS NOT NULL))');
        Schema::dropIfExists('saas_payment_proof_claims');
    }
};

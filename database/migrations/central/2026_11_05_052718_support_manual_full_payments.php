<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE saas_transfers DROP CHECK ck_saas_transfers_829a434184');
        DB::statement('ALTER TABLE saas_transfers ADD CONSTRAINT ck_saas_transfers_829a434184 CHECK (transfer_method IN (1,2,3,4,5))');
        DB::statement("ALTER TABLE saas_transfers MODIFY transfer_method TINYINT UNSIGNED NOT NULL COMMENT 'TransferMethodEnum: 1 BANK_TRANSFER, 2 CCP_TRANSFER, 3 BARIDIMOB, 4 OTHER_MANUAL, 5 CASH_IN_PERSON'");
        $referenced = "EXISTS (SELECT 1 FROM saas_transfers p WHERE p.proof_media_id=OLD.id OR p.source_proof_media_id=OLD.id)
            OR (OLD.model_type='saas_payment' AND OLD.collection_name='payment_receipt' AND EXISTS (SELECT 1 FROM saas_transfers p WHERE p.id=OLD.model_id AND p.record_type=1 AND p.transfer_status IN (3,7)))";
        $fields = ['id', 'uuid', 'model_type', 'model_id', 'collection_name', 'disk', 'storage_key', 'file_hash', 'visibility', 'mime_type', 'size_bytes', 'original_name', 'created_by_id', 'created_at', 'width', 'height', 'is_primary', 'position', 'deleted_at'];
        $changed = implode(' OR ', array_map(fn (string $field): string => "NOT (OLD.`{$field}` <=> NEW.`{$field}`)", $fields));
        DB::unprepared("CREATE TRIGGER guard_payment_media_update BEFORE UPDATE ON media FOR EACH ROW BEGIN IF ({$referenced}) AND ({$changed}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payment evidence is immutable'; END IF; END");
        DB::unprepared("CREATE TRIGGER guard_payment_media_delete BEFORE DELETE ON media FOR EACH ROW BEGIN IF {$referenced} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Payment evidence cannot be deleted'; END IF; END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Conserver les nouvelles écritures espèces : le retour arrière du code ne doit pas supprimer leur réalité.
        DB::unprepared('DROP TRIGGER IF EXISTS guard_payment_media_update');
        DB::unprepared('DROP TRIGGER IF EXISTS guard_payment_media_delete');
    }
};

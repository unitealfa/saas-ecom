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
        $fields = ['model_type', 'model_id', 'collection_name', 'disk', 'storage_key', 'file_hash',
            'visibility', 'mime_type', 'size_bytes', 'original_name', 'is_primary', 'position', 'created_by_id', 'created_at', 'deleted_at'];
        $changed = implode(' OR ', array_map(fn (string $field): string => "NOT (OLD.`{$field}` <=> NEW.`{$field}`)", $fields));
        $referenced = 'EXISTS (SELECT 1 FROM saas_invoices d WHERE d.document_media_id = OLD.id AND d.status IN (2,4))';
        DB::unprepared("CREATE TRIGGER guard_invoice_media_update BEFORE UPDATE ON media FOR EACH ROW BEGIN
            IF ({$referenced}) AND ({$changed}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Issued invoice media is immutable'; END IF; END");
        DB::unprepared("CREATE TRIGGER guard_invoice_media_delete BEFORE DELETE ON media FOR EACH ROW BEGIN
            IF {$referenced} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Issued invoice media cannot be deleted'; END IF; END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS guard_invoice_media_update');
        DB::unprepared('DROP TRIGGER IF EXISTS guard_invoice_media_delete');
    }
};

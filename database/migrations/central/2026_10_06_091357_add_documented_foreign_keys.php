<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add forward and circular foreign keys after every parent table and UNIQUE key exists.
     */
    public function up(): void
    {

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->foreign(['billing_rule_id'], 'fk_saas_invoices_7de4923723')->references(['id'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id'], 'fk_saas_invoices_ac063d431a')->references(['id'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['document_media_id'], 'fk_saas_invoices_1707541b1b')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['billing_rule_id', 'billing_rule_record_type'], 'fk_saas_invoices_1bae90140d')->references(['id', 'record_type'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sequence_id', 'document_type', 'fiscal_year', 'sequence_record_type'], 'fk_saas_invoices_b70ad3b9df')->references(['id', 'document_type', 'fiscal_year', 'record_type'])->on('saas_billing_settings')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->foreign(['proof_media_id'], 'fk_saas_document_deliveries_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->foreign(['proof_media_id'], 'fk_saas_transfers_6aed88530f')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_proof_media_id'], 'fk_saas_transfers_2ac3d6fcc8')->references(['id'])->on('media')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {

        Schema::table('saas_transfers', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_transfers_6aed88530f');
            $table->dropForeign('fk_saas_transfers_2ac3d6fcc8');
        });

        Schema::table('saas_document_deliveries', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_document_deliveries_6aed88530f');
        });

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_invoices_7de4923723');
            $table->dropForeign('fk_saas_invoices_ac063d431a');
            $table->dropForeign('fk_saas_invoices_1707541b1b');
            $table->dropForeign('fk_saas_invoices_1bae90140d');
            $table->dropForeign('fk_saas_invoices_b70ad3b9df');
        });
    }
};

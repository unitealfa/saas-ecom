<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string ORIGINAL = "status IN (1,2,3,4,5,6) AND payment_choice IN (1,2) AND reference_version > 0 AND currency='DZD' AND (current_invoice_id IS NULL OR current_subscription_id IS NOT NULL) AND (current_payment_id IS NULL OR current_invoice_id IS NOT NULL) AND (status<>4 OR (decision_reason IS NOT NULL AND CHAR_LENGTH(TRIM(decision_reason))>0 AND decided_by_id IS NOT NULL AND decided_at IS NOT NULL)) AND (status<>5 OR (current_subscription_id IS NOT NULL AND current_invoice_id IS NOT NULL AND current_payment_id IS NOT NULL AND decided_by_id IS NOT NULL AND decided_at IS NOT NULL AND decision_reason IS NULL))";

    public function up(): void
    {
        DB::statement("ALTER TABLE subscription_requests
            ADD COLUMN accepted_without_payment BOOLEAN NOT NULL DEFAULT FALSE,
            DROP CHECK ck_subscription_request_shape,
            ADD CONSTRAINT ck_subscription_request_decision CHECK ((status IN (1,2,3,4,5,6) AND payment_choice IN (1,2) AND reference_version > 0 AND currency='DZD' AND (current_invoice_id IS NULL OR current_subscription_id IS NOT NULL) AND (current_payment_id IS NULL OR current_invoice_id IS NOT NULL) AND (status<>4 OR (decision_reason IS NOT NULL AND CHAR_LENGTH(TRIM(decision_reason))>0 AND decided_by_id IS NOT NULL AND decided_at IS NOT NULL)) AND (status<>5 OR (current_subscription_id IS NOT NULL AND current_invoice_id IS NOT NULL AND (current_payment_id IS NOT NULL OR accepted_without_payment = 1) AND decided_by_id IS NOT NULL AND decided_at IS NOT NULL AND decision_reason IS NULL)))
                AND (accepted_without_payment = 0 OR (accepted_without_payment = 1 AND status = 5 AND payment_choice = 1 AND current_payment_id IS NULL)))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscription_requests
            ADD CONSTRAINT ck_subscription_request_shape CHECK ('.self::ORIGINAL.'),
            DROP CHECK ck_subscription_request_decision,
            DROP COLUMN accepted_without_payment');
    }
};

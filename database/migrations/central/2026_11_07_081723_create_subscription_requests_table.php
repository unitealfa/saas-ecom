<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('current_subscription_id')->nullable();
            $table->foreignId('current_invoice_id')->nullable();
            $table->foreignId('current_payment_id')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('status')->default(1)->comment('RequestStatusEnum');
            $table->unsignedTinyInteger('payment_choice')->comment('PaymentChoiceEnum: cash or external');
            $table->unsignedInteger('reference_version')->default(1);
            $table->char('currency', 3)->default('DZD');
            $table->string('operation_key')->unique();
            $table->char('initial_payload_hash', 64);
            $table->dateTime('submitted_at');
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('invoice_error')->nullable();
            $table->unsignedTinyInteger('subscription_record_type')->nullable()->storedAs('CASE WHEN current_subscription_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('document_type')->nullable()->storedAs('CASE WHEN current_invoice_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('payment_record_type')->nullable()->storedAs('CASE WHEN current_payment_id IS NOT NULL THEN 1 ELSE NULL END');
            $table->timestamps();
            $table->unique(['id', 'user_id'], 'uq_subscription_requests_owner');
            $table->index(['user_id', 'status', 'id']);
            $table->index(['status', 'submitted_at', 'id']);
            $table->foreign(['current_subscription_id', 'user_id', 'subscription_record_type'], 'fk_request_subscription')
                ->references(['id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['current_payment_id', 'current_invoice_id', 'user_id', 'payment_record_type'], 'fk_request_payment')
                ->references(['id', 'document_id', 'user_id', 'record_type'])->on('saas_transfers')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->unique(['id', 'subscription_id', 'user_id', 'document_type'], 'uq_invoice_request_scope');
        });
        Schema::table('subscription_requests', function (Blueprint $table): void {
            $table->foreign(['current_invoice_id', 'current_subscription_id', 'user_id', 'document_type'], 'fk_request_invoice')
                ->references(['id', 'subscription_id', 'user_id', 'document_type'])->on('saas_invoices')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('subscription_request_id')->nullable();
            $table->foreign(['subscription_request_id', 'user_id'], 'fk_subscription_request_owner')->references(['id', 'user_id'])
                ->on('subscription_requests')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement("ALTER TABLE subscription_requests ADD CONSTRAINT ck_subscription_request_shape CHECK (status IN (1,2,3,4,5,6) AND payment_choice IN (1,2) AND reference_version > 0 AND currency='DZD' AND (current_invoice_id IS NULL OR current_subscription_id IS NOT NULL) AND (current_payment_id IS NULL OR current_invoice_id IS NOT NULL) AND (status<>4 OR (decision_reason IS NOT NULL AND CHAR_LENGTH(TRIM(decision_reason))>0 AND decided_by_id IS NOT NULL AND decided_at IS NOT NULL)) AND (status<>5 OR (current_subscription_id IS NOT NULL AND current_invoice_id IS NOT NULL AND current_payment_id IS NOT NULL AND decided_by_id IS NOT NULL AND decided_at IS NOT NULL AND decision_reason IS NULL)))");
        DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT ck_subscription_request_type CHECK (subscription_request_id IS NULL OR record_type=1)');
        DB::unprepared("CREATE TRIGGER guard_subscription_request_update BEFORE UPDATE ON subscription_requests FOR EACH ROW BEGIN
            IF OLD.status IN (5,6) OR NOT (OLD.user_id <=> NEW.user_id) OR NOT (OLD.uuid <=> NEW.uuid) OR NOT (OLD.operation_key <=> NEW.operation_key) OR NOT (OLD.initial_payload_hash <=> NEW.initial_payload_hash)
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Subscription request history is immutable'; END IF; END");
        DB::unprepared("CREATE TRIGGER guard_subscription_request_delete BEFORE DELETE ON subscription_requests FOR EACH ROW BEGIN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Subscription requests cannot be deleted'; END");
        DB::unprepared("CREATE TRIGGER guard_subscription_request_link BEFORE UPDATE ON subscriptions FOR EACH ROW BEGIN
            IF OLD.subscription_request_id IS NOT NULL AND NOT (OLD.subscription_request_id <=> NEW.subscription_request_id)
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Subscription request origin is immutable'; END IF; END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('subscription_requests')->exists()) {
            throw new LogicException('Historical requests must be preserved; use a forward migration.');
        }
        DB::unprepared('DROP TRIGGER IF EXISTS guard_subscription_request_update');
        DB::unprepared('DROP TRIGGER IF EXISTS guard_subscription_request_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS guard_subscription_request_link');
        DB::statement('ALTER TABLE subscriptions DROP CHECK ck_subscription_request_type');
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign('fk_subscription_request_owner');
            $table->dropColumn('subscription_request_id');
        });
        Schema::dropIfExists('subscription_requests');
        Schema::table('saas_invoices', fn (Blueprint $table) => $table->dropUnique('uq_invoice_request_scope'));
    }
};

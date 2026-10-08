<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (original_return_id, original_incident_id, current_revision_id, confirmed_revision_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('visitor_id')->nullable()->constrained('visitors', indexName: 'fk_orders_f4e34ae2ec')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('cart_id')->nullable()->constrained('carts', indexName: 'fk_orders_eb4226caa5')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_session_id')->nullable()->constrained('visit_sessions', indexName: 'fk_orders_2a9ca5a1fd')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_sales_page_id')->nullable()->constrained('content_pages', indexName: 'fk_orders_e50ff0c50c')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_return_id')->nullable();
            $table->foreignId('original_order_id')->nullable()->constrained('orders', indexName: 'fk_orders_b41c795bd0')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('original_incident_id')->nullable();
            $table->foreignId('current_revision_id')->nullable();
            $table->foreignId('confirmed_revision_id')->nullable();
            $table->foreignId('confirmation_owner_id')->nullable()->constrained('users', indexName: 'fk_orders_0d5c9ac004')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('operationally_confirmed_by_id')->nullable()->constrained('users', indexName: 'fk_orders_7f60a79ecc')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('original_sales_page_kind')->nullable()->storedAs('CASE WHEN original_sales_page_id IS NOT NULL THEN 2 ELSE NULL END')->comment('PageKindEnum: 1, 2');
            $table->unsignedTinyInteger('unpaid_resend_slot')->nullable()->storedAs('CASE WHEN order_type = 4 THEN 1 ELSE NULL END');
            $table->string('number');
            $table->string('data_policy_version');
            $table->dateTime('data_notice_acknowledged_at');
            $table->char('notice_text_hash')->charset('ascii')->collation('ascii_bin')->nullable();
            $table->integer('original_incident_quantity')->nullable();
            $table->string('replacement_reason')->nullable();
            $table->unsignedTinyInteger('order_type')->comment('OrderTypeEnum: 1, 2, 4');
            $table->unsignedTinyInteger('channel')->comment('OrderChannelEnum: 1, 2');
            $table->unsignedTinyInteger('commercial_status')->default(1)->comment('OrderStatusEnum: 1, 2, 5');
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('operationally_confirmed_at')->nullable();
            $table->string('submission_key');
            $table->char('submission_hash')->charset('ascii')->collation('ascii_bin');
            $table->integer('lock_version');
            $table->boolean('retention_hold');
            $table->text('retention_hold_reason')->nullable();
            $table->dateTime('hold_review_at')->nullable();
            $table->timestamps();

            $table->unique(['number'], 'uq_orders_12886f9d00');
            $table->unique(['submission_key'], 'uq_orders_4903422f6d');
            $table->unique(['cart_id'], 'uq_orders_eb4226caa5');
            $table->unique(['original_return_id', 'unpaid_resend_slot'], 'uq_orders_41c1334e87');
            $table->index(['commercial_status', 'created_at'], 'ix_orders_9625194f6a');
            $table->index(['original_incident_id', 'commercial_status'], 'ix_orders_6258548af6');
            $table->index(['current_revision_id', 'id'], 'ix_orders_79f7bc6c67');
            $table->index(['confirmed_revision_id', 'id'], 'ix_orders_430d4a293b');
            $table->index(['original_incident_id', 'original_order_id'], 'ix_orders_00146a1f46');
            $table->index(['original_return_id', 'original_order_id'], 'ix_orders_c1dd89c96a');
            $table->index(['original_sales_page_id', 'original_sales_page_kind'], 'ix_orders_122c5334b9');
            $table->index(['original_order_id'], 'ix_orders_b41c795bd0');
            $table->index(['visitor_id'], 'ix_orders_f4e34ae2ec');
            $table->index(['original_session_id'], 'ix_orders_2a9ca5a1fd');
            $table->index(['confirmation_owner_id'], 'ix_orders_0d5c9ac004');
            $table->index(['operationally_confirmed_by_id'], 'ix_orders_7f60a79ecc');
            $table->foreign(['original_sales_page_id', 'original_sales_page_kind'], 'fk_orders_122c5334b9')->references(['id', 'page_kind'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

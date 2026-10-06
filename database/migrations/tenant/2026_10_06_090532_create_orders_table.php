<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('visitor_id')->nullable();
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->unsignedBigInteger('original_session_id')->nullable();
            $table->unsignedBigInteger('original_sales_page_id')->nullable();
            $table->unsignedBigInteger('original_return_id')->nullable();
            $table->unsignedBigInteger('original_order_id')->nullable();
            $table->unsignedBigInteger('original_incident_id')->nullable();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('confirmed_revision_id')->nullable();
            $table->unsignedBigInteger('confirmation_owner_id')->nullable();
            $table->unsignedBigInteger('operationally_confirmed_by_id')->nullable();
            $table->unsignedTinyInteger('original_sales_page_kind')->nullable()->storedAs('CASE WHEN original_sales_page_id IS NOT NULL THEN 2 ELSE NULL END')->comment('PageKindEnum: 1, 2');
            $table->unsignedTinyInteger('unpaid_resend_slot')->nullable()->storedAs('CASE WHEN order_type = 4 THEN 1 ELSE NULL END');
            $table->string('number');
            $table->string('data_policy_version');
            $table->dateTime('data_notice_acknowledged_at', 6);
            $table->char('notice_text_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable();
            $table->integer('original_incident_quantity')->nullable();
            $table->string('replacement_reason')->nullable();
            $table->unsignedTinyInteger('order_type')->comment('OrderTypeEnum: 1, 2, 4');
            $table->unsignedTinyInteger('channel')->comment('OrderChannelEnum: 1, 2');
            $table->unsignedTinyInteger('commercial_status')->default(1)->comment('OrderStatusEnum: 1, 2, 5');
            $table->dateTime('validated_at', 6)->nullable();
            $table->dateTime('operationally_confirmed_at', 6)->nullable();
            $table->string('submission_key');
            $table->char('submission_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->integer('lock_version');
            $table->boolean('retention_hold');
            $table->text('retention_hold_reason')->nullable();
            $table->dateTime('hold_review_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

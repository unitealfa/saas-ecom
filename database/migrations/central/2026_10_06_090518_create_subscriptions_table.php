<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parent_subscription_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            $table->string('installment_number', 191)->nullable()->unique();
            $table->string('operation_key', 191)->unique();
            $table->unsignedTinyInteger('record_type')->comment('SubscriptionRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_record_type')->nullable()->storedAs('CASE WHEN record_type = 2 THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('status')->nullable()->comment('SubscriptionStatusEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('period')->nullable()->comment('BillingPeriodEnum: 1, 2');
            $table->decimal('agreed_amount', 14, 2)->nullable();
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('period_starts_at', 6);
            $table->dateTime('period_ends_at', 6)->nullable();
            $table->dateTime('trial_ends_at', 6)->nullable();
            $table->dateTime('ended_at', 6)->nullable();
            $table->boolean('auto_renew')->nullable();
            $table->decimal('installment_amount', 14, 2)->nullable();
            $table->dateTime('due_at', 6)->nullable();
            $table->unsignedTinyInteger('installment_status')->nullable()->comment('InstallmentStatusEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('active_owner_slot')->nullable()->storedAs('CASE WHEN record_type = 1 AND status = 3 THEN 1 ELSE NULL END');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

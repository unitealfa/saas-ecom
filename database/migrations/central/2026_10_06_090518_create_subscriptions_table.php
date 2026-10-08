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
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_subscriptions_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('parent_subscription_id')->nullable()->constrained('subscriptions', indexName: 'fk_subscriptions_acf499a627')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants', indexName: 'fk_subscriptions_759b6ffea8')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('plan_id')->nullable()->constrained('plans', indexName: 'fk_subscriptions_a427196bc8')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users', indexName: 'fk_subscriptions_bc15dd68ce')->restrictOnDelete()->restrictOnUpdate();
            $table->string('installment_number')->nullable()->unique();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('record_type')->comment('SubscriptionRecordTypeEnum: 1, 2');
            $table->unsignedTinyInteger('parent_record_type')->nullable()->storedAs('CASE WHEN record_type = 2 THEN 1 ELSE NULL END');
            $table->unsignedTinyInteger('status')->nullable()->comment('SubscriptionStatusEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('period')->nullable()->comment('BillingPeriodEnum: 1, 2');
            $table->decimal('agreed_amount')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->boolean('auto_renew')->nullable();
            $table->decimal('installment_amount')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->unsignedTinyInteger('installment_status')->nullable()->comment('InstallmentStatusEnum: 1, 2, 3, 4');
            $table->unsignedTinyInteger('active_owner_slot')->nullable()->storedAs('CASE WHEN record_type = 1 AND status = 3 THEN 1 ELSE NULL END');
            $table->timestamps();

            $table->unique(['id', 'user_id', 'record_type'], 'uq_subscriptions_8ef95e0c44');
            $table->unique(['id', 'parent_subscription_id', 'user_id', 'record_type'], 'uq_subscriptions_f5e32fea0c');
            $table->unique(['user_id', 'active_owner_slot'], 'uq_subscriptions_f59d836b7a');
            $table->index(['record_type', 'user_id', 'status'], 'ix_subscriptions_c0c4780fba');
            $table->index(['parent_subscription_id', 'installment_status', 'due_at'], 'ix_subscriptions_2606f12b0d');
            $table->index(['parent_subscription_id', 'user_id', 'parent_record_type'], 'ix_subscriptions_e56456f06c');
            $table->index(['tenant_id', 'user_id'], 'ix_subscriptions_1bd81732ff');
            $table->index(['plan_id'], 'ix_subscriptions_a427196bc8');
            $table->index(['assigned_by_id'], 'ix_subscriptions_bc15dd68ce');
            $table->foreign(['tenant_id', 'user_id'], 'fk_subscriptions_1bd81732ff')->references(['id', 'user_id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['parent_subscription_id', 'user_id', 'parent_record_type'], 'fk_subscriptions_e56456f06c')->references(['id', 'user_id', 'record_type'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

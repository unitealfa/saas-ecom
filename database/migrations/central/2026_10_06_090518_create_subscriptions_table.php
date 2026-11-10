<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schéma final dès la création : abonnement et échéance sont deux entités. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_subscriptions_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants', indexName: 'fk_subscriptions_759b6ffea8')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('plan_id')->nullable()->constrained('plans', indexName: 'fk_subscriptions_a427196bc8')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users', indexName: 'fk_subscriptions_bc15dd68ce')->restrictOnDelete()->restrictOnUpdate();
            $table->string('operation_key')->unique();
            $table->unsignedTinyInteger('status')->nullable()->comment('SubscriptionStatusEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('period')->nullable()->comment('BillingPeriodEnum: 1, 2');
            $table->decimal('agreed_amount')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at')->nullable();
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->boolean('auto_renew')->nullable();
            $table->unsignedTinyInteger('active_owner_slot')->nullable()->storedAs('CASE WHEN status = 3 THEN 1 ELSE NULL END');
            $table->timestamps();

            $table->unique(['id', 'user_id'], 'uq_subscriptions_id_user');
            $table->unique(['user_id', 'active_owner_slot'], 'uq_subscriptions_f59d836b7a');
            $table->index(['user_id'], 'ix_subscriptions_owner');
            $table->index(['tenant_id', 'user_id'], 'ix_subscriptions_1bd81732ff');
            $table->index(['plan_id'], 'ix_subscriptions_a427196bc8');
            $table->index(['assigned_by_id'], 'ix_subscriptions_bc15dd68ce');
            $table->foreign(['tenant_id', 'user_id'], 'fk_subscriptions_1bd81732ff')
                ->references(['id', 'user_id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('subscription_installments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_subscription_installments_user')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('subscription_id')->constrained('subscriptions', indexName: 'fk_subscription_installments_subscription')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->string('installment_number')->unique();
            $table->string('operation_key')->unique();
            $table->decimal('installment_amount');
            $table->unsignedTinyInteger('installment_status')->comment('InstallmentStatusEnum: 1, 2, 3, 4');
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at');
            $table->dateTime('due_at');
            $table->timestamps();

            $table->unique(['id', 'subscription_id', 'user_id'], 'uq_subscription_installments_context');
            $table->index(['subscription_id', 'installment_status', 'due_at'], 'ix_subscription_installments_due');
            $table->foreign(['subscription_id', 'user_id'], 'fk_subscription_installments_owner')
                ->references(['id', 'user_id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_installments');
        Schema::dropIfExists('subscriptions');
    }
};

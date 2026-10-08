<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_usage', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_feature_usage_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants', indexName: 'fk_feature_usage_759b6ffea8')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('feature_id')->constrained('features', indexName: 'fk_feature_usage_3de8f04067')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at')->nullable();
            $table->bigInteger('quantity');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id'], 'ix_feature_usage_1bd81732ff');
            $table->index(['user_id'], 'ix_feature_usage_f89d6b6960');
            $table->index(['feature_id'], 'ix_feature_usage_3de8f04067');
            $table->foreign(['tenant_id', 'user_id'], 'fk_feature_usage_1bd81732ff')->references(['id', 'user_id'])->on('tenants')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_usage');
    }
};

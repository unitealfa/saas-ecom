<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('plan_id')->constrained('plans', indexName: 'fk_plan_features_a427196bc8')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('feature_id')->constrained('features', indexName: 'fk_plan_features_3de8f04067')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('is_active');
            $table->bigInteger('limit')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id'], 'uq_plan_features_b5beb535fd');
            $table->index(['feature_id'], 'ix_plan_features_3de8f04067');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};

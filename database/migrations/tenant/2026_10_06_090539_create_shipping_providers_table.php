<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (carrier_account_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('shipping_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'fk_shipping_providers_f89d6b6960')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('carrier_account_id')->nullable();
            $table->unsignedTinyInteger('type')->comment('ShippingProviderTypeEnum: 1, 2, 3');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('reference_configuration')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['carrier_account_id'], 'uq_shipping_providers_fd68d11272');
            $table->index(['user_id'], 'ix_shipping_providers_f89d6b6960');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_providers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('created_by_id')->constrained('users', indexName: 'fk_carrier_accounts_6fb667974b')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('carrier_uuid');
            $table->string('label');
            $table->string('adapter');
            $table->string('external_account_id')->nullable();
            $table->string('api_url')->nullable();
            $table->text('encrypted_api_credentials')->nullable();
            $table->string('encryption_key_version')->nullable();
            $table->boolean('is_active');
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['carrier_uuid', 'external_account_id'], 'uq_carrier_accounts_573826f6df');
            $table->index(['created_by_id'], 'ix_carrier_accounts_6fb667974b');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_accounts');
    }
};

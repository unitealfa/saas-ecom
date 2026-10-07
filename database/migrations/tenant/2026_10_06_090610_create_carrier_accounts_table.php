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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('created_by_id');
            $table->char('carrier_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->string('label');
            $table->string('adapter');
            $table->string('external_account_id')->nullable();
            $table->string('api_url')->nullable();
            $table->text('encrypted_api_credentials')->nullable();
            $table->string('encryption_key_version')->nullable();
            $table->boolean('is_active');
            $table->dateTime('last_synced_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_accounts');
    }
};

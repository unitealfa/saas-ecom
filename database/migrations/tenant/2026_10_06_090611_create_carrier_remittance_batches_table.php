<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_remittance_batches', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('carrier_account_id');
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->string('operation_key')->unique();
            $table->string('external_reference')->nullable();
            $table->decimal('reported_account_net_amount', 14, 2)->nullable();
            $table->decimal('computed_shop_net_amount', 14, 2);
            $table->decimal('verified_net_amount', 14, 2)->nullable();
            $table->unsignedTinyInteger('status')->default(1)->comment('RemittanceBatchStatusEnum: 1, 2, 3');
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_remittance_batches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remittance_statements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('carrier_remittance_batch_id')->nullable();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->string('number');
            $table->string('external_reference')->nullable();
            $table->unsignedTinyInteger('type')->comment('StatementTypeEnum: 1, 2, 3, 4, 5');
            $table->unsignedTinyInteger('status')->default(1)->comment('RemittanceStatementStatusEnum: 1, 2, 3, 4, 5, 6');
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('fee_amount', 14, 2);
            $table->decimal('expected_net_amount', 14, 2);
            $table->decimal('received_net_amount', 14, 2)->nullable();
            $table->dateTime('declared_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->text('note')->nullable();
            $table->string('operation_key');
            $table->dateTime('reconciled_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_statements');
    }
};

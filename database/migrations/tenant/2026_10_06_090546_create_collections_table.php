<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('shipment_id');
            $table->unsignedTinyInteger('declared_status')->comment('CollectionStatusEnum: 1, 2, 3, 4, 5, 6, 7');
            $table->decimal('expected_amount', 14, 2);
            $table->decimal('declared_collected_amount', 14, 2)->nullable();
            $table->dateTime('collected_at', 6)->nullable();
            $table->dateTime('payment_ready_at', 6)->nullable();
            $table->dateTime('declared_paid_at', 6)->nullable();
            $table->string('source');
            $table->dateTime('reconciled_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};

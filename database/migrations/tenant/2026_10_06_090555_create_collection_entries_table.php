<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('verified_by_id');
            $table->unsignedBigInteger('proof_media_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('correction_of_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->dateTime('collected_at', 6);
            $table->dateTime('verified_at', 6);
            $table->string('reference');
            $table->text('reason');
            $table->string('operation_key');
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_entries');
    }
};

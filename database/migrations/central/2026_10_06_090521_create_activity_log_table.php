<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('operation_key', 191)->nullable()->unique();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('log_name', 64)->nullable();
            $table->text('description');
            $table->string('subject_type', 64)->nullable();
            $table->string('event', 100)->nullable();
            $table->string('causer_type', 64)->nullable();
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->char('correlation_id', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->unsignedTinyInteger('origin')->comment('ActivityOriginEnum: 1, 2, 3, 4');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};

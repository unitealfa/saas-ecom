<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('visitor_id');
            $table->dateTime('started_at', 6);
            $table->dateTime('last_activity_at', 6);
            $table->dateTime('ended_at', 6)->nullable();
            $table->string('entry_path');
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('referrer_host')->nullable();
            $table->unsignedTinyInteger('device_type')->nullable()->comment('DeviceTypeEnum: 1, 2, 3, 4');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_sessions');
    }
};

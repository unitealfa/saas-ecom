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
            $table->uuid('uuid');
            $table->foreignId('visitor_id')->constrained('visitors', indexName: 'fk_visit_sessions_f4e34ae2ec')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('started_at');
            $table->dateTime('last_activity_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('entry_path');
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('referrer_host')->nullable();
            $table->unsignedTinyInteger('device_type')->nullable()->comment('DeviceTypeEnum: 1, 2, 3, 4');
            $table->timestamps();

            $table->index(['visitor_id', 'started_at'], 'ix_visit_sessions_772cffd42f');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_sessions');
    }
};

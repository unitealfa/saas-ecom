<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_incident_details', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedTinyInteger('type')->comment('IncidentTypeEnum: 1, 2, 3, 4, 5, 6');
            $table->integer('quantity');
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_incident_details');
    }
};

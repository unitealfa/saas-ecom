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
            $table->uuid('uuid');
            $table->foreignId('incident_id')->constrained('order_incidents', indexName: 'fk_order_incident_details_04dbd90085')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('author_id')->nullable()->constrained('users', indexName: 'fk_order_incident_details_378e66e226')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('type')->comment('IncidentTypeEnum: 1, 2, 3, 4, 5, 6');
            $table->integer('quantity');
            $table->text('reason');
            $table->timestamps();

            $table->index(['incident_id'], 'ix_order_incident_details_04dbd90085');
            $table->index(['author_id'], 'ix_order_incident_details_378e66e226');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_incident_details');
    }
};

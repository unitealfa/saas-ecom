<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete()->restrictOnUpdate();
            $table->string('domain')->unique();
            $table->unsignedTinyInteger('type')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('verification_status')->default(1);
            $table->dateTime('verified_at')->nullable();
            $table->unsignedTinyInteger('certificate_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedTinyInteger('primary_slot')->nullable()
                ->storedAs('CASE WHEN is_primary = TRUE AND deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['tenant_id', 'primary_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};

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
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('domain', 253)->unique();
            $table->unsignedTinyInteger('type')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('verification_status')->default(1);
            $table->dateTime('verified_at', 6)->nullable();
            $table->unsignedTinyInteger('certificate_status')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unsignedTinyInteger('primary_slot')->nullable()
                ->storedAs('CASE WHEN is_primary = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END');
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

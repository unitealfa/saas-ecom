<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedTinyInteger('super_admin_slot')->nullable()->storedAs('CASE WHEN is_super_admin = 1 THEN 1 ELSE NULL END')->unique();
            $table->char('permission_signature', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->string('name', 125);
            $table->string('guard_name', 32);
            $table->string('label');
            $table->boolean('is_system');
            $table->boolean('is_protected');
            $table->boolean('is_super_admin');
            $table->unsignedBigInteger('permission_version');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

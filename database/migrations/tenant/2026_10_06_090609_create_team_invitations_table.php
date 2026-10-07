<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('initial_role_id');
            $table->unsignedBigInteger('invited_by_id');
            $table->string('token_hash')->unique();
            $table->string('email');
            $table->unsignedBigInteger('role_permission_version');
            $table->dateTime('expires_at', 6);
            $table->dateTime('accepted_at', 6)->nullable();
            $table->dateTime('revoked_at', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};

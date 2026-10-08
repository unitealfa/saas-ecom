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
            $table->uuid('uuid');
            $table->foreignId('initial_role_id')->constrained('roles', indexName: 'fk_team_invitations_d5576bc50f')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('invited_by_id')->constrained('users', indexName: 'fk_team_invitations_e3979f2144')->restrictOnDelete()->restrictOnUpdate();
            $table->string('token_hash')->unique();
            $table->string('email');
            $table->unsignedBigInteger('role_permission_version');
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['initial_role_id'], 'ix_team_invitations_d5576bc50f');
            $table->index(['invited_by_id'], 'ix_team_invitations_e3979f2144');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};

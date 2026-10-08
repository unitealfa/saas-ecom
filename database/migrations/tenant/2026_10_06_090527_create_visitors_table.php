<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('token_hash');
            $table->dateTime('first_visited_at');
            $table->dateTime('last_visited_at');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['token_hash'], 'uq_visitors_e6511e19f6');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};

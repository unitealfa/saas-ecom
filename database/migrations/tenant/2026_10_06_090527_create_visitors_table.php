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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->string('token_hash');
            $table->dateTime('first_visited_at', 6);
            $table->dateTime('last_visited_at', 6);
            $table->dateTime('expires_at', 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};

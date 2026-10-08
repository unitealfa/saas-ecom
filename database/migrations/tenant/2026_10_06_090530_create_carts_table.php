<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('visitor_id')->constrained('visitors', indexName: 'fk_carts_f4e34ae2ec')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('status')->default(1)->comment('CartStatusEnum: 1, 2, 3, 4');
            $table->dateTime('last_activity_at');
            $table->dateTime('expires_at');
            $table->dateTime('converted_at')->nullable();
            $table->timestamps();

            $table->index(['visitor_id'], 'ix_carts_f4e34ae2ec');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

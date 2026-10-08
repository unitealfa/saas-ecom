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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('country_id')->constrained('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('legal_verified_by_id')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('last_name');
            $table->string('first_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('phone_verified_at')->nullable();
            $table->dateTime('whatsapp_verified_at')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('activity_nature')->nullable();
            $table->string('nif')->nullable();
            $table->string('nis')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('artisan_card_number')->nullable();
            $table->text('legal_address')->nullable();
            $table->decimal('share_capital')->nullable();
            $table->unsignedBigInteger('legal_profile_version')->nullable();
            $table->unsignedTinyInteger('legal_verification_status')->nullable();
            $table->dateTime('legal_verified_at')->nullable();
            $table->string('locale')->default('fr');
            $table->unsignedTinyInteger('status')->default(1);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

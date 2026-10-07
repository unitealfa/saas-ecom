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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->char('central_user_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->nullable()->unique();
            $table->string('last_name');
            $table->string('first_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->dateTime('email_verified_at', 6)->nullable();
            $table->string('locale', 10)->default('fr');
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedTinyInteger('membership_status')->default(2);
            $table->dateTime('joined_at', 6)->nullable();
            $table->dateTime('last_login_at', 6)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('channel');
            $table->string('normalized_destination');
            $table->string('code_hash');
            $table->dateTime('expires_at', 6);
            $table->integer('attempts_count')->default(0);
            $table->dateTime('consumed_at', 6)->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
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
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('contact_verifications');
        Schema::dropIfExists('users');
    }
};

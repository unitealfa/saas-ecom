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
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('slug', 63)->unique();
            $table->string('document_prefix', 32)->unique();
            $table->string('internal_label');
            $table->string('shop_name');
            $table->unsignedBigInteger('profile_version')->default(1);
            $table->string('creation_key');
            $table->char('creation_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->integer('activation_priority')->nullable();
            $table->dateTime('over_quota_since_at', 6)->nullable();
            $table->json('data');
            $table->string('schema_version')->nullable();
            $table->dateTime('provisioned_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->dateTime('deleted_at', 6)->nullable();
            $table->unique(['user_id', 'creation_key']);
            $table->unique(['id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

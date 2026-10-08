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
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('slug')->unique();
            $table->string('document_prefix')->unique();
            $table->string('internal_label');
            $table->string('shop_name');
            $table->unsignedBigInteger('profile_version')->default(1);
            $table->string('creation_key');
            $table->char('creation_hash')->charset('ascii')->collation('ascii_bin');
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->integer('activation_priority')->nullable();
            $table->dateTime('over_quota_since_at')->nullable();
            $table->json('data');
            $table->string('schema_version')->nullable();
            $table->dateTime('provisioned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
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

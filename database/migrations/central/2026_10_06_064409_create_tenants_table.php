<?php

use App\Enums\Central\Tenants\StatusEnum;
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
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('document_prefix')->unique();
            $table->string('internal_label');
            $table->string('shop_name')->index();
            $table->unsignedBigInteger('profile_version')->default(1);
            $table->string('creation_key')->unique();
            $table->char('creation_hash', 64)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->unsignedTinyInteger('status')->default(StatusEnum::PENDING);
            $table->boolean('is_primary')->default(false);
            $table->integer('activation_priority')->nullable();
            $table->dateTime('over_quota_since_at', 6)->nullable();
            $table->json('data');
            $table->string('schema_version')->nullable();
            $table->dateTime('provisioned_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

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

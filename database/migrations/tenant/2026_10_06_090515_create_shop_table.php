<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->char('tenant_uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin');
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->unsignedBigInteger('favicon_media_id')->nullable();
            $table->tinyInteger('singleton')->default(1)->unique();
            $table->bigInteger('central_profile_version');
            $table->string('shop_name');
            $table->text('description')->nullable();
            $table->text('about')->nullable();
            $table->string('business_type');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('locale');
            $table->char('currency', 3)->default('DZD');
            $table->string('timezone')->default('Africa/Algiers');
            $table->string('theme_code');
            $table->json('colors');
            $table->json('shipping_tax_configuration')->nullable();
            $table->integer('cart_lifetime_days');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop');
    }
};

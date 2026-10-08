<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (logo_media_id, favicon_media_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('shop', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->uuid('tenant_uuid');
            $table->foreignId('logo_media_id')->nullable();
            $table->foreignId('favicon_media_id')->nullable();
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
            $table->char('currency')->default('DZD');
            $table->string('timezone')->default('Africa/Algiers');
            $table->string('theme_code');
            $table->json('colors');
            $table->json('shipping_tax_configuration')->nullable();
            $table->integer('cart_lifetime_days');
            $table->timestamps();

            $table->unique(['tenant_uuid'], 'uq_shop_40ae0da513');
            $table->index(['logo_media_id'], 'ix_shop_bd28b60ea5');
            $table->index(['favicon_media_id'], 'ix_shop_8e7646ae6d');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('visitor_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('moderated_by_id')->nullable();
            $table->string('display_name');
            $table->integer('note');
            $table->text('comment');
            $table->unsignedTinyInteger('moderation_status')->comment('ReviewModerationStatusEnum: 1, 2, 3, 4');
            $table->dateTime('moderated_at', 6)->nullable();
            $table->dateTime('published_at', 6)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};

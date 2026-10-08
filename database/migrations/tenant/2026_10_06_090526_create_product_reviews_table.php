<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (visitor_id, order_item_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_product_reviews_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('visitor_id')->nullable();
            $table->foreignId('order_item_id')->nullable();
            $table->foreignId('moderated_by_id')->nullable()->constrained('users', indexName: 'fk_product_reviews_9ebbb59674')->restrictOnDelete()->restrictOnUpdate();
            $table->string('display_name');
            $table->integer('note');
            $table->text('comment');
            $table->unsignedTinyInteger('moderation_status')->comment('ReviewModerationStatusEnum: 1, 2, 3, 4');
            $table->dateTime('moderated_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_item_id', 'product_id'], 'ix_product_reviews_f503fc1f02');
            $table->index(['product_id'], 'ix_product_reviews_c3adad4f81');
            $table->index(['visitor_id'], 'ix_product_reviews_f4e34ae2ec');
            $table->index(['moderated_by_id'], 'ix_product_reviews_9ebbb59674');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};

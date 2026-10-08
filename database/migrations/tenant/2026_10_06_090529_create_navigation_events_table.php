<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forward references (cart_id) are constrained by 2026_10_06_091358_add_documented_foreign_keys.php after their parents exist.
     */
    public function up(): void
    {
        Schema::create('navigation_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('session_id')->constrained('visit_sessions', indexName: 'fk_navigation_events_5c3a09bf22')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->nullable()->constrained('products', indexName: 'fk_navigation_events_c3adad4f81')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants', indexName: 'fk_navigation_events_14f215ed6d')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('sales_page_id')->nullable()->constrained('content_pages', indexName: 'fk_navigation_events_b31f7a60fc')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('content_page_id')->nullable()->constrained('content_pages', indexName: 'fk_navigation_events_fe9e538371')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('cart_id')->nullable();
            $table->unsignedTinyInteger('sales_page_kind')->nullable()->storedAs('CASE WHEN sales_page_id IS NOT NULL THEN 2 ELSE NULL END')->comment('PageKindEnum: 1, 2');
            $table->unsignedTinyInteger('content_page_kind')->nullable()->storedAs('CASE WHEN content_page_id IS NOT NULL THEN 1 ELSE NULL END')->comment('PageKindEnum: 1, 2');
            $table->string('type');
            $table->string('path');
            $table->integer('quantity')->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('received_at');
            $table->timestamp('created_at');

            $table->index(['session_id', 'occurred_at'], 'ix_navigation_events_0fdb027686');
            $table->index(['product_id', 'occurred_at'], 'ix_navigation_events_5e29e3a9d7');
            $table->index(['sales_page_id', 'occurred_at'], 'ix_navigation_events_e05dc7153a');
            $table->index(['sales_page_id', 'product_id'], 'ix_navigation_events_59bc361caa');
            $table->index(['sales_page_id', 'sales_page_kind'], 'ix_navigation_events_09613a1a6f');
            $table->index(['content_page_id', 'content_page_kind'], 'ix_navigation_events_2e86a84456');
            $table->index(['variant_id'], 'ix_navigation_events_14f215ed6d');
            $table->index(['cart_id'], 'ix_navigation_events_eb4226caa5');
            $table->foreign(['sales_page_id', 'product_id'], 'fk_navigation_events_59bc361caa')->references(['id', 'product_id'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sales_page_id', 'sales_page_kind'], 'fk_navigation_events_09613a1a6f')->references(['id', 'page_kind'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['content_page_id', 'content_page_kind'], 'fk_navigation_events_2e86a84456')->references(['id', 'page_kind'])->on('content_pages')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_events');
    }
};

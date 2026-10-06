<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_events', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('sales_page_id')->nullable();
            $table->unsignedBigInteger('content_page_id')->nullable();
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->unsignedTinyInteger('sales_page_kind')->nullable()->storedAs('CASE WHEN sales_page_id IS NOT NULL THEN 2 ELSE NULL END')->comment('PageKindEnum: 1, 2');
            $table->unsignedTinyInteger('content_page_kind')->nullable()->storedAs('CASE WHEN content_page_id IS NOT NULL THEN 1 ELSE NULL END')->comment('PageKindEnum: 1, 2');
            $table->string('type');
            $table->string('path');
            $table->integer('quantity')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->dateTime('received_at', 6);
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_events');
    }
};

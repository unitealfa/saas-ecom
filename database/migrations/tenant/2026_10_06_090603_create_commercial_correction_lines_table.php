<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_correction_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->charset('ascii')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'ascii_bin')->unique();
            $table->unsignedBigInteger('correction_id');
            $table->unsignedBigInteger('source_revision_id');
            $table->unsignedBigInteger('order_item_id');
            $table->integer('affected_quantity');
            $table->decimal('reference_sale_amount', 14, 2);
            $table->decimal('revenue_delta', 14, 2);
            $table->decimal('sold_cost_delta', 14, 2);
            $table->text('detailed_reason')->nullable();
            $table->dateTime('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_correction_lines');
    }
};

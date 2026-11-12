<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('carrier_id')->constrained('shipping_carriers')->restrictOnDelete();
            $table->foreignId('geographic_area_id')->constrained('geographic_areas')->restrictOnDelete();
            $table->string('service', 20);
            $table->string('delivery_mode', 20);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('DZD');
            $table->string('reference_source');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['carrier_id', 'geographic_area_id', 'service', 'delivery_mode'], 'central_shipping_rates_scope_unique');
        });

        DB::statement("ALTER TABLE shipping_rates ADD CONSTRAINT central_shipping_rates_values CHECK (amount >= 0 AND currency = 'DZD' AND service IN ('delivery', 'pickup', 'exchange', 'collection', 'return') AND delivery_mode IN ('home', 'office'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};

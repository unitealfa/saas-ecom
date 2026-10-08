<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX uq_product_axis_name ON product_options (product_id, (CASE WHEN record_type = 1 THEN LOWER(TRIM(name)) ELSE NULL END))');
        DB::statement('CREATE UNIQUE INDEX uq_product_value_name ON product_options (parent_id, (LOWER(TRIM(name))))');
        DB::statement('CREATE UNIQUE INDEX uq_visitor_active_cart ON carts (visitor_id, (CASE WHEN status = 1 THEN 1 ELSE NULL END))');
        DB::statement('CREATE UNIQUE INDEX uq_cart_variant_customization_origin ON cart_items (cart_id, variant_id, customization_signature, (COALESCE(sales_page_id, 0)))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX uq_product_axis_name ON product_options');
        DB::statement('DROP INDEX uq_product_value_name ON product_options');
        DB::statement('DROP INDEX uq_visitor_active_cart ON carts');
        DB::statement('DROP INDEX uq_cart_variant_customization_origin ON cart_items');
    }
};

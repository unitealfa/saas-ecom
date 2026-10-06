<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function assertDocumentedAuthorizationConstraints(string $context): void
{
    $now = '2026-10-06 12:00:00.000000';
    $permission = ['uuid' => (string) Str::uuid(), 'name' => $context === 'central' ? 'saas.plans.manage' : 'products.manage', 'guard_name' => $context, 'label' => 'Manage', 'created_at' => $now, 'updated_at' => $now];
    $permissionId = DB::table('permissions')->insertGetId($permission);
    $role = ['uuid' => (string) Str::uuid(), 'name' => 'manager', 'guard_name' => $context, 'label' => 'Manager', 'permission_signature' => hash('sha256', $permissionId.':9999'), 'permission_version' => 1, 'is_system' => false, 'is_protected' => false, 'is_super_admin' => false, 'created_at' => $now, 'updated_at' => $now];
    $roleId = DB::table('roles')->insertGetId($role);
    DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
    expect((int) DB::table('role_has_permissions')->value('duration_days'))->toBe(9999);
    foreach ([0, 10000] as $duration) {
        expect(fn (): int => DB::table('role_has_permissions')->update(['duration_days' => $duration]))->toThrow(QueryException::class);
    }
    DB::table('role_has_permissions')->update(['duration_days' => 7]);
    expect((int) DB::table('role_has_permissions')->value('duration_days'))->toBe(7);
    expect(fn (): bool => DB::table('roles')->insert(array_replace($role, ['uuid' => (string) Str::uuid(), 'name' => 'different-name'])))->toThrow(QueryException::class);
    expect(fn (): bool => DB::table('roles')->insert(array_replace($role, ['uuid' => (string) Str::uuid(), 'permission_signature' => hash('sha256', 'other-composition')])))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('roles')->where('id', $roleId)->update(['uuid' => (string) Str::uuid()]))->toThrow(QueryException::class);
    expect(fn (): bool => DB::table('permissions')->insert(array_replace($permission, ['uuid' => (string) Str::uuid(), 'name' => 'other', 'guard_name' => $context === 'central' ? 'tenant' : 'central'])))->toThrow(QueryException::class);
    if ($context === 'tenant') {
        expect(fn (): bool => DB::table('permissions')->insert(array_replace($permission, ['uuid' => (string) Str::uuid(), 'name' => 'saas.plans.manage'])))->toThrow(QueryException::class);
    }

    $activity = ['uuid' => (string) Str::uuid(), 'description' => 'Constraint test', 'origin' => 2, 'correlation_id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now];
    if ($context === 'tenant') {
        expect(fn (): bool => DB::table('activity_log')->insert(array_replace($activity, ['log_name' => 'privacy'])))->toThrow(QueryException::class);
        $activity = array_replace($activity, ['log_name' => 'privacy', 'performed_at' => $now]);
    }
    $activityId = DB::table('activity_log')->insertGetId($activity);
    expect(fn (): int => DB::table('activity_log')->where('id', $activityId)->update(['description' => 'Changed history']))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('activity_log')->where('id', $activityId)->delete())->toThrow(QueryException::class);
}

function assertDocumentedCentralGeographyConstraints(): void
{
    $now = '2026-10-06 12:00:00.000000';
    $country = ['uuid' => (string) Str::uuid(), 'code' => 'DZ', 'name_fr' => 'Algérie', 'name_en' => 'Algeria', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
    $countryId = DB::table('countries')->insertGetId($country);
    $otherCountryId = DB::table('countries')->insertGetId(array_replace($country, ['uuid' => (string) Str::uuid(), 'code' => 'FR', 'name_fr' => 'France', 'name_en' => 'France']));
    $province = ['uuid' => (string) Str::uuid(), 'country_id' => $countryId, 'type' => 1, 'code' => '16', 'name_fr' => 'Alger', 'is_active' => true, 'reference_source' => 'test-reference', 'reference_version' => 'test-v1', 'effective_at' => '2026-10-06', 'created_at' => $now, 'updated_at' => $now];
    $provinceId = DB::table('geographic_areas')->insertGetId($province);
    $municipality = array_replace($province, ['uuid' => (string) Str::uuid(), 'type' => 2, 'parent_id' => $provinceId, 'code' => '1601', 'name_fr' => 'Alger Centre']);
    $municipalityId = DB::table('geographic_areas')->insertGetId($municipality);
    expect(fn (): bool => DB::table('geographic_areas')->insert(array_replace($municipality, ['uuid' => (string) Str::uuid(), 'code' => 'other-country', 'country_id' => $otherCountryId])))->toThrow(QueryException::class);
    expect(fn (): bool => DB::table('geographic_areas')->insert(array_replace($municipality, ['uuid' => (string) Str::uuid(), 'code' => 'wrong-parent', 'parent_id' => $municipalityId])))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('geographic_areas')->where('id', $municipalityId)->update(['parent_id' => null]))->toThrow(QueryException::class);
}

function assertDocumentedTenantCatalogAndStockConstraints(): void
{
    $now = '2026-10-06 12:00:00.000000';
    $product = ['uuid' => (string) Str::uuid(), 'name' => 'T-shirt', 'slug' => 't-shirt', 'type' => 1, 'allows_customization' => false, 'sale_unit' => 'piece', 'status' => 1, 'is_featured' => false, 'indexable' => true, 'created_at' => $now, 'updated_at' => $now];
    $productId = DB::table('products')->insertGetId($product);
    $otherProductId = DB::table('products')->insertGetId(array_replace($product, ['uuid' => (string) Str::uuid(), 'slug' => 'other-product']));
    $variant = ['uuid' => (string) Str::uuid(), 'product_id' => $productId, 'label' => 'Red M', 'sku' => 'RED-M', 'combination_signature' => hash('sha256', 'red-m'), 'sale_price' => 1000, 'unit_cost' => 500, 'physical_stock' => 5, 'reserved_stock' => 0, 'quarantine_stock' => 0, 'low_stock_threshold' => 1, 'is_active' => true, 'position' => 0, 'created_at' => $now, 'updated_at' => $now];
    $variantId = DB::table('product_variants')->insertGetId($variant);
    expect(fn (): int => DB::table('product_variants')->where('id', $variantId)->update(['reserved_stock' => 6]))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('product_variants')->where('id', $variantId)->update(['product_id' => $otherProductId]))->toThrow(QueryException::class);
    $axis = ['uuid' => (string) Str::uuid(), 'product_id' => $productId, 'record_type' => 1, 'name' => 'Color', 'display_type' => 1, 'position' => 0, 'created_at' => $now, 'updated_at' => $now];
    $axisId = DB::table('product_options')->insertGetId($axis);
    expect(fn (): bool => DB::table('product_options')->insert(array_replace($axis, ['uuid' => (string) Str::uuid(), 'name' => ' color '])))->toThrow(QueryException::class);
    $otherAxisId = DB::table('product_options')->insertGetId(array_replace($axis, ['uuid' => (string) Str::uuid(), 'product_id' => $otherProductId]));
    $value = array_replace($axis, ['uuid' => (string) Str::uuid(), 'record_type' => 2, 'parent_id' => $axisId, 'name' => 'Red', 'identity_code' => 'RED', 'display_type' => null]);
    $valueId = DB::table('product_options')->insertGetId($value);
    $composition = ['uuid' => (string) Str::uuid(), 'product_id' => $productId, 'variant_id' => $variantId, 'option_id' => $axisId, 'value_id' => $valueId, 'created_at' => $now, 'updated_at' => $now];
    expect(fn (): bool => DB::table('variant_option_values')->insert(array_replace($composition, ['option_id' => $otherAxisId])))->toThrow(QueryException::class);
    $compositionId = DB::table('variant_option_values')->insertGetId($composition);
    DB::table('product_variants')->where('id', $variantId)->update(['used_at' => $now]);
    expect(fn (): int => DB::table('product_variants')->where('id', $variantId)->update(['combination_signature' => hash('sha256', 'new-identity')]))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('variant_option_values')->where('id', $compositionId)->delete())->toThrow(QueryException::class);
    expect(fn (): int => DB::table('product_options')->where('id', $valueId)->update(['identity_code' => 'BLUE']))->toThrow(QueryException::class);

    $order = ['uuid' => (string) Str::uuid(), 'number' => 'ORDER-1', 'data_policy_version' => 'v1', 'data_notice_acknowledged_at' => $now, 'order_type' => 1, 'channel' => 1, 'commercial_status' => 1, 'submission_key' => 'submission-1', 'submission_hash' => hash('sha256', 'submission-1'), 'lock_version' => 0, 'retention_hold' => false, 'created_at' => $now, 'updated_at' => $now];
    $orderId = DB::table('orders')->insertGetId($order);
    $otherOrderId = DB::table('orders')->insertGetId(array_replace($order, ['uuid' => (string) Str::uuid(), 'number' => 'ORDER-2', 'submission_key' => 'submission-2']));
    $revision = ['uuid' => (string) Str::uuid(), 'order_id' => $orderId, 'province_uuid' => (string) Str::uuid(), 'municipality_uuid' => (string) Str::uuid(), 'revision_number' => 1, 'currency' => 'DZD', 'country_code' => 'DZ', 'legal_seller_snapshot' => '{}', 'shipping_tax_snapshot' => '{}', 'recipient_last_name' => 'Client', 'phone' => '+213555000000', 'address' => 'Address', 'province_name' => 'Alger', 'municipality_name' => 'Alger Centre', 'delivery_mode' => 1, 'catalog_subtotal' => 1000, 'applied_subtotal' => 1000, 'customer_shipping_fee' => 300, 'shipping_discount' => 0, 'shipping_charge_bearer' => 1, 'merchant_shipping_amount' => 300, 'order_total' => 1300, 'amount_to_collect' => 1300, 'sales_terms_version' => 'v1', 'sales_terms_snapshot' => '{}', 'created_at' => $now];
    $revisionId = DB::table('order_revisions')->insertGetId($revision);
    DB::table('orders')->where('id', $orderId)->update(['current_revision_id' => $revisionId]);
    expect(fn (): int => DB::table('orders')->where('id', $otherOrderId)->update(['current_revision_id' => $revisionId]))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('order_revisions')->where('id', $revisionId)->update(['recipient_last_name' => 'Changed']))->toThrow(QueryException::class);
    expect(fn (): bool => DB::table('order_revisions')->insert(array_replace($revision, ['uuid' => (string) Str::uuid(), 'revision_number' => 2, 'return_cost_recovery_amount' => 100, 'return_cost_recovery_reason' => 'Return fee', 'order_total' => 1400, 'amount_to_collect' => 1400])))->toThrow(QueryException::class);
    $itemId = DB::table('order_items')->insertGetId(['uuid' => (string) Str::uuid(), 'revision_id' => $revisionId, 'variant_id' => $variantId, 'product_id' => $productId, 'product_name' => 'T-shirt', 'variant_name' => 'Red M', 'sku' => 'RED-M', 'quantity' => 1, 'catalog_unit_price' => 1000, 'applied_unit_price' => 1000, 'is_price_overridden' => false, 'price_origin' => 1, 'unit_cost_snapshot' => 500, 'line_total' => 1000, 'tax_snapshot' => '{}', 'created_at' => $now]);
    expect(fn (): int => DB::table('order_items')->where('id', $itemId)->update(['quantity' => 2, 'line_total' => 2000]))->toThrow(QueryException::class);
    $active = ['reservation_status' => 1, 'reserved_at' => $now, 'reservation_created_at' => $now, 'reservation_updated_at' => $now];
    expect(fn (): int => DB::table('order_items')->where('id', $itemId)->update(['reservation_status' => 1]))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('order_items')->where('id', $itemId)->update(array_replace($active, ['reservation_status' => 2, 'reservation_released_at' => $now])))->toThrow(QueryException::class);
    DB::table('order_items')->where('id', $itemId)->update($active);
    DB::table('order_items')->where('id', $itemId)->update(['reservation_status' => 2, 'reservation_released_at' => $now]);
    expect((int) DB::table('order_items')->where('id', $itemId)->value('reservation_status'))->toBe(2);
    expect(fn (): int => DB::table('order_items')->where('id', $itemId)->update(['reservation_status' => 1, 'reservation_released_at' => null]))->toThrow(QueryException::class);
    expect(fn (): int => DB::table('order_items')->where('id', $itemId)->delete())->toThrow(QueryException::class);
}

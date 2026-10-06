<?php

namespace Database\Seeders\Tenant;

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalFixtureSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

class LocalDevelopmentSeeder extends LocalFixtureSeeder
{
    private Connection $db;

    private int $ownerId;

    private int $ruleId;

    private stdClass $province;

    private stdClass $municipality;

    private stdClass $pickup;

    /** @var array<string, mixed> */
    private array $seller;

    public function run(): void
    {
        $this->assertLocalEnvironment();
        $tenant = tenancy()->tenant;

        if (! tenancy()->initialized || ! $tenant instanceof Tenant || ! str_starts_with($tenant->creation_key, 'local:')) {
            throw new LogicException('This seeder requires a registered local fixture tenant.');
        }

        $this->db = DB::connection('tenant');
        if ($this->db->table('activity_log')->where('operation_key', 'local:seed:tenant:v1')->exists()) {
            return;
        }
        if ($this->db->table('shop')->exists() || $this->db->table('products')->exists() || $this->db->table('orders')->exists()) {
            throw new LogicException('Refusing to mix local fixtures with existing shop data.');
        }

        $central = DB::connection(config('tenancy.database.central_connection'));
        $this->province = $central->table('geographic_areas')->where('type', 1)->where('code', '16')->first() ?? throw new LogicException('Missing central province.');
        $this->municipality = $central->table('geographic_areas')->where('parent_id', $this->province->id)->where('code', '1601')->first() ?? throw new LogicException('Missing central municipality.');
        $this->pickup = $central->table('pickup_points')->where('external_code', 'LOCAL-ALGER-01')->first() ?? throw new LogicException('Missing central pickup point.');
        $carrierUuid = (string) $central->table('shipping_carriers')->where('code', 'local-fixture')->value('uuid');
        $owner = $tenant->owner;
        $this->seller = [
            'format_version' => 1, 'fixture_only' => true, 'owner_uuid' => $owner->uuid,
            'legal_profile_version' => 1, 'first_name' => $owner->first_name, 'last_name' => $owner->last_name,
            'trade_name' => $tenant->shop_name, 'legal_form' => $owner->legal_form,
            'activity_nature' => $owner->activity_nature, 'nif' => null, 'nis' => null,
            'registration_number' => null, 'artisan_card_number' => null, 'legal_address' => $owner->legal_address,
            'country_code' => 'DZ', 'phone' => $owner->phone, 'email' => $owner->email, 'share_capital' => null,
        ];

        $this->db->transaction(function () use ($tenant, $owner, $carrierUuid): void {
            $localOwner = User::where('central_user_uuid', $owner->uuid)->firstOrFail();
            $localOwner->password = 'LocalTest!2026-Shop';
            $localOwner->membership_status = 1;
            $localOwner->joined_at = now();
            $localOwner->email_verified_at = now();
            $localOwner->save();
            $this->ownerId = $localOwner->id;
            $this->team($tenant);
            $this->profile($tenant);
            $variants = $this->catalog();
            $providers = $this->shipping($carrierUuid);
            $this->billingRules($tenant);
            $returns = [];

            foreach (range(1, 50) as $number) {
                $variant = $variants[($number - 1) % count($variants)];
                $return = $this->order($tenant, $number, $variant, $providers[$number === 47 ? 1 : 0], $returns);
                if ($return !== null) {
                    $returns[$number] = $return;
                }
            }

            $this->insert($this->db, 'expenses', [
                'author_id' => $this->ownerId, 'category' => 'packaging', 'label' => 'Emballages — achat fictif',
                'amount' => '1200.00', 'expense_date' => now()->subDays(30), 'status' => 2,
                'source' => 'local-fixture', 'operation_key' => 'local:expense:packaging:v1',
            ]);
            $this->audit('local:seed:tenant:v1', 'Jeu fonctionnel local créé : 20 produits, 50 commandes et scénarios de livraison/retour.', null);
        });
    }

    private function team(Tenant $tenant): void
    {
        $ownerRole = $this->insert($this->db, 'roles', [
            'name' => 'shop-owner', 'guard_name' => 'tenant', 'label' => 'Propriétaire de la boutique',
            'is_system' => true, 'is_protected' => true, 'is_super_admin' => true,
            'permission_version' => 1, 'permission_signature' => hash('sha256', '[]'),
        ]);
        $this->db->table('model_has_roles')->insert([
            'role_id' => $ownerRole, 'model_type' => 'shop_user', 'model_id' => $this->ownerId, 'assigned_at' => now(),
        ]);
        $employee = new User([
            'first_name' => 'Employé', 'last_name' => 'Essai', 'email' => 'equipe@'.$tenant->slug.'.example.test',
            'password' => 'LocalTest!2026-Team', 'locale' => 'fr',
        ]);
        $employee->membership_status = 1;
        $employee->joined_at = now();
        $employee->email_verified_at = now();
        $employee->save();
        $initialRole = null;

        foreach (['orders-manager' => ['orders.view' => 9999, 'orders.confirm' => 30], 'stock-manager' => ['stock.manage' => 9999]] as $name => $permissions) {
            $pairs = [];
            foreach ($permissions as $permission => $days) {
                $id = $this->insert($this->db, 'permissions', ['name' => $permission, 'label' => $permission, 'guard_name' => 'tenant']);
                $pairs[] = [$id, $days];
            }
            $role = $this->insert($this->db, 'roles', [
                'name' => $name, 'guard_name' => 'tenant', 'label' => $name, 'is_system' => false,
                'is_protected' => false, 'is_super_admin' => false, 'permission_version' => 1,
                'permission_signature' => hash('sha256', $this->json($pairs)),
            ]);
            $initialRole ??= $role;
            foreach ($pairs as [$permission, $days]) {
                $this->db->table('role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission, 'duration_days' => $days]);
            }
            $this->db->table('model_has_roles')->insert([
                'role_id' => $role, 'model_type' => 'shop_user', 'model_id' => $employee->id, 'assigned_at' => now(),
            ]);
        }

        $reviewPermission = $this->insert($this->db, 'permissions', ['name' => 'reviews.moderate', 'label' => 'Modérer les avis', 'guard_name' => 'tenant']);
        $this->db->table('model_has_permissions')->insert([
            'permission_id' => $reviewPermission, 'model_type' => 'shop_user', 'model_id' => $employee->id,
            'assigned_at' => now(), 'expires_at' => now()->addDays(14),
        ]);
        $this->insert($this->db, 'team_invitations', [
            'initial_role_id' => $initialRole, 'invited_by_id' => $this->ownerId,
            'token_hash' => hash('sha256', Str::random(64)), 'email' => $employee->email,
            'role_permission_version' => 1, 'expires_at' => now()->addDays(7), 'accepted_at' => now(),
        ]);
    }

    private function profile(Tenant $tenant): void
    {
        $shop = $this->insert($this->db, 'shop', [
            'tenant_uuid' => $tenant->uuid, 'singleton' => 1, 'central_profile_version' => $tenant->profile_version,
            'shop_name' => $tenant->shop_name, 'description' => 'Boutique fictive pour les essais locaux.',
            'about' => 'Catalogue et commandes de démonstration, aucune activité réelle.', 'business_type' => 'retail',
            'contact_email' => $tenant->owner->email, 'locale' => 'fr', 'currency' => 'DZD',
            'timezone' => 'Africa/Algiers', 'theme_code' => 'standard',
            'colors' => $this->json(['primary' => '#1d4ed8', 'background' => '#ffffff']),
            'shipping_tax_configuration' => $this->json(['fixture_only' => true, 'taxes' => $this->taxSnapshot(300)['taxes']]),
            'cart_lifetime_days' => 7,
        ]);
        $this->insert($this->db, 'shop_addresses', [
            'shop_id' => $shop, 'record_type' => 1, 'label' => 'Magasin fictif', 'position' => 0,
            'province_uuid' => $this->province->uuid, 'municipality_uuid' => $this->municipality->uuid,
            'is_primary' => true, 'visible' => true,
            'payload' => $this->json(['address' => 'Adresse fictive, Alger Centre', 'map_url' => null]),
        ]);
        $this->insert($this->db, 'shop_addresses', [
            'shop_id' => $shop, 'record_type' => 2, 'label' => 'Instagram — exemple', 'position' => 1,
            'is_primary' => false, 'visible' => false,
            'payload' => $this->json(['platform' => 'instagram', 'url' => 'https://example.test/local-shop']),
        ]);
        $this->insert($this->db, 'content_pages', [
            'page_kind' => 1, 'slug' => 'conditions-essai', 'type' => 'sales_terms', 'title' => 'Conditions fictives locales',
            'content' => $this->json(['version' => 'local-v1', 'text' => 'Commande confirmée par téléphone, paiement à réception. Essai uniquement.']),
            'indexable' => false, 'is_published' => true, 'published_at' => now(), 'version' => 1,
        ]);
    }

    /** @return list<stdClass> */
    private function catalog(): array
    {
        $category = $this->insert($this->db, 'categories', [
            'record_type' => 1, 'name' => 'Vêtements', 'slug' => 'vetements', 'position' => 0, 'is_active' => true,
        ]);
        $child = $this->insert($this->db, 'categories', [
            'parent_id' => $category, 'record_type' => 1, 'name' => 'T-shirts', 'slug' => 't-shirts', 'position' => 1, 'is_active' => true,
        ]);
        $tag = $this->insert($this->db, 'categories', ['record_type' => 2, 'name' => 'Essai local', 'slug' => 'essai-local', 'position' => 0, 'is_active' => true]);
        $variants = [];

        foreach (range(1, 20) as $number) {
            $custom = $number % 5 === 0;
            $product = $this->insert($this->db, 'products', [
                'category_id' => $child, 'name' => 'T-shirt coton '.$number, 'slug' => 't-shirt-coton-'.$number,
                'short_description' => 'Produit fictif '.$number, 'description' => 'Fiche produit pour les essais locaux.',
                'type' => $custom ? 2 : 1, 'allows_customization' => $custom,
                'customization_instructions' => $custom ? 'Décrivez le texte à imprimer.' : null,
                'sale_unit' => 'piece', 'status' => 2, 'published_at' => now()->subDays(70),
                'is_featured' => $number <= 4, 'indexable' => false,
            ]);
            $this->insert($this->db, 'product_tags', ['product_id' => $product, 'tag_id' => $tag]);
            $page = $this->insert($this->db, 'content_pages', [
                'product_id' => $product, 'page_kind' => 2, 'slug' => 'offre-t-shirt-'.$number,
                'title' => 'Offre T-shirt '.$number, 'content' => $this->json(['blocks' => [['type' => 'text', 'text' => 'Offre fictive locale']]]),
                'indexable' => false, 'is_published' => true, 'published_at' => now()->subDays(70), 'version' => 1,
            ]);
            $axis = $this->insert($this->db, 'product_options', [
                'product_id' => $product, 'record_type' => 1, 'name' => 'Couleur', 'display_type' => 2, 'position' => 0,
            ]);
            foreach (['Rouge' => '#dc2626', 'Bleu' => '#2563eb'] as $color => $hex) {
                $value = $this->insert($this->db, 'product_options', [
                    'product_id' => $product, 'parent_id' => $axis, 'record_type' => 2,
                    'name' => $color, 'identity_code' => strtoupper($color), 'color_hex' => $hex, 'position' => $color === 'Rouge' ? 0 : 1,
                ]);
                $variant = $this->insert($this->db, 'product_variants', [
                    'product_id' => $product, 'label' => $color, 'sku' => 'LOCAL-'.$number.'-'.strtoupper($color),
                    'combination_signature' => hash('sha256', $this->json([[$axis, $value]])),
                    'sale_price' => 1000 + $number * 100, 'unit_cost' => 500 + $number * 50,
                    'physical_stock' => 0, 'reserved_stock' => 0, 'quarantine_stock' => 0,
                    'low_stock_threshold' => 3, 'is_active' => true, 'position' => $color === 'Rouge' ? 0 : 1,
                    'tax_configuration' => $this->json(['fixture_only' => true, 'taxes' => $this->taxSnapshot(1000 + $number * 100)['taxes']]),
                ]);
                $this->insert($this->db, 'variant_option_values', ['product_id' => $product, 'variant_id' => $variant, 'option_id' => $axis, 'value_id' => $value]);
                $this->moveStock($variant, 1, 30, 0, 0, null, null, 'opening');
                $record = $this->db->table('product_variants')->where('id', $variant)->first() ?? throw new LogicException('Missing variant.');
                $record->product_name = 'T-shirt coton '.$number;
                $record->sales_page_id = $page;
                $record->allows_customization = $custom;
                $record->options_snapshot = [['option' => 'Couleur', 'value' => $color]];
                $variants[] = $record;
            }
            if ($number <= 3) {
                $this->insert($this->db, 'product_promotions', [
                    'product_id' => $product, 'name' => 'Promotion fictive future', 'discount_type' => 1, 'value' => 10,
                    'minimum_quantity' => 1, 'started_at' => now()->addDays(7), 'ended_at' => now()->addDays(14),
                    'priority' => 1, 'is_active' => true,
                ]);
            }
        }

        return $variants;
    }

    /** @return list<array{provider_id: int, account_id: int, return_rate_id: int, return_amount: int}> */
    private function shipping(string $carrierUuid): array
    {
        $providers = [];
        foreach ([300, 0] as $index => $returnAmount) {
            $account = $this->insert($this->db, 'carrier_accounts', [
                'created_by_id' => $this->ownerId, 'carrier_uuid' => $carrierUuid,
                'label' => $returnAmount === 0 ? 'Contrat fictif — retour gratuit' : 'Contrat fictif — retour payant',
                'adapter' => 'local-fixture', 'is_active' => false,
            ]);
            $provider = $this->insert($this->db, 'shipping_providers', [
                'carrier_account_id' => $account, 'type' => 1, 'name' => 'Transporteur fictif '.($index + 1),
                'reference_configuration' => $this->json(['fixture_only' => true, 'no_external_calls' => true]), 'is_active' => false,
            ]);
            $returnRate = $this->insert($this->db, 'shipping_rates', [
                'carrier_account_id' => $account, 'created_by_id' => $this->ownerId,
                'record_type' => 3, 'amount' => $returnAmount, 'source' => 1,
                'starts_at' => now()->subDays(70), 'is_active' => true,
            ]);
            foreach ([1, 2] as $mode) {
                $this->insert($this->db, 'shipping_rates', [
                    'provider_id' => $provider, 'province_uuid' => $this->province->uuid,
                    'record_type' => 2, 'delivery_mode' => $mode, 'service_type' => 1, 'amount' => 300,
                    'source' => 1, 'retrieved_at' => now(), 'is_active' => true,
                ]);
            }
            $providers[] = ['provider_id' => $provider, 'account_id' => $account, 'return_rate_id' => $returnRate, 'return_amount' => $returnAmount];
        }
        $this->insert($this->db, 'shipping_rates', [
            'province_uuid' => $this->province->uuid, 'record_type' => 1, 'delivery_mode' => 1,
            'service_type' => 1, 'amount' => 300, 'is_active' => true,
        ]);
        $this->insert($this->db, 'free_shipping_rules', [
            'name' => 'Livraison offerte — seuil fictif', 'minimum_cart_amount' => 10000, 'priority' => 1, 'is_active' => true,
        ]);

        return $providers;
    }

    private function billingRules(Tenant $tenant): void
    {
        $this->ruleId = $this->insert($this->db, 'billing_rules', [
            'record_type' => 2, 'code' => 'local-functional', 'version' => 1,
            'trigger_event' => 'shipment_delivered', 'return_resend_rule' => 'new_unpaid_order',
            'numbering_scope' => 'shop', 'parameters' => $this->json(['fixture_only' => true]), 'policy_status' => 1,
        ]);
        foreach ([1, 2] as $type) {
            $this->insert($this->db, 'billing_rules', [
                'record_type' => 1, 'document_type' => $type, 'fiscal_year' => now()->year,
                'shop_prefix' => $tenant->document_prefix.'-LOCAL-'.($type === 1 ? 'INV' : 'CR'), 'next_number' => 1,
            ]);
        }
    }

    /**
     * @param  array{provider_id: int, account_id: int, return_rate_id: int, return_amount: int}  $provider
     * @param  array<int, array{order_id: int, return_id: int}>  $returns
     * @return array{order_id: int, return_id: int}|null
     */
    private function order(Tenant $tenant, int $number, stdClass $variant, array $provider, array $returns): ?array
    {
        $created = CarbonImmutable::instance(now())->subDays(60)->addDays($number);
        $quantity = 1 + $number % 3;
        $price = (int) $variant->sale_price;
        $subtotal = $price * $quantity;
        $recovery = $number === 48 ? 300 : 0;
        $total = $subtotal + 300 + $recovery;
        $isResend = in_array($number, [48, 49], true);
        $isConfirmed = $number > 10 && ! $isResend;
        $isShipped = $number > 20 && ! $isResend;
        $isDelivered = ($number >= 31 && $number <= 45) || $number === 50;
        $isReturn = in_array($number, [46, 47], true);
        $pickup = $number % 4 === 0;
        $key = 'local:order:'.$number;
        $visitor = $this->insert($this->db, 'visitors', [
            'token_hash' => hash('sha256', Str::random(64)), 'first_visited_at' => $created,
            'last_visited_at' => $created->addMinutes(10), 'expires_at' => $created->addDays(90),
        ]);
        $session = $this->insert($this->db, 'visit_sessions', [
            'visitor_id' => $visitor, 'started_at' => $created, 'last_activity_at' => $created->addMinutes(10),
            'ended_at' => $created->addMinutes(10), 'entry_path' => '/produits/local', 'source' => 'local-fixture',
            'device_type' => $number % 2 === 0 ? 2 : 1,
        ]);
        $cart = $this->insert($this->db, 'carts', [
            'visitor_id' => $visitor, 'status' => 3, 'last_activity_at' => $created->addMinutes(10),
            'expires_at' => $created->addDays(7), 'converted_at' => $created->addMinutes(10),
        ]);
        $customization = $variant->allows_customization ? 'Texte fictif à imprimer : Essai '.$number : null;
        $this->insert($this->db, 'cart_items', [
            'cart_id' => $cart, 'product_id' => $variant->product_id, 'variant_id' => $variant->id,
            'sales_page_id' => $variant->sales_page_id, 'quantity' => $quantity,
            'customization_text' => $customization, 'customization_signature' => hash('sha256', $customization ?? ''),
        ]);
        $this->insert($this->db, 'navigation_events', [
            'session_id' => $session, 'product_id' => $variant->product_id, 'variant_id' => $variant->id,
            'sales_page_id' => $variant->sales_page_id, 'cart_id' => $cart, 'type' => 'checkout_submit',
            'path' => '/commande', 'quantity' => $quantity, 'occurred_at' => $created->addMinutes(10),
            'received_at' => $created->addMinutes(10),
        ], false);
        $original = $isResend ? $returns[$number === 48 ? 46 : 47] : null;
        $recipient = [
            'recipient_first_name' => ['Amine', 'Sarah', 'Yacine', 'Lina'][$number % 4],
            'recipient_last_name' => 'Client fictif '.$number,
            'phone' => '+213555'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'email' => 'client'.$number.'@example.test', 'address' => 'Adresse fictive '.$number.', Alger Centre',
        ];
        if ($original !== null) {
            $originalRevision = $this->db->table('orders as o')->join('order_revisions as r', 'r.id', '=', 'o.current_revision_id')
                ->where('o.id', $original['order_id'])->first(['r.recipient_first_name', 'r.recipient_last_name', 'r.phone', 'r.email', 'r.address'])
                ?? throw new LogicException('Missing original recipient for an unpaid resend.');
            $recipient = (array) $originalRevision;
        }
        $order = $this->insert($this->db, 'orders', [
            'visitor_id' => $visitor, 'cart_id' => $cart, 'original_session_id' => $session,
            'original_sales_page_id' => $variant->sales_page_id,
            'original_order_id' => $original['order_id'] ?? null, 'original_return_id' => $original['return_id'] ?? null,
            'number' => $tenant->document_prefix.'-LOCAL-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'data_policy_version' => 'local-v1', 'data_notice_acknowledged_at' => $created,
            'order_type' => $isResend ? 4 : 1, 'channel' => $isResend ? 2 : 1, 'commercial_status' => 1,
            'submission_key' => $key, 'submission_hash' => hash('sha256', $this->json([$key, $variant->uuid, $quantity, $customization])),
            'lock_version' => 0, 'retention_hold' => false, 'created_at' => $created, 'updated_at' => $created,
        ]);
        $revision = $this->insert($this->db, 'order_revisions', [
            'order_id' => $order, 'author_id' => $isResend ? $this->ownerId : null, 'revision_number' => 1,
            'province_uuid' => $this->province->uuid, 'municipality_uuid' => $this->municipality->uuid,
            'currency' => 'DZD', 'country_code' => 'DZ', 'legal_seller_snapshot' => $this->json($this->seller),
            'shipping_tax_snapshot' => $this->json($this->taxSnapshot(300 + $recovery)),
            ...$recipient,
            'province_name' => $this->province->name_fr, 'municipality_name' => $this->municipality->name_fr, 'postal_code' => '16000',
            'delivery_mode' => $pickup ? 2 : 1, 'pickup_point_uuid' => $pickup ? $this->pickup->uuid : null,
            'pickup_point_snapshot' => $pickup ? $this->json(['uuid' => $this->pickup->uuid, 'name' => $this->pickup->name, 'address' => $this->pickup->address, 'fixture_only' => true]) : null,
            'catalog_subtotal' => $subtotal, 'applied_subtotal' => $subtotal, 'customer_shipping_fee' => 300,
            'shipping_discount' => 0, 'shipping_charge_bearer' => 1, 'merchant_shipping_amount' => 300,
            'order_total' => $total, 'amount_to_collect' => $total, 'return_cost_recovery_amount' => $recovery,
            'return_cost_recovery_reason' => $recovery > 0 ? 'Ajout manuel des 300 DA du premier retour payant — essai local' : null,
            'customer_note' => $customization, 'sales_terms_version' => 'local-v1',
            'sales_terms_snapshot' => $this->json(['version' => 'local-v1', 'text' => 'Conditions fictives locales, paiement à réception.']),
            'created_at' => $created,
        ], false);
        $item = $this->insert($this->db, 'order_items', [
            'revision_id' => $revision, 'product_id' => $variant->product_id, 'variant_id' => $variant->id,
            'sales_page_id' => $variant->sales_page_id, 'product_name' => $variant->product_name,
            'variant_name' => $variant->label, 'sku' => $variant->sku, 'options_snapshot' => $this->json($variant->options_snapshot),
            'customization_text' => $customization, 'quantity' => $quantity,
            'catalog_unit_price' => $price, 'applied_unit_price' => $price, 'is_price_overridden' => false,
            'price_origin' => 1, 'unit_cost_snapshot' => $variant->unit_cost, 'line_total' => $subtotal,
            'tax_snapshot' => $this->json($this->taxSnapshot($subtotal)), 'created_at' => $created,
        ], false);
        $this->db->table('orders')->where('id', $order)->update(['current_revision_id' => $revision]);
        $this->insert($this->db, 'sales_terms_acceptances', [
            'order_id' => $order, 'revision_id' => $revision, 'operation_key' => 'local:terms:'.$number,
            'sales_terms_version' => 'local-v1', 'terms_hash' => hash('sha256', 'Conditions fictives locales, paiement à réception.'),
            'accepted_at' => $created, 'acceptance_mode' => $isResend ? 2 : 1,
        ], false);
        $this->insert($this->db, 'order_history', [
            'order_id' => $order, 'next_revision_id' => $revision, 'action' => 'order.submitted',
            'new_status' => 1, 'correlation_id' => (string) Str::uuid(), 'origin' => 2, 'created_at' => $created,
        ], false);

        if (! $isConfirmed) {
            return null;
        }

        $validated = $created->addMinutes(30);
        $this->db->table('orders')->where('id', $order)->update([
            'confirmed_revision_id' => $revision, 'validated_at' => $validated,
            'operationally_confirmed_by_id' => $this->ownerId, 'operationally_confirmed_at' => $validated,
            'commercial_status' => 2, 'lock_version' => 1, 'updated_at' => $validated,
        ]);
        $this->db->table('order_items')->where('id', $item)->update([
            'reservation_status' => 1, 'reserved_at' => $validated,
            'reservation_created_at' => $validated, 'reservation_updated_at' => $validated,
        ]);
        $this->moveStock((int) $variant->id, 3, 0, $quantity, 0, $item, null, 'reserve', $validated);
        $this->db->table('product_variants')->where('id', $variant->id)->whereNull('used_at')->update(['used_at' => $validated]);
        $this->audit('local:confirm:'.$number, 'Clic Valider après confirmation téléphonique — simulation locale.', $order, $validated);

        if (! $isShipped) {
            return null;
        }

        $shippedAt = $validated->addDay();
        $deliveredAt = $isDelivered ? $shippedAt->addDays(2) : null;
        $shipment = $this->insert($this->db, 'shipments', [
            'order_id' => $order, 'shipped_revision_id' => $revision, 'provider_id' => $provider['provider_id'],
            'assigned_by_id' => $this->ownerId, 'delivery_mode' => $pickup ? 2 : 1,
            'pickup_point_uuid' => $pickup ? $this->pickup->uuid : null,
            'status' => $isReturn ? 8 : ($isDelivered ? 6 : 4), 'tracking' => 'LOCAL-'.$tenant->id.'-'.$number,
            'merchant_reference' => 'LOCAL-'.$tenant->uuid.'-'.$number, 'cod_amount' => $total,
            'estimated_cost' => 300, 'is_fragile' => false, 'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt, 'created_at' => $shippedAt, 'updated_at' => $deliveredAt ?? $shippedAt,
        ]);
        $this->db->table('order_items')->where('id', $item)->update(['reservation_status' => 3, 'reservation_updated_at' => $shippedAt]);
        $this->moveStock((int) $variant->id, 5, -$quantity, -$quantity, 0, $item, null, 'ship', $shippedAt);
        $collection = $this->insert($this->db, 'collections', [
            'shipment_id' => $shipment, 'declared_status' => $isDelivered ? 5 : ($isReturn ? 2 : 1),
            'expected_amount' => $total, 'declared_collected_amount' => $isDelivered ? $total : ($isReturn ? 0 : null),
            'collected_at' => $deliveredAt, 'source' => 'local-fixture', 'reconciled_at' => $deliveredAt,
        ]);
        $event = $this->insert($this->db, 'shipment_events', [
            'shipment_id' => $shipment, 'actor_id' => $this->ownerId,
            'logistics_status' => $isReturn ? 8 : ($isDelivered ? 6 : 4), 'event_type' => 'local.simulated_status',
            'adapter_version' => 'local-v1', 'payload_hash' => hash('sha256', 'local:'.$number),
            'observed_at' => $deliveredAt ?? $shippedAt, 'occurred_at' => $deliveredAt ?? $shippedAt,
            'source' => 1, 'deduplication_key' => 'local:event:'.$number,
        ], false);

        if ($isDelivered) {
            $this->settleDelivery($shipment, $collection, $provider['provider_id'], $total, $subtotal, $deliveredAt);
            $invoice = $this->invoice($order, $revision, $item, 1, $total, null, null);
            $this->insert($this->db, 'billing_obligations', [
                'order_id' => $order, 'revision_id' => $revision, 'billing_rule_id' => $this->ruleId,
                'invoice_id' => $invoice, 'operation_key' => 'local:obligation:'.$number, 'event_id' => $event,
                'rule_snapshot' => $this->json(['fixture_only' => true, 'version' => 1]),
                'event_type' => 'shipment_delivered', 'triggered_at' => $deliveredAt, 'document_type' => 1, 'status' => 3, 'attempts_count' => 1,
            ]);
            $this->insert($this->db, 'product_reviews', [
                'product_id' => $variant->product_id, 'visitor_id' => $visitor, 'order_item_id' => $item,
                'moderated_by_id' => $this->ownerId, 'display_name' => 'Client fictif '.$number, 'note' => 4 + $number % 2,
                'comment' => 'Avis fictif pour tester l’affichage et la modération.', 'moderation_status' => 2,
                'moderated_at' => now(), 'published_at' => now(),
            ]);
            if ($number === 50) {
                $this->refund($order, $revision, $item, $shipment, $invoice, $price);
            }
        }

        if ($isReturn) {
            return ['order_id' => $order, 'return_id' => $this->returnParcel($order, $revision, $item, $shipment, $variant, $quantity, $provider, $shippedAt)];
        }

        return null;
    }

    private function moveStock(int $variantId, int $type, int $physical, int $reserved, int $quarantine, ?int $itemId, ?int $returnItemId, string $phase, ?CarbonImmutable $at = null): void
    {
        $variant = $this->db->table('product_variants')->where('id', $variantId)->lockForUpdate()->first() ?? throw new LogicException('Missing stock variant.');
        $sequence = 1 + (int) $this->db->table('stock_movements')->where('variant_id', $variantId)->max('variant_sequence');
        $this->insert($this->db, 'stock_movements', [
            'variant_id' => $variantId, 'order_item_id' => $itemId, 'return_item_id' => $returnItemId, 'actor_id' => $this->ownerId,
            'variant_sequence' => $sequence, 'type' => $type, 'physical_delta' => $physical, 'reserved_delta' => $reserved,
            'quarantine_delta' => $quarantine, 'return_received_delta' => $type === 6 ? $quarantine : 0,
            'return_restocked_delta' => $type === 7 ? $physical : 0, 'return_lost_delta' => 0, 'return_missing_delta' => 0,
            'physical_before' => $variant->physical_stock, 'physical_after' => $variant->physical_stock + $physical,
            'reserved_before' => $variant->reserved_stock, 'reserved_after' => $variant->reserved_stock + $reserved,
            'quarantine_before' => $variant->quarantine_stock, 'quarantine_after' => $variant->quarantine_stock + $quarantine,
            'unit_cost_snapshot' => $variant->unit_cost, 'loss_amount' => 0,
            'operation_key' => 'local:stock:'.$variantId.':'.$sequence.':'.$phase,
            'correlation_id' => (string) Str::uuid(), 'created_at' => $at ?? now()->subDays(70),
        ], false);
        $this->db->table('product_variants')->where('id', $variantId)->update([
            'physical_stock' => $variant->physical_stock + $physical, 'reserved_stock' => $variant->reserved_stock + $reserved,
            'quarantine_stock' => $variant->quarantine_stock + $quarantine,
        ]);
    }

    private function audit(string $key, string $description, ?int $orderId, ?CarbonImmutable $at = null): void
    {
        $this->insert($this->db, 'activity_log', [
            'operation_key' => $key, 'log_name' => 'local-fixtures', 'description' => $description,
            'subject_type' => $orderId !== null ? 'order' : null, 'subject_id' => $orderId,
            'causer_type' => 'shop_user', 'causer_id' => $this->ownerId, 'origin' => 2,
            'correlation_id' => (string) Str::uuid(), 'performed_at' => $at ?? now(), 'created_at' => $at ?? now(),
        ]);
    }

    private function settleDelivery(int $shipment, int $collection, int $provider, int $total, int $subtotal, CarbonImmutable $at): void
    {
        $this->insert($this->db, 'collection_entries', [
            'collection_id' => $collection, 'verified_by_id' => $this->ownerId,
            'amount' => $total, 'collected_at' => $at, 'verified_at' => $at,
            'reference' => 'LOCAL-COD-'.$shipment, 'reason' => 'Encaissement simulé pour le test local',
            'operation_key' => 'local:collection:'.$shipment,
        ], false);
        $this->insert($this->db, 'carrier_fees', [
            'shipment_id' => $shipment, 'provider_id' => $provider, 'fee_type' => 1, 'payer' => 1,
            'settlement_mode' => 1, 'amount' => 300, 'status' => 3, 'triggered_at' => $at,
            'recognized_at' => $at, 'date_source' => 'local-fixture', 'operation_key' => 'local:fee:delivery:'.$shipment,
        ]);
        $accountId = (int) $this->db->table('shipping_providers')->where('id', $provider)->value('carrier_account_id');
        $batch = $this->insert($this->db, 'carrier_remittance_batches', [
            'carrier_account_id' => $accountId, 'operation_key' => 'local:batch:'.$shipment,
            'computed_shop_net_amount' => $subtotal, 'status' => 1,
        ]);
        $statement = $this->insert($this->db, 'remittance_statements', [
            'provider_id' => $provider, 'carrier_remittance_batch_id' => $batch,
            'number' => 'LOCAL-REMIT-'.$shipment, 'type' => 1, 'status' => 1,
            'gross_amount' => $total, 'fee_amount' => 300, 'expected_net_amount' => $subtotal,
            'operation_key' => 'local:remittance:'.$shipment,
        ]);
        $this->insert($this->db, 'carrier_settlement_lines', [
            'provider_id' => $provider, 'remittance_statement_id' => $statement, 'collection_id' => $collection,
            'shipment_id' => $shipment, 'record_type' => 1, 'amount' => $subtotal,
            'operation_key' => 'local:remittance-line:'.$shipment,
        ], false);
        $proof = $this->pdf($this->db, 'remittance_statement', $statement, $this->ownerId, 'Simulated remittance '.$subtotal.' DZD');
        $this->db->table('remittance_statements')->where('id', $statement)->update([
            'proof_media_id' => $proof, 'validated_by_id' => $this->ownerId, 'status' => 4,
            'received_net_amount' => $subtotal, 'received_at' => $at, 'reconciled_at' => $at,
        ]);
        $this->db->table('carrier_remittance_batches')->where('id', $batch)->update([
            'proof_media_id' => $proof, 'validated_by_id' => $this->ownerId, 'status' => 2,
            'reported_account_net_amount' => $subtotal, 'verified_net_amount' => $subtotal, 'received_at' => $at,
        ]);
    }

    /** @param array{provider_id: int, account_id: int, return_rate_id: int, return_amount: int} $provider */
    private function returnParcel(int $order, int $revision, int $item, int $shipment, stdClass $variant, int $quantity, array $provider, CarbonImmutable $shipped): int
    {
        $received = $shipped->addDays(4);
        $return = $this->insert($this->db, 'order_returns', [
            'shipment_id' => $shipment, 'order_id' => $order, 'shipped_revision_id' => $revision,
            'received_by_id' => $this->ownerId, 'reason' => 5, 'detail' => 'Le client demande un autre produit avant paiement — simulation locale.',
            'status' => 3, 'requested_at' => $shipped->addDays(2), 'received_at' => $received,
        ]);
        $returnItem = $this->insert($this->db, 'return_items', [
            'return_id' => $return, 'order_item_id' => $item, 'shipped_revision_id' => $revision,
            'variant_id' => $variant->id, 'expected_quantity' => $quantity, 'received_quantity' => 0,
            'restocked_quantity' => 0, 'lost_quantity' => 0, 'quarantined_quantity' => 0, 'documented_missing_quantity' => 0,
            'unit_cost_snapshot' => $variant->unit_cost,
        ]);
        $this->moveStock((int) $variant->id, 6, 0, 0, $quantity, $item, $returnItem, 'return-receive', $received);
        $this->moveStock((int) $variant->id, 7, $quantity, 0, -$quantity, $item, $returnItem, 'return-restock', $received->addHour());
        $this->db->table('return_items')->where('id', $returnItem)->update([
            'received_quantity' => $quantity, 'restocked_quantity' => $quantity,
            'inspected_by_id' => $this->ownerId, 'inspected_at' => $received->addHour(),
        ]);
        $this->db->table('order_returns')->where('id', $return)->update(['status' => 5, 'closed_at' => $received->addHour()]);
        $this->insert($this->db, 'carrier_fees', [
            'shipment_id' => $shipment, 'provider_id' => $provider['provider_id'], 'return_id' => $return,
            'carrier_account_id' => $provider['account_id'], 'source_rate_id' => $provider['return_rate_id'],
            'rate_snapshot' => $this->json(['amount' => $provider['return_amount'].'.00', 'fixture_only' => true, 'source' => 'local-contract']),
            'fee_type' => 2, 'payer' => $provider['return_amount'] === 0 ? 4 : 2,
            'settlement_mode' => $provider['return_amount'] === 0 ? 4 : 2,
            'amount' => $provider['return_amount'], 'status' => 2,
            'triggered_at' => $received, 'recognized_at' => $received, 'date_source' => 'local-fixture',
            'operation_key' => 'local:fee:return:'.$return,
        ]);

        return $return;
    }

    private function invoice(int $order, int $revision, int $itemId, int $type, int $amount, ?int $originalInvoice, ?int $incident): int
    {
        $revisionRow = $this->db->table('order_revisions')->where('id', $revision)->first() ?? throw new LogicException('Missing revision.');
        $item = $this->db->table('order_items')->where('id', $itemId)->first() ?? throw new LogicException('Missing item.');
        $shipping = $type === 1 ? 300 : 0;
        $productAmount = $amount - $shipping;
        $sequence = $this->db->table('billing_rules')->where('record_type', 1)->where('document_type', $type)->lockForUpdate()->first() ?? throw new LogicException('Missing invoice sequence.');
        $number = $sequence->shop_prefix.'-'.now()->year.'-'.$sequence->next_number;
        $client = [
            'type' => 'particulier', 'nom' => $revisionRow->recipient_last_name, 'prenom' => $revisionRow->recipient_first_name,
            'adresse' => $revisionRow->address, 'country_code' => 'DZ', 'phone' => $revisionRow->phone, 'email' => $revisionRow->email,
        ];
        $items = [[
            'order_item_id' => $itemId, 'designation' => $item->product_name.' '.$item->variant_name,
            'quantite' => $type === 1 ? $item->quantity : 1, 'options' => json_decode($item->options_snapshot, true, flags: JSON_THROW_ON_ERROR),
            'personnalisation' => $item->customization_text,
            'net_unit_price' => $type === 1 ? (string) $item->applied_unit_price : $productAmount.'.00',
            'prix_unitaire_ttc' => $type === 1 ? (string) $item->applied_unit_price : $productAmount.'.00', 'net_discount' => '0.00',
            'total_ht' => $productAmount.'.00', 'taxes' => $this->taxSnapshot($productAmount)['taxes'],
            'total_taxes' => '0.00', 'total_ttc' => $productAmount.'.00', 'motif_exoneration' => 'Simulation locale uniquement.',
        ]];
        $totals = [
            'devise' => 'DZD', 'total_produits_ht' => $productAmount.'.00', 'total_taxes_produits' => '0.00',
            'livraison_ht' => $shipping.'.00', 'taxes_livraison' => $this->taxSnapshot($shipping)['taxes'],
            'livraison_taxes' => '0.00', 'livraison_ttc' => $shipping.'.00', 'remise_livraison_ttc' => '0.00',
            'total_ht' => $amount.'.00', 'total_taxes' => '0.00', 'total_ttc' => $amount.'.00',
            'total_ttc_lettres' => $amount.' dinars algériens (simulation)', 'mode_paiement' => 'COD',
            'date_reglement' => null, 'echeance' => null, 'regle_arrondi' => 'half_up_cent', 'version_calcul' => 'local-v1', 'fixture_only' => true,
        ];
        $invoice = $this->insert($this->db, 'invoices', [
            'order_id' => $order, 'revision_id' => $revision, 'original_invoice_id' => $originalInvoice,
            'issued_by_id' => $this->ownerId, 'incident_id' => $incident, 'document_type' => $type,
            'snapshot_format_version' => 1, 'currency' => 'DZD', 'status' => 1,
            'seller_snapshot' => $this->json($this->seller), 'client_snapshot' => $this->json($client),
            'items_snapshot' => $this->json($items), 'totals_snapshot' => $this->json($totals),
            'operation_key' => 'local:invoice:'.$order.':'.$type,
            'document_reason' => $type === 2 ? 'Réduction de prix fictive de 100 DA' : null,
        ]);
        $pdf = $this->pdf($this->db, 'invoice', $invoice, $this->ownerId, $number.' - '.$amount.' DZD');
        $this->db->table('invoices')->where('id', $invoice)->update([
            'sequence_id' => $sequence->id, 'fiscal_year' => now()->year, 'sequence_number' => $sequence->next_number,
            'number' => $number, 'media_id' => $pdf, 'status' => 2, 'issued_at' => now(),
        ]);
        $this->db->table('billing_rules')->where('id', $sequence->id)->increment('next_number');
        if ($type === 1) {
            $orderPdf = $this->pdf($this->db, 'order', $order, $this->ownerId, 'Local order '.$order);
            $this->insert($this->db, 'order_documents', [
                'order_id' => $order, 'revision_id' => $revision, 'media_id' => $orderPdf,
                'generated_by_id' => $this->ownerId, 'number' => 'LOCAL-ORDER-'.$order, 'document_version' => 1,
                'issuer_snapshot' => $this->json($this->seller), 'generated_at' => now(),
            ], false);
        }

        return $invoice;
    }

    private function refund(int $order, int $revision, int $item, int $shipment, int $originalInvoice, int $unitPrice): void
    {
        $incident = $this->insert($this->db, 'order_incidents', [
            'order_id' => $order, 'shipment_id' => $shipment, 'shipped_revision_id' => $revision, 'order_item_id' => $item,
            'opened_by_id' => $this->ownerId, 'validated_by_id' => $this->ownerId,
            'operation_key' => 'local:incident:'.$order, 'affected_quantity' => 1,
            'eligible_product_amount' => $unitPrice, 'eligible_shipping_amount' => 0,
            'status' => 2, 'reason' => 'Réduction fictive de 100 DA après réclamation.', 'validated_at' => now(),
        ]);
        $this->insert($this->db, 'order_incident_details', [
            'incident_id' => $incident, 'author_id' => $this->ownerId, 'type' => 6, 'quantity' => 1,
            'reason' => 'Réclamation fictive, réduction de prix acceptée.',
        ]);
        $correction = $this->insert($this->db, 'commercial_corrections', [
            'order_id' => $order, 'source_revision_id' => $revision, 'incident_id' => $incident, 'actor_id' => $this->ownerId,
            'operation_key' => 'local:correction:'.$order, 'correction_type' => 2, 'status' => 1,
            'non_product_revenue_delta' => 0, 'non_product_kind' => 1,
            'effective_at' => now(), 'recorded_at' => now(), 'reason' => 'Réduction fictive de 100 DA',
        ], false);
        $this->insert($this->db, 'commercial_correction_lines', [
            'correction_id' => $correction, 'source_revision_id' => $revision, 'order_item_id' => $item,
            'affected_quantity' => 1, 'reference_sale_amount' => $unitPrice, 'revenue_delta' => -100, 'sold_cost_delta' => 0,
            'detailed_reason' => 'Le remboursement est distinct de cette correction du revenu.',
        ], false);
        $this->db->table('commercial_corrections')->where('id', $correction)->update(['status' => 2]);
        $credit = $this->invoice($order, $revision, $item, 2, 100, $originalInvoice, $incident);
        $proof = $this->pdf($this->db, 'order_incident', $incident, $this->ownerId, 'Simulated customer refund 100 DZD');
        $this->insert($this->db, 'customer_adjustments', [
            'order_id' => $order, 'incident_id' => $incident, 'credit_note_id' => $credit,
            'validated_by_id' => $this->ownerId, 'proof_media_id' => $proof,
            'compensated_quantity' => 1, 'amount_kind' => 1, 'type' => 1, 'amount' => 100, 'status' => 3,
            'performed_at' => now(), 'reference' => 'LOCAL-REFUND-'.$order,
            'reason' => 'Remboursement fictif de la réduction', 'operation_key' => 'local:refund:'.$order,
        ]);
        $this->audit('local:refund-audit:'.$order, 'Réduction de revenu et remboursement de trésorerie fictifs, comptés séparément.', $order);
    }
}

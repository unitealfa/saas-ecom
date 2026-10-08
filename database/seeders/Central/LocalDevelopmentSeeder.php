<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Tenants\StatusEnum;
use App\Models\Central\Country;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalFixtureSeeder;
use Database\Seeders\Tenant\LocalDevelopmentSeeder as ShopSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class LocalDevelopmentSeeder extends LocalFixtureSeeder
{
    public function run(): void
    {
        $this->assertLocalEnvironment();

        if (tenancy()->initialized) {
            throw new LogicException('Run the local seeder in the central context.');
        }

        $this->call(CountrySeeder::class);
        $connection = DB::connection(config('tenancy.database.central_connection'));

        if (! $connection->table('activity_log')->where('operation_key', 'local:references:v1')->exists()) {
            if (User::exists() || Tenant::withTrashed()->exists()) {
                throw new LogicException('Local fixtures require a fresh central database or an existing fixture marker.');
            }

            $connection->transaction(fn () => $this->references($connection));
        }

        foreach ([1, 2] as $number) {
            $owner = User::where('email', "boutique{$number}@example.test")->firstOrFail();
            $key = "local:boutique{$number}:v1";
            $attributes = [
                'user_id' => $owner->id, 'internal_label' => "Boutique {$number} — essai local",
                'shop_name' => "Boutique {$number}", 'slug' => "boutique{$number}",
                'document_prefix' => "BOUTIQUE{$number}", 'creation_key' => $key,
            ];
            $tenant = Tenant::where('user_id', $owner->id)->where('creation_key', $key)->first();

            if ($tenant === null) {
                $tenant = $connection->transaction(fn (): Tenant => Tenant::create([
                    ...$attributes, 'creation_hash' => hash('sha256', $this->json($attributes)),
                ]));
            }

            $tenant = $tenant->fresh() ?? throw new LogicException('Missing tenant after provisioning.');

            if (! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
                throw new LogicException('The registered fixture database is missing; do not silently replace it.');
            }

            if (! $tenant->domains()->where('is_primary', true)->exists()) {
                $tenant->domains()->create([
                    'domain' => $tenant->slug.'.'.config('tenancy.saas_base_domain'), 'type' => 1,
                    'is_primary' => true, 'verification_status' => 2, 'verified_at' => now(),
                ]);
            }

            $tenant->run(fn () => $this->call(ShopSeeder::class));
            $marker = "local:billing:{$tenant->uuid}:v1";

            if (! $connection->table('activity_log')->where('operation_key', $marker)->exists()) {
                $connection->transaction(fn () => $this->billing($connection, $tenant, $owner, $marker));
            }

            if ($tenant->status !== StatusEnum::ACTIVE) {
                $tenant->status = StatusEnum::ACTIVE;
                $tenant->is_primary = true;
                $tenant->activation_priority = 1;
                $tenant->provisioned_at = now();
                $tenant->save();
            }
        }

        $this->command->info('Local fixtures: 2 owners, 1 central administrator, 2 shops, 20 products and 50 orders per shop.');
        $this->command->info('Local passwords: central LocalTest!2026-Owner; shop owner LocalTest!2026-Shop; employee LocalTest!2026-Team.');
    }

    private function references(Connection $db): void
    {
        $country = Country::where('code', 'DZ')->firstOrFail();

        foreach ([['Karim', 'Benameur'], ['Nadia', 'Benali'], ['Admin', 'Essai']] as $index => [$firstName, $lastName]) {
            $number = $index + 1;
            $user = new User([
                'first_name' => $firstName, 'last_name' => $lastName,
                'email' => $number === 3 ? 'admin@example.test' : "boutique{$number}@example.test",
                'password' => 'LocalTest!2026-Owner',
            ]);
            $user->country_id = $country->id;
            $user->email_verified_at = CarbonImmutable::now();
            $user->phone = "+21355500000{$number}";
            $user->legal_form = 'FICTIVE_LOCAL_TEST';
            $user->activity_nature = 'Données fictives, commerce de détail';
            $user->legal_address = 'Adresse fictive pour les tests, Alger';
            $user->legal_profile_version = 1;
            $user->save();
        }

        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $root = $this->insert($db, 'roles', [
            'name' => 'root', 'guard_name' => 'central', 'label' => 'Administrateur local d’essai',
            'is_system' => true, 'is_protected' => true, 'is_super_admin' => true,
            'permission_version' => 1, 'permission_signature' => hash('sha256', '[]'),
        ]);
        $db->table('model_has_roles')->insert([
            'role_id' => $root, 'model_type' => 'central_user', 'model_id' => $admin->id, 'assigned_at' => now(),
        ]);

        foreach (['saas.subscriptions.manage', 'saas.payments.validate'] as $name) {
            $this->insert($db, 'permissions', ['name' => $name, 'guard_name' => 'central', 'label' => $name]);
        }

        $plan = $this->insert($db, 'plans', [
            'code' => 'local-functional', 'version' => 1, 'name' => 'Offre fictive locale',
            'description' => 'Uniquement pour vérifier les relations et requêtes.',
            'monthly_price' => '3000.00', 'annual_price' => '30000.00', 'is_active' => true,
        ]);

        foreach ([['shops', 1, 1, 1], ['products', 2, 1, 100], ['orders', 2, 3, 1000]] as [$code, $scope, $period, $limit]) {
            $feature = $this->insert($db, 'features', [
                'code' => 'local.'.$code, 'name' => ucfirst($code).' — essai', 'value_type' => 2,
                'quota_scope' => $scope, 'period' => $period, 'unit' => 'count', 'is_active' => true,
            ]);
            $this->insert($db, 'plan_features', ['plan_id' => $plan, 'feature_id' => $feature, 'is_active' => true, 'limit' => $limit]);
        }

        $province = $this->insert($db, 'geographic_areas', [
            'country_id' => $country->id, 'type' => 1, 'code' => '16', 'name_fr' => 'Alger', 'name_ar' => 'الجزائر',
            'is_active' => true, 'reference_source' => 'local-fixture', 'reference_version' => 'local-v1',
            'effective_at' => now()->toDateString(),
        ]);
        $municipality = $this->insert($db, 'geographic_areas', [
            'country_id' => $country->id, 'parent_id' => $province, 'type' => 2, 'code' => '1601',
            'name_fr' => 'Alger Centre', 'is_active' => true, 'reference_source' => 'local-fixture',
            'reference_version' => 'local-v1', 'effective_at' => now()->toDateString(),
        ]);
        $carrier = $this->insert($db, 'shipping_carriers', [
            'code' => 'local-fixture', 'name' => 'Transporteur fictif local', 'adapter' => 'local-fixture',
            'is_active' => false, 'reference_source' => 'local-fixture', 'reference_version' => 1,
        ]);

        foreach ([[$province, 1, '16', 'Alger'], [$municipality, 2, '1601', 'Alger Centre']] as [$area, $type, $code, $name]) {
            $this->insert($db, 'carrier_geo_mappings', [
                'carrier_id' => $carrier, 'geographic_area_id' => $area, 'zone_type' => $type,
                'external_code' => $code, 'external_name' => $name, 'external_province_code' => '16',
                'verification_source' => 'local-fixture', 'mapping_version' => 1, 'is_active' => false,
            ]);
        }

        $this->insert($db, 'pickup_points', [
            'carrier_id' => $carrier, 'province_id' => $province, 'municipality_id' => $municipality,
            'external_code' => 'LOCAL-ALGER-01', 'name' => 'Point de retrait fictif',
            'address' => 'Adresse fictive, Alger Centre', 'is_carrier_active' => false,
            'reference_source' => 'local-fixture', 'reference_version' => 1,
        ]);
        $this->insert($db, 'saas_billing_settings', [
            'operation_key' => 'saas:rule:local-v1', 'record_type' => 2, 'code' => 'local-functional',
            'version' => 1, 'trigger_event' => 'local_installment_created', 'numbering_scope' => 'saas_issuer',
            'parameters' => $this->json(['fixture_only' => true]), 'policy_status' => 3,
            'created_by_id' => $admin->id, 'validated_by_id' => $admin->id,
            'validated_at' => now(), 'effective_at' => now(), 'validation_reference' => 'LOCAL_TEST_ONLY',
            'correlation_id' => (string) Str::uuid(),
        ]);

        foreach ([1, 2] as $type) {
            $this->insert($db, 'saas_billing_settings', [
                'operation_key' => 'saas:sequence:local:'.$type, 'record_type' => 1,
                'document_type' => $type, 'fiscal_year' => now()->year,
                'prefix' => $type === 1 ? 'LOCAL-INV' : 'LOCAL-CREDIT', 'next_number' => 1,
            ]);
        }

        $this->insert($db, 'activity_log', [
            'operation_key' => 'local:references:v1', 'log_name' => 'local-fixtures',
            'description' => 'Référentiels et comptes fictifs créés pour les tests locaux.',
            'causer_id' => $admin->id, 'causer_type' => 'central_user',
            'correlation_id' => (string) Str::uuid(), 'origin' => 2,
        ]);
    }

    private function billing(Connection $db, Tenant $tenant, User $owner, string $marker): void
    {
        $adminId = (int) User::where('email', 'admin@example.test')->value('id');
        $planId = (int) $db->table('plans')->where('code', 'local-functional')->value('id');
        $periodStart = now()->startOfMonth();
        $periodEnd = $periodStart->addMonth();
        $monthlyOrders = $tenant->run(fn (): int => DB::connection('tenant')->table('orders')
            ->where('created_at', '>=', $periodStart)->where('created_at', '<', $periodEnd)->count());
        $subscription = $this->insert($db, 'subscriptions', [
            'user_id' => $owner->id, 'tenant_id' => $tenant->id, 'plan_id' => $planId, 'assigned_by_id' => $adminId,
            'operation_key' => 'local:subscription:'.$tenant->uuid, 'record_type' => 1,
            'status' => 3, 'period' => 1, 'agreed_amount' => '3000.00', 'started_at' => $periodStart,
            'period_starts_at' => $periodStart, 'period_ends_at' => $periodEnd, 'auto_renew' => false,
        ]);
        $installment = $this->insert($db, 'subscriptions', [
            'user_id' => $owner->id, 'parent_subscription_id' => $subscription, 'record_type' => 2,
            'operation_key' => 'local:installment:'.$tenant->uuid, 'installment_number' => 'LOCAL-'.$tenant->uuid,
            'period_starts_at' => $periodStart, 'period_ends_at' => $periodEnd,
            'installment_amount' => '3000.00', 'due_at' => $periodEnd, 'installment_status' => 3,
        ]);
        $ruleId = (int) $db->table('saas_billing_settings')->where('record_type', 2)->value('id');
        $documents = [];
        $lineIds = [];

        foreach ([1 => 3000, 2 => 300] as $type => $amount) {
            $originalDocument = $type === 2 ? ($documents[1] ?? throw new LogicException('Credit note requires its invoice.')) : null;
            $originalLine = $type === 2 ? ($lineIds[1] ?? throw new LogicException('Credit line requires its original line.')) : null;
            $sequence = $db->table('saas_billing_settings')->where('record_type', 1)->where('document_type', $type)->lockForUpdate()->first();
            if ($sequence === null) {
                throw new LogicException('Missing local sequence.');
            }

            $number = $sequence->prefix.'-'.now()->year.'-'.$sequence->next_number;
            $document = $this->insert($db, 'saas_invoices', [
                'user_id' => $owner->id, 'subscription_id' => $subscription, 'installment_id' => $installment,
                'billing_rule_id' => $ruleId, 'document_type' => $type, 'status' => 1,
                'original_invoice_id' => $originalDocument,
                'operation_key' => ($type === 1 ? 'saas:invoice:' : 'saas:credit:').'local:'.$tenant->uuid,
                'billing_rule_snapshot' => $this->json(['fixture_only' => true, 'version' => 1]),
                'net_amount' => $amount, 'tax_amount' => 0, 'total_amount' => $amount,
                'taxes' => $this->json($this->taxSnapshot($amount)['taxes']), 'currency' => 'DZD',
                'period_starts_at' => $periodStart, 'period_ends_at' => $periodEnd,
                'due_at' => $type === 1 ? $periodEnd : null,
                'reason' => $type === 2 ? 'Geste commercial fictif local' : null,
                'saas_identity_snapshot' => $this->json(['fixture_only' => true, 'name' => 'Aydra — simulation']),
                'customer_identity_snapshot' => $this->json(['owner_uuid' => $owner->uuid, 'first_name' => $owner->first_name, 'last_name' => $owner->last_name, 'trade_name' => $tenant->shop_name, 'fixture_only' => true]),
            ]);
            $lineIds[$type] = $this->insert($db, 'saas_invoice_lines', [
                'document_id' => $document, 'user_id' => $owner->id, 'document_type' => $type,
                'original_invoice_id' => $originalDocument,
                'original_invoice_line_id' => $originalLine,
                'operation_key' => 'saas:line:local:'.$tenant->uuid.':'.$type, 'line_number' => 1,
                'description' => $type === 1 ? 'Abonnement mensuel fictif' : 'Avoir fictif', 'quantity' => 1,
                'net_unit_price' => $type === 1 ? $amount : null, 'net_discount' => $type === 1 ? 0 : null,
                'net_amount' => $amount, 'tax_amount' => 0, 'total_amount' => $amount,
                'taxes' => $this->json($this->taxSnapshot($amount)['taxes']),
                'reason' => $type === 2 ? 'Geste commercial fictif local' : null,
            ]);
            $pdfId = $this->pdf($db, 'saas_invoice', $document, $adminId, $number);
            $db->table('saas_invoices')->where('id', $document)->update([
                'sequence_id' => $sequence->id, 'fiscal_year' => now()->year,
                'sequence_number' => $sequence->next_number, 'number' => $number,
                'document_media_id' => $pdfId, 'status' => 2, 'issued_at' => now(),
            ]);
            $db->table('saas_billing_settings')->where('id', $sequence->id)->increment('next_number');
            $documents[$type] = $document;
            $this->insert($db, 'saas_document_deliveries', [
                'user_id' => $owner->id, 'document_id' => $document, 'document_type' => $type,
                'created_by_id' => $adminId, 'operation_key' => 'saas:delivery:local:'.$tenant->uuid.':'.$type,
                'channel' => 4, 'encrypted_recipient' => Crypt::encryptString($owner->email),
                'delivery_status' => 1, 'attempts_count' => 0, 'correlation_id' => (string) Str::uuid(),
            ]);
        }

        $paymentId = null;
        foreach ([1 => 3000, 2 => 300] as $type => $amount) {
            $proofId = $this->pdf($db, 'saas_invoice', $documents[1], $type === 1 ? $owner->id : $adminId, 'Simulated bank transfer '.$amount.' DZD');
            $id = $this->insert($db, 'saas_transfers', [
                'user_id' => $owner->id, 'document_id' => $documents[1], 'record_type' => $type,
                'original_payment_id' => $type === 2 ? $paymentId : null, 'credit_note_id' => $type === 2 ? $documents[2] : null,
                'proof_media_id' => $proofId, 'created_by_id' => $type === 1 ? $owner->id : $adminId,
                'validated_by_id' => $adminId, 'performed_by_id' => $type === 2 ? $adminId : null,
                'operation_key' => ($type === 1 ? 'saas:payment:' : 'saas:refund:').'local:'.$tenant->uuid,
                'transfer_method' => $type === 1 ? 3 : 2, 'transfer_status' => 3,
                'refund_reason' => $type === 2 ? 1 : null, 'reason' => $type === 2 ? 'Remboursement fictif de l’avoir' : null,
                'amount' => $amount, 'currency' => 'DZD', 'transfer_reference' => 'LOCAL-'.$tenant->id.'-'.$type,
                'financial_account_key' => 'LOCAL-TEST-ACCOUNT', 'transaction_fingerprint' => hash('sha256', $tenant->uuid.':'.$type),
                'encrypted_transfer_details' => Crypt::encryptString($this->json(['fixture_only' => true])),
                'occurred_at' => now(), 'validated_at' => now(), 'sending_started_at' => $type === 2 ? now() : null,
                'correlation_id' => (string) Str::uuid(),
            ]);
            if ($type === 1) {
                $paymentId = $id;
            }
        }

        foreach ($db->table('features')->get(['id', 'code', 'quota_scope']) as $feature) {
            $this->insert($db, 'feature_usage', [
                'user_id' => $owner->id, 'tenant_id' => $feature->quota_scope === 1 ? null : $tenant->id,
                'feature_id' => $feature->id, 'period_starts_at' => $periodStart,
                'period_ends_at' => $feature->code === 'local.orders' ? $periodEnd : null,
                'quantity' => match ($feature->code) {
                    'local.shops' => 1, 'local.products' => 20, default => $monthlyOrders
                },
            ]);
        }

        $this->insert($db, 'tenant_schema_deployments', [
            'tenant_id' => $tenant->id, 'target_version' => $tenant->schema_version,
            'operation' => 1, 'status' => 3, 'attempt_number' => 1, 'operation_key' => 'local:deployment:'.$tenant->uuid,
            'started_at' => $tenant->created_at, 'ended_at' => now(), 'correlation_id' => (string) Str::uuid(),
            'runtime_versions' => $this->json(['php' => PHP_VERSION, 'laravel' => app()->version()]),
        ]);
        $this->insert($db, 'activity_log', [
            'tenant_id' => $tenant->id, 'operation_key' => $marker, 'log_name' => 'local-fixtures',
            'description' => 'Facture, avoir, paiement et remboursement simulés localement.',
            'subject_type' => 'tenant', 'subject_id' => $tenant->id, 'causer_type' => 'central_user', 'causer_id' => $adminId,
            'correlation_id' => (string) Str::uuid(), 'origin' => 2,
        ]);
    }
}

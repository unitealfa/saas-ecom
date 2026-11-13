<?php

namespace App\Providers;

use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Countries\Country;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Features\Feature;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Media\Media;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User as CentralUser;
use App\Models\Examples\Test;
use App\Models\Shared\Authorization\PermissionAssignment;
use App\Models\Tenant\Audit\Activity as TenantActivity;
use App\Models\Tenant\Media\Media as TenantMedia;
use App\Models\Tenant\Permissions\Permission as TenantPermission;
use App\Models\Tenant\Roles\Role as TenantRole;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User as ShopUser;
use App\Models\User;
use App\Policies\Central\ActivityPolicy;
use App\Policies\Central\BillingRulePolicy;
use App\Policies\Central\BillingSequencePolicy;
use App\Policies\Central\CarrierGeoMappingPolicy;
use App\Policies\Central\CountryPolicy;
use App\Policies\Central\DomainPolicy;
use App\Policies\Central\FeaturePolicy;
use App\Policies\Central\GeographicAreaPolicy;
use App\Policies\Central\MediaPolicy;
use App\Policies\Central\PermissionPolicy;
use App\Policies\Central\PickupPointPolicy;
use App\Policies\Central\PlanPolicy;
use App\Policies\Central\RolePolicy;
use App\Policies\Central\SaasCreditNotePolicy;
use App\Policies\Central\SaasDocumentDeliveryPolicy;
use App\Policies\Central\SaasInvoicePolicy;
use App\Policies\Central\SaasPaymentPolicy;
use App\Policies\Central\ShippingCarrierPolicy;
use App\Policies\Central\SubscriptionInstallmentPolicy;
use App\Policies\Central\SubscriptionPolicy;
use App\Policies\Central\SubscriptionRequestPolicy;
use App\Policies\Central\TenantPolicy;
use App\Policies\Central\TenantSchemaDeploymentPolicy;
use App\Policies\Central\UserPolicy;
use App\Policies\Tenant\ShopAddressPolicy;
use App\Policies\Tenant\ShopPolicy;
use App\Policies\Tenant\ShopSocialLinkPolicy;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Tenants\TenantStatusService;
use App\Services\SchemaBlueprint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> Modèle => policy. */
    private const array POLICIES = [
        Activity::class => ActivityPolicy::class,
        SaasCreditNote::class => SaasCreditNotePolicy::class,
        SaasDocumentDelivery::class => SaasDocumentDeliveryPolicy::class,
        SaasInvoice::class => SaasInvoicePolicy::class,
        SaasPayment::class => SaasPaymentPolicy::class,
        SubscriptionInstallment::class => SubscriptionInstallmentPolicy::class,
        BillingRule::class => BillingRulePolicy::class,
        BillingSequence::class => BillingSequencePolicy::class,
        User::class => UserPolicy::class,
        CentralUser::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        Plan::class => PlanPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        SubscriptionRequest::class => SubscriptionRequestPolicy::class,
        Country::class => CountryPolicy::class,
        ShippingCarrier::class => ShippingCarrierPolicy::class,
        CarrierGeoMapping::class => CarrierGeoMappingPolicy::class,
        PickupPoint::class => PickupPointPolicy::class,
        Media::class => MediaPolicy::class,
        GeographicArea::class => GeographicAreaPolicy::class,
        Domain::class => DomainPolicy::class,
        Feature::class => FeaturePolicy::class,
        TenantSchemaDeployment::class => TenantSchemaDeploymentPolicy::class,
        Tenant::class => TenantPolicy::class,
        Shop::class => ShopPolicy::class,
        ShopAddress::class => ShopAddressPolicy::class,
        ShopSocialLink::class => ShopSocialLinkPolicy::class,
    ];

    public function register(): void
    {
        $this->app->bind(Blueprint::class, SchemaBlueprint::class);
    }

    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerPolicies();
        Event::listen(DefaultSubscription::EFFECTIVE_CHANGED, [TenantStatusService::class, 'applySubscriptionQuota']);

        $this->loadMigrationsFrom(database_path('migrations/central'));
        Livewire::addPersistentMiddleware([EnsureActiveCentralAccount::class]);

        $this->configureDefaults();
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'subscription_request' => SubscriptionRequest::class,
            'saas_document_delivery' => SaasDocumentDelivery::class,
            'saas_credit_note' => SaasCreditNote::class,
            'saas_invoice' => SaasInvoice::class,
            'saas_payment' => SaasPayment::class,
            'subscription_installment' => SubscriptionInstallment::class,
            'saas_billing_rule' => BillingRule::class,
            'saas_sequence' => BillingSequence::class,
            'central_user' => User::class,
            'shop_user' => ShopUser::class,
            'shop' => Shop::class,
            'shop_address' => ShopAddress::class,
            'shop_social_link' => ShopSocialLink::class,

            // Alias historiques : ils sont déjà stockés en base, ne pas les renommer.
            'App\\Models\\Central\\Country' => Country::class,
            'App\\Models\\Central\\Role' => Role::class,
            'App\\Models\\Central\\Permission' => Permission::class,
            'App\\Models\\Central\\Tenant' => Tenant::class,
            'App\\Models\\Central\\Domain' => Domain::class,
            'App\\Models\\Central\\TenantSchemaDeployment' => TenantSchemaDeployment::class,
            'App\\Models\\Central\\Plan' => Plan::class,
            'App\\Models\\Central\\PlanFeature' => PlanFeature::class,
            'App\\Models\\Central\\Feature' => Feature::class,
            'App\\Models\\Central\\Subscription' => Subscription::class,
            'App\\Models\\Central\\Media' => Media::class,
            'App\\Models\\Central\\Activity' => Activity::class,
            'App\\Models\\Tenant\\Role' => TenantRole::class,
            'App\\Models\\Tenant\\Permission' => TenantPermission::class,
            'App\\Models\\Tenant\\Shop' => Shop::class,
            'App\\Models\\Tenant\\Media' => TenantMedia::class,
            'App\\Models\\Tenant\\Activity' => TenantActivity::class,
            'App\\Models\\PermissionAssignment' => PermissionAssignment::class,
            'App\\Models\\Test' => Test::class,
        ]);
    }

    private function registerPolicies(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    private function configureDefaults(): void
    {
        $isProduction = $this->app->environment('production');

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands($isProduction);

        Password::defaults(fn (): ?Password => $isProduction
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
            : null);
    }
}

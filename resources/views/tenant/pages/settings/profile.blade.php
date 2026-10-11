<x-tenant::layouts.app :title="__('Mon profil')" :shop-name="$dashboard->shopName" :user-name="$dashboard->userName">
    <div class="flex flex-col gap-6">
        <flux:heading size="xl">{{ __('Mon profil') }}</flux:heading>
        <x-tenant::components.account-summary :name="$dashboard->userName" />
        @include('tenant.partials.preparation-notice')
    </div>
</x-tenant::layouts.app>

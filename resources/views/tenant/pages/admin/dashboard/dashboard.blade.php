<x-tenant::layouts.app :title="__('Tableau de bord')" :shop-name="$dashboard->shopName" :user-name="$dashboard->userName">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Bonjour :name', ['name' => $dashboard->userName]) }}</flux:heading>
            <flux:text>{{ $dashboard->shopName }}</flux:text>
        </div>

        @can('view', \App\Models\Tenant\Shop\Shop::sole())
            <flux:button :href="route('tenant.admin.shop.profile', absolute: false)">Profil de la boutique</flux:button>
        @endcan
    </div>
</x-tenant::layouts.app>

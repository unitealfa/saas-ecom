<x-layouts::app :title="__('Boutiques')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Administration des boutiques</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">{{ $errors->first() }}</flux:callout>@endif
        @can('create', \App\Models\Central\Tenants\Tenant::class)<flux:button variant="primary" :href="route('central.tenants.create')">Créer une boutique</flux:button>@endcan
        <form method="GET" action="{{ route('central.tenants.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input name="search" label="Nom, slug, propriétaire ou domaine" :value="$search" />
            <flux:select name="status" label="État">
                <option value="">Toutes les boutiques non archivées</option>
                @foreach ($statuses as $choice)<option value="{{ $choice->value }}" @selected($status === $choice)>{{ $choice->label() }}</option>@endforeach
            </flux:select>
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>@foreach (['Boutique', 'Propriétaire', 'Domaines', 'État', 'Abonnement', 'Base MySQL', 'Actions'] as $heading)<th class="p-4">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($shops as $tenant)
                        <tr>
                            <td class="p-4">{{ $tenant->shop_name }}<br>{{ $tenant->slug }}</td>
                            <td class="p-4">{{ $tenant->owner?->name ?? 'Compte archivé' }}<br>{{ $tenant->owner?->email }}</td>
                            <td class="p-4">{{ $tenant->domains->pluck('domain')->join(', ') ?: 'Aucun domaine' }}</td>
                            <td class="p-4">{{ $tenant->status->label() }}</td>
                            <td class="p-4">{{ $subscriptions->get($tenant->user_id)?->plan?->name ?? 'Aucun abonnement actif enregistré' }}</td>
                            <td class="p-4">{{ $tenant->getInternal('db_name') ?? 'Non réservée' }}</td>
                            <td class="p-4">@include('central.pages.admin.tenants.actions', ['detail' => false])</td>
                        </tr>
                    @empty
                        <tr><td class="p-4" colspan="7">Aucune boutique ne correspond à la recherche.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $shops->links() }}
    </section>
</x-layouts::app>

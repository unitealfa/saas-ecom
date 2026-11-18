<x-layouts::app :title="__('Modifier le domaine')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">Modifier le domaine</h1><p class="aydra-muted break-words text-sm sm:text-base">{{ $domain->domain }} · {{ $domain->tenant?->shop_name }}</p></div>@can('viewAny', \App\Models\Central\Domains\Domain::class)<flux:button :href="route('central.domains.index')" icon="arrow-left">Retour aux domaines</flux:button>@endcan</header>
        @include('central.pages.admin.domains.messages')
        @if ($domain->is_primary)
            <flux:callout>Cette adresse est utilisée comme domaine principal. Préparez une adresse de remplacement, vérifiez-la, puis définissez-la comme principale. L’adresse actuelle reste disponible pendant la préparation.</flux:callout>
            @can('create', \App\Models\Central\Domains\Domain::class)
                <flux:button class="max-w-full self-start whitespace-normal" :href="route('central.domains.create', ['tenant_uuid' => $domain->tenant->uuid])" variant="primary">Préparer une adresse de remplacement</flux:button>
            @else
                <flux:text>Un administrateur disposant du droit d’ajouter un domaine doit préparer cette adresse.</flux:text>
            @endcan
        @else
            <form method="POST" action="{{ route('central.domains.update', $domain) }}" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6">
                @csrf @method('PUT')
                <h2 class="aydra-heading text-lg font-semibold">Adresse secondaire</h2>
                <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                <flux:input name="domain" label="Nom de domaine" :value="old('domain', $domain->domain)" required />
                <flux:text>Modifier cette adresse secondaire annule ses preuves DNS et HTTPS. La boutique, son slug, son propriétaire et sa base restent inchangés.</flux:text>
                <flux:button type="submit" variant="primary" class="self-start">Enregistrer</flux:button>
            </form>
        @endif
        @if ($domain->type === \App\Enums\Central\Domains\TypeEnum::CUSTOM)
            <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Configuration DNS</h2>@include('central.pages.admin.domains.dns')</section>
        @endif
    </section>
</x-layouts::app>

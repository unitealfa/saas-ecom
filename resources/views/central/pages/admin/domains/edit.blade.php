<x-layouts::app :title="__('Modifier le domaine')">
    <section class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $domain->domain }}</flux:heading>
        <flux:text>Boutique : {{ $domain->tenant?->shop_name }}</flux:text>
        @include('central.pages.admin.domains.messages')
        @if ($domain->is_primary)
            <flux:callout>Cette adresse est utilisée comme domaine principal. Préparez une adresse de remplacement, vérifiez-la, puis définissez-la comme principale. L’adresse actuelle reste disponible pendant la préparation.</flux:callout>
            @can('create', \App\Models\Central\Domains\Domain::class)
                <flux:button :href="route('central.domains.create', ['tenant_uuid' => $domain->tenant->uuid])" variant="primary">Préparer une adresse de remplacement</flux:button>
            @else
                <flux:text>Un administrateur disposant du droit d’ajouter un domaine doit préparer cette adresse.</flux:text>
            @endcan
        @else
            <form method="POST" action="{{ route('central.domains.update', $domain) }}" class="flex flex-col gap-5">
                @csrf @method('PUT')
                <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                <flux:input name="domain" label="Nom de domaine" :value="old('domain', $domain->domain)" required />
                <flux:text>Modifier cette adresse secondaire annule ses preuves DNS et HTTPS. La boutique, son slug, son propriétaire et sa base restent inchangés.</flux:text>
                <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            </form>
        @endif
        @if ($domain->type === \App\Enums\Central\Domains\TypeEnum::CUSTOM)
            @include('central.pages.admin.domains.dns')
        @endif
        @can('viewAny', \App\Models\Central\Domains\Domain::class)
            <flux:button :href="route('central.domains.index')">Retour aux domaines</flux:button>
        @endcan
    </section>
</x-layouts::app>

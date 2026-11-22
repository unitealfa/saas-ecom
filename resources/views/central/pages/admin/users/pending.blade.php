<x-layouts::app :title="__('Inscriptions en attente')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2">
                <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Inscriptions en attente d’approbation</h1>
                <p class="aydra-muted max-w-2xl text-sm sm:text-base">Consultez les comptes en attente et prenez une décision sur leur inscription.</p>
            </div>
            <flux:button :href="route('central.users.index')" icon="arrow-left">Tous les utilisateurs</flux:button>
        </header>
        <flux:callout>Les comptes en attente disposent d’une session limitée. Après approbation et vérification de l’adresse e-mail, le commerçant peut configurer sa première boutique.</flux:callout>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <div class="aydra-users-panel">
            <div class="aydra-users-toolbar"><div>
                <h2 class="aydra-heading text-lg font-semibold">Comptes en attente</h2>
                <p class="aydra-muted mt-1 text-sm">{{ $users->total() }} {{ $users->total() === 1 ? 'inscription en attente' : 'inscriptions en attente' }}</p>
            </div></div>
            <div class="aydra-users-table">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nom</flux:table.column><flux:table.column>E-mail</flux:table.column>
                    <flux:table.column>Téléphone</flux:table.column><flux:table.column>Pays</flux:table.column>
                    <flux:table.column>Inscription</flux:table.column><flux:table.column>Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($users as $user)
                        <flux:table.row>
                            <flux:table.cell variant="strong"><div class="flex items-center gap-3"><flux:avatar :name="$user->name" size="sm" /><span>{{ $user->name }}</span></div></flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>{{ $user->phone ?? 'Non renseigné' }}</flux:table.cell>
                            <flux:table.cell>{{ $user->country?->name_fr }}</flux:table.cell>
                            <flux:table.cell>{{ $user->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu>
                                @can('view', $user)
                                    <flux:menu.item :href="route('central.users.show', $user)">Informations</flux:menu.item>
                                @endcan
                                @include('central.pages.admin.users.lifecycle-actions', ['inActionMenu' => true])
                            </flux:menu></flux:dropdown></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="6"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucune inscription en attente.</p><p class="aydra-muted mt-1 text-sm">Les nouvelles inscriptions à examiner apparaîtront ici.</p></div></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            <div class="mt-4">{{ $users->links() }}</div>
            </div>
        </div>
    </section>
</x-layouts::app>

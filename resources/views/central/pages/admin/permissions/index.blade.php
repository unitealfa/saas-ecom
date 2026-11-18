<x-layouts::app :title="__('Catalogue des permissions')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Catalogue des permissions</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Consultez les actions disponibles et leurs libellés.</p></div>
            @can('create', \App\Models\Central\Roles\Role::class)
                <flux:button :href="route('central.roles.create')" icon="plus">Créer un rôle</flux:button>
            @endcan
        </header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">{{ $errors->first() }}</flux:callout>@endif
        <div class="aydra-users-panel">
            <div class="aydra-users-toolbar">
                <div class="min-w-0 flex-1"><h2 class="aydra-heading text-lg font-semibold">Permissions</h2><p class="aydra-muted mt-1 text-sm">{{ $permissions->total() }} {{ $permissions->total() === 1 ? 'permission trouvée' : 'permissions trouvées' }}</p></div>
                <form method="GET" action="{{ route('central.permissions.index') }}" class="flex w-full flex-wrap items-end gap-3 lg:w-auto">
                    <flux:input name="search" label="Rechercher par code ou libellé" :value="$search" type="search" icon="magnifying-glass" list="permission-suggestions" placeholder="Code ou libellé" class="min-w-0 flex-1 lg:w-80" />
                    <datalist id="permission-suggestions">
                        @foreach ($suggestions as $suggestion)
                            <option value="{{ $suggestion->name }}">{{ $suggestion->label }}</option>
                            <option value="{{ $suggestion->label }}">{{ $suggestion->name }}</option>
                        @endforeach
                    </datalist>
                    <flux:button type="submit" variant="primary">Rechercher</flux:button>
                    @if ($search)<flux:button :href="route('central.permissions.index')">Effacer</flux:button>@endif
                </form>
            </div>
            <div class="aydra-users-table">
            <flux:table class="whitespace-normal">
                <flux:table.columns><flux:table.column>Groupe</flux:table.column><flux:table.column>Code technique</flux:table.column><flux:table.column>Libellé</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @forelse ($permissions as $permission)
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ $groups[$permission->name] ?? 'Autres permissions centrales' }}</flux:table.cell>
                            <flux:table.cell>{{ $permission->name }}</flux:table.cell>
                            <flux:table.cell>{{ $permission->label }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="3"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucune permission ne correspond à la recherche.</p><p class="aydra-muted mt-1 text-sm">Essayez un autre code ou libellé.</p></div></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            @if ($permissions->hasPages())
                <nav aria-label="Pagination des permissions" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <span class="aydra-muted text-sm">Page {{ $permissions->currentPage() }} sur {{ $permissions->lastPage() }}</span>
                    <div class="flex gap-2">
                        @if ($permissions->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$permissions->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif
                        @if ($permissions->hasMorePages())<flux:button :href="$permissions->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif
                    </div>
                </nav>
            @endif
            </div>
        </div>
    </section>
</x-layouts::app>

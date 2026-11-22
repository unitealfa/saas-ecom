<x-layouts::app :title="__('Rôles SaaS')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Rôles SaaS</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Consultez et gérez les profils de droits du personnel.</p></div>
            @can('create', \App\Models\Central\Roles\Role::class)
                <flux:button variant="primary" icon="plus" :href="route('central.roles.create')">Créer un rôle</flux:button>
            @endcan
        </header>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger">{{ $errors->first() }}</flux:callout>@endif
        <div class="aydra-users-panel">
            <div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Profils de rôles</h2><p class="aydra-muted mt-1 text-sm">{{ $roles->total() }} {{ $roles->total() === 1 ? 'rôle' : 'rôles' }}</p></div></div>
            <div class="aydra-users-table">
            <flux:table class="whitespace-normal">
                <flux:table.columns><flux:table.column>Rôle</flux:table.column><flux:table.column>Permissions et durées</flux:table.column><flux:table.column>Gestion</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @forelse ($roles as $role)
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ $role->label }}<br><span class="aydra-muted font-normal">{{ $role->name }}</span></flux:table.cell>
                            <flux:table.cell>
                                @if ($role->is_super_admin)
                                    Super Admin protégé
                                @elseif (auth('central')->user()->can('viewPermissions', $role))
                                    @foreach ($role->permissionComposition as $permission)
                                        <p>{{ $permission->label }} : {{ $permission->pivot->duration_days }} jours</p>
                                    @endforeach
                                @else
                                    Consultation non autorisée
                                @endif
                            </flux:table.cell>
                            <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu>
                                    <flux:menu.item :href="route('central.roles.show', $role)">Informations</flux:menu.item>
                                    @can('edit', $role)<flux:menu.item :href="route('central.roles.edit', $role)">Modifier</flux:menu.item>@endcan
                                    @can('delete', $role)
                                        <form method="POST" action="{{ route('central.roles.destroy', $role) }}" x-data x-on:submit="if (!confirm('Supprimer ce rôle inutilisé ?')) $event.preventDefault()">
                                            @csrf @method('DELETE')
                                            <flux:menu.item as="button" type="submit" variant="danger">Supprimer</flux:menu.item>
                                        </form>
                                    @endcan
                                </flux:menu></flux:dropdown>
                                @if ($role->is_system || $role->is_protected)<span class="aydra-muted mt-2 block text-xs">Rôle système protégé</span>@endif</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="3"><div class="aydra-users-empty">Aucun rôle SaaS.</div></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            <div class="mt-4">{{ $roles->links() }}</div>
            </div>
        </div>
    </section>
</x-layouts::app>

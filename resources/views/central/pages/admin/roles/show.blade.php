<x-layouts::app :title="$role->label">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $role->label }}</h1><p class="aydra-muted text-sm">Nom du rôle : {{ $role->name }}</p></header>
        @if ($role->is_system || $role->is_protected)<flux:callout>Rôle système protégé.</flux:callout>@endif
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger">{{ $errors->first() }}</flux:callout>@endif
        @if ($permissions !== null)
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="role-permissions">
            <h2 id="role-permissions" class="aydra-heading mb-4 text-lg font-semibold">Permissions et durées</h2>
            @if ($role->is_super_admin)
                <flux:text>Accès Super Admin protégé : les droits sont implicites.</flux:text>
            @else
                <div class="overflow-x-auto">
                    <flux:table>
                        <flux:table.columns><flux:table.column>Permission</flux:table.column><flux:table.column>Durée</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @foreach ($permissions as $permission)
                                <flux:table.row><flux:table.cell>{{ $permission->label }}</flux:table.cell><flux:table.cell>{{ $permission->pivot->duration_days }} jours</flux:table.cell></flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
                <flux:text>Ces droits et durées sont copiés lors d’une nouvelle attribution du profil. Les permissions individuelles existantes ne sont pas modifiées.</flux:text>
            @endif
            </section>
        @endif
        @if ($assignments !== null)
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="role-assignments">
            <h2 id="role-assignments" class="aydra-heading mb-4 text-lg font-semibold">Administrateurs possédant ce rôle</h2>
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns><flux:table.column>Compte</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Attribué le (UTC)</flux:table.column><flux:table.column>Informations</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($assignments as $user)
                            <flux:table.row>
                                <flux:table.cell>{{ $user->name }}<br>{{ $user->email }}</flux:table.cell>
                                <flux:table.cell>{{ $user->trashed() ? 'Archivé' : $user->status->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $user->roles->first()->pivot->assigned_at }}</flux:table.cell>
                                <flux:table.cell>@can('viewStaff', $user)<flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu><flux:menu.item :href="route('central.staff.show', $user)">Informations</flux:menu.item></flux:menu></flux:dropdown>@endcan</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4">Aucun administrateur ne possède ce rôle.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            {{ $assignments->links() }}
            </section>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            @can('edit', $role)<flux:button :href="route('central.roles.edit', $role)">Modifier</flux:button>@endcan
            @can('delete', $role)
                <form method="POST" action="{{ route('central.roles.destroy', $role) }}" x-data x-on:submit="if (!confirm('Supprimer ce rôle inutilisé ?')) $event.preventDefault()">
                    @csrf @method('DELETE')
                    <flux:button type="submit" variant="danger">Supprimer</flux:button>
                </form>
            @endcan
            <flux:button :href="route('central.roles.index')">Retour aux rôles</flux:button>
        </div>
    </section>
</x-layouts::app>

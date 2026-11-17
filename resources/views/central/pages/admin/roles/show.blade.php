<x-layouts::app :title="$role->label">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $role->label }}</flux:heading>
        <flux:text>Nom du rôle : {{ $role->name }}</flux:text>
        @if ($role->is_system || $role->is_protected)<flux:callout>Rôle système protégé.</flux:callout>@endif
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger">{{ $errors->first() }}</flux:callout>@endif
        @if ($permissions !== null)
            <flux:heading level="2">Permissions et durées</flux:heading>
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
        @endif
        @if ($assignments !== null)
            <flux:heading level="2">Administrateurs possédant ce rôle</flux:heading>
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns><flux:table.column>Compte</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Attribué le (UTC)</flux:table.column><flux:table.column>Informations</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($assignments as $user)
                            <flux:table.row>
                                <flux:table.cell>{{ $user->name }}<br>{{ $user->email }}</flux:table.cell>
                                <flux:table.cell>{{ $user->trashed() ? 'Archivé' : $user->status->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $user->roles->first()->pivot->assigned_at }}</flux:table.cell>
                                <flux:table.cell>@can('viewStaff', $user)<flux:button :href="route('central.staff.show', $user)">Informations</flux:button>@endcan</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4">Aucun administrateur ne possède ce rôle.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            {{ $assignments->links() }}
        @endif
        <div class="flex flex-wrap gap-3">
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

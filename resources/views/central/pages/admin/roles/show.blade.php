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
                    <table class="w-full text-left text-sm">
                        <thead><tr><th class="p-3">Permission</th><th class="p-3">Durée</th></tr></thead>
                        <tbody>
                            @foreach ($permissions as $permission)
                                <tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3">{{ $permission->label }}</td><td class="p-3">{{ $permission->pivot->duration_days }} jours</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <flux:text>Ces droits et durées sont copiés lors d’une nouvelle attribution du profil. Les permissions individuelles existantes ne sont pas modifiées.</flux:text>
            @endif
        @endif
        @if ($assignments !== null)
            <flux:heading level="2">Administrateurs possédant ce rôle</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-3">Compte</th><th class="p-3">État</th><th class="p-3">Attribué le (UTC)</th><th class="p-3">Informations</th></tr></thead>
                    <tbody>
                        @forelse ($assignments as $user)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="p-3">{{ $user->name }}<br>{{ $user->email }}</td>
                                <td class="p-3">{{ $user->trashed() ? 'Archivé' : $user->status->label() }}</td>
                                <td class="p-3">{{ $user->roles->first()->pivot->assigned_at }}</td>
                                <td class="p-3">@can('viewStaff', $user)<flux:button :href="route('central.staff.show', $user)">Informations</flux:button>@endcan</td>
                            </tr>
                        @empty
                            <tr><td class="p-3" colspan="4">Aucun administrateur ne possède ce rôle.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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

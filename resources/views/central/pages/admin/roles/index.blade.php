<x-layouts::app :title="__('Rôles SaaS')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Rôles SaaS</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger">{{ $errors->first() }}</flux:callout>@endif
        @can('create', \App\Models\Central\Role::class)
            <flux:button variant="primary" :href="route('central.roles.create')">Créer un rôle</flux:button>
        @endcan
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Rôle</th><th class="p-3">Permissions et durées</th><th class="p-3">Gestion</th></tr></thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $role->label }}<br><span class="text-zinc-500">{{ $role->name }}</span></td>
                            <td class="p-3">
                                @if ($role->is_super_admin)
                                    Super Admin protégé
                                @elseif (auth('central')->user()->can('viewPermissions', $role))
                                    @foreach ($role->permissionComposition as $permission)
                                        <p>{{ $permission->label }} : {{ $permission->pivot->duration_days }} jours</p>
                                    @endforeach
                                @else
                                    Consultation non autorisée
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    <flux:button :href="route('central.roles.show', $role)">Informations</flux:button>
                                    @can('edit', $role)<flux:button :href="route('central.roles.edit', $role)">Modifier</flux:button>@endcan
                                    @can('delete', $role)
                                        <form method="POST" action="{{ route('central.roles.destroy', $role) }}" x-data x-on:submit="if (!confirm('Supprimer ce rôle inutilisé ?')) $event.preventDefault()">
                                            @csrf @method('DELETE')
                                            <flux:button type="submit" variant="danger">Supprimer</flux:button>
                                        </form>
                                    @endcan
                                </div>
                                @if ($role->is_system || $role->is_protected)<span>Rôle système protégé</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="3">Aucun rôle SaaS.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $roles->links() }}
    </section>
</x-layouts::app>

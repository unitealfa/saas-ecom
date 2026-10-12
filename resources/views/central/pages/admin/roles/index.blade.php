<x-layouts::app :title="__('Rôles SaaS')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Rôles SaaS</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:button variant="primary" :href="route('central.roles.create')">Créer un rôle</flux:button>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Rôle</th><th class="p-3">Permissions et durées</th><th class="p-3">Gestion</th></tr></thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $role->label }}<br><span class="text-zinc-500">{{ $role->name }}</span></td>
                            <td class="p-3">
                                @if ($role->is_super_admin)
                                    Super Admin protégé
                                @else
                                    @foreach ($role->permissions as $permission)
                                        <p>{{ $permission->label }} : {{ $permission->pivot->duration_days }} jours</p>
                                    @endforeach
                                @endif
                            </td>
                            <td class="p-3">
                                @can('update', $role)<flux:button :href="route('central.roles.edit', $role)">Modifier</flux:button>
                                @else Rôle système protégé @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts::app>

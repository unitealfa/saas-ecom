<x-layouts::app :title="__('Catalogue des permissions')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Catalogue des permissions</flux:heading>
        <flux:text>Consultez les actions disponibles et leurs libellés. Les codes techniques sont définis lors du développement ; les droits s’attribuent depuis les rôles.</flux:text>
        @if ($errors->any())<flux:callout variant="danger" role="alert">{{ $errors->first() }}</flux:callout>@endif
        <form method="GET" action="{{ route('central.permissions.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input name="search" label="Rechercher par code ou libellé" :value="$search" class="sm:flex-1" />
            <flux:button type="submit" variant="primary">Rechercher</flux:button>
            @if ($search)<flux:button :href="route('central.permissions.index')">Effacer la recherche</flux:button>@endif
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-4">Groupe</th><th class="p-4">Code technique</th><th class="p-4">Libellé</th><th class="p-4">Actions</th></tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($permissions as $permission)
                        <tr>
                            <td class="p-4">{{ $groups[$permission->name] ?? 'Autres permissions centrales' }}</td>
                            <td class="p-4">{{ $permission->name }}</td>
                            <td class="p-4">{{ $permission->label }}</td>
                            <td class="p-4">
                                <flux:button :href="route('central.permissions.show', $permission)">Informations</flux:button>
                                @can('update', $permission)
                                    <flux:button :href="route('central.permissions.show', $permission).'#edit-label'">Modifier</flux:button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-4" colspan="4">Aucune permission ne correspond à la recherche.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $permissions->links() }}
    </section>
</x-layouts::app>

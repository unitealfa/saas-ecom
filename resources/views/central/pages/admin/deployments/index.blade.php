<x-layouts::app :title="__('Déploiements')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Déploiements des bases boutiques</flux:heading>
        <flux:text>Suivez la création et les mises à jour techniques. Les opérations sont exécutées par le worker central, sans créer de nouvelle boutique.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="GET" action="{{ route('central.deployments.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" :value="request('search')" label="Boutique" placeholder="Nom de la boutique" />
            <flux:select name="status" label="État">
                <option value="">Tous les états</option>
                @foreach (\App\Enums\Central\Deployments\StatusEnum::cases() as $status)
                    <option value="{{ $status->value }}" @selected((string) request('status') === (string) $status->value)>{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select name="operation" label="Opération">
                <option value="">Toutes les opérations</option>
                @foreach (\App\Enums\Central\Deployments\OperationEnum::cases() as $operation)
                    <option value="{{ $operation->value }}" @selected((string) request('operation') === (string) $operation->value)>{{ $operation->label() }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>
                    @foreach (['Boutique', 'Opération', 'État', 'Tentative', 'Version cible', 'Déployer le', 'Actions'] as $heading)
                        <th class="p-4">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($deployments as $deployment)
                        <tr>
                            <td class="p-4">{{ $deployment->tenant?->shop_name ?? 'Boutique indisponible' }}
                                @if ($deployment->tenant?->trashed())<flux:badge>Archivée</flux:badge>@endif
                            </td>
                            <td class="p-4">{{ $deployment->operation->label() }}</td>
                            <td class="p-4">{{ $deployment->status->label() }}</td>
                            <td class="p-4">{{ $deployment->attempt_number }}</td>
                            <td class="p-4">{{ $deployment->target_version }}</td>
                            <td class="p-4">{{ $deployment->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="p-4"><flux:button :href="route('central.deployments.show', $deployment)">Informations</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4">Aucun déploiement trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $deployments->links() }}
    </section>
</x-layouts::app>

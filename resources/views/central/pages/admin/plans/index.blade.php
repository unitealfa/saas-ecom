<x-layouts::app :title="__('Plans et offres')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Plans et offres</flux:heading>
        <flux:text>Choisissez le plan attribué gratuitement aux nouveaux commerçants et utilisé lorsque leurs autres abonnements expirent.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @can('create', \App\Models\Central\Plans\Plan::class)
            <div><flux:button :href="route('central.plan-pages.create')" variant="primary">Créer un plan</flux:button></div>
        @endcan
        <form method="GET" action="{{ route('central.plan-pages.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Rechercher" :value="request('search')" placeholder="Nom ou code" />
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>@foreach (['Plan', 'Version', 'Prix mensuel', 'Prix annuel', 'Commercialisation', 'Actions'] as $heading)<th class="p-4">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="p-4">{{ $plan->name }}@if ($plan->is_default)<flux:badge>Par défaut — attribution gratuite</flux:badge>@endif<div class="text-xs">{{ $plan->code }}</div></td>
                            <td class="p-4">{{ $plan->version }}</td>
                            <td class="p-4">{{ $plan->monthly_price }} DZD</td>
                            <td class="p-4">{{ $plan->annual_price }} DZD</td>
                            <td class="p-4">{{ $plan->trashed() ? 'Archivée' : ($plan->is_active ? 'Active' : 'Inactive') }}</td>
                            <td class="p-4">
                                <flux:button :href="route('central.plan-pages.show', $plan)">Détails</flux:button>
                                @can('setDefault', $plan)
                                    @if (! $plan->is_default)
                                        <form method="POST" action="{{ route('central.plan-pages.default', $plan) }}" class="mt-3 flex flex-col gap-2">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="expected_state" value="{{ $plan->compositionState($plan->features) }}">
                                            <flux:checkbox name="confirmed" value="1" label="Attribuer gratuitement ce plan par défaut aux commerçants" required />
                                            <flux:button type="submit">Définir comme plan par défaut</flux:button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Aucun plan trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $plans->links() }}
    </section>
</x-layouts::app>

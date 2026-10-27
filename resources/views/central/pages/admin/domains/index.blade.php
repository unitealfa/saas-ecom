<x-layouts::app :title="__('Domaines')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Domaines des boutiques</flux:heading>
        <flux:text>Chaque domaine appartient à une seule boutique. Un domaine archivé reste réservé et ne permet plus d’accéder à la boutique.</flux:text>
        @include('central.pages.admin.domains.messages')
        <div class="flex flex-wrap gap-3">
            @can('create', \App\Models\Central\Domains\Domain::class)
                <flux:button :href="route('central.domains.create')" variant="primary">Ajouter un domaine</flux:button>
            @endcan
        </div>
        <form method="GET" action="{{ route('central.domains.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" :value="request('search')" label="Rechercher" placeholder="Domaine ou nom de boutique" />
            <flux:select name="archived" label="État">
                <option value="0" @selected(! request()->boolean('archived'))>Domaines actuels</option>
                <option value="1" @selected(request()->boolean('archived'))>Domaines archivés</option>
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>
                    @foreach (['Domaine', 'Boutique', 'Type', 'DNS', 'HTTPS (dernier état connu)', 'Actions'] as $heading)
                        <th class="p-4">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($domains as $domain)
                        <tr>
                            <td class="p-4">
                                {{ $domain->domain }}
                                @if ($domain->is_primary)<flux:badge>Principal</flux:badge>@endif
                                @if ($domain->trashed())<flux:badge>Archivé</flux:badge>@endif
                            </td>
                            <td class="p-4">{{ $domain->tenant?->shop_name ?? 'Boutique indisponible' }}</td>
                            <td class="p-4">{{ $domain->type === \App\Enums\Central\Domains\TypeEnum::SUBDOMAIN ? 'Sous-domaine Aydra' : 'Personnalisé' }}</td>
                            <td class="p-4">{{ match ($domain->verification_status) {
                                \App\Enums\VerificationStatusEnum::VERIFIED => 'Vérifié',
                                \App\Enums\VerificationStatusEnum::FAILED => 'Échec',
                                \App\Enums\VerificationStatusEnum::EXPIRED => 'Expiré',
                                default => 'En attente',
                            } }}</td>
                            <td class="p-4">{{ match ($domain->certificate_status) {
                                \App\Enums\Central\Domains\CertificateStatusEnum::ACTIVE => 'Valide',
                                \App\Enums\Central\Domains\CertificateStatusEnum::ERROR => 'Erreur',
                                \App\Enums\Central\Domains\CertificateStatusEnum::EXPIRED => 'Expiré',
                                \App\Enums\Central\Domains\CertificateStatusEnum::PENDING => 'En attente',
                                default => 'Non contrôlé',
                            } }}</td>
                            <td class="p-4">
                                @if (! $domain->trashed() && $domain->tenant !== null && ! $domain->tenant->trashed())
                                    <div class="flex flex-wrap gap-2">
                                        @can('update', $domain)
                                            <flux:button :href="route('central.domains.edit', $domain)">Modifier</flux:button>
                                        @endcan
                                        @can('verify', $domain)
                                            <form method="POST" action="{{ route('central.domains.verify', $domain) }}">
                                                @csrf
                                                <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                                <flux:button type="submit">Vérifier</flux:button>
                                            </form>
                                        @endcan
                                        @if (! $domain->is_primary)
                                            @can('setPrimary', $domain)
                                                <form method="POST" action="{{ route('central.domains.primary', $domain) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                                    <input type="hidden" name="expected_primary_uuid" value="{{ $primaryDomains[$domain->tenant_id] ?? '' }}">
                                                    <flux:button type="submit">Définir comme principal</flux:button>
                                                </form>
                                            @endcan
                                            @can('archive', $domain)
                                                <form method="POST" action="{{ route('central.domains.destroy', $domain) }}" x-data x-on:submit="if (! window.confirm('Archiver ce domaine ? Il ne donnera plus accès à la boutique.')) $event.preventDefault()">
                                                    @csrf @method('DELETE')
                                                    <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                                    <flux:button type="submit" variant="danger">Archiver</flux:button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                    @can('verify', $domain)
                                        @if ($domain->type === \App\Enums\Central\Domains\TypeEnum::CUSTOM)
                                            <details class="mt-3">
                                                <summary class="cursor-pointer">Configuration DNS</summary>
                                                @include('central.pages.admin.domains.dns', ['verificationToken' => $verificationTokens[$domain->uuid]])
                                            </details>
                                        @endif
                                    @endcan
                                @elseif ($domain->trashed())
                                    @can('restore', $domain)
                                        <form method="POST" action="{{ route('central.domains.restore', $domain) }}">
                                            @csrf
                                            <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                            <flux:button type="submit">Restaurer et vérifier à nouveau</flux:button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Aucun domaine trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $domains->links() }}
    </section>
</x-layouts::app>

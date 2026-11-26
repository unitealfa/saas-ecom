<x-layouts::app :title="__('Domaines de la boutique')">
    <section class="mx-auto flex max-w-4xl flex-col gap-5">
        <header class="flex flex-wrap items-start justify-between gap-4"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight">Domaines</h1><p class="aydra-muted text-sm">Gérez les adresses de {{ $tenant->shop_name }}.</p></div><flux:button :href="route('dashboard')" icon="arrow-left">Retour aux boutiques</flux:button></header>
        @if (session('status')) <flux:callout role="status">{{ session('status') }}</flux:callout> @endif
        @if ($errors->any()) <flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout> @endif
        <form method="POST" action="{{ route('owner.shops.domains.store', $tenant) }}" class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6"
              x-data="{ name: @js(old('domain', '')), confirming: false, confirmed: false }"
              x-on:submit="if (! confirmed) { $event.preventDefault(); confirming = true }">
            @csrf
            <flux:input name="domain" label="Nom du sous-domaine" placeholder="ma-boutique" x-model="name" maxlength="63" required />
            <flux:text>Adresse complète : <span x-text="name.trim().toLowerCase() + '.' + @js(trim((string) config('tenancy.saas_base_domain'), '.'))"></span></flux:text>
            <input type="hidden" name="confirmed" value="1" x-bind:disabled="! confirmed">
            <flux:button type="submit" variant="primary" class="self-start">Ajouter</flux:button>
            <div x-cloak x-show="confirming" role="dialog" aria-modal="true" aria-label="Confirmer le domaine" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="space-y-4 rounded-lg bg-white p-6 shadow-xl dark:bg-zinc-800">
                    <p>Voulez-vous enregistrer ce domaine ?</p>
                    <strong x-text="name.trim().toLowerCase() + '.' + @js(trim((string) config('tenancy.saas_base_domain'), '.'))"></strong>
                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" x-on:click="confirmed = true">OK</flux:button>
                        <flux:button type="button" x-on:click="confirming = false">Modifier</flux:button>
                        <flux:button type="button" x-on:click="confirming = false; name = ''">Annuler</flux:button>
                    </div>
                </div>
            </div>
        </form>
        @php($primaryUuid = $domains->firstWhere('is_primary', true)?->uuid)
        @foreach ($domains as $domain)
            <article class="aydra-users-panel space-y-3 p-5 sm:p-6">
                <flux:heading>{{ $domain->domain }} @if ($domain->is_primary) — Principal @endif</flux:heading>
                <flux:text>Vérification : {{ match ($domain->verification_status) { \App\Enums\VerificationStatusEnum::VERIFIED => 'Accepté', \App\Enums\VerificationStatusEnum::FAILED => 'Échec', default => 'En attente' } }}</flux:text>
                @if ($domain->type === \App\Enums\Central\Domains\TypeEnum::CUSTOM)
                    @include('central.pages.admin.domains.dns', ['verificationToken' => $inspector->token($domain), 'ownerMode' => true])
                @endif
                <div class="flex flex-wrap gap-2">
                    @if ($domain->verification_status !== \App\Enums\VerificationStatusEnum::VERIFIED)
                        <flux:text>En attente de vérification par un administrateur autorisé.</flux:text>
                    @endif
                    @if (! $domain->is_primary && $domain->isReadyAsPrimary())
                        <form method="POST" action="{{ route('owner.shops.domains.primary', [$tenant, $domain]) }}">@csrf @method('PATCH')
                            <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                            <input type="hidden" name="expected_primary_uuid" value="{{ $primaryUuid }}">
                            <flux:button type="submit">Définir comme principal</flux:button>
                        </form>
                    @endif
                    @if (! $domain->is_primary)
                        <form method="POST" action="{{ route('owner.shops.domains.destroy', [$tenant, $domain]) }}">@csrf @method('DELETE')
                            <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                            <flux:button type="submit" variant="danger">Archiver</flux:button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </section>
</x-layouts::app>

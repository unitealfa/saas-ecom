<x-layouts::app title="Informations du fichier">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $media->original_name }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $media->mime_type }}</span><flux:badge size="sm" :color="$media->trashed() ? 'zinc' : 'green'">{{ $media->trashed() ? 'Archivé' : 'Disponible au catalogue' }}</flux:badge></div></div><flux:button :href="route('central.media.index')" icon="arrow-left">Retour aux fichiers</flux:button></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations du fichier</h2><p class="aydra-muted mt-1 text-sm">Propriétés, provenance et visibilité.</p><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Identifiant public</dt><dd>{{ $media->uuid }}</dd></div>
            <div><dt>Type de contenu</dt><dd>{{ $media->mime_type }}</dd></div>
            <div><dt>Taille</dt><dd>{{ number_format($media->size_bytes / 1024, 1, ',', ' ') }} Ko</dd></div>
            <div><dt>Dimensions</dt><dd>{{ $media->width !== null && $media->height !== null ? $media->width.' × '.$media->height.' pixels' : 'Non applicable' }}</dd></div>
            <div><dt>Usage</dt><dd>{{ $media->isAdministrativeDocument() ? 'Document administratif' : ($media->collection_name === 'profile_photo' ? 'Photo de profil' : 'Pièce métier protégée') }}</dd></div>
            <div><dt>Importé par</dt><dd>{{ $media->creator?->name ?? 'Non renseigné' }}</dd></div>
            <div><dt>Ajouté le</dt><dd>{{ $media->created_at?->format('d/m/Y H:i') ?? 'Non renseigné' }}</dd></div>
            <div><dt>Visibilité</dt><dd>{{ $media->visibility === \App\Enums\MediaVisibilityEnum::PRIVATE ? 'Privé' : 'Public' }}</dd></div>
            <div><dt>État</dt><dd>{{ $media->trashed() ? 'Archivé' : 'Disponible au catalogue' }}</dd></div>
        </dl></section>
        @if ($downloadUrl)
            <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Téléchargement</h2><p class="aydra-muted mt-2 max-w-3xl text-sm">Le lien expire après cinq minutes. La connexion, les droits et l’intégrité du contenu seront revérifiés au téléchargement. Une fiche au catalogue ne garantit pas que le fichier physique est encore présent.</p><div class="mt-4"><flux:button :href="$downloadUrl" variant="primary" icon="arrow-down-tray">Télécharger le fichier privé</flux:button></div></section>
        @endif
        @if (! $media->isAdministrativeDocument())
            <flux:callout>Cette pièce appartient à une fonctionnalité métier. Le catalogue ne permet pas de contourner ses règles de conservation ou de retrait. Les photos restent gérées depuis le profil.</flux:callout>
        @endif
        @can('archive', $media)<section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions</h2>@include('central.pages.admin.media.archive-action')</section>@endcan
    </section>
</x-layouts::app>

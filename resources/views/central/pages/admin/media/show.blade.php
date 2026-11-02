<x-layouts::app title="Informations du fichier">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $media->original_name }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="space-y-3">
            <div><dt>Identifiant public</dt><dd>{{ $media->uuid }}</dd></div>
            <div><dt>Type de contenu</dt><dd>{{ $media->mime_type }}</dd></div>
            <div><dt>Taille</dt><dd>{{ number_format($media->size_bytes / 1024, 1, ',', ' ') }} Ko</dd></div>
            <div><dt>Dimensions</dt><dd>{{ $media->width !== null && $media->height !== null ? $media->width.' × '.$media->height.' pixels' : 'Non applicable' }}</dd></div>
            <div><dt>Usage</dt><dd>{{ $media->isAdministrativeDocument() ? 'Document administratif' : ($media->collection_name === 'profile_photo' ? 'Photo de profil' : 'Pièce métier protégée') }}</dd></div>
            <div><dt>Importé par</dt><dd>{{ $media->creator?->name ?? 'Non renseigné' }}</dd></div>
            <div><dt>Ajouté le</dt><dd>{{ $media->created_at?->format('d/m/Y H:i') ?? 'Non renseigné' }}</dd></div>
            <div><dt>Visibilité</dt><dd>{{ $media->visibility === \App\Enums\MediaVisibilityEnum::PRIVATE ? 'Privé' : 'Public' }}</dd></div>
            <div><dt>État</dt><dd>{{ $media->trashed() ? 'Archivé' : 'Disponible au catalogue' }}</dd></div>
        </dl>
        @if ($downloadUrl)
            <flux:button :href="$downloadUrl" variant="primary">Télécharger le fichier privé</flux:button>
            <flux:text>Le lien expire après cinq minutes. La connexion, les droits et l’intégrité du contenu seront revérifiés au téléchargement. Une fiche au catalogue ne garantit pas que le fichier physique est encore présent.</flux:text>
        @endif
        @if (! $media->isAdministrativeDocument())
            <flux:text>Cette pièce appartient à une fonctionnalité métier. Le catalogue ne permet pas de contourner ses règles de conservation ou de retrait. Les photos restent gérées depuis le profil.</flux:text>
        @endif
        @include('central.pages.admin.media.archive-action')
        <flux:button :href="route('central.media.index')">Retour aux fichiers</flux:button>
    </section>
</x-layouts::app>

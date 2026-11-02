<x-layouts::app title="Fichiers centraux">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Fichiers centraux</flux:heading>
        <flux:text>Fichiers de la plateforme centrale uniquement. Les fichiers des boutiques restent isolés dans leurs propres espaces.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @can('upload', \App\Models\Central\Media\Media::class)<flux:button :href="route('central.media.create')" variant="primary">Importer un fichier</flux:button>@endcan
        <form method="GET" action="{{ route('central.media.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Nom du fichier" :value="request('search')" />
            <flux:checkbox name="archived" value="1" label="Archives uniquement" :checked="request()->boolean('archived')" />
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr><th class="p-2">Fichier</th><th class="p-2">Type</th><th class="p-2">Taille</th><th class="p-2">Usage</th><th class="p-2">Visibilité</th><th class="p-2">État</th><th class="p-2">Actions</th></tr></thead>
                <tbody>
                    @forelse ($files as $media)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-2">{{ $media->original_name }}</td>
                            <td class="p-2">{{ $media->mime_type }}</td>
                            <td class="p-2">{{ number_format($media->size_bytes / 1024, 1, ',', ' ') }} Ko</td>
                            <td class="p-2">{{ $media->isAdministrativeDocument() ? 'Document administratif' : ($media->collection_name === 'profile_photo' ? 'Photo de profil' : 'Pièce métier') }}</td>
                            <td class="p-2">{{ $media->visibility === \App\Enums\MediaVisibilityEnum::PRIVATE ? 'Privé' : 'Public' }}</td>
                            <td class="p-2">{{ $media->trashed() ? 'Archivé' : 'Disponible au catalogue' }}</td>
                            <td class="p-2"><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('central.media.show', $media)">Informations</flux:button>
                                @include('central.pages.admin.media.archive-action')
                            </div></td>
                        </tr>
                    @empty<tr><td colspan="7" class="p-2">Aucun fichier central.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $files->links() }}
    </section>
</x-layouts::app>

<x-layouts::app title="Importer un fichier central">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Importer un fichier central</flux:heading>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:text>PDF, JPG, PNG ou WebP, {{ \App\Services\Central\Media\CentralMediaService::MAX_SIZE_KB / 1024 }} Mo maximum. Images : {{ \App\Services\Central\Media\CentralMediaService::MAX_DIMENSION }} pixels maximum par côté. Le fichier restera privé et sera rattaché à votre compte dans la collection des documents administratifs. Cet import ne remplace pas une facture ou une preuve métier.</flux:text>
        <form method="POST" action="{{ route('central.media.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf
            <flux:input type="file" name="file" label="Fichier" accept=".pdf,.jpg,.jpeg,.png,.webp" required />
            <flux:button type="submit" variant="primary">Importer le fichier privé</flux:button>
        </form>
        @can('viewAny', \App\Models\Central\Media\Media::class)<flux:button :href="route('central.media.index')">Retour aux fichiers</flux:button>@endcan
    </section>
</x-layouts::app>

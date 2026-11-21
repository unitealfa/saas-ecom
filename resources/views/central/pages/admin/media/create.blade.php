<x-layouts::app title="Importer un fichier central">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Importer un fichier central</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Ajoutez un document administratif privé à votre compte.</p></div>@can('viewAny', \App\Models\Central\Media\Media::class)<flux:button :href="route('central.media.index')" icon="arrow-left">Retour aux fichiers</flux:button>@endcan</header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.media.store') }}" enctype="multipart/form-data" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6">
            @csrf
            <div><h2 class="aydra-heading text-lg font-semibold">Fichier à importer</h2><p class="aydra-muted mt-2 max-w-2xl text-sm">PDF, JPG, PNG ou WebP, {{ \App\Services\Central\Media\CentralMediaService::MAX_SIZE_KB / 1024 }} Mo maximum. Images : {{ \App\Services\Central\Media\CentralMediaService::MAX_DIMENSION }} pixels maximum par côté. Le fichier restera privé et sera rattaché à votre compte dans la collection des documents administratifs. Cet import ne remplace pas une facture ou une preuve métier.</p></div>
            <flux:input type="file" name="file" label="Fichier" accept=".pdf,.jpg,.jpeg,.png,.webp" required />
            <flux:button type="submit" variant="primary" class="self-start">Importer le fichier privé</flux:button>
        </form>
    </section>
</x-layouts::app>

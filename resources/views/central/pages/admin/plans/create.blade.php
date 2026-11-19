<x-layouts::app :title="__('Créer un plan')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Créer un plan</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">La première version est créée inactive et sans fonctionnalités. Vous pourrez ensuite définir sa composition.</p></div><flux:button :href="route('central.plan-pages.index')" icon="arrow-left">Retour aux plans</flux:button></header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.plan-pages.store') }}" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6">
            @csrf
            <div><h2 class="aydra-heading text-lg font-semibold">Informations du plan</h2><p class="aydra-muted mt-1 text-sm">Définissez son identité et ses prix.</p></div>
            <flux:input name="code" label="Code permanent" :value="old('code')" required />
            <flux:input name="name" label="Nom" :value="old('name')" required />
            <flux:textarea name="description" label="Description">{{ old('description') }}</flux:textarea>
            <flux:input type="number" min="0" step="0.01" name="monthly_price" label="Prix mensuel (DZD)" :value="old('monthly_price')" required />
            <flux:input type="number" min="0" step="0.01" name="annual_price" label="Prix annuel (DZD)" :value="old('annual_price')" required />
            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary">Créer</flux:button><flux:button :href="route('central.plan-pages.index')">Retour</flux:button></div>
        </form>
    </section>
</x-layouts::app>

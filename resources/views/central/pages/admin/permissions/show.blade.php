<x-layouts::app :title="$permission->label">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $permission->label }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <dl class="grid gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-2 dark:border-zinc-700">
            <dt class="font-medium">Code technique</dt><dd>{{ $permission->name }}</dd>
            <dt class="font-medium">Groupe</dt><dd>{{ $group }}</dd>
            <dt class="font-medium">Contexte</dt><dd>Administration du SaaS</dd>
            <dt class="font-medium">Libellé affiché</dt><dd>{{ $permission->label }}</dd>
        </dl>
        @can('update', $permission)
            <form id="edit-label" method="POST" action="{{ route('central.permissions.update', $permission) }}" class="flex flex-col gap-4">
                @csrf @method('PUT')
                <flux:heading level="2">Modifier le libellé</flux:heading>
                <flux:text>Ce texte décrit l’action dans les formulaires de rôles. Sa modification conserve les droits et leurs attributions.</flux:text>
                <flux:input name="label" label="Libellé affiché" :value="old('label', $permission->label)" required />
                <flux:button type="submit" variant="primary">Enregistrer le libellé</flux:button>
            </form>
        @endcan
        <flux:button :href="route('central.permissions.index')">Retour au catalogue</flux:button>
    </section>
</x-layouts::app>

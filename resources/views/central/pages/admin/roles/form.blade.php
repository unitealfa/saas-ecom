<x-layouts::app :title="$role ? __('Modifier un rôle') : __('Créer un rôle')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $role ? 'Modifier le rôle' : 'Créer un rôle SaaS' }}</flux:heading>
        <flux:text>Chaque permission dure de 1 à 9 999 jours à compter de l’attribution du rôle. La valeur par défaut et le maximum sont 9 999 jours. Une modification s’applique à tous les comptes portant ce rôle.</flux:text>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ $role ? route('central.roles.update', $role) : route('central.roles.store') }}" class="flex flex-col gap-5">
            @csrf
            @if ($role) @method('PUT') @endif
            <flux:input name="name" label="Nom unique du rôle" :value="old('name', $role?->name)" required />
            <flux:input name="label" label="Nom affiché" :value="old('label', $role?->label)" required />
            <flux:text>Un droit de gestion donne aussi accès à la consultation de son module, pendant sa durée de validité et dans les limites des restrictions. Il ne donne aucun autre droit de modification.</flux:text>
            @foreach ($permissionGroups as $groupLabel => $permissionNames)
            <fieldset class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <legend class="px-2 font-medium">{{ $groupLabel }}</legend>
                @foreach ($permissions->whereIn('name', $permissionNames) as $permission)
                    <div class="grid gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-2 dark:border-zinc-700">
                        <flux:checkbox name="permissions[]" :value="$permission->uuid" :label="$permission->label"
                            :checked="in_array($permission->uuid, old('permissions', array_keys($grants)), true)" />
                        <flux:input type="number" name="durations[{{ $permission->uuid }}]" label="Durée en jours"
                            min="1" max="9999" :value="old('durations.'.$permission->uuid, $grants[$permission->uuid] ?? 9999)" required />
                    </div>
                @endforeach
            </fieldset>
            @endforeach
            <flux:text>Deux rôles ne peuvent avoir le même nom, ni exactement les mêmes permissions et durées.</flux:text>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Enregistrer le rôle</flux:button>
                <flux:button :href="route('central.roles.index')">Retour</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>

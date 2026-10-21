<x-layouts::app :title="$role ? __('Modifier un rôle') : __('Créer un rôle')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $role ? 'Modifier le rôle' : 'Créer un rôle SaaS' }}</flux:heading>
        <flux:text>Ce profil définit les droits attribués au départ, pour une durée de 1 à 9 999 jours. Modifier le profil ne change pas les droits des membres existants.</flux:text>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ $role ? route('central.roles.update', $role) : route('central.roles.store') }}" class="flex flex-col gap-5">
            @csrf
            @if ($role) @method('PUT') @endif
            @if ($role)
                <input type="hidden" name="expected_version" value="{{ old('expected_version', $role->permission_version) }}">
            @endif
            @if ($role === null || auth('central')->user()->can('update', $role))
                <flux:input name="name" label="Nom unique du rôle" :value="old('name', $role?->name)" required />
                <flux:input name="label" label="Nom affiché" :value="old('label', $role?->label)" required />
            @else
                <flux:text>{{ $role->label }} ({{ $role->name }})</flux:text>
            @endif
            <flux:text>Les consultations sont des permissions explicites. Sélectionnez les lectures nécessaires au parcours souhaité.</flux:text>
            @foreach ($permissionGroups as $groupLabel => $permissionNames)
            @if ($permissions->whereIn('name', $permissionNames)->isNotEmpty())
            <fieldset class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <legend class="px-2 font-medium">{{ $groupLabel }}</legend>
                @foreach ($permissions->whereIn('name', $permissionNames) as $permission)
                    <div class="grid gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-2 dark:border-zinc-700">
                        @if ($canChangePermissions)
                            <flux:checkbox name="permissions[]" :value="$permission->uuid" :label="$permission->label"
                                :checked="in_array($permission->uuid, old('permissions', array_keys($grants)), true)" />
                        @else
                            <flux:text>{{ $permission->label }}</flux:text>
                        @endif
                        @if ($canChangeDurations)
                            <flux:input type="number" name="durations[{{ $permission->uuid }}]" label="Durée en jours"
                                min="1" max="9999" :value="old('durations.'.$permission->uuid, $grants[$permission->uuid] ?? 9999)" required />
                        @endif
                    </div>
                @endforeach
            </fieldset>
            @endif
            @endforeach
            <flux:text>Deux rôles ne peuvent avoir le même nom, ni exactement les mêmes permissions et durées.</flux:text>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Enregistrer le rôle</flux:button>
                <flux:button :href="route('central.roles.index')">Retour</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>

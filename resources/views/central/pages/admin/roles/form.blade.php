<x-layouts::app :title="$role ? __('Modifier un rôle') : __('Créer un rôle')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $role ? 'Modifier le rôle' : 'Créer un rôle SaaS' }}</h1><p class="aydra-muted text-sm sm:text-base">Définissez le profil et les droits attribués au départ.</p></header>
        <flux:callout>Ce profil définit les droits attribués au départ, pour une durée de 1 à 9 999 jours. Modifier le profil ne change pas les droits des membres existants.</flux:callout>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form id="role-form" method="POST" action="{{ $role ? route('central.roles.update', $role) : route('central.roles.store') }}" class="flex flex-col gap-5">
            @csrf
            @if ($role) @method('PUT') @endif
            @if ($role)
                <input type="hidden" name="expected_version" value="{{ old('expected_version', $role->permission_version) }}">
            @endif
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="role-identity">
            <h2 id="role-identity" class="aydra-heading text-lg font-semibold">Identité du rôle</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
            @if ($role === null || auth('central')->user()->can('update', $role))
                <flux:input name="name" label="Nom unique du rôle" :value="old('name', $role?->name)" required />
                <flux:input name="label" label="Nom affiché" :value="old('label', $role?->label)" required />
            @else
                <flux:text>{{ $role->label }} ({{ $role->name }})</flux:text>
            @endif
            </div>
            </section>
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="role-permissions-heading">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 id="role-permissions-heading" class="aydra-heading text-lg font-semibold">Permissions et durées</h2>
                        <p class="aydra-muted mt-1 text-sm">Sélectionnez les droits et leurs durées dans le panneau dédié.</p>
                    </div>
                    <flux:modal.trigger name="role-permissions">
                        <flux:button type="button" icon="adjustments-horizontal">Gérer les permissions</flux:button>
                    </flux:modal.trigger>
                </div>
            </section>
            <flux:modal name="role-permissions" flyout class="w-full md:w-[42rem]">
                <div class="flex flex-col gap-5">
                    <div><flux:heading size="lg">Permissions et durées</flux:heading><flux:text class="mt-2">Les consultations sont des permissions explicites. Vos choix seront enregistrés avec le rôle.</flux:text></div>
            @foreach ($permissionGroups as $groupLabel => $permissionNames)
            @if ($permissions->whereIn('name', $permissionNames)->isNotEmpty())
            <details name="role-permission-groups" class="aydra-permission-group group rounded-lg border border-zinc-200 dark:border-zinc-700">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 font-medium text-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:text-zinc-100 [&::-webkit-details-marker]:hidden">
                    {{ $groupLabel }}
                    <flux:icon.chevron-down class="size-4 shrink-0 transition-transform duration-200 motion-reduce:transition-none group-open:rotate-180" aria-hidden="true" />
                </summary>
            <fieldset class="flex flex-col gap-4 border-t border-zinc-200 p-4 dark:border-zinc-700">
                <legend class="sr-only">{{ $groupLabel }}</legend>
                <flux:checkbox.group>
                @if ($canChangePermissions)
                    <flux:checkbox.all :label="'Tout sélectionner : '.$groupLabel" />
                @endif
                @foreach ($permissions->whereIn('name', $permissionNames) as $permission)
                    <div class="grid gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-2 dark:border-zinc-700">
                        @if ($canChangePermissions)
                            <flux:checkbox name="permissions[]" form="role-form" :value="$permission->uuid" :label="$permission->label"
                                :checked="in_array($permission->uuid, old('permissions', array_keys($grants)), true)" />
                        @else
                            <flux:text>{{ $permission->label }}</flux:text>
                        @endif
                        @if ($canChangeDurations)
                            <flux:input type="number" name="durations[{{ $permission->uuid }}]" form="role-form" label="Durée en jours" x-on:invalid="if ($el.form.querySelector(':invalid') === $el) { $el.closest('details').open = true; $flux.modal('role-permissions').show() }"
                                min="1" max="9999" :value="old('durations.'.$permission->uuid, $grants[$permission->uuid] ?? 9999)" required />
                        @endif
                    </div>
                @endforeach
                </flux:checkbox.group>
            </fieldset>
            </details>
            @endif
            @endforeach
                    <flux:modal.close><flux:button type="button" variant="primary">Terminé</flux:button></flux:modal.close>
                </div>
            </flux:modal>
            <flux:text>Deux rôles ne peuvent avoir le même nom, ni exactement les mêmes permissions et durées.</flux:text>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Enregistrer le rôle</flux:button>
                <flux:button :href="route('central.roles.index')">Retour</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>

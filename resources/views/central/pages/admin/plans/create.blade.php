<x-layouts::app :title="__('Créer un plan')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">

        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2">
                <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    Créer un plan
                </h1>

                <p class="aydra-muted max-w-2xl text-sm sm:text-base">
                    Configurez les informations, les tarifs, les fonctionnalités
                    et les quotas du plan. La première version sera créée inactive.
                </p>
            </div>

            <flux:button :href="route('central.plan-pages.index')" icon="arrow-left">
                Retour aux plans
            </flux:button>
        </header>

        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </flux:callout>
        @endif

        <form
            method="POST"
            action="{{ route('central.plan-pages.store') }}"
            class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6"
            x-data="{ selectionError: false }"
            x-on:submit="
                selectionError = ! $el.querySelector('[data-feature]:checked');
                if (selectionError) $event.preventDefault();
            "
        >
            @csrf

            <div>
                <h2 class="aydra-heading text-lg font-semibold">
                    Informations du plan
                </h2>

                <p class="aydra-muted mt-1 text-sm">
                    Définissez son identité et ses prix.
                </p>
            </div>

            <flux:input
                name="code"
                label="Code permanent"
                :value="old('code')"
                required
            />

            <flux:input
                name="name"
                label="Nom"
                :value="old('name')"
                required
            />

            <flux:textarea name="description" label="Description">
                {{ old('description') }}
            </flux:textarea>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    type="number"
                    min="0"
                    step="0.01"
                    name="monthly_price"
                    label="Prix mensuel (DZD)"
                    :value="old('monthly_price')"
                    required
                />

                <flux:input
                    type="number"
                    min="0"
                    step="0.01"
                    name="annual_price"
                    label="Prix annuel (DZD)"
                    :value="old('annual_price')"
                    required
                />
            </div>

            <flux:separator />

            <fieldset class="flex flex-col gap-4">

                <legend class="aydra-heading text-lg font-semibold">
                    Fonctionnalités et quotas
                </legend>

                <p class="aydra-muted text-sm">
                    Sélectionnez les fonctionnalités incluses dans ce plan
                    et configurez leurs limites d'utilisation.
                </p>

                <div x-cloak x-show="selectionError">
                    <flux:callout variant="danger" role="alert">
                        Sélectionnez au moins une fonctionnalité.
                    </flux:callout>
                </div>

                @forelse ($availableFeatures as $feature)

                    @php
                        $oldFeature = collect(old('features', []))
                            ->firstWhere('uuid', $feature->uuid);
                    @endphp

                    <div
                        x-data="{
                            selected: @js($oldFeature !== null),
                            unlimited: @js((bool) ($oldFeature['unlimited'] ?? false))
                        }"
                        class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"
                    >
                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                type="checkbox"
                                data-feature
                                x-model="selected"
                                x-on:change="selectionError = false"
                                class="h-4 w-4 rounded border-zinc-300 dark:border-zinc-600"
                            />

                            <span class="aydra-heading font-medium">
                                {{ $feature->name }}
                            </span>
                        </label>

                        <template x-if="selected">
                            <div class="mt-4 flex flex-col gap-3">

                                <input
                                    type="hidden"
                                    name="features[{{ $loop->index }}][uuid]"
                                    value="{{ $feature->uuid }}"
                                />

                                @if ($feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::QUANTITY)

                                    <label class="flex cursor-pointer items-center gap-3">
                                        <input
                                            type="checkbox"
                                            x-model="unlimited"
                                            class="h-4 w-4 rounded border-zinc-300 dark:border-zinc-600"
                                        />

                                        <span class="text-sm">
                                            Sans limite
                                        </span>
                                    </label>

                                    <input
                                        type="hidden"
                                        name="features[{{ $loop->index }}][unlimited]"
                                        x-bind:value="unlimited ? 1 : 0"
                                    />

                                    <div x-show="! unlimited">
                                        <flux:input
                                            type="number"
                                            min="0"
                                            step="1"
                                            label="Limite"
                                            name="features[{{ $loop->index }}][limit]"
                                            :value="$oldFeature['limit'] ?? null"
                                            x-bind:disabled="unlimited"
                                            x-bind:required="! unlimited"
                                        />
                                    </div>

                                @else

                                    <input
                                        type="hidden"
                                        name="features[{{ $loop->index }}][unlimited]"
                                        value="0"
                                    />

                                @endif

                            </div>
                        </template>
                    </div>

                @empty

                    <flux:callout variant="warning">
                        Aucune fonctionnalité active disponible.
                        Créez une fonctionnalité avant de créer un plan.
                    </flux:callout>

                @endforelse

            </fieldset>

            <flux:separator />

            <div class="flex flex-wrap gap-3">
                <flux:button
                    type="submit"
                    variant="primary"
                    :disabled="$availableFeatures->isEmpty()"
                >
                    Créer
                </flux:button>

                <flux:button :href="route('central.plan-pages.index')">
                    Retour
                </flux:button>
            </div>

        </form>
    </section>
</x-layouts::app>
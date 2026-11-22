<x-layouts::app :title="__('Créer un plan')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Créer un plan</flux:heading>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('central.plan-pages.store') }}" class="flex flex-col gap-5" x-data="{ selectionError: false }" x-on:submit="selectionError = ! $el.querySelector('[data-feature]:checked'); if (selectionError) $event.preventDefault()">
            @csrf
            <flux:input name="code" label="Code permanent" :value="old('code')" required />
            <flux:input name="name" label="Nom" :value="old('name')" required />
            <flux:textarea name="description" label="Description">{{ old('description') }}</flux:textarea>
            <flux:input type="number" min="0" step="0.01" name="monthly_price" label="Prix mensuel (DZD)" :value="old('monthly_price')" required />
            <flux:input type="number" min="0" step="0.01" name="annual_price" label="Prix annuel (DZD)" :value="old('annual_price')" required />
            <fieldset class="space-y-3">
                <legend>Fonctionnalités et quotas</legend>
                <p x-cloak x-show="selectionError" role="alert">Sélectionnez au moins une fonctionnalité.</p>
                @foreach ($availableFeatures as $feature)
                    <div x-data="{ selected: false, unlimited: false }" class="rounded border p-3">
                        <label><input type="checkbox" data-feature x-model="selected"> {{ $feature->name }}</label>
                        <template x-if="selected">
                            <div class="mt-2 space-y-2">
                                <input type="hidden" name="features[{{ $loop->index }}][uuid]" value="{{ $feature->uuid }}">
                                @if ($feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::QUANTITY)
                                    <label><input type="checkbox" x-model="unlimited"> Sans limite</label>
                                    <input type="hidden" name="features[{{ $loop->index }}][unlimited]" x-bind:value="unlimited ? 1 : 0">
                                    <label>Limite <input type="number" min="0" name="features[{{ $loop->index }}][limit]" x-bind:disabled="unlimited" x-bind:required="! unlimited"></label>
                                @else
                                    <input type="hidden" name="features[{{ $loop->index }}][unlimited]" value="0">
                                @endif
                            </div>
                        </template>
                    </div>
                @endforeach
            </fieldset>
            <div class="flex gap-3"><flux:button type="submit" variant="primary">Créer</flux:button><flux:button :href="route('central.plan-pages.index')">Retour</flux:button></div>
        </form>
    </section>
</x-layouts::app>

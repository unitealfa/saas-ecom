@foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
@if ($referenced)<flux:text>Cette fonctionnalité est utilisée ou réservée au système : seul son nom peut être modifié.</flux:text>@endif
<form method="POST" action="{{ $feature === null ? route('central.features.store') : route('central.features.update', $feature) }}" class="flex flex-col gap-5">
    @csrf
    @if ($feature !== null)
        @method('PUT')
        <input type="hidden" name="expected_state" value="{{ $feature->managementState() }}">
    @endif
    <flux:input name="code" label="Code technique permanent" :value="$feature?->code ?? old('code')" :readonly="$feature !== null" required placeholder="Ex. : local.products" />
    <flux:input name="name" label="Nom affiché" :value="old('name', $feature?->name)" required placeholder="Ex. : Nombre de produits" />
    <flux:select name="value_type" label="Type de valeur" :disabled="$referenced" required>
        @foreach (\App\Enums\Central\Features\ValueTypeEnum::cases() as $type)
            <option value="{{ $type->value }}" @selected((string) ($referenced ? $feature->value_type->value : old('value_type', $feature?->value_type->value ?? \App\Enums\Central\Features\ValueTypeEnum::QUANTITY->value)) === (string) $type->value)>{{ $type->label() }}</option>
        @endforeach
    </flux:select>
    <flux:input name="unit" label="Unité facultative" :value="$referenced ? $feature->unit : old('unit', $feature?->unit)" :readonly="$referenced" placeholder="Ex. : produits, commandes, octets" />
    <flux:select name="quota_scope" label="Portée du quota" :disabled="$referenced" required>
        @foreach (\App\Enums\Central\Features\QuotaScopeEnum::cases() as $scope)
            <option value="{{ $scope->value }}" @selected((string) ($referenced ? $feature->quota_scope->value : old('quota_scope', $feature?->quota_scope->value ?? \App\Enums\Central\Features\QuotaScopeEnum::OWNER->value)) === (string) $scope->value)>{{ $scope->label() }}</option>
        @endforeach
    </flux:select>
    <flux:select name="period" label="Période de comptage" :disabled="$referenced" required>
        @foreach (\App\Enums\Central\Features\PeriodEnum::cases() as $period)
            <option value="{{ $period->value }}" @selected((string) ($referenced ? $feature->period->value : old('period', $feature?->period->value ?? \App\Enums\Central\Features\PeriodEnum::LIFETIME->value)) === (string) $period->value)>{{ $period->label() }}</option>
        @endforeach
    </flux:select>
    @if ($referenced)
        <input type="hidden" name="value_type" value="{{ $feature->value_type->value }}">
        <input type="hidden" name="quota_scope" value="{{ $feature->quota_scope->value }}">
        <input type="hidden" name="period" value="{{ $feature->period->value }}">
    @endif
    <div class="flex flex-wrap gap-3">
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        @can('viewAny', \App\Models\Central\Features\Feature::class)
            <flux:button :href="route('central.features.index')">Retour au catalogue</flux:button>
        @endcan
    </div>
</form>

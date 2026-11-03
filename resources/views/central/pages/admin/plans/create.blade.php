<x-layouts::app :title="__('Créer un plan')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Créer un plan</flux:heading>
        <flux:text>La première version est créée inactive et sans fonctionnalités. Vous pourrez ensuite définir sa composition.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('central.plan-pages.store') }}" class="flex flex-col gap-5">
            @csrf
            <flux:input name="code" label="Code permanent" :value="old('code')" required />
            <flux:input name="name" label="Nom" :value="old('name')" required />
            <flux:textarea name="description" label="Description">{{ old('description') }}</flux:textarea>
            <flux:input type="number" min="0" step="0.01" name="monthly_price" label="Prix mensuel (DZD)" :value="old('monthly_price')" required />
            <flux:input type="number" min="0" step="0.01" name="annual_price" label="Prix annuel (DZD)" :value="old('annual_price')" required />
            <div class="flex gap-3"><flux:button type="submit" variant="primary">Créer</flux:button><flux:button :href="route('central.plan-pages.index')">Retour</flux:button></div>
        </form>
    </section>
</x-layouts::app>

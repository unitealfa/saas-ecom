<x-layouts::app :title="__('Plans et offres')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">

        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2">
                <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">
                    Plans et offres
                </h1>

                <p class="aydra-muted max-w-2xl text-sm sm:text-base">
                    Choisissez le plan attribué gratuitement aux nouveaux
                    commerçants et utilisé lorsque leurs autres abonnements expirent.
                </p>
            </div>

            @can('create', \App\Models\Central\Plans\Plan::class)
                <flux:button
                    :href="route('central.plan-pages.create')"
                    variant="primary"
                    icon="plus"
                >
                    Créer un plan
                </flux:button>
            @endcan
        </header>

        @if (session('status'))
            <flux:callout role="status">
                {{ session('status') }}
            </flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </flux:callout>
        @endif

        <section
            class="aydra-users-panel p-5 sm:p-6"
            aria-labelledby="plan-search"
        >
            <h2 id="plan-search" class="aydra-heading text-lg font-semibold">
                Rechercher un plan
            </h2>

            <p class="aydra-muted mt-1 text-sm">
                Les suggestions proviennent des plans affichés sur cette page.
            </p>

            <form
                method="GET"
                action="{{ route('central.plan-pages.index') }}"
                class="mt-5 flex flex-wrap items-end gap-3"
            >
                <flux:input
                    name="search"
                    label="Nom ou code"
                    :value="request('search')"
                    type="search"
                    icon="magnifying-glass"
                    placeholder="Nom ou code"
                    list="plan-suggestions"
                    class="min-w-0 flex-1 sm:min-w-72"
                />

                <datalist id="plan-suggestions">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->name }}"></option>
                        <option value="{{ $plan->code }}"></option>
                    @endforeach
                </datalist>

                @if (request()->filled('sort'))
                    <input
                        type="hidden"
                        name="sort"
                        value="{{ request('sort') }}"
                    >

                    <input
                        type="hidden"
                        name="direction"
                        value="{{ request('direction', 'asc') }}"
                    >
                @endif

                <flux:button type="submit" variant="primary">
                    Rechercher
                </flux:button>

                <flux:button :href="route('central.plan-pages.index')">
                    Effacer
                </flux:button>
            </form>
        </section>

        @php($sortBy = request('sort'))
        @php($sortDirection = request('direction', 'asc'))

        <section class="aydra-users-panel">

            <div class="aydra-users-toolbar">
                <div>
                    <h2 class="aydra-heading text-lg font-semibold">
                        Plans
                    </h2>

                    <p class="aydra-muted mt-1 text-sm">
                        {{ $plans->total() }}
                        {{ $plans->total() === 1 ? 'plan trouvé' : 'plans trouvés' }}
                    </p>
                </div>
            </div>

            <div class="aydra-users-table">

                <flux:table class="whitespace-normal">

                    <flux:table.columns>

                        @foreach ([
                            'name' => 'Plan',
                            'version' => 'Version',
                            'monthly_price' => 'Prix mensuel',
                            'annual_price' => 'Prix annuel',
                            'is_active' => 'Commercialisation',
                            'actions' => 'Actions'
                        ] as $column => $heading)

                            @if ($column !== 'actions')

                                <flux:table.column
                                    sortable
                                    :sorted="$sortBy === $column"
                                    :direction="$sortDirection"
                                    :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'"
                                    :data-sort-url="route('central.plan-pages.index', [
                                        ...request()->only(['search']),
                                        'sort' => $column,
                                        'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'
                                    ])"
                                    onclick="window.location.assign(this.dataset.sortUrl)"
                                >
                                    {{ $heading }}
                                </flux:table.column>

                            @else

                                <flux:table.column>
                                    {{ $heading }}
                                </flux:table.column>

                            @endif

                        @endforeach

                    </flux:table.columns>

                    <flux:table.rows>

                        @forelse ($plans as $plan)

                            <flux:table.row :key="$plan->uuid">

                                <flux:table.cell variant="strong">
                                    {{ $plan->name }}

                                    @if ($plan->is_default)
                                        <flux:badge size="sm">
                                            Par défaut — attribution gratuite
                                        </flux:badge>
                                    @endif

                                    <div class="aydra-muted mt-1 text-xs">
                                        {{ $plan->code }}
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    {{ $plan->version }}
                                </flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap tabular-nums">
                                    {{ $plan->monthly_price }} DZD
                                </flux:table.cell>

                                <flux:table.cell class="whitespace-nowrap tabular-nums">
                                    {{ $plan->annual_price }} DZD
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge
                                        size="sm"
                                        :color="$plan->trashed() ? 'zinc' : ($plan->is_active ? 'green' : 'amber')"
                                    >
                                        {{ $plan->trashed() ? 'Archivée' : ($plan->is_active ? 'Active' : 'Inactive') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-2">

                                        <flux:button
                                            :href="route('central.plan-pages.show', $plan)"
                                        >
                                            Détails
                                        </flux:button>

                                        @can('setDefault', $plan)
                                            @if (! $plan->is_default)

                                                <form
                                                    method="POST"
                                                    action="{{ route('central.plan-pages.default', $plan) }}"
                                                    onsubmit="return confirm('Attribuer gratuitement ce plan par défaut aux commerçants ?')"
                                                >
                                                    @csrf
                                                    @method('PATCH')

                                                    <input
                                                        type="hidden"
                                                        name="expected_state"
                                                        value="{{ $plan->compositionState($plan->features) }}"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="confirmed"
                                                        value="1"
                                                    >

                                                    <flux:button type="submit">
                                                        Définir comme plan par défaut
                                                    </flux:button>

                                                </form>

                                            @endif
                                        @endcan

                                    </div>
                                </flux:table.cell>

                            </flux:table.row>

                        @empty

                            <flux:table.row>
                                <flux:table.cell colspan="6">
                                    <div class="aydra-users-empty">

                                        <p class="aydra-heading font-medium">
                                            Aucun plan trouvé.
                                        </p>

                                        <p class="aydra-muted mt-1 text-sm">
                                            Essayez un autre nom ou code.
                                        </p>

                                    </div>
                                </flux:table.cell>
                            </flux:table.row>

                        @endforelse

                    </flux:table.rows>

                </flux:table>

                @if ($plans->hasPages())

                    <nav
                        aria-label="Pagination des plans"
                        class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"
                    >
                        <span class="aydra-muted text-sm">
                            Page {{ $plans->currentPage() }} sur {{ $plans->lastPage() }}
                        </span>

                        <div class="flex gap-2">

                            @if ($plans->onFirstPage())

                                <flux:button disabled icon="chevron-left">
                                    Précédent
                                </flux:button>

                            @else

                                <flux:button
                                    :href="$plans->previousPageUrl()"
                                    icon="chevron-left"
                                >
                                    Précédent
                                </flux:button>

                            @endif

                            @if ($plans->hasMorePages())

                                <flux:button
                                    :href="$plans->nextPageUrl()"
                                    icon:trailing="chevron-right"
                                >
                                    Suivant
                                </flux:button>

                            @else

                                <flux:button disabled icon:trailing="chevron-right">
                                    Suivant
                                </flux:button>

                            @endif

                        </div>
                    </nav>

                @endif

            </div>

        </section>

    </section>
</x-layouts::app>
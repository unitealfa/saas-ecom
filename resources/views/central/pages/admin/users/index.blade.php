<?php

use App\Models\User;
use App\Services\Central\Users\UserManagementService;
use App\Enums\Central\Users\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Utilisateurs')] class extends Component {
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $sortBy = 'id';

    public string $sortDirection = 'asc';

    public function sort(string $column): void
    {
        abort_unless(in_array($column, ['name', 'email'], true), 422);
        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<User> */
    #[Computed]
    public function users(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('viewAny', User::class);
        $this->validate(['search' => ['string', 'max:255'], 'status' => ['nullable', Rule::in(array_column(StatusEnum::cases(), 'value'))],
            'sortBy' => [Rule::in(['id', 'name', 'email'])], 'sortDirection' => [Rule::in(['asc', 'desc'])]]);

        return app(UserManagementService::class)->search(Auth::guard('central')->user(), $this->search,
            $this->status === '' ? null : StatusEnum::from((int) $this->status), $this->sortBy, $this->sortDirection);
    }
}; ?>

<section class="aydra-users mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
    <header class="flex flex-wrap items-start justify-between gap-5">
        <div class="space-y-2">
            <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Utilisateurs</h1>
            <p class="aydra-muted max-w-2xl text-sm sm:text-base">Retrouvez et gérez les comptes commerçants et le personnel SaaS.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('central.users.pending')" icon="clock">Inscriptions en attente</flux:button>
            @if (auth('central')->user()->can('create', \App\Models\User::class) || auth('central')->user()->can('createStaff', \App\Models\User::class))
                <flux:button :href="route('central.users.create')" variant="primary" icon="plus">Créer un compte</flux:button>
            @endif
        </div>
    </header>
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
    @if ($errors->any())
        <flux:callout variant="danger" role="alert">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </flux:callout>
    @endif
    <div class="aydra-users-panel">
        <div class="aydra-users-toolbar">
            <div class="min-w-0 flex-1">
                <h2 class="aydra-heading text-lg font-semibold">Comptes</h2>
                <p class="aydra-muted mt-1 text-sm">{{ $this->users->total() }} {{ $this->users->total() === 1 ? 'compte trouvé' : 'comptes trouvés' }}</p>
            </div>
            <div class="grid w-full gap-3 sm:grid-cols-2 lg:w-auto lg:min-w-[32rem]">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Rechercher par nom ou e-mail')" type="search" icon="magnifying-glass" placeholder="Nom ou e-mail" />
                <flux:select wire:model.live="status" label="État du compte">
        <option value="">Comptes non archivés</option>
        <option value="1">Actifs</option>
        <option value="2">Inactifs</option>
        <option value="3">Suspendus</option>
        <option value="4">Archivés</option>
        <option value="5">En attente d’approbation</option>
        <option value="6">Refusés</option>
                </flux:select>
            </div>
        </div>
        <div class="aydra-users-table">
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" :aria-sort="$sortBy === 'name' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" wire:click="sort('name')">{{ __('Nom') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" :aria-sort="$sortBy === 'email' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" wire:click="sort('email')">{{ __('E-mail') }}</flux:table.column>
                <flux:table.column>{{ __('Type de compte') }}</flux:table.column>
                <flux:table.column>{{ __('État') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->uuid">
                        <flux:table.cell variant="strong"><div class="flex items-center gap-3"><flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="sm" /><span>{{ $user->name }}</span></div></flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->roles->pluck('label')->join(', ') ?: __('Commerçant') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($user->status) {
                                StatusEnum::ACTIVE => 'green',
                                StatusEnum::PENDING => 'amber',
                                StatusEnum::SUSPENDED, StatusEnum::REJECTED => 'red',
                                default => 'zinc',
                            }">{{ $user->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu>
                            @can('view', $user)
                                <flux:menu.item :href="route('central.users.show', $user)">Informations</flux:menu.item>
                            @endcan
                            @can('update', $user)
                                <flux:menu.item :href="route('central.users.edit', $user)">Modifier</flux:menu.item>
                            @endcan
                            @can('updateStaffPermissions', $user)
                                <flux:menu.item :href="route('central.staff.permissions.index', $user)">Modifier les permissions</flux:menu.item>
                            @elsecan('viewStaffPermissions', $user)
                                <flux:menu.item :href="route('central.staff.permissions.index', $user)">Permissions effectives</flux:menu.item>
                            @endcan
                            @can('create', [\App\Models\Central\Tenants\Tenant::class, $user])
                                <flux:menu.item :href="route('central.users.tenants.create', $user)">Boutiques</flux:menu.item>
                            @endcan
                            @include('central.pages.admin.users.lifecycle-actions', ['inActionMenu' => true])
                            </flux:menu></flux:dropdown></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun utilisateur trouvé.</p><p class="aydra-muted mt-1 text-sm">Essayez un autre nom, e-mail ou état de compte.</p></div></flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        </div>
    </div>
</section>

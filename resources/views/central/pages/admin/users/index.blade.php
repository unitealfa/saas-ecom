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

<section class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Utilisateurs') }}</flux:heading>
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
    @if ($errors->any())
        <flux:callout variant="danger" role="alert">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </flux:callout>
    @endif
    @if (auth('central')->user()->can('create', \App\Models\User::class) || auth('central')->user()->can('createStaff', \App\Models\User::class))
        <flux:button :href="route('central.users.create')" variant="primary">Créer un compte</flux:button>
    @endif
    <flux:button :href="route('central.users.pending')">Inscriptions en attente</flux:button>
    <flux:text>{{ __('Comptes de la plateforme centrale : commerçants et personnel SaaS.') }}</flux:text>
    <flux:input wire:model.live.debounce.300ms="search" :label="__('Rechercher par nom ou e-mail')" type="search" />
    <flux:select wire:model.live="status" label="État du compte">
        <option value="">Comptes non archivés</option>
        <option value="1">Actifs</option>
        <option value="2">Inactifs</option>
        <option value="3">Suspendus</option>
        <option value="4">Archivés</option>
        <option value="5">En attente d’approbation</option>
        <option value="6">Refusés</option>
    </flux:select>
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
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
                        <flux:table.cell><div class="flex items-center gap-2"><flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="sm" />{{ $user->name }}</div></flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->roles->pluck('label')->join(', ') ?: __('Commerçant') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->status->label() }}</flux:table.cell>
                        <flux:table.cell>
                            @can('view', $user)
                                <flux:button :href="route('central.users.show', $user)">Informations</flux:button>
                            @endcan
                            @can('update', $user)
                                <flux:button :href="route('central.users.edit', $user)">Modifier</flux:button>
                            @endcan
                            @can('updateStaffPermissions', $user)
                                <flux:button :href="route('central.staff.permissions.index', $user)">Modifier les permissions</flux:button>
                            @elsecan('viewStaffPermissions', $user)
                                <flux:button :href="route('central.staff.permissions.index', $user)">Permissions effectives</flux:button>
                            @endcan
                            @can('create', [\App\Models\Central\Tenants\Tenant::class, $user])
                                <flux:button :href="route('central.users.tenants.create', $user)">Boutiques</flux:button>
                            @endcan
                            @include('central.pages.admin.users.lifecycle-actions')
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5">{{ __('Aucun utilisateur trouvé.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>

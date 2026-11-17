<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\User;
use App\Services\Central\Users\StaffManagementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app'), Title('Personnel SaaS')] class extends Component
{
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

    public function mount(): void
    {
        Gate::authorize('viewAnyStaff', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Models\Central\Users\User> */
    #[Computed]
    public function staff(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('viewAnyStaff', User::class);
        $this->validate([
            'search' => ['string', 'max:255'],
            'status' => ['nullable', Rule::in(array_column(StatusEnum::cases(), 'value'))],
            'sortBy' => [Rule::in(['id', 'name', 'email'])],
            'sortDirection' => [Rule::in(['asc', 'desc'])],
        ]);

        return app(StaffManagementService::class)->search(Auth::guard('central')->user(), $this->search,
            $this->status === '' ? null : StatusEnum::from((int) $this->status), $this->sortBy, $this->sortDirection);
    }
};
?>

<section class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">Personnel SaaS</flux:heading>
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
    @can('createStaff', \App\Models\User::class)
        <flux:button :href="route('central.staff.create')" variant="primary">Inviter un administrateur</flux:button>
    @endcan
    <flux:text>Comptes possédant au moins un rôle SaaS, y compris lorsque leurs droits ont expiré.</flux:text>
    <flux:input wire:model.live.debounce.300ms="search" label="Rechercher par nom ou e-mail" type="search" />
    <flux:select wire:model.live="status" label="État du compte">
        <option value="">Comptes non archivés</option>
        @foreach (\App\Enums\Central\Users\StatusEnum::cases() as $state)
            <option value="{{ $state->value }}">{{ $state->label() }}</option>
        @endforeach
    </flux:select>
    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:table :paginate="$this->staff">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" :aria-sort="$sortBy === 'name' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" wire:click="sort('name')">Nom</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" :aria-sort="$sortBy === 'email' ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" wire:click="sort('email')">E-mail</flux:table.column>
                <flux:table.column>Rôles</flux:table.column>
                <flux:table.column>État</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->staff as $user)
                    <flux:table.row :key="$user->uuid">
                        <flux:table.cell><div class="flex items-center gap-2"><flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="sm" />{{ $user->name }}</div></flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>{{ $user->roles->pluck('label')->join(', ') }}</flux:table.cell>
                        <flux:table.cell>{{ $user->status->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button :href="route('central.staff.show', $user)">Informations</flux:button>
                            @include('central.pages.admin.staff.actions')
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5">Aucun administrateur trouvé.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>

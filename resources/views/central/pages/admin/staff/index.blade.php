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

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<\App\Models\Central\User> */
    #[Computed]
    public function staff(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Gate::authorize('viewAnyStaff', User::class);
        $this->validate([
            'search' => ['string', 'max:255'],
            'status' => ['nullable', Rule::in(array_column(StatusEnum::cases(), 'value'))],
        ]);

        return app(StaffManagementService::class)->search(Auth::guard('central')->user(), $this->search,
            $this->status === '' ? null : StatusEnum::from((int) $this->status));
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
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-4">Nom</th><th class="p-4">E-mail</th><th class="p-4">Rôles</th><th class="p-4">État</th><th class="p-4">Actions</th></tr></thead>
            <tbody>
                @forelse ($this->staff as $user)
                    <tr wire:key="staff-{{ $user->uuid }}">
                        <td class="p-4">{{ $user->name }}</td><td class="p-4">{{ $user->email }}</td>
                        <td class="p-4">{{ $user->roles->pluck('label')->join(', ') }}</td><td class="p-4">{{ $user->status->label() }}</td>
                        <td class="p-4">
                            <flux:button :href="route('central.staff.show', $user)">Informations</flux:button>
                            @include('central.pages.admin.staff.actions')
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4">Aucun administrateur trouvé.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->staff->links() }}
</section>

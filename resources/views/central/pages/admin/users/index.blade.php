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
        $this->validate(['search' => ['string', 'max:255'], 'status' => ['nullable', Rule::in(array_column(StatusEnum::cases(), 'value'))]]);

        return app(UserManagementService::class)->search(Auth::guard('central')->user(), $this->search,
            $this->status === '' ? null : StatusEnum::from((int) $this->status));
    }
}; ?>

<section class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Utilisateurs') }}</flux:heading>
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
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
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="p-4">{{ __('Nom') }}</th>
                    <th class="p-4">{{ __('E-mail') }}</th>
                    <th class="p-4">{{ __('Type de compte') }}</th>
                    <th class="p-4">{{ __('État') }}</th>
                    <th class="p-4">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->uuid }}">
                        <td class="p-4"><div class="flex items-center gap-2"><flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="sm" />{{ $user->name }}</div></td>
                        <td class="p-4">{{ $user->email }}</td>
                        <td class="p-4">{{ $user->roles->pluck('label')->join(', ') ?: __('Commerçant') }}</td>
                        <td class="p-4">{{ $user->status->label() }}</td>
                        <td class="p-4">
                            @can('view', $user)
                                <flux:button :href="route('central.users.show', $user)">Informations</flux:button>
                            @endcan
                            @can('update', $user)
                                <flux:button :href="route('central.users.edit', $user)">Modifier</flux:button>
                            @endcan
                            @can('assignRoles', $user)
                                <flux:button :href="route('central.users.roles.edit', $user)">Rôles</flux:button>
                            @endcan
                            @can('create', [\App\Models\Central\Tenant::class, $user])
                                <flux:button :href="route('central.users.tenants.create', $user)">Boutiques</flux:button>
                            @endcan
                            @include('central.pages.admin.users.lifecycle-actions')
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4">{{ __('Aucun utilisateur trouvé.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->users->links() }}
</section>

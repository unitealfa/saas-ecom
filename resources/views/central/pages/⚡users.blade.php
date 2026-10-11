<?php

use App\Models\User;
use App\Services\Central\CentralUserAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Utilisateurs')] class extends Component {
    use WithPagination;

    public string $search = '';

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
        $this->validate(['search' => ['string', 'max:255']]);
        $query = User::query()->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'email_verified_at'])
            ->with('roles:id,uuid,label,guard_name');
        app(CentralUserAccess::class)->scopeVisibleUsers($query, Auth::guard('central')->user());
        if (trim($this->search) !== '') {
            $query->where(function ($query): void {
                $search = '%'.trim($this->search).'%';
                $query->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search)->orWhere('email', 'like', $search);
            });
        }

        return $query->orderBy('id')->paginate(20);
    }
}; ?>

<section class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Utilisateurs') }}</flux:heading>
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
    @if (auth('central')->user()->can('create', \App\Models\User::class) || auth('central')->user()->can('createStaff', \App\Models\User::class))
        <flux:button :href="route('central.users.create')" variant="primary">Créer un compte</flux:button>
    @endif
    <flux:text>{{ __('Comptes de la plateforme centrale : commerçants et personnel SaaS.') }}</flux:text>
    <flux:input wire:model.live.debounce.300ms="search" :label="__('Rechercher par nom ou e-mail')" type="search" />
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr>
                    <th class="p-4">{{ __('Nom') }}</th>
                    <th class="p-4">{{ __('E-mail') }}</th>
                    <th class="p-4">{{ __('Rôles SaaS') }}</th>
                    <th class="p-4">{{ __('État') }}</th>
                    <th class="p-4">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->uuid }}">
                        <td class="p-4">{{ $user->name }}</td>
                        <td class="p-4">{{ $user->email }}</td>
                        <td class="p-4">{{ $user->roles->pluck('label')->join(', ') ?: __('Commerçant') }}</td>
                        <td class="p-4">{{ match ($user->status) {
                            \App\Enums\Users\StatusEnum::ACTIVE => __('Actif'),
                            \App\Enums\Users\StatusEnum::INACTIVE => __('Inactif'),
                            \App\Enums\Users\StatusEnum::SUSPENDED => __('Suspendu'),
                            \App\Enums\Users\StatusEnum::DELETED => __('Supprimé'),
                        } }}</td>
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
                            @include('central.pages.users.lifecycle-actions')
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

<?php

use App\Models\Central\Users\User;
use App\Services\Central\Subscriptions\SubscriptionCatalogService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Validate('string|max:255')]
    public string $search = '';

    #[Validate('nullable|uuid')]
    public ?string $ownerUuid = null;

    public function updatedSearch(): void
    {
        $this->resetPage('ownersPage');
    }

    /** @return array{owners: LengthAwarePaginator<int, User>, selectedOwner: ?User} */
    #[Computed]
    public function candidates(): array
    {
        $actor = auth('central')->user();
        abort_unless($actor instanceof User, 403);
        return app(SubscriptionCatalogService::class)->assignmentOwners($actor, $this->search, $this->ownerUuid);
    }
};
?>

<div class="space-y-3">
    @php(['owners' => $owners, 'selectedOwner' => $selectedOwner] = $this->candidates)
    <flux:input wire:model.live.debounce.350ms="search" x-on:keydown.enter.prevent label="Rechercher un commerçant" placeholder="Nom ou adresse e-mail" maxlength="255" />
    <flux:error name="search" />
    <p role="status" wire:loading>Recherche en cours…</p>
    <fieldset class="min-w-0 space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:loading.attr="disabled">
        <legend>Choisir le commerçant — {{ $owners->total() }} résultat(s), 10 par page</legend>
        <div class="max-h-48 space-y-3 overflow-y-auto overscroll-contain" tabindex="0" aria-label="Liste des commerçants">
            @if ($selectedOwner)
                <label class="flex items-center gap-3" wire:key="selected-{{ $selectedOwner->uuid }}">
                    <input type="radio" name="owner_uuid" value="{{ $selectedOwner->uuid }}" wire:model.live="ownerUuid" @checked($ownerUuid === $selectedOwner->uuid) required>
                    <span class="min-w-0 break-words">{{ $selectedOwner->name }} — {{ $selectedOwner->email }} (sélectionné)</span>
                </label>
            @endif
            @forelse ($owners as $owner)
                @continue($selectedOwner?->uuid === $owner->uuid)
                <label class="flex items-center gap-3" wire:key="owner-{{ $owner->uuid }}">
                    <input type="radio" name="owner_uuid" value="{{ $owner->uuid }}" wire:model.live="ownerUuid" required>
                    <span class="min-w-0 break-words">{{ $owner->name }} — {{ $owner->email }}</span>
                </label>
            @empty
                <flux:text>Aucun commerçant actif ne correspond à votre recherche.</flux:text>
            @endforelse
        </div>
        {{ $owners->links('livewire::simple-tailwind', data: ['scrollTo' => false]) }}
    </fieldset>
</div>

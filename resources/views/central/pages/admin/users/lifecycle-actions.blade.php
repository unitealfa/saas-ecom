@if (in_array($user->status, [\App\Enums\Central\Users\StatusEnum::INACTIVE, \App\Enums\Central\Users\StatusEnum::SUSPENDED], true))
    @can('activate', $user)
        <form method="POST" action="{{ route('central.users.status', $user) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Enums\Central\Users\StatusEnum::ACTIVE->value }}">
            <flux:button type="submit">Réactiver</flux:button>
        </form>
    @endcan
@else
    @can('suspend', $user)
        <form method="POST" action="{{ route('central.users.status', $user) }}" x-data x-on:submit="if (! window.confirm('Suspendre l’accès de ce compte ?')) $event.preventDefault()">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Enums\Central\Users\StatusEnum::SUSPENDED->value }}">
            <flux:button type="submit">Suspendre</flux:button>
        </form>
    @endcan
@endif
@can('archive', $user)
    <form method="POST" action="{{ route('central.users.destroy', $user) }}" x-data x-on:submit="if (! window.confirm('Archiver ce compte ? Les données historiques et les boutiques seront conservées.')) $event.preventDefault()">
        @csrf @method('DELETE')
        <flux:button type="submit" variant="danger">Archiver</flux:button>
    </form>
@endcan
@can('approve', $user)
    <flux:modal.trigger :name="'approve-registration-'.$user->uuid">
        <flux:button>Approuver</flux:button>
    </flux:modal.trigger>
    <flux:modal :name="'approve-registration-'.$user->uuid" class="md:w-96">
        <form method="POST" action="{{ route('central.users.approve', $user) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="expected_status" value="{{ $user->status->value }}">
            <input type="hidden" name="legal_profile_version" value="{{ $user->legal_profile_version ?? 0 }}">
            <flux:heading size="lg">Approuver l’inscription de {{ $user->name }}</flux:heading>
            <flux:text>Le compte et son dossier seront approuvés. La vérification de l’e-mail reste obligatoire. Aucune boutique ne sera créée automatiquement.</flux:text>
            <flux:modal.close><flux:button>Annuler</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">Confirmer l’approbation</flux:button>
        </form>
    </flux:modal>
@endcan
@can('reject', $user)
    <flux:modal.trigger :name="'reject-registration-'.$user->uuid">
        <flux:button variant="danger">Refuser</flux:button>
    </flux:modal.trigger>
    <flux:modal :name="'reject-registration-'.$user->uuid" class="md:w-96">
        <form method="POST" action="{{ route('central.users.reject', $user) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="expected_status" value="{{ $user->status->value }}">
            <input type="hidden" name="legal_profile_version" value="{{ $user->legal_profile_version ?? 0 }}">
            <flux:heading size="lg">Refuser l’inscription de {{ $user->name }}</flux:heading>
            <flux:textarea name="reason" label="Motif du refus" description="Obligatoire. Ce motif sera visible par le commerçant sur sa page d’inscription refusée." maxlength="1000" required />
            <flux:button type="submit" variant="danger">Confirmer le refus</flux:button>
        </form>
    </flux:modal>
@endcan

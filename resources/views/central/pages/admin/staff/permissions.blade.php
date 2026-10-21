<x-layouts::app title="Permissions individuelles">
    <section class="mx-auto flex max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">Permissions de {{ $user->name }}</flux:heading>
        <flux:text>Profils : {{ $user->roles->pluck('label')->join(', ') }}</flux:text>
        @can('assignStaffRoles', $user)
            <flux:button :href="route('central.staff.roles.edit', $user)">Rôles de {{ $user->name }}</flux:button>
        @endcan
        <flux:text>Les profils fournissent les droits de départ. Modifier les permissions ci-dessous concerne uniquement ce compte. Une attribution conservée garde ses dates ; une attribution expirée ne donne plus accès.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>
        @endif
        @php($canEdit = auth('central')->user()->can('updateStaffPermissions', $user) && \Illuminate\Support\Facades\Route::has('central.staff.permissions.update'))
        @if ($canEdit)
        <form method="POST" action="{{ route('central.staff.permissions.update', $user) }}" class="flex flex-col gap-5">
            @csrf @method('PUT')
            <input type="hidden" name="expected_state" value="{{ old('expected_state', $expectedState) }}">
        @endif
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-3">Permission</th><th class="p-3">Attribuée</th><th class="p-3">Source et dates UTC</th><th class="p-3">Accès actuel</th><th class="p-3">Durée et renouvellement</th></tr></thead>
                    <tbody>
                    @forelse ($permissions as $permission)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $permission['label'] }}<br>{{ $permission['code'] }}</td>
                            <td class="p-3">
                                @if ($permission['editable'])
                                    <input type="hidden" name="grants[{{ $permission['uuid'] }}][permission]" value="{{ $permission['uuid'] }}">
                                    <input type="hidden" name="grants[{{ $permission['uuid'] }}][enabled]" value="0">
                                    <flux:checkbox name="grants[{{ $permission['uuid'] }}][enabled]" value="1" :label="$permission['label']"
                                        :checked="(bool) old('grants.'.$permission['uuid'].'.enabled', $permission['direct'])" />
                                @else
                                    {{ $permission['direct'] || count($permission['sources']) > 0 ? 'Oui' : 'Non' }}
                                @endif
                            </td>
                            <td class="p-3">
                                @forelse ($permission['sources'] as $source)
                                    <p>{{ $source['label'] }}</p>
                                    <p>{{ $source['assigned_at']->format('d/m/Y H:i:s') }} → {{ $source['expires_at']?->format('d/m/Y H:i:s') ?? 'Privilège protégé' }}</p>
                                    @if ($source['expires_at']?->isPast())<p>Expirée</p>@elseif ($source['assigned_at']->isFuture())<p>À venir</p>@endif
                                @empty
                                    Aucune attribution
                                @endforelse
                            </td>
                            <td class="p-3">{{ $permission['effective'] ? 'Autorisé' : 'Non autorisé' }}</td>
                            <td class="p-3">
                                @if ($permission['editable'])
                                    <flux:input type="number" name="grants[{{ $permission['uuid'] }}][days]" label="Jours" min="1" max="9999"
                                        :value="old('grants.'.$permission['uuid'].'.days', $permission['days'])" required />
                                    @if ($permission['direct'])
                                        @can('renewStaffAssignments', $user)
                                            <flux:checkbox name="grants[{{ $permission['uuid'] }}][renew]" value="1" label="Renouveler explicitement"
                                                :checked="(bool) old('grants.'.$permission['uuid'].'.renew', false)" />
                                        @endcan
                                    @endif
                                @else
                                    Consultation uniquement
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-3">Aucune permission attribuée.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canEdit)
                <flux:textarea name="reason" label="Motif — obligatoire pour un renouvellement" :value="old('reason')" />
                <flux:button type="submit" variant="primary">Enregistrer les permissions</flux:button>
            @endif
        @if ($canEdit)
        </form>
        @endif
        <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
    </section>
</x-layouts::app>

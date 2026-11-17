@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Invitations" :shop-name="$shopName">
    <div class="mx-auto max-w-5xl space-y-5"><flux:heading size="xl">Invitations de l’équipe</flux:heading>
        @if(session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if($errors->any())@foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach@endif
        @can('create', \App\Models\Tenant\Users\TeamInvitation::class)
            <form method="POST" action="{{ route('tenant.admin.team.invitations.store', absolute: false) }}" class="rounded border p-4 space-y-3">
                @csrf
                <label class="block">E-mail <input required type="email" name="email" class="rounded border p-2 text-zinc-900"></label>
                <label class="block">Profil initial <select required name="role" class="rounded border p-2 text-zinc-900"><option value="">Choisir</option>@foreach($roles as $role)<option value="{{ $role->uuid }}">{{ $role->label }}</option>@endforeach</select></label>
                <button class="rounded border p-2">Envoyer une invitation</button>
            </form>
        @endcan
        <div class="divide-y rounded border p-3">
        @foreach($invitations as $invite)
            <div class="py-3"><strong>{{ $invite->email }}</strong> — {{ $invite->initialRole?->label }}
                <p>{{ $invite->accepted_at ? 'Acceptée' : ($invite->revoked_at ? 'Révoquée' : ($invite->expires_at->isPast() ? 'Expirée' : 'En attente')) }}</p>
                @can('revoke', $invite)
                    <form method="POST" action="{{ route('tenant.admin.team.invitations.revoke', $invite, false) }}" class="inline">
                        @csrf <input type="hidden" name="expected_state" value="{{ $service->state($invite) }}"><button class="rounded border p-2">Révoquer</button>
                    </form>
                @endcan
                @can('resend', $invite)
                    <form method="POST" action="{{ route('tenant.admin.team.invitations.resend', $invite, false) }}" class="inline">
                        @csrf <input type="hidden" name="expected_state" value="{{ $service->state($invite) }}"><button class="rounded border p-2">Renvoyer</button>
                    </form>
                @endcan
            </div>
        @endforeach
        </div>{{ $invitations->links() }}
    </div>
</x-tenant::layouts.app>

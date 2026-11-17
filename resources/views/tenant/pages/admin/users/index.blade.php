@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Équipe de boutique" :shop-name="$shopName">
    <div class="mx-auto max-w-5xl space-y-4">
        <flux:heading size="xl">Équipe de la boutique</flux:heading>
        @can('viewAny', \App\Models\Tenant\Users\TeamInvitation::class)<a class="underline" href="{{ route('tenant.admin.team.invitations.index', absolute: false) }}">Invitations</a>@endcan
        <form method="GET"><input name="search" value="{{ request('search') }}" placeholder="E-mail ou nom" class="rounded border p-2 text-zinc-900"><button class="rounded border p-2">Rechercher</button></form>
        <div class="divide-y rounded border p-4">
            @forelse($members as $member)
                <div class="flex justify-between py-2"><a class="underline" href="{{ route('tenant.admin.users.show', $member, false) }}">{{ $member->last_name }} {{ $member->first_name }} — {{ $member->email }}</a><span>{{ $member->trashed() ? 'Archivé' : $member->membership_status->name }}</span></div>
            @empty <p>Aucun membre</p> @endforelse
        </div>{{ $members->links() }}
    </div>
</x-tenant::layouts.app>

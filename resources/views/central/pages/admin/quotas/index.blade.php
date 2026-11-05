<x-layouts::app :title="__('Quotas')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Quotas</flux:heading>
        <form method="GET"><flux:input name="search" label="Rechercher un commerçant" :value="request('search')" /><flux:button type="submit">Rechercher</flux:button></form>
        <div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th>Commerçant</th><th>Actions</th></tr></thead><tbody>
            @forelse ($owners as $owner)
                <tr><td class="p-4">{{ $owner->name }}<br>{{ $owner->email }}</td><td><flux:button :href="route('central.quotas.show', $owner)">Consulter</flux:button></td></tr>
            @empty <tr><td>Aucun commerçant trouvé.</td></tr> @endforelse
        </tbody></table></div>
        {{ $owners->links() }}
    </section>
</x-layouts::app>

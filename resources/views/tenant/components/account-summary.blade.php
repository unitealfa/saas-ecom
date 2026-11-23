@props(['name'])

<flux:dropdown position="bottom" align="start" {{ $attributes }}>
    <flux:sidebar.profile :avatar="auth('tenant')->user()->profile_photo_url" :name="$name" icon:trailing="chevrons-up-down" aria-label="Menu du compte" />
    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar :src="auth('tenant')->user()->profile_photo_url" :name="$name" />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $name }}</flux:heading>
                <flux:text class="truncate">{{ auth('tenant')->user()->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        @if (auth('tenant')->user()->central_user_uuid === null)
            <flux:menu.item :href="route('tenant.settings.show', absolute: false)" icon="cog">Paramètres</flux:menu.item>
        @endif
        <form method="POST" action="{{ route('tenant.logout', absolute: false) }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">Se déconnecter</flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>

<section class="my-6 space-y-4" aria-labelledby="profile-photo-heading">
    <flux:heading id="profile-photo-heading" level="3">Photo de profil</flux:heading>
    <flux:avatar :src="auth('central')->user()->profile_photo_url" :name="auth('central')->user()->name" size="xl" />
    <flux:text>Facultative. Sans photo personnelle, l’avatar par défaut est affiché.</flux:text>
    @if (session('photo_status'))
        <flux:callout>{{ session('photo_status') }}</flux:callout>
    @endif
    <form method="POST" action="{{ route('central.profile-photo.update') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <flux:input type="file" name="photo" label="Choisir une photo" accept="image/jpeg,image/png,image/webp" required />
        <flux:error name="photo" />
        <flux:text>JPG, PNG ou WebP, 2 Mo maximum et 4096 pixels maximum par côté.</flux:text>
        <flux:button type="submit" variant="primary">Enregistrer la photo</flux:button>
    </form>
    @if (auth('central')->user()->profilePhoto !== null)
        <form method="POST" action="{{ route('central.profile-photo.destroy') }}">
            @csrf
            @method('DELETE')
            <flux:button type="submit">Retirer la photo</flux:button>
        </form>
    @endif
</section>

<x-layouts::auth title="Vérifiez votre adresse e-mail">
    <div class="space-y-6">
        <flux:heading size="xl">Vérifiez votre adresse e-mail</flux:heading>
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        <flux:text>Ouvrez le message reçu à {{ auth('central')->user()->email }} et cliquez sur le lien de vérification. Vérifiez aussi vos courriers indésirables. Si le lien est expiré, demandez-en un nouveau ici.</flux:text>
        <flux:text>Après la vérification de votre e-mail, votre demande sera transmise à l’administration pour approbation. Vous pourrez ensuite configurer votre première boutique.</flux:text>
        @if (session('status') === 'verification-link-sent')
            <flux:text role="status">Un nouvel e-mail de vérification a été envoyé.</flux:text>
        @endif
        @if (session('status') && session('status') !== 'verification-link-sent')
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @isset($verificationError)<flux:text role="alert">{{ $verificationError }}</flux:text>@endisset
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <flux:button type="submit" variant="primary">Renvoyer l’e-mail de vérification</flux:button>
        </form>
        <flux:text>Les demandes répétées sont limitées pour protéger votre compte.</flux:text>
        <form method="POST" action="{{ route('logout') }}">@csrf <flux:button type="submit" variant="ghost">Retour à la connexion</flux:button></form>
    </div>
</x-layouts::auth>

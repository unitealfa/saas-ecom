<x-layouts::auth title="Vérifiez votre adresse e-mail">
    <div class="space-y-6">
        @if (session('registration_created'))
            <flux:modal name="registration-created" class="md:w-xl" x-init="$nextTick(() => $flux.modal('registration-created').show())">
                <div class="space-y-5">
                    <flux:heading size="xl">Votre compte Aydra a été créé avec succès !</flux:heading>
                    @if (session('registration_email_sent', false))
                        <flux:text>Un e-mail de vérification vient de vous être envoyé.</flux:text>
                    @else
                        <flux:text role="alert">L’envoi de l’e-mail a échoué. Votre compte est conservé. Utilisez le bouton de renvoi ci-dessous pour réessayer.</flux:text>
                    @endif
                    <flux:text>Veuillez confirmer votre adresse e-mail pour poursuivre.</flux:text>
                    <flux:text>Votre dossier sera également examiné par notre équipe. Après approbation, vous pourrez configurer votre première boutique.</flux:text>
                    <flux:modal.close><flux:button variant="primary">J’ai compris</flux:button></flux:modal.close>
                </div>
            </flux:modal>
        @endif
        <flux:heading size="xl">Vérifiez votre adresse e-mail</flux:heading>
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        <flux:text>Ouvrez le message reçu à {{ auth('central')->user()->email }} et cliquez sur le lien de vérification. Vérifiez aussi vos courriers indésirables. Si le lien est expiré, demandez-en un nouveau ici.</flux:text>
        <flux:text>La vérification de votre e-mail et l’approbation administrative sont deux étapes distinctes. Aucune boutique n’est créée automatiquement.</flux:text>
        @if (session('status') === 'verification-link-sent')
            <flux:text role="status">Un nouvel e-mail de vérification a été envoyé.</flux:text>
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

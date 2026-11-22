@php
    $flashToasts = [];
    $status = session('status');
    $statusVariant = session('status_variant');

    // Fortify uses this value as a signal, not as a message for the user.
    if (is_string($status) && $status !== '' && $status !== 'verification-link-sent') {
        $flashToasts[] = [
            'slots' => ['text' => __($status)],
            'dataset' => ['variant' => in_array($statusVariant, ['success', 'warning', 'danger', 'info'], true) ? $statusVariant : 'info'],
            'duration' => 7000,
        ];
    }

    if (is_string(session('warning')) && session('warning') !== '') {
        $flashToasts[] = [
            'slots' => ['text' => session('warning')],
            'dataset' => ['variant' => 'warning'],
            'duration' => 10000,
        ];
    }

    if (session('registration_created')) {
        $flashToasts[] = [
            'slots' => ['text' => 'Votre compte a bien été créé. Connectez-vous pour vérifier votre adresse e-mail.'],
            'dataset' => ['variant' => 'success'],
            'duration' => 7000,
        ];
    }
@endphp

@if ($flashToasts !== [])
    <span hidden x-data x-init="$nextTick(() => { for (const toast of {{ Illuminate\Support\Js::from($flashToasts) }}) $dispatch('toast-show', toast) })"></span>
@endif

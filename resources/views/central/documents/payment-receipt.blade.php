<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>{{ $confirmed ? 'Reçu de paiement' : 'Constat interne d’encaissement' }}</title></head>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 12px">
    <h1>{{ $confirmed ? 'Reçu de paiement intégral' : 'Constat interne — contrôle du règlement à terminer' }}</h1>
    <p>Émetteur : {{ $payment->invoice->saas_identity_snapshot['name'] }}</p>
    <p>{{ $payment->invoice->saas_identity_snapshot['address'] }} — NIF : {{ $payment->invoice->saas_identity_snapshot['nif'] }}</p>
    <p>Client : {{ $payment->invoice->customer_identity_snapshot['name'] }}</p>
    <p>Facture : {{ $payment->invoice->number }}</p>
    <p>Montant {{ $confirmed ? 'confirmé reçu' : 'déclaré reçu par l’administration' }} : {{ $payment->getAttribute('amount') }} {{ $payment->getAttribute('currency') }}</p>
    <p>Méthode : {{ $payment->getAttribute('transfer_method')->label() }}</p>
    <p>Date de réception : {{ $payment->getAttribute('occurred_at')->format('d/m/Y H:i:s') }}</p>
    <p>Référence interne : PAY-{{ $payment->getAttribute('uuid') }}</p>
    <p>Période : {{ $payment->invoice->period_starts_at->format('d/m/Y') }} — {{ $payment->invoice->period_ends_at->format('d/m/Y') }}</p>
    <p>Ce document confirme un encaissement administratif. Il ne remplace pas la facture fiscale et ne constitue pas un justificatif bancaire.</p>
</body></html>

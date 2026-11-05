<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Facture {{ $invoice->number }}</title>
    <style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#18181b}h1{font-size:23px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left}.total{text-align:right;font-size:16px}.identity{margin:15px 0}</style>
</head>
<body>
<h1>Facture {{ $invoice->number }}</h1>
<p>Période : {{ $invoice->period_starts_at->format('d/m/Y') }} au {{ $invoice->period_ends_at->format('d/m/Y') }} (fin exclue)</p>
<p>Exercice : {{ $invoice->getAttribute('fiscal_year') }} — Échéance : {{ $invoice->due_at->format('d/m/Y') }}</p>
@foreach (['Émetteur' => $invoice->saas_identity_snapshot, 'Client' => $invoice->customer_identity_snapshot] as $label => $identity)
    <div class="identity"><strong>{{ $label }} : {{ $identity['name'] }}</strong><br>{{ $identity['address'] }}<br>NIF : {{ $identity['nif'] }}</div>
@endforeach
<table><thead><tr><th>Prestation</th><th>Qté</th><th>Prix HT</th><th>Remise HT</th><th>HT</th><th>Taxes</th><th>TTC</th></tr></thead><tbody>
@foreach ($invoice->lines as $line)
    <tr><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $line->net_unit_price }}</td><td>{{ $line->getAttribute('net_discount') }}</td><td>{{ $line->net_amount }}</td><td>{{ $line->tax_amount }}</td><td>{{ $line->total_amount }}</td></tr>
@endforeach
</tbody></table>
<p>Montant HT : {{ $invoice->net_amount }} {{ $invoice->currency }}</p>
@foreach ($invoice->getAttribute('taxes') as $tax)
    <p>{{ $tax['code'] }} ({{ $tax['rate'] }} %) : {{ $tax['amount'] }} {{ $invoice->currency }}</p>
@endforeach
@if (empty($invoice->getAttribute('taxes')))<p>{{ $invoice->billing_rule_snapshot['fiscal_policy']['no_tax_reason'] }}</p>@endif
<p>Taxes : {{ $invoice->tax_amount }} {{ $invoice->currency }}</p>
<p class="total"><strong>Total TTC : {{ $invoice->total_amount }} {{ $invoice->currency }}</strong></p>
<p>Règle : {{ $invoice->billing_rule_snapshot['code'] }}, version {{ $invoice->billing_rule_snapshot['version'] }}.</p>
<p>Référence fiscale : {{ $invoice->billing_rule_snapshot['fiscal_policy']['validation_reference'] }}.</p>
<p>Ce document ne constitue pas une confirmation de paiement.</p>
</body></html>

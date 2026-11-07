<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Avoir {{ $invoice->number }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left}</style></head>
<body>
<h1>Avoir {{ $invoice->number }}</h1>
<p>Facture corrigée : {{ $invoice->originalInvoice->number }} — {{ $invoice->originalInvoice->uuid }}</p>
<p>Motif : {{ $invoice->getAttribute('reason') }}</p>
<p>Période : {{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }} (fin exclue).</p>
@foreach (['Émetteur' => $invoice->saas_identity_snapshot, 'Client' => $invoice->customer_identity_snapshot] as $label => $identity)
<p><strong>{{ $label }} : {{ $identity['name'] }}</strong><br>{{ $identity['address'] }}<br>NIF : {{ $identity['nif'] }}</p>
@endforeach
<table><thead><tr><th>Prestation corrigée</th><th>Quantité</th><th>Motif</th><th>HT</th><th>Taxes</th><th>TTC</th></tr></thead><tbody>
@foreach ($invoice->lines as $line)
<tr><td>{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $line->getAttribute('reason') }}</td><td>{{ $line->net_amount }}</td><td>{{ $line->tax_amount }}</td><td>{{ $line->total_amount }}</td></tr>
@endforeach
</tbody></table>
<p>Correction HT : {{ $invoice->net_amount }} {{ $invoice->currency }}</p>
@foreach ($invoice->getAttribute('taxes') as $tax)<p>{{ $tax['code'] }} — {{ $tax['rate'] }} % : {{ $tax['amount'] }} {{ $invoice->currency }}</p>@endforeach
<p><strong>Correction totale TTC : {{ $invoice->total_amount }} {{ $invoice->currency }}</strong></p>
<p>Règle : {{ $invoice->billing_rule_snapshot['code'] }} — version {{ $invoice->billing_rule_snapshot['version'] }}.</p>
<p>Politique fiscale historique : {{ $invoice->billing_rule_snapshot['fiscal_policy']['validation_reference'] }}.</p>
<p>Cet avoir corrige le montant dû. Il ne constitue ni un remboursement effectué ni une modification de la facture originale.</p>
</body></html>

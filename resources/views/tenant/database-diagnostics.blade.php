<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Aydra — diagnostic boutique local</title></head>
<body>
<h1>Diagnostic local — {{ $shopName }}</h1>
<p>This is your multi-tenant application. The id of the current tenant is {{ $tenantId }}</p>
<p>UUID public boutique : {{ $tenantUuid }}. Base sélectionnée : <strong>{{ $database }}</strong>.</p>
<h2>Comptes de cette boutique</h2>
<table border="1"><thead><tr><th>ID</th><th>UUID local</th><th>UUID du propriétaire central</th><th>Prénom et nom</th><th>E-mail</th><th>Adhésion</th></tr></thead><tbody>
@foreach ($users as $user)
<tr><td>{{ $user->id }}</td><td>{{ $user->uuid }}</td><td>{{ $user->central_user_uuid ?? 'Employé local, sans identité centrale' }}</td><td>{{ $user->first_name }} {{ $user->last_name }}</td><td>{{ $user->email }}</td><td>{{ $user->membership_status }}</td></tr>
@endforeach
</tbody></table>
<h2>Temps des requêtes</h2>
<p>Mesures de cette requête HTTP, sans cache des résultats. Le temps SQL est mesuré par Laravel ; le temps total inclut le traitement PHP du scénario. Les caches MySQL et la machine peuvent changer les résultats. 50 commandes permettent un test fonctionnel, pas un test de charge. Les comptages de tables sont exclus de ces mesures.</p>
<table border="1"><thead><tr><th>Scénario</th><th>Requêtes SQL</th><th>Lignes retournées</th><th>SQL (ms)</th><th>Total (ms)</th></tr></thead><tbody>
@foreach ($measures as $measure)
<tr><td>{{ $measure['name'] }}</td><td>{{ $measure['queries'] }}</td><td>{{ $measure['rows'] }}</td><td>{{ $measure['sql_ms'] }}</td><td>{{ $measure['elapsed_ms'] }}</td></tr>
@endforeach
</tbody></table>
<h2>20 dernières commandes de cette boutique</h2>
<table border="1"><thead><tr><th>ID</th><th>UUID</th><th>Numéro</th><th>Statut commercial</th><th>Total</th></tr></thead><tbody>
@foreach ($orders as $order)
<tr><td>{{ $order->id }}</td><td>{{ $order->uuid }}</td><td>{{ $order->number }}</td><td>{{ $order->commercial_status }}</td><td>{{ $order->order_total }} {{ $order->currency }}</td></tr>
@endforeach
</tbody></table>
<h2>Plan MySQL EXPLAIN de la jointure</h2>
<pre>{{ json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
<h2>Nombre réel de lignes par table</h2>
<table border="1"><thead><tr><th>Table</th><th>Lignes</th></tr></thead><tbody>
@foreach ($tables as $table => $count)
<tr><td>{{ $table }}</td><td>{{ $count }}</td></tr>
@endforeach
</tbody></table>
<p>Les tables techniques et les scénarios non simulés peuvent rester vides. Aucun message client ni appel à un transporteur réel n’est effectué.</p>
</body></html>

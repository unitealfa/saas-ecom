<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Aydra — diagnostic central local</title></head>
<body>
<h1>Diagnostic local — BDD centrale {{ $database }}</h1>
<p>Lecture seule, accès local avec APP_DEBUG actif. Aucun mot de passe ni secret n’est affiché.</p>
<p>Domaines centraux : {{ implode(', ', $centralDomains) }}</p>
<h2>Comptes centraux</h2>
<table border="1"><thead><tr><th>ID</th><th>UUID</th><th>Prénom</th><th>Nom</th><th>E-mail</th></tr></thead><tbody>
@foreach ($users as $user)
<tr><td>{{ $user->id }}</td><td>{{ $user->uuid }}</td><td>{{ $user->first_name }}</td><td>{{ $user->last_name }}</td><td>{{ $user->email }}</td></tr>
@endforeach
</tbody></table>
<h2>Propriétaire → boutique → domaine → base</h2>
<table border="1"><thead><tr><th>Boutique</th><th>Tenant ID / UUID</th><th>Propriétaire ID / UUID</th><th>Base MySQL</th><th>Domaine et liens</th></tr></thead><tbody>
@foreach ($tenants as $tenant)
<tr><td>{{ $tenant['shop_name'] }}</td><td>{{ $tenant['id'] }}<br>{{ $tenant['uuid'] }}</td><td>{{ $tenant['user_id'] }} — {{ $tenant['owner'] }}<br>{{ $tenant['owner_uuid'] }}</td><td>{{ $tenant['database'] }}</td><td>
@foreach ($tenant['domains'] as $domain)
<p>ID domaine {{ $domain['id'] }}, tenant_id {{ $domain['tenant_id'] }} : {{ $domain['domain'] }} — <a href="{{ request()->getScheme().'://'.$domain['domain'].(! in_array(request()->getPort(), [80, 443], true) ? ':'.request()->getPort() : '').route('tenant.database-diagnostics', [], false) }}">Diagnostic boutique</a></p>
@endforeach
</td></tr>
@endforeach
</tbody></table>
<h2>Nombre réel de lignes par table</h2>
<table border="1"><thead><tr><th>Table</th><th>Lignes</th></tr></thead><tbody>
@foreach ($tables as $table => $count)
<tr><td>{{ $table }}</td><td>{{ $count }}</td></tr>
@endforeach
</tbody></table>
</body></html>

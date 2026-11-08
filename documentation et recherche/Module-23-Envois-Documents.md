# Module 23 — Envois de documents SaaS

## État au 8 novembre 2026

Installation manuelle déclarée effectuée par l’utilisateur. Référence de préparation fournie : `unitealfa/saas-ecom`, branche `dev`, commit `b1ebe822e`. Lors de cette lecture locale : branche `dev`, HEAD `10329ec`. Aucun changement de branche, publication, migration, seeder ou envoi réel effectué pendant cette vérification.

La préparation indiquait une syntaxe contrôlée sous PHP 8.4.24 : douze fichiers source PHP et un fichier de test. Ce résultat est rapporté, pas reproduit ici sous le PHP 8.5 du projet. Les tests locaux sont déclarés réalisés par l’utilisateur ; leurs sorties et résultats détaillés ne sont pas fournis dans cette intervention.

## Contrôle rapide effectué ici

- Routes chargées par `routes/central/admin.php`, sous `auth:central`, `verified` et `EnsureActiveCentralAccount`, avec paramètres UUID.
- Catalogue : trois permissions `saas.deliveries.view`, `saas.deliveries.send`, `saas.deliveries.retry`, regroupées dans `CentralRoleCatalog`.
- Policy enregistrée dans `AppServiceProvider` ; Form Request d’envoi contrôlant l’acteur central et la capacité serveur.
- Menu administratif et accès depuis factures et avoirs présents.
- `DeliveryPermissionSeeder` utilise `firstOrCreate`, une transaction et l’invalidation native Spatie après commit. Il n’a pas été exécuté ici.
- Huit statuts et quatre canaux déclarés dans les enums ; migration et check des huit statuts déjà présents dans le dépôt. Schéma MySQL installé non inspecté.
- Job asynchrone, revendication sous verrou d’une ligne PENDING et incrément du nombre de tentatives.
- Le traitement distingue SMTP, résultat incertain et transport non SMTP. Aucune preuve de remise ou de lecture déduite du simple statut SENT.

## Écarts confirmés à traiter

1. `routes/central/admin/deliveries.php` ajoute `throttle:6,1` sur send et `throttle:3,1` sur retry. Cela contredit la règle du projet interdisant les throttles administratifs. Aucune correction de code effectuée dans cette intervention documentaire.
2. `SaasDocumentDeliveryService::request()` crée une nouvelle intention lorsqu’aucune n’existe, avec une clé suffixée `email:manual`. Cela diffère de la consigne fournie : reprendre uniquement une intention PENDING existante, sans création implicite. Le contrôle historique annoncé « aucune ligne supplémentaire » ne doit donc pas être présenté comme acquis pour ce cas.
3. `SendSaasDocumentJob` ne définit pas explicitement sa connexion ou sa file. Sa compatibilité avec le worker installé reste à vérifier ; ne pas supposer que démarrer le worker de provisionnement traite ce job.

## Fonctionnement attendu

- Envoi des seuls PDF ISSUED, privés, intègres, vers le destinataire historique autorisé ; aucune adresse libre fournie par l’administrateur.
- Réutilisation de l’intention PENDING créée à l’émission. Les tâches concurrentes ne doivent revendiquer qu’une tentative.
- SENT signifie accepté par SMTP, jamais remis ou lu. Une issue SMTP incertaine interdit une relance aveugle.
- Relance manuelle limitée aux états FAILED/RETRYABLE autorisés, à moins de trois tentatives, avec état attendu.
- Aucun changement de facture, avoir, abonnement ou paiement ; aucun reçu, remboursement, SMS ou WhatsApp automatique.
- Destinataire chiffré ; audit sans adresse ni octets du PDF.

## Fichiers du module

- Enums : `app/Enums/Central/Billing/DeliveryChannelEnum.php`, `DeliveryStatusEnum.php`.
- Modèle : `app/Models/Central/Billing/SaasDocumentDelivery.php`.
- Policy : `app/Policies/Central/SaasDocumentDeliveryPolicy.php`.
- Requests : `app/Http/Requests/Central/Billing/{SearchSaasDeliveriesRequest,SendSaasDocumentRequest,RetrySaasDocumentRequest}.php`.
- Controller : `app/Http/Controllers/Central/Admin/Billing/SaasDocumentDeliveryController.php`.
- Service : `app/Services/Central/Billing/SaasDocumentDeliveryService.php`.
- Job : `app/Jobs/Central/SendSaasDocumentJob.php`.
- Routes : `routes/central/admin/deliveries.php`.
- Vues : `resources/views/central/pages/admin/billing/deliveries/{index,show}.blade.php`.
- Seeder : `database/seeders/Central/DeliveryPermissionSeeder.php`.
- Tests : `tests/Central/Feature/Admin/Billing/SaasDocumentDeliveryManagementTest.php`.

Les raccordements existants sont le catalogue de permissions, le Provider, le chargeur de routes administratives, la sidebar et les fiches de facture/avoir. Aucune migration nouvelle ajoutée ici.

## Contrôles non exécutés pendant cette intervention

Aucun test Laravel/Pest, PHPStan, Pint, lint PHP, build, serveur HTTP, SMTP, worker, CI ou contrôle MySQL. Aucun résultat fonctionnel attribué à cette simple lecture. Les preuves de tests de l’utilisateur restent distinctes des contrôles réalisés ici.

Pour une validation ultérieure : vérifier les accès sans permission, la lecture seule, le refus des brouillons, les doublons/concurrences, l’intégrité du PDF, les transports log/array, les issues SMTP incertaines, les limites de relance et l’isolation Central/Tenant. Utiliser exclusivement le PHP 8.5 documenté dans AGENTS.md. Toute écriture sur une base réelle exige un accord précis ; préférer le seeder dédié additif aux permissions pour une base existante.

**Conclusion : intégration principale présente, deux divergences métier/configuration confirmées et compatibilité de queue non établie. Validation fonctionnelle non certifiée par cette intervention.**

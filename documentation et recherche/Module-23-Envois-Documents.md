# Module 23 — Envois de documents SaaS

## État au 8 novembre 2026

Branche locale : `module-25-avec-documentation-du-module-23`. Les corrections ci-dessous sont apportées directement sur cette branche ; aucun changement de branche, publication, migration ou seeder n’a été effectué.

## Contrôle rapide effectué ici

- Routes chargées par `routes/central/admin.php`, sous `auth:central`, `verified` et `EnsureActiveCentralAccount`, avec paramètres UUID.
- Catalogue : trois permissions `saas.deliveries.view`, `saas.deliveries.send`, `saas.deliveries.retry`, regroupées dans `CentralRoleCatalog`.
- Policy enregistrée dans `AppServiceProvider` ; Form Request d’envoi contrôlant l’acteur central et la capacité serveur.
- Menu administratif et accès depuis factures et avoirs présents.
- `DeliveryPermissionSeeder` utilise `firstOrCreate`, une transaction et l’invalidation native Spatie après commit. Il n’a pas été exécuté ici.
- Huit statuts et quatre canaux déclarés dans les enums ; migration et check des huit statuts déjà présents dans le dépôt. Schéma MySQL installé non inspecté.
- Job asynchrone affecté à la connexion `database` et à la file `saas-documents`, revendication sous verrou d’une ligne PENDING et incrément du nombre de tentatives.
- Le traitement distingue SMTP, résultat incertain et transport non SMTP. Aucune preuve de remise ou de lecture déduite du simple statut SENT.

## Corrections appliquées

1. Retrait des middlewares `throttle` des routes administratives d’envoi et de relance. Les protections d’authentification, Policy, CSRF, verrouillage et idempotence restent en place.
2. `SaasDocumentDeliveryService::request()` refuse maintenant de créer une intention absente. Il reprend uniquement l’intention historique et refuse les états qui ne peuvent pas être redemandés ; la relance reste une action distincte autorisée.
3. `SendSaasDocumentJob` force `database` et `saas-documents`. Le test de feature exécute le job par un worker Laravel `queue:work --once` avec SMTP simulé, sans envoyer de courriel réel.

Configuration locale vérifiée sans afficher de secrets : `APP_ENV=local`, `QUEUE_CONNECTION=database`, `DB_CONNECTION=mysql` sur boucle locale ; `DB_QUEUE_CONNECTION` est absent et la connexion de queue `database` retombe donc sur `DB_CONNECTION`. Le mailer local est SMTP et son hôte n’est pas local ; un worker persistant peut donc envoyer les courriels réellement en attente.

## Fonctionnement attendu

- Envoi des seuls PDF ISSUED, privés, intègres, vers le destinataire historique autorisé ; aucune adresse libre fournie par l’administrateur.
- Réutilisation de l’intention PENDING créée à l’émission. Aucun enregistrement de remplacement pour les anciens documents ; ils nécessitent une régularisation administrative distincte. Les tâches concurrentes ne doivent revendiquer qu’une tentative.
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

## Worker de développement

Commande du worker secondaire :

```powershell
& 'C:\Users\habou\.config\herd\bin\php85\php.exe' artisan queue:work database --queue=saas-documents --sleep=2 --tries=1 --timeout=60
```

Ce worker est distinct du worker `tenant-provisioning`. Pour un usage permanent, l’installer comme service ou tâche supervisée. Le démarrer peut transmettre les documents déjà en attente par SMTP.

Le worker `saas-documents` a été démarré en arrière-plan pour l’environnement local à la demande. Aucun service Windows ou mécanisme de supervision permanent n’a été installé.

## Vérifications

- Tests de gestion des envois : **14 réussis, 87 assertions**, incluant l’absence d’intention, la réutilisation et le double clic, la connexion/file du job et son exécution par `queue:work --once`.
- Aperçu de facturation commerçant : **12 réussis, 106 assertions**.
- Gestion des avoirs : **42 réussis, 218 assertions, 1 ignoré**.
- Les trois scénarios T20-10 de processus concurrents (« reservations », « same-operation », « issue ») passent dans cette exécution. Leurs sorties enfants correspondent aux valeurs attendues (`conflict`/`ok`) ; les anomalies de sauts de ligne signalées antérieurement ne sont pas reproduites. Les tests n’ont pas été modifiés.
- Aucun courriel réel ni changement de base réelle effectué par les tests. Les tests utilisent des bases MySQL temporaires isolées.

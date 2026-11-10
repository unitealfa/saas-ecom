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

## Supervision locale — Windows / Herd

La commande `app/Console/Commands/Central/LocalProvisioningWorker.php` gère les deux tâches propres au chemin du projet, avec suffixe dérivé de son SHA-256 :

- `Aydra-Provisioning-<suffixe>` : `central-provisioning` / `tenant-provisioning`.
- `Aydra-Documents-<suffixe>` : `database` / `saas-documents`, une tentative Laravel, timeout 60 secondes et redémarrage du worker après une heure.

Depuis la racine du projet, avec PHP 8.5 Herd :

```powershell
$aydraPhp = "$env:USERPROFILE\.config\herd\bin\php85\php.exe"
& $aydraPhp artisan test tests/Central/Unit/LocalProvisioningWorkerTest.php
& $aydraPhp artisan test tests/Central/Feature/Admin/Billing/SaasDocumentDeliveryManagementTest.php
& $aydraPhp artisan aydra:local-queue install --no-interaction
& $aydraPhp artisan aydra:local-queue start --no-interaction
& $aydraPhp artisan aydra:local-queue status --no-interaction
```

Vérifier que les deux tâches sont Enabled/Running. `install` crée les tâches sans les démarrer ; `start` les active et les lance ; `stop` arrête et désactive les deux. Journaux :

- `storage/logs/local-provisioning-worker.log`
- `storage/logs/local-documents-worker.log`

Avant le démarrage, vérifier que `DB_CONNECTION` et `DB_QUEUE_CONNECTION` pointent vers la base centrale. La connexion Laravel `database` utilise `DB_QUEUE_CONNECTION` et, si elle n’est pas définie, retombe sur `DB_CONNECTION`. Vérifier ces noms sans afficher ni partager les valeurs secrètes de `.env`.

Les tests feature peuvent créer et supprimer une base MySQL temporaire via `tests/TestCase.php`. Vérifier que la configuration de test cible uniquement une base isolée ; ne jamais lancer ces commandes en les reliant à une base réelle.

Le worker de documents peut envoyer les courriels SMTP déjà en attente. Tester les transmissions avec une base isolée et un transport SMTP simulé avant de le démarrer sur une base habituelle. Après modification de `.env`, exécuter `& $aydraPhp artisan config:clear --no-interaction`, puis `aydra:local-queue stop` et `aydra:local-queue start`.

Le worker `saas-documents` a été démarré en arrière-plan pour l’environnement local à la demande. Aucun service Windows ou mécanisme de supervision permanent n’a été installé.

## Supervision de production — Linux / Supervisor

Installer [aydra-documents.conf](../deployment/supervisor/aydra-documents.conf) dans `/etc/supervisor/conf.d/`, avec les variables `AYDRA_ROOT`, `AYDRA_PHP` et `AYDRA_USER` déjà utilisées par [aydra-provisioning.conf](../deployment/supervisor/aydra-provisioning.conf). Le worker documents écoute `database` / `saas-documents`, avec `--tries=1`.

Après vérification de la configuration sur l’hôte :

```shell
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Les journaux Supervisor sont `storage/logs/provisioning-worker.log` et `storage/logs/documents-worker.log` (distincts des journaux des wrappers Windows).

## Contrôles d’exploitation

- Examiner les échecs avec `php artisan queue:failed`.
- Sur la base centrale, compter les jobs en attente en lecture seule :

  ```sql
  SELECT queue, COUNT(*) AS total
  FROM jobs
  GROUP BY queue;
  ```

- Dans l’administration, vérifier la page des transmissions SaaS et les états `RETRYABLE`, `SENT` et `UNCERTAIN`.
- `SENT` indique l’acceptation par le transport, pas la remise au destinataire. Ne pas relancer aveuglément un état `UNCERTAIN` ni un job échoué si le courriel a pu partir.

## Vérifications de cette documentation

La livraison de supervision indique un contrôle de syntaxe `php -l` ; il n’a pas été reproduit dans cette mise à jour documentaire. Les tests Laravel/Windows/Supervisor et l’installation de ces tâches ne sont pas certifiés par cette mise à jour. Les tests des envois, de l’aperçu commerçant et des avoirs exécutés lors de la tâche précédente sont consignés dans [owner-billing.md](./owner-billing.md) ; ils ne valident pas l’installation Windows ou Supervisor.

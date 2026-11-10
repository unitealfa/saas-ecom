# Séparation des échéances SaaS — état et procédure de validation

Mise à jour documentaire : 10 novembre 2026.

## Statut et portée

Le document source fourni décrit un patch préparé depuis `dev` au commit
`d38c293284a96524cca234660c401e8a950a9994`. Il précise que ce patch n’a pas été
validé de bout en bout sur MySQL 8.4 / Laravel 13 et interdit son déploiement sur
une base réelle avant validation sur une copie.

Le checkout local examiné est différent : branche `fix-enum`, HEAD
`8e04dfaea521d1e4c38eb1ecf15aab55a3a401dd`. Le commit de référence `d38c293` n’est
pas un ancêtre de ce HEAD. La séparation physique est toutefois déjà présente
dans ce checkout, notamment dans le commit `9391b0d164fa5f3283c77b185eda6676aabe08f3`,
qui modifie 26 fichiers. La migration, les deux modèles et le test de stockage
distinct sont présents dans le dépôt.

Ce constat concerne le code versionné, pas une base de données : aucune
vérification de l’état des migrations centrales ou des données réelles n’a été
faite pour cette mise à jour documentaire. Aucun test, migration, seeder,
changement de branche ou contrôle navigateur n’a été exécuté ici. Ne pas
appliquer à nouveau un script de patch fondé sur un autre commit.

## Modèle cible

- `subscriptions` contient exclusivement les abonnements. Les colonnes
  historiques propres aux échéances et le discriminant `record_type` en sont
  retirés par la migration centrale dédiée.
- `subscription_installments` contient les obligations, avec leurs identifiants
  numériques, UUID, propriétaire, abonnement parent, période, montant, état,
  date limite et clé idempotente.
- La migration copie les ID et UUID des anciennes lignes
  `subscriptions.record_type = 2`. Les audits conservent l’alias polymorphique
  historique `subscription_installment`.
- `saas_invoices.installment_id` pointe vers la table d’échéances ; les FK
  composites conservent les contrôles de correspondance entre facture,
  échéance, abonnement et propriétaire.
- Une échéance de période exige le montant intégral. La séparation physique
  n’ajoute ni paiement partiel, ni échéancier, ni moteur de paiement. Un avoir
  émis corrige le document et le dû selon les règles existantes ; ce n’est pas
  un paiement et ne confirme pas à lui seul des fonds.

La structure est décrite dans le [schéma principal](Schema-BDD-SaaS-Ecommerce-UUID.md)
et le [diagramme central](Diagramme-BDD-Centrale-Complet.md). La présente note
porte sur la migration et son statut de validation, pas sur une autorisation de
l’exécuter.

## Migration et risque de reprise

Fichier concerné :
`database/migrations/central/2026_11_09_160000_split_subscription_installments.php`.

La migration est centrale uniquement et exige MySQL 8.4. Elle vérifie les
données historiques, copie les échéances en préservant leurs identifiants,
bascule les références, puis retire les lignes enfant de l’ancienne table.
Les DDL MySQL peuvent valider implicitement des étapes : `down()` refuse le
rollback automatique. En cas d’échec partiel, ne pas relancer à l’aveugle ni
compter sur `migrate:rollback` ; arrêter les écritures concernées, préserver les
journaux et faire intervenir un DBA avec une sauvegarde cohérente restaurable.

La migration n’est pas autorisée sur une base réelle par cette documentation.
Avant toute décision d’exploitation :

1. Confirmer le commit et l’état du dépôt ; sauvegarder le travail local et
   vérifier une restauration complète de la base centrale, avec routines,
   triggers et historiques.
2. Créer une copie cohérente MySQL 8.4 isolée. Empêcher pendant l’opération les
   créations ou modifications de demandes, échéances, paiements et factures.
3. Vérifier la liste des migrations en attente et la compatibilité des données
   sur cette copie seulement. Ne pas forcer une migration en conflit.
4. Conserver les totaux avant/après, les ID/UUID, clés d’opération, factures,
   paiements, événements `activity_log` et empreintes des PDF émis.
5. N’envisager la production qu’après réussite des tests, validation des
   contrôles SQL, sauvegarde restaurable, plan de reprise et autorisation
   explicite de déploiement.

## Contrôles SQL en lecture seule

Après migration de la copie, les deux requêtes suivantes doivent retourner
`0`. Elles ne modifient aucune donnée.

```sql
SELECT COUNT(*) AS factures_sans_echeance
FROM saas_invoices i
LEFT JOIN subscription_installments d
  ON d.id = i.installment_id
 AND d.subscription_id = i.subscription_id
 AND d.user_id = i.user_id
WHERE d.id IS NULL;

SELECT COUNT(*) AS echeances_sans_abonnement
FROM subscription_installments d
LEFT JOIN subscriptions s
  ON s.id = d.subscription_id
 AND s.user_id = d.user_id
WHERE s.id IS NULL;
```

Sur la même copie seulement, `SELECT COUNT(*)` sur les deux tables ainsi que
`SHOW CREATE TABLE subscription_installments` et
`SHOW CREATE TABLE saas_invoices` permettent de contrôler le schéma obtenu.
Les requêtes avant/après mentionnées dans le paquet initial n’étaient pas
présentes dans le clone examiné ; ces contrôles sont donc reproduits ici, sans
créer ni exécuter de script externe.

Les compteurs annoncés dans le paquet — 79 triggers / 84 CHECK au central et
205 triggers / 151 CHECK par base boutique — sont des attentes dérivées des
migrations de référence, non des mesures effectuées sur une base. Ne pas les
présenter comme résultats d’un audit réel.

## Vérifications à lancer sur une copie isolée

Sur Windows, utiliser PHP 8.5 de Herd et une configuration exclusivement dédiée
aux tests. Les commandes ci-dessous sont une procédure à exécuter après
préparation de cette copie ; elles n’ont pas été lancées lors de cette mise à
jour documentaire.

```powershell
$aydraPhp = "$env:USERPROFILE\.config\herd\bin\php85\php.exe"
& $aydraPhp artisan migrate:status --path=database/migrations/central --no-interaction
& $aydraPhp artisan migrate --path=database/migrations/central --force --no-interaction
& $aydraPhp artisan test tests/Central/Feature/Admin/Subscriptions --no-interaction
& $aydraPhp artisan test tests/Central/Feature/Admin/Billing --no-interaction
& $aydraPhp artisan test tests/Central/Feature/Admin/Media/CentralMediaManagementTest.php --no-interaction
& $aydraPhp artisan test tests/Central/Feature/Database/DocumentedCentralSchemaTest.php --no-interaction
& $aydraPhp artisan test tests/Tenant/Database/DocumentedMysqlSchemaTest.php --no-interaction
```

Ne lancer la commande `migrate` que sur la copie isolée après vérification des
migrations en attente ; elle peut exécuter toutes les migrations centrales en
attente du chemin indiqué. Les tests de schéma central et tenant doivent être
exécutés avec leurs bases de test isolées. Aucun de ces résultats ne prouve à
lui seul l’absence de conflit métier.

Compléter par un parcours manuel isolé couvrant l’abonnement gratuit, les
périodes mensuelle et annuelle, la demande, l’émission de facture, le paiement
manuel intégral confirmé, l’activation, le changement d’offre, l’avoir,
l’annulation/refus, les PDF, l’idempotence et la conservation des historiques.
Contrôler spécifiquement les anciennes activités polymorphiques
`subscription_installment` et l’intégrité des PDF existants.

## Fichiers du changement recensé

Le commit local `9391b0d` porte 26 fichiers modifiés :

- Migration et modèles : `database/migrations/central/2026_11_09_160000_split_subscription_installments.php`,
  `app/Models/Central/Subscriptions/Subscription.php`,
  `app/Models/Central/Subscriptions/SubscriptionInstallment.php`.
- Facturation et abonnements :
  `app/Services/Central/Billing/SaasInvoiceService.php`,
  `app/Services/Central/Billing/SaasInvoiceIssuanceService.php`,
  `app/Services/Central/Billing/SaasPaymentService.php`,
  `app/Services/Central/Billing/SaasNumberingService.php`,
  `app/Services/Central/Subscriptions/DefaultSubscription.php`,
  `app/Services/Central/Subscriptions/SubscriptionPurchaseService.php`,
  `app/Services/Central/Subscriptions/SubscriptionRequestService.php`,
  `app/Services/Central/Subscriptions/SubscriptionTransitionService.php`,
  `app/Http/Controllers/Central/Admin/Billing/SaasInvoiceController.php`,
  `app/Http/Controllers/Central/Owner/Billing/SubscriptionPurchaseController.php`.
- Tests : `tests/Central/Feature/Admin/Subscriptions/SubscriptionAssignmentTest.php`,
  `tests/Central/Feature/Admin/Subscriptions/SeparateInstallmentsStorageTest.php`,
  `tests/Central/Feature/Admin/Billing/SaasBillingScenarioTest.php`,
  `tests/Central/Feature/Admin/Billing/SaasInvoiceIssuanceTest.php`,
  `tests/Central/Feature/Admin/Billing/SaasPaymentManagementTest.php`,
  `tests/Support/ManualBillingFixtures.php`,
  `tests/Central/Feature/Admin/Media/CentralMediaManagementTest.php`,
  `tests/Central/Feature/Admin/Billing/BillingRuleManagementTest.php`,
  `tests/Pest.php`,
  `tests/Tenant/Database/DocumentedMysqlSchemaTest.php`,
  `tests/Tenant/Database/LiveMysqlSchemaAuditTest.php`.
- Schémas : `documentation et recherche/Diagramme-BDD-Centrale-Complet.md`,
  `documentation et recherche/Schema-BDD-SaaS-Ecommerce-UUID.md`.

Cette liste décrit le commit local recensé, pas l’ensemble des fichiers d’un
paquet ZIP. Ne pas supposer que le script d’application, les fichiers de
contrôle SQL ou une sauvegarde sont disponibles dans le clone.

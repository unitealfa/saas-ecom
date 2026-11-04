# Module 18 — Vérification du 5 novembre 2026

État : **MODULE PARTIEL / NON VALIDÉ intégralement**. Le parcours des règles et des compteurs de factures est implémenté et testé. La série des avoirs exige encore l'enregistrement autorisé du droit `saas.credit-notes.issue`, absent du catalogue actuel. Aucun droit supplémentaire ni aucune attribution réelle n'a été créé pendant cette revue.

## 1. Références et analyse

Mission : `module18.txt`. Sources consultées : `Schema-BDD-SaaS-Ecommerce-UUID.md` (C8.1, C8.4 et §6.7), `Diagramme-BDD-Centrale-Complet.md`, `instructionn.txt`, architecture §51–52 et code installé. Les fichiers suffixés `(10)` et `(3)` demandés ne sont pas présents ; ils ne sont pas présentés comme lus. La mention historique `saas.billing.rules` ne remplace pas les six droits précis du module actuel.

Versions constatées : PHP 8.5.12, Laravel 13.34.0, MySQL 8.0.46, Spatie Permission 8.3.0, Activity Log 5.0.0, Stancl Tenancy 3.10.1, Livewire 4.4.7.

## 2. Fonctionnalités et matrice d'acceptation

« Validée » ci-dessous concerne le scénario testé, pas la livraison intégrale des futurs modules de facturation.

| Fonctionnalité | Backend | Frontend | Permission | Test | Résultat |
|---|---|---|---|---|---|
| T18-01 Création DRAFT | Implémenté | Formulaire existant adapté | billing-rules.create | HTTP + navigateur | TESTÉE / VALIDÉE |
| T18-02 Données invalides | Liste de paramètres autorisés, champs serveur absents | Erreurs et saisie conservée | Même contrôle serveur | Jeu de six entrées et autres cas invalides | TESTÉE / VALIDÉE |
| T18-03 Version dupliquée | UNIQUE(code,version), version serveur | Numéro non éditable | versions.create | Vraie contrainte MySQL | TESTÉE / VALIDÉE |
| T18-04 Édition brouillon | État attendu, verrou cible | Formulaire éditable | billing-rules.update | HTTP + navigateur 15 → 20 jours | TESTÉE / VALIDÉE |
| T18-05 Immutabilité | Policy, service, modèle et contraintes existantes | Édition masquée après validation | Aucun contournement root | Référence de document et contenu validé | TESTÉE / VALIDÉE |
| T18-06 Activation invalide | DRAFT refusé, preuve préalable exigée | Étapes distinctes | billing-rules.activate | HTTP/services | TESTÉE / VALIDÉE |
| T18-07 Validation/activation | DRAFT → VALIDATED → ACTIVE | Référence puis dates, confirmations | billing-rules.activate | HTTP + navigateur | TESTÉE / VALIDÉE |
| T18-08 Chevauchement | Verrou commun au code, intervalles semi-ouverts | Erreur explicite | billing-rules.activate | Deux processus + navigateur | TESTÉE / VALIDÉE |
| T18-09 V2/historique | Ancienne ligne conservée | Création V2 et tableau des versions | versions.create / view | HTTP + navigateur | TESTÉE / VALIDÉE |
| T18-10 Retrait | Motif, fin de période, aucun effacement | Confirmation et état retiré | billing-rules.activate | HTTP + navigateur | TESTÉE / VALIDÉE |
| Recherche/filtres | Code, état, version, période, pagination | Champs de filtres et historique paginé | billing-rules.view | Tests HTTP | TESTÉE / VALIDÉE |
| T18-11 Compteurs | Types/exercices distincts | Tableau et initialisation autorisée | billing-sequences.view + droit d'émission pour créer | Facture/avoir en base temporaire | TESTÉE ; avoir réel BLOQUÉ |
| T18-12 Compteur dupliqué | UNIQUE, création concurrente idempotente | Aucun préfixe/next_number libre | Droit d'émission du type | Deux processus ; initialisation répétée au navigateur | TESTÉE / VALIDÉE |
| T18-13 Réservation | Compteur et document dans la même transaction | Aucun incrément à la lecture | invoices.issue | Services MySQL | TESTÉE pour facture |
| T18-14 Rejeu/interruption | Même document PREPARING retrouvé ; rollback atomique avant commit | Numéro enregistré affiché | invoices.issue | Rejeu, rollback, deux processus sur même document | TESTÉE / VALIDÉE |
| T18-15 Concurrence réelle | Deux documents distincts obtiennent deux numéros consécutifs | Sans rôle dans l'exclusion SQL | invoices.issue | Deux processus PHP/MySQL indépendants | TESTÉE / VALIDÉE |
| T18-16 Permissions | Refus serveur, droits indépendants | Actions masquées | Six droits Module 18 conservés | HTTP et Policies, pas de navigateur avec lecteur | TESTÉE côté serveur |
| T18-17 Isolation | Services refusés en contexte tenant, modèles centraux explicites | Aucun accès boutique ajouté | Contexte central exigé | Vraie base tenant temporaire avec témoin inchangé | TESTÉE / VALIDÉE |
| Audit | Auteur et correlation_id natif, transaction atomique | Référence échappée | Autorisation avant mutation | Corrélation, XSS et rollback | TESTÉE / VALIDÉE |

Les noms raccourcis de permissions du tableau commencent tous par `saas.`.

## 3. Fichiers applicatifs

Code du module et de son prolongement existant :

- `app/Enums/Central/Billing/{DocumentStatusEnum,DocumentTypeEnum,PolicyStatusEnum,TriggerEventEnum}.php`.
- `app/Models/Central/Billing/{BillingRule,BillingSequence,SaasInvoice,SaasInvoiceLine,SaasCreditNote}.php`.
- `app/Services/Central/Billing/{BillingRuleService,SaasInvoiceService,SaasNumberingService}.php`.
- `app/Policies/Central/{BillingRulePolicy,BillingSequencePolicy,SaasInvoicePolicy,SaasCreditNotePolicy}.php`.
- `app/Http/Controllers/Central/Admin/Billing/{BillingRuleController,SaasInvoiceController}.php`.
- `app/Http/Requests/Central/Billing/{StoreBillingRuleRequest,BillingRuleActionRequest,SearchBillingRulesRequest,InitializeBillingSequenceRequest,PrepareSaasInvoiceRequest,ReserveSaasInvoiceNumberRequest}.php`.
- `resources/views/central/pages/admin/billing/{index,form,show,sequences}.blade.php` et sous-dossier `invoices`.
- `routes/central/admin/billing.php`, chargé par `routes/central/admin.php` ; raccordements existants dans sidebar, fiche d'abonnement, AppServiceProvider et CentralRoleCatalog.
- Seeders additifs existants `BillingPermissionSeeder` et `InvoicePermissionSeeder` ; aucun seeder financier ajouté.

Le périmètre contient déjà les fichiers non commités du scénario Pro antérieur. Cette revue les préserve ; la liste n'affirme pas qu'ils ont tous été créés pendant cette seule revue. Les échéances et leurs raccordements sont décrits au §52 de l'architecture.

## 4. Migrations

Aucune migration créée, réécrite ou exécutée sur les données réelles. Les migrations existantes ont été réellement exécutées sur MySQL temporaire. Vérifiés : table commune RULE/SEQUENCE, colonnes générées, contraintes UNIQUE, CHECK et protections des compteurs utilisés. Aucun besoin de changement de schéma confirmé.

## 5. Routes

Sous le parent administratif authentifié central, vérifié et actif :

- `GET/POST admin/billing/rules`, création, fiche, édition et `PUT` existants.
- `PATCH admin/billing/rules/{billingRule}/validation` : nouvelle validation distincte.
- `PATCH .../activation` et `.../retirement` : activation/retrait.
- `GET/POST .../{billingRule}/versions` (création GET via `/versions/create`).
- `GET/POST admin/billing/sequences` : lecture et initialisation protégée.

Binding public UUID inchangé. Les routes factures/échéances du scénario Pro restent raccordées. `route:cache` passe avec un fichier temporaire dédié ensuite supprimé ; le cache du site n'a pas été vidé.

## 6. Permissions

Six droits Module 18 inchangés : `saas.billing-rules.view`, `.create`, `.update`, `.activate`, `.versions.create`, et `saas.billing-sequences.view`. La validation utilise `.activate` ; aucun droit supplémentaire inventé pour elle.

Initialiser une série exige le droit documenté d'émission correspondant : `saas.invoices.issue` ou `saas.credit-notes.issue`. Le second n'est pas encore enregistré dans le catalogue réel. Les tests enregistrent ce droit uniquement dans leur base isolée pour éprouver le contrat ; cela ne rend pas la série disponible en production.

## 7. Tests réellement exécutés

Commande avec PHP Herd 8.5 et caches de configuration/routes isolés dans le processus :

```powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP 'aydra-module18-review-config.php'
$env:APP_ROUTES_CACHE = Join-Path $env:TEMP 'aydra-module18-review-routes.php'
& $aydraPhp artisan test --compact tests/Central/Feature/Admin/Billing tests/Central/Feature/Database/DocumentedCentralSchemaTest.php
```

Résultat de la série backend : **59 tests réussis, 2 669 assertions, 156 376 ms**. Fichiers BillingRuleManagementTest, SaasBillingScenarioTest, BillingSequenceManagementTest et DocumentedCentralSchemaTest. Elle précède l'ajout du test navigateur interactif.

Session navigateur isolée : **1 test réussi, 5 assertions, 165 560 ms**, avec actions réelles dans Chrome : connexion fictive, création, édition, validation, activation, V2, rejet du chevauchement, retrait motivé de V1, activation adjacente de V2 et initialisation répétée de la série facture. Le serveur, sa base et son stockage temporaires sont arrêtés/nettoyés après la session. Le test interactif Module18BrowserTest est explicitement ignoré sans `AYDRA_MODULE18_BROWSER=1` ; cette option nécessite un pilote navigateur effectuant les actions et signalant leur fin, ce n'est pas un test autonome sans navigateur.

**60 scénarios distincts exécutés, 2 674 assertions** au total. Les séries antérieures répétées ne sont pas additionnées. Pint conforme ; PHPStan ciblé sur le code Billing et ses Policies : **0 erreur** ; `git diff --check` conforme. Graphe de code actualisé par extraction AST.

## 8. Défauts corrigés

- Ancienne validation combinée à l'activation incompatible avec T18-06/07 : opérations séparées, preuve initiale conservée.
- Version serveur explicitement `null` acceptée par `prohibited` : champs réservés du formulaire de règle désormais `missing`.
- Filtres version/période et historique dédiés absents : ajoutés.
- Vérification de droits trop tardive en contexte tenant : refus explicite dans les services avant transaction/Spatie ; aucune vérification réintroduite dans AuthorizationMutation.
- Audit contenant la corrélation seulement dans le JSON : colonne native désormais renseignée pour les nouveaux événements de règle et de réservation.
- Vérification d'utilisation répétée par ligne : agrégat `withExists`, exclu du hash d'état ; contrôle de mutation relu sous verrou et garde modèle fraîche.
- Initialisation contrôlée des compteurs absente : ajout avec permission par type, unicité et audit atomique.
- Réservation liée à un seul type : point d'entrée réutilisable conservant le modèle concret ; le module consommateur valide son document sous les mêmes verrous.

Les premiers tests ont échoué sur le contexte tenant et sur une fixture root ne disposant pas d'un droit enregistré pour les avoirs. Le premier est un défaut réel corrigé ; la seconde a été complétée uniquement en base de test. Aucun contrôle d'autorisation n'a été assoupli.

## 9. Non exécuté

Pas de suite complète du projet, de test de charge prolongé ni d'émission définitive/PDF. Pas de parcours navigateur avec lecteur sans permission ni de formulaire d'avoir réel. Ces refus sont couverts côté HTTP/Policy ; cela ne remplace pas un contrôle navigateur de ces comptes.

## 10. Limites

Enregistrement du droit d'émission des avoirs encore soumis à accord. Le modèle d'en-tête SaasCreditNote et sa Policy ne constituent pas le Module 20 complet : calcul des plafonds remboursables, contenu des avoirs et émission restent à développer et tester. La réservation facture est réellement testée ; aucune réservation métier complète d'avoir n'est revendiquée.

Aucune opération financière réelle dans cette revue : pas de nouveau Pro, facture, abonnement, règle, compteur, boutique ou attribution réelle. Le scénario réel antérieur est laissé en place.

## 11. Procédure manuelle

Sur un environnement de test : se connecter avec un personnel autorisé ; ouvrir Facturation — Configuration ; créer un code unique avec délai 15 ; modifier à 20 ; valider avec une référence ; constater l'absence d'édition ; activer sur une période. Créer V2 et la valider ; essayer un chevauchement (refus), fermer V1 avec motif puis activer V2 à sa fin exacte. Vérifier l'historique. Dans Compteurs, initialiser Facture/exercice choisi deux fois : une ligne, prochain numéro inchangé. Répéter avec un lecteur : aucun bouton de mutation, soumission directe refusée. Ne pas réaliser cette démonstration dans la base réelle sans autorisation spécifique.

## 12. Fonctionnement simple

Formulaire → route protégée → Form Request et Policy → Controller → service → transaction et verrous → modèles centraux → MySQL → redirection avec message → page. La règle décrit quoi appliquer ; le compteur attribue un numéro. Lire un compteur ne consomme rien. Réserver avance le compteur et attache le numéro au même document dans une seule transaction : tout réussit ensemble ou tout est annulé.

## 13. Préparation du Module 19

Disponibles : règles validées/versionnées, périodes, compteur facture, réservation atomique et idempotente, préparation d'échéance et facture DRAFT/PREPARING, snapshots et audits. Restent hors de cette livraison : émission juridique définitive, exigences fiscales complètes, PDF, envoi et paiement. Le Module 19 peut réutiliser ces services ; il ne doit pas considérer une facture PREPARING comme émise.

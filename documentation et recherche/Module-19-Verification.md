# Module 19 — Factures SaaS : livraison et vérification

Date : 5 novembre 2026.

**Code implémenté et vérifié sur données isolées.** L'émission réelle reste volontairement bloquée tant que les identités et la politique fiscales privées ne sont pas renseignées et validées. Aucune facture réelle n'a été émise par cette intervention. Le scénario Pro historique est conservé en préparation limitée.

## 1. Analyse et décisions

Sources : `module19.txt`, `instructionn.txt`, architecture §51–53, schéma principal C8.1/C8.2/C8.4 et §6.7, diagramme central et code installé. Les copies suffixées `Schema-BDD-SaaS-Ecommerce-UUID(10).md` et `Diagramme-BDD-Centrale-Complet(3).md` ne sont pas présentes ; les documents canoniques du dépôt ont été utilisés.

Le Module 18 savait préparer une intention et réserver un numéro, sans émission ni PDF. Ce parcours de démonstration est conservé. Le Module 19 ajoute une facture depuis une échéance existante, puis son émission explicite.

Décisions confirmées pendant la mission : tests MySQL locaux temporaires, bibliothèque PDF compatible, ajout du seul droit de téléchargement, application de la seule migration de protection, configuration fiscale serveur et **montant de l'échéance TTC**. Nom, adresse, NIF, référence et date de validation sont exigés pour les deux identités fiscales. Une politique sans taxe doit être motivée ; sinon seuls des taux explicitement configurés et validés sont utilisés.

L'approbation du compte ne remplace pas la validation fiscale d'informations modifiées ensuite. Le contrôle fiscal ne change jamais le statut du compte et ne relance pas son approbation.

Versions contrôlées : PHP 8.5.12, Laravel 13.34.0, MySQL 8.0.46 ; bibliothèque ajoutée `barryvdh/laravel-dompdf` 3.1.2 avec Dompdf 3.1.6 ; Brick Math 1.0.0 déjà présent. La [déclaration officielle de compatibilité du package](https://github.com/barryvdh/laravel-dompdf/blob/v3.1.2/composer.json) inclut Laravel 13 ; son fonctionnement a également été éprouvé par la génération réelle de PDF.

## 2. Matrice fonctionnelle

| Fonctionnalité | Fichiers principaux | Backend | Frontend | Permissions | Tests | Résultat |
|---|---|---|---|---|---|---|
| T19-01 Préparer et émettre | SaasInvoiceService, SaasInvoiceIssuanceService, SaasInvoicePdfService | Échéance, snapshots, numéro, PDF et finalisation atomique | Détail, confirmation, chargement, téléchargement | invoices.create / issue | HTTP, SQL, vrai PDF, navigateur | IMPLÉMENTÉ / TESTÉ / VALIDÉ sur fixture |
| T19-02 Échéance étrangère | SaasInvoiceService | Propriétaire, parent et état relus sous verrou | Erreur serveur | invoices.create | Refus sans facture ni compteur | VALIDÉ |
| T19-03 Règle ou preuves invalides | SaasInvoiceService, IssuanceService | Blocage avant réservation | Erreurs explicites | Contrôles financiers obligatoires pour tous | Règle expirée, preuve émetteur/politique/client absente | VALIDÉ |
| T19-04 Calculs | InvoiceCalculator | Décimaux exacts, remises, ventilation et total TTC préservé | Calculs affichés depuis les données enregistrées | Aucun montant libre accepté | Plusieurs lignes et taux de fixture ; 3000 TTC = 2727,27 HT + 272,73 de taxe de test | VALIDÉ ; aucun taux réel inventé |
| T19-05 Numérotation | SaasNumberingService réutilisé | Compteur facture/exercice, réservation atomique | Numéro et exercice affichés | invoices.issue | Deux documents/processus distincts | VALIDÉ |
| T19-06 Rejeu | Services de préparation/émission | Même document, lignes, numéro, PDF et intention | Résultat existant retrouvé | Autorisation toujours vérifiée | Rejeu séquentiel et concurrent | VALIDÉ |
| T19-07 Crash après réservation | IssuanceService | PREPARING conservé, même numéro à la reprise | Bouton Reprendre l'émission | invoices.issue | Exception de génération puis reprise réelle | VALIDÉ |
| T19-08 Stockage/finalisation interrompue | PdfService, IssuanceService | Écriture temporaire, contrôle d'empreinte, rollback de la finalisation | Erreur sans faux succès | invoices.issue | Erreur de génération ; vrai fichier conservé avant échec de delivery, puis même fichier réutilisé | VALIDÉ |
| T19-09 Immutabilité | Triggers existants + nouvelle migration | En-tête/lignes émis et métadonnées du PDF protégés | Aucune édition fiscale historique | Root soumis aux invariants | Vrais refus MySQL sur montants, lignes, hash, chemin et archivage | VALIDÉ |
| T19-10 Profil modifié | Snapshots de facture | Historique indépendant du profil courant | Identité du snapshot affichée | Propriétaire réel | Profil modifié après émission, snapshots et octets inchangés | VALIDÉ |
| T19-11 PDF privé | InvoicePolicy, MediaPolicy, services privés | Signature URL, propriétaire, parent, visibilité et empreinte | Lien signé uniquement si autorisé | invoices.download ou propriété du document | Autre commerçant refusé ; fichier altéré refusé ; propriétaire au navigateur | VALIDÉ |
| T19-12 Concurrence | IssuanceService + NumberingService | Verrou MySQL par document, verrous SQL, UNIQUE | Pas de double émission après double demande | invoices.issue | Deux processus indépendants, même opération puis opérations distinctes | VALIDÉ |
| Liste, recherche, filtres | SearchSaasInvoicesRequest, InvoiceService | Numéro, propriétaire, période et état ; pagination | Formulaire et tableau | invoices.view / viewOwn | HTTP et liste propriétaire au navigateur | VALIDÉ |
| Accès administratifs | Policies et Form Requests | Refus serveur sans droit, montants injectés interdits | Actions masquées | Quatre droits Module 19 | HTTP 403, champ serveur rejeté | VALIDÉ |
| Alias de domaines centraux | SearchSaasInvoicesRequest | Route propriétaire reconnue avec ou sans préfixe d'alias | Même liste | Propriété, sans droit SaaS | Requête HTTP via alias central | VALIDÉ |
| Isolation boutique | Services centraux | Refus avant accès tenant | Aucune fonctionnalité tenant ajoutée | Contexte central | Vraie base temporaire avec témoin inchangé | VALIDÉ |
| Intention de transmission | SaasDocumentDelivery | Destinataire réel chiffré, PENDING, zéro essai ; même transaction que ISSUED | État annoncé sans preuve de remise | Émission administrative | SQL, rejeu et rollback | VALIDÉ ; envoi effectif NON IMPLÉMENTÉ dans ce module |

Les permissions abrégées du tableau commencent par `saas.`. « Validé » décrit le scénario effectivement testé, pas une certification fiscale ni une garantie de fonctionnement sur toute configuration possible.

## 3. Fichiers créés

- `app/Enums/Central/Billing/DeliveryChannelEnum.php`
- `app/Enums/Central/Billing/DeliveryStatusEnum.php`
- `app/Models/Central/Billing/SaasDocumentDelivery.php`
- `app/Services/Central/Billing/InvoiceCalculator.php`
- `app/Services/Central/Billing/SaasInvoicePdfService.php`
- `app/Services/Central/Billing/SaasInvoiceIssuanceService.php`
- `app/Http/Requests/Central/Billing/CreateInstallmentInvoiceRequest.php`
- `app/Http/Requests/Central/Billing/IssueSaasInvoiceRequest.php`
- `app/Http/Requests/Central/Billing/SearchSaasInvoicesRequest.php`
- `app/Http/Controllers/Central/Owner/Billing/InvoiceController.php`
- `config/central/billing.php`
- `database/migrations/central/2026_11_04_202934_protect_issued_invoice_media.php`
- `resources/views/central/documents/saas-invoice.blade.php`
- `tests/Central/Feature/Admin/Billing/SaasInvoiceIssuanceTest.php`
- Le présent rapport.

## 4. Fichiers existants modifiés

- `app/Http/Controllers/Central/Admin/Billing/SaasInvoiceController.php`
- `app/Models/Central/Billing/SaasInvoice.php`
- `app/Models/Central/Billing/SaasInvoiceLine.php`
- `app/Models/Central/Subscriptions/SubscriptionInstallment.php`
- `app/Policies/Central/SaasInvoicePolicy.php`
- `app/Policies/Central/MediaPolicy.php`
- `app/Services/Central/Billing/SaasInvoiceService.php`
- `app/Services/Central/Billing/SaasNumberingService.php`
- `app/Services/Central/Roles/CentralRoleCatalog.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/central/layouts/app/sidebar.blade.php`
- `resources/views/central/pages/admin/billing/invoices/{index,show,installments}.blade.php`
- `routes/central.php`, `routes/central/admin/billing.php`
- `composer.json`, `composer.lock`, `.env.example`
- `AGENTS.md`, architecture §53 et suivi de développement.

Le graphe de code est actualisé. La modification de `.gitignore` et le fichier d'entrée `module19.txt` préexistaient et ne sont pas réécrits. Aucun ancien test n'a été supprimé.

## 5. Routes et permissions

Ajouts sous les protections centrales existantes :

- `POST /admin/subscriptions/{subscription}/installments/{installment}/invoice` — préparation de l'échéance existante, UUID publics et état attendu.
- `POST /admin/billing/invoices/{invoice}/issue` — émission ou reprise.
- `GET /admin/billing/invoices/{invoice}/pdf` — téléchargement signé, droit administratif propre.
- `GET /factures` et `GET /factures/{invoice}` — uniquement les documents du commerçant connecté.
- `GET /factures/{invoice}/pdf` — téléchargement signé du propriétaire autorisé.

Les anciennes URL restent fonctionnelles, notamment la préparation limitée et la réservation du scénario historique. Elles ne permettent pas de contourner les prérequis d'émission. Toutes les actions administratives conservent CSRF/Policy et n'ajoutent aucun throttle.

Les quatre droits officiels sont `saas.invoices.view`, `.create`, `.issue`, `.download`. Seul `.download` manquait. Il rejoint le catalogue et le seeder dédié ; aucune permission supplémentaire pour les commerçants.

## 6. Services et migration

Parcours : Form Request → Controller → préparation depuis l'échéance → réservation existante → PDF privé hors transaction longue → transaction finale média/ISSUED/intention/audit → redirection.

Les montants de l'échéance sont TTC. Le calculateur utilise Brick Math déjà installé, avec arrondi au centime HalfUp. Les taux configurés portent sur le même HT ; un reliquat de centime est affecté à la dernière taxe sans résultat négatif. Les taxes composées et règles non spécifiées ne sont pas inventées.

La migration ajoute deux triggers protégeant la modification et la suppression des médias déjà rattachés aux documents PREPARING/ISSUED. Aucune table ou colonne ajoutée, aucune migration historique réécrite. Elle a été exécutée sur MySQL temporaire puis, après tests et accord spécifique, seule sur la base centrale réelle.

Le PDF utilise une clé UUID déterministe. L'écriture temporaire évite un fichier final partiel. Un sceau HMAC contrôle les octets et données figées d'un fichier repris avant finalisation ; après émission, `media.file_hash` reste la preuve canonique. Ce sceau technique n'est pas une signature fiscale certifiée. Une rotation de clé applicative pendant une préparation demande une procédure explicite ; le service ne remplace pas silencieusement une pièce non vérifiable.

## 7. Commandes et résultats réellement exécutés

PHP utilisé exclusivement :

```powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP 'aydra-module19-config.php'
$env:APP_ROUTES_CACHE = Join-Path $env:TEMP 'aydra-module19-routes.php'
& $aydraPhp artisan test --compact tests/Central/Feature/Admin/Billing/SaasInvoiceIssuanceTest.php tests/Central/Feature/Admin/Billing/SaasBillingScenarioTest.php tests/Central/Feature/Admin/Billing/BillingSequenceManagementTest.php tests/Central/Feature/Admin/Billing/BillingRuleManagementTest.php tests/Central/Feature/Database/DocumentedCentralSchemaTest.php tests/Central/Feature/Admin/Media/CentralMediaManagementTest.php
```

Résultat de cette série : **102 réussis, 2 959 assertions, 396 866 ms ; 1 test navigateur interactif ignoré dans cette commande**.

Après séparation finale de la validation fiscale du client, fichier Module 19 relancé : **24 réussis, 117 assertions, 130 798 ms ; 1 interactif ignoré**. Contrôle ultérieur du formulaire et de l'alias central : **1 réussi, 17 assertions, 35 753 ms**. Les séries se recouvrent ; elles ne sont pas additionnées.

Test ciblé de crash après PDF : **1 réussi, 6 assertions, 34 186 ms**, avec réutilisation des mêmes octets. Deux vrais processus PHP/MySQL ont également exécuté les scénarios de même opération et de documents distincts ; aucune simulation SQLite pour ces contraintes.

Session navigateur séparée avec serveur/base/stockage temporaires : **1 réussi, 6 assertions, 124 154 ms**. Actions réelles : connexion du personnel de test, confirmation de l'émission, facture ISSUED, téléchargement privé HTTP 200 d'un PDF de 23 492 octets ; déconnexion puis connexion du propriétaire, liste/détail/téléchargement 200 ; URL administrative 403, UUID inconnu 404, absence des boutons d'émission et de l'historique administratif. La session a été arrêtée et nettoyée. Ce test interactif est opt-in via `AYDRA_MODULE19_BROWSER=1` et nécessite un pilote navigateur ; il n'est pas présenté comme autonome sans pilote. Il a précédé le renforcement technique du sceau du fichier, couvert ensuite par les tests backend de génération/reprise.

Autres contrôles : Pint conforme, PHPStan ciblé **0 erreur** après correction de types réels, `route:cache` avec un chemin isolé ensuite supprimé, `git diff --check` et extraction AST du graphe. La création des tables/contraintes et la nouvelle migration ont été réellement exécutées sur MySQL temporaire.

## 8. Erreurs rencontrées et corrections

- Première exécution : méthode du calculateur encore absente, test rouge attendu avant implémentation.
- Fixture juridique sans auteur : ajout de l'auteur réel de test exigé par le CHECK, sans retirer la contrainte.
- Mauvaise configuration d'un disque simulé : corrigée dans la fixture, avec stockage distinct par processus.
- Média créé sans `position` : corrigé dans le service de finalisation conformément au schéma.
- Fixture d'état attendu calculée avant relecture SQL : modèle d'échéance relu avant l'appel ; garde de formulaire obsolète conservée.
- Import de Media manquant dans un test de crash : ajouté.
- Simulation d'une interruption restaurant tout le dispatcher et perdant les événements natifs de modèles : remplacée par la restauration du seul événement simulé, sans affaiblir la génération d'UUID.
- Typage du cast décimal, liste de lignes et variable JSON de configuration : corrigés fidèlement, sans désactiver l'analyse.
- Route propriétaire sur un alias central initialement reconnue comme administrative : détection corrigée et test HTTP ajouté.
- Validation fiscale initialement déduite de l'approbation du compte : séparée de cette décision pour respecter les modifications de profil sans réapprobation.

## 9. Application réelle autorisée et contrôlée

Après réussite des contrôles :

```powershell
& $aydraPhp artisan migrate --path=database/migrations/central/2026_11_04_202934_protect_issued_invoice_media.php --force --no-interaction
& $aydraPhp artisan db:seed --class=Database\Seeders\Central\InvoicePermissionSeeder --force --no-interaction
```

Résultat : migration et seeder réussis. Les cinq anciens droits concernés existaient déjà ; ajout du seul droit `saas.invoices.download`, vérifié de 0 à 1. Deux triggers présents. Avant/après : **0 attribution directe, 1 attribution de rôle, 4 compositions de rôle, 1 document et 0 média**, inchangés. Aucun compte, abonnement, plan, facture, PDF réel ou attribution n'a été créé par les tests ni par ces commandes. Aucun autre fichier de migration n'a été exécuté sur cette base.

## 10. Limites et contrôles non exécutés

- Pas de suite intégrale du projet ni de test de charge prolongé.
- Pas d'émission réelle, de données fiscales réelles inventées, de paiement, PDF réel ou envoi de courrier.
- Aucun job d'émission distinct ajouté : l'émission explicite appelle le service ; ses reprises et appels par processus sont testés. Une future tâche de fond doit réutiliser le même service, pas dupliquer le workflow.
- L'intention de transmission reste PENDING. Le service ne prétend ni envoyer ni prouver une remise.
- La configuration fiscale privée reste à renseigner avec de vraies données validées avant utilisation réelle. Le blocage lorsque ces preuves manquent est attendu et testé.
- L'audit Composer a signalé **12 avis affectant 7 dépendances déjà présentes** (Livewire et composants Symfony), aucun sur les six packages ajoutés pour le PDF. Ces dépendances existantes n'ont pas été mises à jour dans cette mission ; cela reste un point distinct à traiter avant de considérer l'ensemble du projet prêt pour production.

## 11. Configuration et procédure manuelle

Sur un environnement de test uniquement : créer un commerçant fictif actif et vérifié, un abonnement et une échéance ; activer une règle validée. Ne pas utiliser un seeder financier général et ne pas exécuter cette démonstration sur les données réelles sans accord.

Renseigner les variables privées décrites dans `.env.example` : `BILLING_ISSUER_NAME`, `BILLING_ISSUER_ADDRESS`, `BILLING_ISSUER_NIF`, `BILLING_ISSUER_VALIDATION_REFERENCE`, `BILLING_ISSUER_VALIDATED_AT`, `BILLING_VALIDATED_CUSTOMERS`, `BILLING_TAX_VALIDATION_REFERENCE`, `BILLING_TAX_VALIDATED_AT` et, si aucune taxe n'est appliquée, `BILLING_NO_TAX_REASON`. Aucune identité ni référence de validation ne doit être inventée dans l'environnement réel.

`BILLING_VALIDATED_CUSTOMERS` est un objet JSON privé indexé par UUID public du client. Chaque entrée comporte `name`, `address`, `nif`, `validation_reference` et `validated_at`. Nom/adresse/NIF doivent correspondre au profil courant ; l'e-mail vient du compte vérifié. Les vraies identités restent dans le fichier privé, jamais dans un fichier de configuration commité. Les éventuels taux explicitement approuvés se configurent côté serveur dans `central.billing.tax_policy.rates`, avec `code` et `rate` en chaîne décimale ; aucune valeur légale n'est fournie par défaut.

Avec le personnel autorisé : Abonnements → Échéances → sélectionner une règle applicable → Préparer la facture de cette échéance. Vérifier les montants/snapshots puis Émettre la facture et confirmer. Résultat attendu : un numéro, état Émise, PDF téléchargeable, intention en attente et historique. Répéter l'émission par le service retrouve le même document. Avec le commerçant : Mes factures → Informations → Télécharger le PDF privé ; aucune action administrative. Un autre commerçant doit obtenir 403.

La préparation historique portant `preparation_only=true` reste limitée et son bouton d'émission est masqué. Aucun remplacement de ses snapshots ou dégel de son numéro n'est réalisé.

## 12. Vérifications SQL et PDF

MySQL vérifie types logiques, FK de propriétaire/abonnement/échéance, règles/compteurs, unicités et invariants existants. Les tests vérifient en plus les totaux, périodes, JSON de taxes, preuves, rollback d'audit et intention unique. Les refus de mutation d'une pièce émise sont exécutés réellement en base de test.

Le PDF existe réellement, commence par `%PDF-`, contient une fin PDF, possède une empreinte SHA-256 contrôlée sur ses octets et utilise un parent/collection/chemin déterminé par le serveur. Une pièce modifiée n'est pas transmise. Le téléchargement passe par les contrôles privés existants et répond avec `Cache-Control: private, no-store`.

## 13. Préparation du Module 21

Le Module 21 peut s'appuyer sur une facture ISSUED, une échéance du même propriétaire, une devise et des totaux figés, un PDF privé protégé et des clés d'opération stables. Aucun passage à PAID, vérification de virement, reçu ou plan supplémentaire n'est simulé ici. Le prochain module doit développer ses propres opérations financières et contrôles, sans modifier les documents fiscaux historiques.

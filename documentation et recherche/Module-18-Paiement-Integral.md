# Module 18 — Adaptation au paiement intégral

## 1. Périmètre et état

Demande : `modiffmodule18afaire`. Travail sur `module-18`, base `62d565f`, pour la future PR de l'utilisateur vers `module-19`. Aucun commit, push, changement de branche ou merge effectué. L'utilisateur a explicitement reporté les adaptations propres au Module 19 à une mission ultérieure.

**VALIDÉ pour l'adaptation du Module 18 présent sur cette branche.** L'émission complète du Module 19 est absente du checkout ; sa compatibilité d'exécution après fusion reste **NON TESTÉE**, avec points d'intégration ci-dessous. Ce rapport ne revendique pas une validation de cette fusion future.

Références : AGENTS.md, architecture §51–52, instructionn.txt, schéma canonique C8 et diagramme central. Les copies suffixées `(10)` et `(3)` demandées ne sont pas présentes. Leur remplacement par les documents canoniques est explicite. La nouvelle décision MVP est ajoutée à ces références sans supprimer les descriptions structurelles historiques.

## 2. Ce qui existait

- CRUD autorisé des brouillons, validation distincte, activation, retrait et historique des versions.
- Deux modèles sur saas_billing_settings : RULE=2 et SEQUENCE=1.
- Paramètres limités à document_type=INVOICE et payment_due_days ; pas de moteur de scripts.
- Transactions, verrous, état attendu, audit, index UNIQUE et compteurs idempotents.
- Préparation limitée d'une échéance/facture de période et réservation d'un numéro. Pas d'émission fiscale complète ni de PDF sur cette branche.
- Aucun automatisme d'encaissement, remboursement commercial ou avoir commercial trouvé dans ce parcours ; aucun de ces mécanismes n'a dû être supprimé.

## 3. Incompatibilités confirmées

| Fichier | Problème confirmé |
|---|---|
| SaasInvoiceService | L'échéance était cherchée uniquement par la clé de l'opération normale. Une échéance existante sous une autre clé pouvait être ignorée, puis doublée. |
| SaasInvoiceService | Une période contenant plusieurs échéances ou un ancien état PARTIALLY_PAID n'était pas explicitement refusée. |
| SaasNumberingService | La cohérence des totaux des lignes était contrôlée, mais pas le montant intégral convenu ni l'unicité de l'obligation de période. |
| Services de préparation/numérotation | Une règle RETIRED avec une fin future pouvait être choisie pour une nouvelle opération. |
| Utilisation d'une règle historique | La liste blanche des paramètres était appliquée lors de la création des règles, mais pas relue lors de leur utilisation. |
| Interface de retrait | Le message indiquait que la règle restait utilisable jusqu'à sa fin, ce qui contredisait le nouveau parcours exigeant ACTIVE. |

Les premiers tests ont reproduit cinq échecs : fractionnement, état partiellement réglé, obligation doublée, ancien échéancier et règle retirée encore utilisée. Ces tests ont ensuite réussi après correction, sans suppression de leurs assertions.

## 4. Modifications

- La préparation verrouille et cherche les échéances de l'abonnement et de la période. Zéro échéance : création intégrale. Une échéance compatible PENDING : réutilisation. Plusieurs échéances, montant fractionné ou état incompatible : refus clair, sans modification des historiques.
- `SubscriptionInstallment::matchesFullPeriod()` compare parent, propriétaire, montant décimal et dates. Il ne confirme aucun paiement.
- `BillingRule::isActiveAt()` exige ACTIVE et la période/proof déjà contrôlée par `isApplicableAt()`. Ce dernier reste disponible pour la lecture historique.
- Avant une première réservation INVOICE, le service recontrôle la règle/paramètres, l'échéance intégrale et son unicité, le montant et les dates du document. Ces gardes sont dans `reserveNextNumber()` avant le validateur du module consommateur ; elles ne dépendent donc pas du seul adaptateur de démonstration.
- Le chemin PREPARING retrouve immédiatement son numéro, en conservant snapshots et historique même si la règle a été retirée depuis.
- Les règles retirées ou à paramètres historiques incompatibles ne sont plus proposées dans le formulaire de nouvelle facturation.
- Le formulaire de règles précise que le délai est une date limite unique et que la facture ne confirme ni encaissement ni droits. Le retrait affiche la fin historique et l'interdiction de nouvelle facturation.
- Le contexte tenant est refusé avant la préparation centrale ; aucune assertion ajoutée dans AuthorizationMutation.

Le retrait reste irréversible pour la version concernée. Son intervalle historique continue à bloquer un chevauchement de versions ; choisir une fin future réserve donc cet intervalle, tout en interdisant désormais une nouvelle facturation avec cette version retirée. Les anciennes dates ne sont pas raccourcies artificiellement.

## 5. Fonctionnalités conservées

Versions, brouillons, validation, activation, retrait motivé, historique, recherche, filtres, consultation des compteurs, unicité, idempotence, transactions, verrous, audits, permissions et isolation restent présents. Les types d'avoirs et états de paiements historiques ne sont pas supprimés ; ils n'activent aucun parcours commercial automatique du MVP. Aucun prix de plan n'est modifié.

## 6. Fichiers du code modifiés

1. `app/Models/Central/Billing/BillingRule.php`
2. `app/Models/Central/Subscriptions/SubscriptionInstallment.php`
3. `app/Services/Central/Billing/SaasInvoiceService.php`
4. `app/Services/Central/Billing/SaasNumberingService.php`
5. `app/Http/Controllers/Central/Admin/Billing/SaasInvoiceController.php`
6. `app/Http/Controllers/Central/Admin/Billing/BillingRuleController.php`
7. `resources/views/central/pages/admin/billing/form.blade.php`
8. `resources/views/central/pages/admin/billing/show.blade.php`

Tests adaptés : `SaasBillingScenarioTest.php` et `BillingSequenceManagementTest.php` dans `tests/Central/Feature/Admin/Billing/`.

Consignes/références : AGENTS.md, architecture §51, schéma principal C8, diagramme central, suivi et lien depuis le rapport historique Module-18-Verification.md. Aucun nouveau fichier de production, service intermédiaire ou dépendance.

## 7. Migrations, routes et permissions

Aucune migration ni modification de tables, colonnes, contraintes SQL, enums historiques, routes, URL ou permissions. Six droits du Module 18 conservés intégralement. Aucun seeder ni commande d'écriture exécuté sur la base réelle. Les migrations existantes sont exécutées uniquement par les tests MySQL temporaires autorisés.

## 8. Tests exécutés

Avec PHP Herd 8.5 et caches isolés dans le processus :

```powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP 'aydra-module18-full-config.php'
$env:APP_ROUTES_CACHE = Join-Path $env:TEMP 'aydra-module18-full-routes.php'
& $aydraPhp artisan test --compact tests/Central/Feature/Admin/Billing/SaasBillingScenarioTest.php tests/Central/Feature/Admin/Billing/BillingRuleManagementTest.php tests/Central/Feature/Admin/Billing/BillingSequenceManagementTest.php tests/Central/Feature/Database/DocumentedCentralSchemaTest.php
```

Résultat : **68 tests réussis, 2 710 assertions, 239 543 ms**. Cette série précède l'ajout d'un cas supplémentaire de double obligation avant réservation.

Après simplification du contrôle et ajout de ce cas, fichier de scénarios relancé : **19 réussis, 117 assertions, 110 524 ms**. Ancienne configuration et son absence du sélecteur : **1 réussi, 7 assertions, 33 808 ms**. Isolation réelle avec base boutique témoin et refus de préparation centrale : **1 réussi, 6 assertions, 34 115 ms**. Réservation de deux numéros distincts avec deux processus indépendants : **1 réussi, 6 assertions, 37 245 ms**. Ces séries se recouvrent et ne sont pas additionnées.

Pint conforme ; PHPStan ciblé sur les modèles/services/contrôleurs concernés : **0 erreur**. `route:cache` réussi avec fichier isolé ensuite supprimé, `git diff --check` conforme. Aucun cache de configuration du site vidé. Graphe de code actualisé par extraction AST.

## 9. Couverture des exigences

| Scénario demandé | Preuve |
|---|---|
| T18-01 Régression | Règles, séquences, scénario facture et schéma MySQL réussis. |
| T18-02 Paiement intégral | Une échéance et une facture de 3000 DA ; échéance intégrale existante réutilisée. |
| T18-03 Fractionnement interdit | Échéance 1500/3000, PARTIALLY_PAID, deux fractions, paramètres historiques de fractionnement et double obligation refusés sans effacement. |
| T18-04 Règle inactive | Nouvelle facture sous RETIRED refusée avant écriture ; dates et contenu historique conservés. |
| T18-05 Versions | Tests existants de V1/V2, immutabilité et historique conservés. |
| T18-06 Compteur | Contraintes MySQL réelles et progression contrôlée. |
| T18-07 Idempotence | Rejeu conservant numéro et compteur ; ancien PREPARING conservé après retrait de règle. |
| T18-08 Concurrence | Deux processus pour même document dans les scénarios, et documents distincts dans T18-15. |
| T18-09 Permissions | Matrices Policy et refus HTTP existants réexécutés. |
| T18-10 Historique | Snapshots de facture inchangés après évolution/retrait de la règle. |
| T18-11 Automatisme interdit | Aucun virement, crédit commercial ou changement de droits d'abonnement créé par la facturation. |
| T18-12 Isolation | Vraie base tenant témoin inchangée ; refus de préparation dans ce contexte. |

## 10. Corrections et limites

Les gardes ne convertissent pas les anciens états en PENDING, ne fusionnent pas les échéances et ne réparent pas automatiquement un ancien fractionnement. Une incohérence historique provoque un refus explicite, pas une perte de données.

Le Module 21 n'est pas développé par cette mission : les espèces, justificatifs hors plateforme et confirmation administrative du paiement intégral sont le contrat du futur parcours, pas des fonctions prétendument ajoutées ici. Une facture reste indépendante de l'encaissement et ne donne aucun nouveau droit.

## 11. Tests non exécutés

Pas de suite complète du projet, de charge prolongée, de nouveau parcours navigateur interactif ni d'émission complète sur la branche module-19. Le frontend existant est testé par les requêtes HTTP et le rendu Blade ; les modifications visuelles sont uniquement des textes. Aucun test ni correction de données réelles.

## 12. Préparation de la PR vers module-19

Lecture statique effectuée de `module-19` au commit `1574903`, sans checkout ni modification. Les signatures de `prepareCurrentPeriodWithoutTax()`, `reserve()` et `reserveNextNumber()` sont inchangées. La garde commune de première réservation protège également un appel de l'émetteur du Module 19 via son validateur propre.

Lors de la fusion :

- Conserver `prepareInstallment()`, le calculateur, les preuves fiscales et l'émetteur PDF de module-19. Ne pas remplacer le fichier SaasInvoiceService entier par la version plus courte de module-18.
- Garder à la fois `matchesFullPeriod()` et `managementState()` dans SubscriptionInstallment si Git signale un conflit sur l'ajout des méthodes.
- Garder les verrous propriétaire → abonnement → échéance → document une seule fois dans SaasNumberingService, ainsi que le validateur du module consommateur.
- Conserver les deux historiques documentaires lors d'un conflit de fin de fichier, notamment le suivi et les références propres au Module 19.
- Lors de la mission Module 19 ultérieure : retirer l'acceptation de PARTIALLY_PAID dans `prepareInstallment()`, vérifier l'unicité de l'obligation avant création et utiliser la règle ACTIVE pour une nouvelle préparation. Le code de cette branche n'est pas modifié aujourd'hui, selon la décision de l'utilisateur.
- Réexécuter après fusion les tests d'émission/PDF/validation fiscale/reprise du Module 19 et les scénarios du présent rapport. Une comparaison statique n'est pas une preuve de fusion validée.

Aucune PR créée, aucun merge ou push effectué : l'utilisateur prépare sa PR.

## 13. Procédure manuelle sur un environnement de test

Créer/valider/activer une règle standard avec son délai unique ; préparer la période courante d'un abonnement payant. Vérifier un montant intégral, une seule échéance et aucun paiement confirmé. Rejouer la demande puis réserver deux fois : même document et même numéro. Retirer la règle : aucun nouveau choix possible, anciens documents consultables. Une fixture fractionnée doit être refusée sans changer son état. Ne pas reproduire ces manipulations sur les données réelles sans accord spécifique.

## 14. Logique simple

L'abonnement fournit le prix et la période. La facturation retrouve leur unique échéance intégrale, applique une règle active autorisée et prépare le document. Le compteur attribue un numéro une seule fois. Aucun de ces traitements ne dit que l'argent a été reçu. Cette confirmation et l'activation correspondante des droits appartiennent au Module 21.

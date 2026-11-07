# Module 20 — Avoirs SaaS

État au 7 novembre 2026 : parcours du Module 20 implémentés et vérifiés sur données isolées ; mise en service réelle non effectuée. Branche `module21`, après le commit `6a8e1d8`. Aucune fusion, publication, migration ni écriture financière sur la base réelle. Demande : fichier racine `module20`. Les copies de schémas suffixées citées dans cette demande sont absentes ; les schémas canoniques du dépôt ont été utilisés.

## 1. Matrice fonctionnelle

| Fonction demandée | Implémentation | Vérification |
|---|---|---|
| Liste paginée, recherche et filtre | `SaasCreditNoteService::search`, vues et Request dédiées | HTTP et navigateur |
| Consultation détaillée | Controllers admin/propriétaire, lignes et facture originale | HTTP et navigateur |
| Création depuis une facture émise | `createCreditNoteDraft`, origine dérivée côté serveur | T20-01/02/03/04 |
| Brouillon, lignes et motifs | Deux tables documentaires existantes, références SQL réelles | T20-01 et validations |
| Disponible par ligne | `calculateRemainingCorrection`, quantités/HT/taxes/TTC | T20-05/06/07/08 |
| Réservations DRAFT/PREPARING | Sommes des documents non annulés sous verrou propriétaire | T20-08/10 |
| Modification du brouillon | `updateDraft`, empreinte incluant les lignes, recalcul atomique | État obsolète et rollback |
| Annulation non émise | `cancelDraft`, motif obligatoire, numéro et historique conservés | T20-09 et PREPARING |
| Calcul exact et plafonds | Brick Math, taxes originales, aucun float | T20-05/06/11 |
| Validation/préparation | Règle active type 2 et contrôles sous transaction avant réservation | HTTP et tests de numérotation |
| Numérotation indépendante | `SaasNumberingService`, séquence CREDIT_NOTE/AVOIR | T20-12 et concurrence |
| PDF privé et émission | Services documentaires existants, finalisation atomique | T20-13/14 et navigateur |
| Téléchargement autorisé | Signature temporaire, Policy, contrôle du hash et de l'attachement | Propriétaire, tiers et PDF altéré |
| Historique | `activity_log` central et page administrative paginée | Événements et rollback SQL |
| Reprise après erreur | Même UUID, numéro et fichier vérifié | Échec SQL, HMAC invalide puis reprise |
| Solde et paiement après avoir | `InvoicePaymentService`, intégration du paiement intégral corrigé | T20-15 et paiement 2 500 DZD |
| Préparation Module 22 | Paiements/remboursements nets et réservations distinctes | Fixtures de remboursement et contrepassation |
| Permissions indépendantes | Seulement view/create/issue | T20-16 et matrice de Policies |
| Espace commerçant | Seulement ses avoirs émis, aucune écriture | HTTP, tiers refusé et navigateur |
| Isolation centrale | Refus du contexte tenant avant toute mutation | Base tenant temporaire avec témoin |

## 2. Résumé

Un avoir diminue la dette d'une facture déjà émise. Il conserve l'original et ne sort pas d'argent. Un brouillon réserve le disponible ; seule l'émission change le dû. Une facture de 3 000 DZD et un avoir émis de 500 DZD donnent un dû de 2 500 DZD. Si 3 000 DZD ont déjà été reçus, le surplus est de 500 DZD, sans remboursement automatique.

## 3. Fichiers

Les fichiers applicatifs exacts sont listés à la fin de ce rapport. Les tests principaux sont `SaasCreditNoteManagementTest.php` et les régressions du dossier `tests/Central/Feature/Admin/Billing`. `tests/Support/ManualBillingFixtures.php` mutualise uniquement les données fictives des tests des Modules 20 et 21.

## 4. Migrations

Aucune migration nécessaire : les tables, FK, colonnes calculées et triggers existants prennent déjà en charge le type 2. Les triggers de protection des PDF et des documents émis ne sont pas limités aux factures de type 1. Aucun historique de migration réécrit. Les tests exercent ces contraintes dans des bases MySQL temporaires.

## 5. Routes et permissions

Administration : `central.credit-notes.index/create/store/show/edit/update/prepare/issue/cancel/download`, dans `routes/central/admin/billing.php`. Propriétaire : `owner.credit-notes.index/show/download`, chemin `/avoirs`, dans `routes/central.php`. Authentification centrale, e-mail vérifié et middleware de compte hérités ; UUID publics et téléchargements signés plus Policy. Les fonctions de groupes de routes Laravel ne sont pas des actions Closure.

Trois droits documentés : `saas.credit-notes.view`, `saas.credit-notes.create`, `saas.credit-notes.issue`. Le droit create permet la préparation et les modifications/annulations non émises ; issue réserve le numéro et émet. Un droit d'émission de facture ne donne pas celui d'un avoir. Le seeder dédié est additif et n'attribue aucun droit aux comptes existants. Il n'a pas été exécuté sur la base réelle.

## 6. Services et règles conservées

`SaasCreditNoteService` porte origine, réservations, plafonds, motifs, édition, annulation et audits. `InvoiceCalculator` existant assure les décimales et la ventilation selon les taux historiques. `SaasNumberingService` existant réserve une seule fois le numéro. `SaasInvoiceIssuanceService` et `SaasInvoicePdfService` partagent le cycle documentaire existant, avec template d'avoir distinct. Les nouveaux snapshots conservent les identités fiscales originales ; une intention de transmission d'avoir vise l'adresse actuelle vérifiée, sans réécrire ces snapshots.

Chaque audit de brouillon conserve aussi ses lignes, quantités, montants, taxes et motifs : les éditions successives restent compréhensibles même lorsque le total ne change pas. Les informations du journal restent dans l'historique administratif autorisé, sans exposition aux autres commerçants.

`InvoicePaymentService` centralise le solde et la projection d'échéance. `SaasPaymentService` compare un nouvel encaissement intégral au dû corrigé. `PaymentDocumentService` garde le montant constaté à sa vérification pour préserver le reçu historique. `DefaultSubscription` tient compte des avoirs émis lors d'une activation de période payée, sans activer une période par le seul fait d'un avoir.

## 7. Commandes de vérification

Toutes les écritures de test utilisent MySQL local temporaire, avec fichiers et personnes fictifs. PHP imposé : `C:\Users\habou\.config\herd\bin\php85\php.exe`. Les chemins de cache de test sont isolés de ceux du site.

```powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
$env:APP_CONFIG_CACHE = 'bootstrap/cache/config-module20-test.php'
$env:APP_ROUTES_CACHE = 'bootstrap/cache/routes-module20-test.php'
& $aydraPhp -d memory_limit=512M vendor/bin/pest --compact tests/Central/Feature/Admin/Billing
& $aydraPhp vendor/bin/pint --dirty --format agent
```

L'analyse PHPStan ciblée couvre modèles/services/Requests/Controllers de facturation, Policies concernées et `DefaultSubscription`. `route:cache` puis `route:clear` sont effectués avec un chemin de cache temporaire uniquement. `npm run build` compile le frontend. Le test navigateur est opt-in via `AYDRA_MODULE20_BROWSER=1` ; il exige un pilote qui réalise le parcours dans le serveur de test isolé, puis confirme la fin.

## 8. Résultats

Régression finale sur code figé : **190 réussis, 1 093 assertions, zéro échec/erreur, 5 scénarios navigateur optionnels ignorés**, en 583,969 secondes. Les empreintes des 38 fichiers applicatifs avant/après sont identiques. Les exécutions intermédiaires ne sont pas additionnées à ce résultat.

| Fichier de tests | Réussis | Ignorés | Assertions |
|---|---:|---:|---:|
| BillingRuleManagementTest | 35 | 0 | 228 |
| BillingSequenceManagementTest | 14 | 0 | 86 |
| Module18BrowserTest | 0 | 1 | 0 |
| SaasBillingScenarioTest | 19 | 0 | 119 |
| SaasCreditNoteManagementTest | 42 | 1 | 216 |
| SaasInvoiceIssuanceTest | 32 | 1 | 173 |
| SaasPaymentManagementTest | 48 | 2 | 271 |

Contrôles complémentaires exécutés séparément :

- Navigateur Module 20 : **1 réussi, 6 assertions**. Connexion admin, création, préparation, émission, PDF réel, connexion propriétaire, consultation/PDF et refus administratif 403. PDF téléchargé avec `%PDF-` et `Cache-Control: no-store, private`. Aucun bouton d'écriture propriétaire ni débordement de page observé à la largeur contrôlée.
- Organisation/casts des modèles : **61 réussis, 187 assertions**. La commande élargie avec ConfigurationLayoutTest a aussi été exécutée : 61 réussis et un échec préexistant documenté au §11 ; ce contrôle élargi n'est pas annoncé vert.
- Trois scénarios MySQL à deux processus indépendants : disponible insuffisant, même création et même émission ; un seul document/événement/numéro/intention lorsque l'opération est identique.
- PHPStan ciblé : **zéro erreur** ; nouvelle vérification ciblée après les derniers changements d'audit.
- Pint final : **conforme**. `git diff --check` : conforme.
- `route:cache` : succès avec cache temporaire, puis suppression de ce seul cache. Les 39 routes avec alias correspondent aux 13 routes logiques du module.
- `npm run build` : succès ; avertissement optionnel de polices décrit au §11.
- `graphify update .` : graphe de code actualisé, extraction AST uniquement.

Les preuves JUnit et la sortie compacte de la dernière exécution restent dans `storage/framework/testing/module20-final-junit.xml` et `module20-final-output.json`, ignorés par Git. Aucune vérification n'a utilisé Aiven ou les boutiques existantes.

## 9. Problèmes corrigés pendant la réalisation

- Les modèles de lignes utilisent explicitement la table partagée et le type documentaire approprié ; pas de table déduite `saas_credit_note_lines`.
- Les relations partagées préservent le sous-type documentaire, avec des signatures compatibles avec Eloquent et l'analyse statique.
- La règle conditionnelle distingue Enum PHP et valeur HTTP ; les règles d'avoir ne sont pas proposées à une préparation de facture.
- La signature de brouillon inclut les lignes : changer seulement une quantité invalide aussi l'ancien formulaire.
- Les sommes de réservations utilisent une lecture jointe verrouillée, évitant une relation lue dans un ancien snapshot MySQL après attente.
- Un paiement intégral après avoir porte sur le dû corrigé ; les reçus déjà produits ne changent pas.
- Une dette entièrement corrigée affiche son véritable état et ne prétend pas qu'un paiement a eu lieu.
- Une adresse historique de snapshot ne sert pas à transmettre un nouvel avoir si le propriétaire a une autre adresse courante vérifiée.

## 10. Contrôles non exécutés

Pas de test destructif, migration ou seeder sur la base réelle ; pas d'e-mail réellement envoyé ni de remboursement bancaire. Pas de charge prolongée, de test antivirus ou de certification fiscale. Le contrôle navigateur valide le parcours et l'absence de débordement observé, sans prétendre à un audit complet WCAG ou à une comparaison visuelle sans référence. Les quatre scénarios navigateur optionnels des Modules 18/19/21 ne sont pas rejoués ici ; leurs tests HTTP et MySQL font partie de la régression. Le scénario navigateur du Module 20 est exécuté séparément et reste ignoré dans la commande groupée sans pilote interactif.

## 11. Limites et mise en service

Le test préexistant `ConfigurationLayoutTest.php:11` attend que `geography.reference` soit absent, alors que `config/geography.php` existe déjà dans HEAD. Cet échec sans rapport avec les avoirs est signalé et non masqué. Le build indique aussi que l'optimisation optionnelle des polices nécessite `fontaine` ; aucune dépendance ajoutée pour cet avertissement.

La base réelle reste inchangée. L'ajout des trois permissions peut se faire, après autorisation dédiée, par `CreditNotePermissionSeeder`, sans enrichir les attributions existantes. Une règle d'avoir réelle doit être créée, validée et activée depuis l'administration ; aucune règle financière ni identité fiscale fictive n'est semée. Un utilisateur doit ensuite recevoir les droits appropriés selon le processus existant.

La première régression groupée a été interrompue par la limite PHP de 128 Mo dans la lecture de polices Dompdf. La reprise utilise 512 Mo uniquement pour le processus de test ; aucune limite globale PHP ou configuration du site n'est modifiée. Un lancement interrompu ne compte pas comme une suite réussie.

Un lancement intermédiaire a aussi mélangé une ancienne classe déjà chargée et un nouveau consommateur de `STATE_PATTERN`, car le code a été modifié pendant son exécution. Ce résultat est écarté de la validation finale ; la régression finale est relancée dans un processus neuf sur le code figé, dont les empreintes sont contrôlées à la fin.

## 12. Parcours manuel

1. Avec un administrateur autorisé, créer une règle `invoice.correction.requested`, type Avoir, délai zéro ; la valider puis l'activer.
2. Ouvrir une facture ISSUED, choisir « Préparer un avoir ».
3. Choisir la règle, renseigner le motif général, puis quantité/TTC/motif pour chaque ligne concernée.
4. Créer le brouillon : le disponible diminue, le dû reste inchangé. Modifier ou annuler tant que l'état l'autorise.
5. Valider et réserver le numéro, puis émettre le PDF. L'émission peut aussi reprendre la préparation sous les mêmes contrôles.
6. Vérifier le numéro AVOIR, la facture originale inchangée, le nouveau dû et l'historique.
7. Se connecter comme propriétaire : ouvrir « Mes avoirs », lire et télécharger le PDF. Un autre propriétaire doit être refusé.
8. Rejouer l'émission : même document/numéro/PDF/intention, sans doublon.

## 13. Protections SQL et PDF

Clés étrangères simples/composites réutilisées ; type, propriétaire, abonnement, échéance et ligne originale restent liés. Les réservations sont contrôlées par le service sous ordre stable de verrous ; un CHECK SQL ne vérifie pas à lui seul une somme entre plusieurs documents. Les tests MySQL à deux processus démontrent le contrôle concurrent des points d'entrée applicatifs.

Les données émises et leurs médias ne sont plus modifiables/supprimables par SQL ordinaire. Le PDF est écrit hors transaction longue, signé par HMAC, haché et conservé sous UUID déterministe. Une reprise vérifie le fichier existant. L'association document/média, le hash et la Policy sont revérifiés au téléchargement ; réponse privée `no-store`. Un échec tardif laisse PREPARING et son numéro, sans média final, intention ou audit de succès partiels.

## 14. Explication simple du chemin

Vue → route et middleware centraux → Form Request/Policy → Controller → service → modèles centraux/MySQL → résultat → redirection → vue. Le service verrouille d'abord les données du propriétaire, puis la facture et ses corrections, vérifie le disponible, écrit le brouillon et son audit ensemble. L'émission réserve le numéro, fabrique le PDF, puis confirme document/média/solde/intention/audit ensemble. Une exception annule les écritures de cette dernière étape, sans perdre le numéro réservé.

## 15. Module 22

Le solde expose déjà dette corrigée, paiements nets, remboursements nets, fonds conservés, reste, surplus et remboursements réservés. Les écritures inverses exactes annulent les écritures REVERSED. Le futur module doit contrôler ses budgets par facture/paiement/avoir, les réservations et états incertains sous les mêmes verrous, enregistrer une sortie réellement constatée et gérer ses permissions/preuves. Ce module ne fournit aucune API bancaire, autorisation de remboursement, formulaire de restitution ni garantie d'exécution du Module 22.

## Fichiers applicatifs du changement

Liste ci-dessous hors tests, fichiers d'instruction de mission et graphe généré.
- `app/Enums/Central/Billing/PaymentStateEnum.php`
- `app/Enums/Central/Billing/TriggerEventEnum.php`
- `app/Http/Controllers/Central/Admin/Billing/SaasCreditNoteController.php`
- `app/Http/Controllers/Central/Admin/Billing/SaasInvoiceController.php`
- `app/Http/Controllers/Central/Admin/Billing/SaasPaymentController.php`
- `app/Http/Controllers/Central/Owner/Billing/CreditNoteController.php`
- `app/Http/Controllers/Central/Owner/Billing/InvoiceController.php`
- `app/Http/Controllers/Central/Owner/Billing/PaymentController.php`
- `app/Http/Requests/Central/Billing/CreditNoteActionRequest.php`
- `app/Http/Requests/Central/Billing/SearchCreditNotesRequest.php`
- `app/Http/Requests/Central/Billing/StoreSaasCreditNoteRequest.php`
- `app/Models/Central/Billing/SaasCreditNote.php`
- `app/Models/Central/Billing/SaasCreditNoteLine.php`
- `app/Models/Central/Billing/SaasInvoice.php`
- `app/Models/Central/Billing/SaasInvoiceLine.php`
- `app/Policies/Central/MediaPolicy.php`
- `app/Policies/Central/SaasCreditNotePolicy.php`
- `app/Policies/Central/SaasInvoicePolicy.php`
- `app/Services/Central/Billing/BillingRuleService.php`
- `app/Services/Central/Billing/InvoicePaymentService.php`
- `app/Services/Central/Billing/PaymentDocumentService.php`
- `app/Services/Central/Billing/SaasCreditNoteService.php`
- `app/Services/Central/Billing/SaasInvoiceIssuanceService.php`
- `app/Services/Central/Billing/SaasInvoicePdfService.php`
- `app/Services/Central/Billing/SaasPaymentService.php`
- `app/Services/Central/Roles/CentralRoleCatalog.php`
- `app/Services/Central/Subscriptions/DefaultSubscription.php`
- `database/seeders/Central/CreditNotePermissionSeeder.php`
- `resources/views/central/documents/saas-credit-note.blade.php`
- `resources/views/central/layouts/app/sidebar.blade.php`
- `resources/views/central/pages/admin/billing/credit-notes/create.blade.php`
- `resources/views/central/pages/admin/billing/credit-notes/index.blade.php`
- `resources/views/central/pages/admin/billing/credit-notes/show.blade.php`
- `resources/views/central/pages/admin/billing/form.blade.php`
- `resources/views/central/pages/admin/billing/invoices/show.blade.php`
- `resources/views/central/pages/admin/billing/payments/create.blade.php`
- `routes/central.php`
- `routes/central/admin/billing.php`

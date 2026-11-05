# Module 19 — Adaptation aux factures intégrales

Date : 5 novembre 2026. Demande : `module19v2.txt`. Branche de travail : `module-19`, sans fusion ni publication.

## 1. Fonctionnalités conservées

Préparation depuis une échéance, calculs HT/taxes/TTC exacts, configuration fiscale validée, snapshots, numérotation, PDF privé, téléchargement signé et autorisé, audit, historique, reprise et idempotence. Le prix de l'échéance est TTC. Aucune donnée fiscale réelle ni taux légal inventé.

Les deux documents suffixés `(10)` et `(3)` demandés sont absents. Les références sont les documents canoniques `Schema-BDD-SaaS-Ecommerce-UUID.md` (C8.1, C8.2, C8.4, §6.7) et `Diagramme-BDD-Centrale-Complet.md`, ainsi que l'architecture §51–53. Les exemples historiques de paiements fractionnés sont remplacés dans le parcours MVP par la décision actuelle, sans supprimer les anciens enregistrements.

## 2. Corrections de comportement

- Une nouvelle préparation refuse une échéance PARTIALLY_PAID, un montant inférieur au prix convenu, une période différente ou plusieurs obligations pour cette même période. Aucun fractionnement automatique.
- La préparation historique sans taxe réutilise une obligation intégrale existante et refuse d'ajouter une facture lorsque cette obligation est déjà facturée.
- Nouvelle préparation et première réservation exigent une règle ACTIVE et des paramètres autorisés. Une règle retirée conserve son historique, mais ne permet plus une nouvelle facture. Les sélecteurs et messages correspondent à cette règle.
- Le rejeu retrouve les documents existants ; il ne réécrit pas une facture ISSUED pour refléter un paiement.
- L'état documentaire et l'état de paiement s'affichent séparément. Aucune facture émise ne confirme un encaissement ou n'active un abonnement.

## 3. Fichiers applicatifs concernés

Créés :

- `app/Enums/Central/Billing/PaymentStateEnum.php`
- `app/Enums/Central/Billing/TransferStatusEnum.php`
- `app/Models/Central/Billing/SaasPayment.php`
- `app/Services/Central/Billing/InvoicePaymentService.php`

Modifiés :

- `app/Http/Controllers/Central/Admin/Billing/BillingRuleController.php`
- `app/Http/Controllers/Central/Admin/Billing/SaasInvoiceController.php`
- `app/Http/Controllers/Central/Owner/Billing/InvoiceController.php`
- `app/Models/Central/Billing/BillingRule.php`
- `app/Models/Central/Subscriptions/SubscriptionInstallment.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/Central/Billing/SaasInvoiceService.php`
- `app/Services/Central/Billing/SaasNumberingService.php`
- `app/Services/Central/Billing/SaasInvoiceIssuanceService.php`
- `resources/views/central/pages/admin/billing/invoices/index.blade.php`
- `resources/views/central/pages/admin/billing/invoices/show.blade.php`
- `resources/views/central/pages/admin/billing/invoices/installments.blade.php`
- `resources/views/central/pages/admin/billing/show.blade.php`

Tests : `SaasInvoiceIssuanceTest.php` complété ; les tests de paiement intégral de `SaasBillingScenarioTest.php` sont repris de module-18 après vérification que le fichier de base n'avait pas été modifié par module-19. Aucun test existant supprimé. Instructions et références métier mises à jour séparément. Le remplacement de `module19.txt` par `module19v2.txt` préexistait.

## 4. Services réutilisés

AuthorizationMutation conserve DB::transaction et les Policies. SaasInvoiceService prépare ; InvoiceCalculator calcule sans flottants ; SaasNumberingService réserve ; SaasInvoiceIssuanceService coordonne l'émission ; SaasInvoicePdfService conserve le PDF ; PrivateMediaAccessService autorise et contrôle le téléchargement. Aucun nouveau moteur documentaire.

InvoicePaymentService ne fait que lire. `states(actor, invoices)` calcule l'affichage avec une seule lecture groupée des transferts de la page. `confirmedPayment(actor, invoice)` relit la facture, contrôle l'autorisation et retourne le paiement intégral admissible ou null. Aucune décision financière mise en cache.

## 5. Routes

Aucune route ajoutée ni URL modifiée. Les routes d'administration et `owner.invoices.*` restent distinctes. Le commerçant ne voit que ses propres documents. Aucun formulaire de carte, paiement fractionné ou remboursement automatique.

## 6. Permissions

Les droits `saas.invoices.view/create/issue/download` et ceux des règles/compteurs sont conservés. Aucun nouveau droit, aucune attribution et aucun seeder exécuté. La lecture des paiements réutilise la Policy de consultation de la facture, avec vérification du propriétaire pour le commerçant.

## 7. Base de données

Aucune migration et aucune table créée. SaasPayment lit `saas_transfers`, avec connexion centrale et scope PAYMENT. La lecture des états examine aussi les autres écritures rattachées pour signaler une anomalie plutôt que présenter un remboursement vérifié comme un paiement. Le morph historique `saas_payment` est enregistré. Les colonnes calculées et protections SQL existantes restent intactes.

## 8. Vérifications exécutées

Les résultats détaillés sont consignés dans le suivi de cette adaptation. Toutes les écritures de tests utilisent des bases MySQL locales temporaires et des fichiers isolés. Aucun compte, facture, abonnement, paiement ni fichier réel modifié.

Série finale commune : **120 tests réussis, 3 062 assertions, 275 728 ms ; un test navigateur optionnel ignoré dans cette commande**. Fichiers : `SaasInvoiceIssuanceTest.php`, `SaasBillingScenarioTest.php`, `BillingSequenceManagementTest.php`, `BillingRuleManagementTest.php`, `DocumentedCentralSchemaTest.php` et `CentralMediaManagementTest.php`. Le navigateur est exécuté séparément : **un test réussi, six assertions, 121 928 ms**. Le fichier Module 19 a aussi été relancé après correction de la fixture : 32 réussis / 173 assertions ; régression ciblée Module 18/retrait : 11 réussis / 58 assertions. Ces séries se recouvrent, elles ne sont pas additionnées.

PHP utilisé : `C:\Users\habou\.config\herd\bin\php85\php.exe`. Les processus de tests définissent APP_CONFIG_CACHE et APP_ROUTES_CACHE vers des chemins temporaires distincts. Commande de la série finale : `artisan test --compact` suivie des six chemins ci-dessus sous `tests/Central/Feature/Admin/Billing/`, `tests/Central/Feature/Database/` et `tests/Central/Feature/Admin/Media/`. La suite complète du dépôt n'est pas exécutée.

Pint final conforme ; PHPStan ciblé sur les services/modèles/contrôleurs de facturation, SubscriptionInstallment et AppServiceProvider : **zéro erreur**. Build réussi. Cache des routes vérifié avec un fichier isolé, ensuite supprimé. Diff sans erreur d'espacement et graphe AST actualisé. Aucun changement de dépendances.

Contrôles : régression des règles/compteurs/schéma et scénarios du Module 18 ; préparation/émission/calculs/PDF/permissions/reprise du Module 19 ; deux processus indépendants pour une même émission puis deux documents ; états de paiement avec fixtures réelles MySQL ; immutabilité SQL et empreinte du PDF ; isolation tenant. Pint, PHPStan ciblé, build frontend, route:cache isolé et diff final.

Navigateur réel isolé : connexion administrative, émission après confirmation, numéro FAC-2026-000001, PDF privé HTTP 200 de 23 396 octets. Connexion du commerçant, liste et détail, état À payer malgré ISSUED, PDF 200 ; accès administratif 403, pas de bouton d'émission ni historique administratif. Le serveur et la base temporaires sont nettoyés après le test.

## 9. Couverture des scénarios demandés

| Scénario | Contrôle et limite |
|---|---|
| T19-01 Régression | Tests existants conservés et exécutés ; suite globale du projet non exécutée. |
| T19-02 Intégralité | Rejet des montants réduits, échéances partielles, obligations multiples ; montant TTC intégral conservé. |
| T19-03 Facture non payée | ISSUED sans transfert reste À payer ; aucun droit ajouté. |
| T19-04 Déclaration | DECLARED reste à vérifier. |
| T19-05 Intégral vérifié | Une fixture VERIFIED de 3000 confirme l'affichage ; aucune confirmation réelle par Module 21 revendiquée. |
| T19-06 Insuffisant | 1000 VERIFIED reste anomalie ; 1000 + 2000 ne sont pas cumulés. |
| T19-07 PDF | Métadonnées, montants et octets inchangés après lecture d'un paiement vérifié. |
| T19-08 Reçu | Contrat de lecture prêt ; aucun générateur ni téléchargement de reçu, intégration réelle NON EXÉCUTÉE. |
| T19-09 Propriétaire étranger | Refus HTTP et refus du contrat de lecture. |
| T19-10 Rejeu | Même document, numéro, PDF et intention. |
| T19-11 Concurrence | Vrais processus et contraintes MySQL, même opération ou opérations distinctes. |
| T19-12 Immutabilité | Tests SQL des en-têtes/lignes/médias conservés ; lecture des transferts ne les modifie pas. |
| T19-13 Parcours interdit | Aucune route de carte, fractionnement ou remboursement ajoutée ; refus serveur du fractionnement. |
| T19-14 Isolation | Services refusant le contexte boutique ; témoin tenant inchangé. |

## 10. Contrôles non exécutés

Pas de suite complète du projet ni charge prolongée. Pas de paiement réel, activation financière ou parcours complet Module 21. Pas de reçu produit : son contenu définitif, son stockage et son autorisation de téléchargement devront être réalisés dans le parcours validé du paiement. Aucun envoi réel d'e-mail ; l'intention reste distincte d'une preuve de remise.

## 11. Erreurs rencontrées

Les nouveaux tests ont d'abord confirmé que les échéances réduites et PARTIALLY_PAID étaient acceptées. Correction dans la préparation et avant première réservation. Une fixture de doublon copiait les colonnes calculées parent_record_type/active_owner_slot : ces champs sont exclus de la réplication de test, sans retirer les contraintes MySQL. Le build signale une optimisation optionnelle de police absente ; aucune dépendance ajoutée pour la masquer.

## 12. Parcours final

Échéance unique intégrale → règle ACTIVE autorisée → preuve fiscale → facture DRAFT → numéro réservé PREPARING → PDF privé hors transaction longue → contrôle d'intégrité → transaction courte média/ISSUED/intention/audit. La facture affiche séparément son état documentaire et celui des transferts ; ses montants, snapshots et PDF restent historiques.

## 13. Préparation du Module 21 et du reçu

Le seul état PAID calculé exige une facture ISSUED et exactement une écriture VERIFIED positive de type PAYMENT, sans reversal_of_id, avec même propriétaire/devise et montant exact, ainsi qu'une date de validation. Un montant insuffisant, plusieurs paiements vérifiés ou une écriture de remboursement vérifiée restent une anomalie. Aucun cumul de règlements partiels.

Ce lecteur s'appuie sur le fait enregistré VERIFIED ; il ne contrôle pas à la place du Module 21 que l'argent a été reçu. Le prochain module doit vérifier fonds, preuve privée, auteur, méthode réelle, référence, montant et devise, puis confirmer sous les verrous communs. Le contrat de reçu est une lecture, pas une autorisation d'écriture : un futur générateur devra relire/verrouiller les données nécessaires dans son propre parcours atomique. Aucun faux paiement ni document_type=3.

Le Module 19 n'est pas déclaré entièrement validé avec reçus : leur intégration réelle est reportée jusqu'au Module 21.

## 14. Explication simple

La facture dit « vous devez 3000 DA ». Le paiement vérifié dit « nous avons reçu exactement 3000 DA ». Le reçu confirmera cet encaissement dans le futur parcours. Ces trois choses restent séparées ; payer ne réécrit pas la facture et émettre une facture ne rend pas l'abonnement payé.

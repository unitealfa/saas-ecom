# Module 21 — Paiements manuels intégraux

État au 6 novembre 2026 : implémentation sur `module21`, après intégration locale par avance rapide de `origin/dev` (`5d4cc62`, Modules 18 et 19). Aucun commit, push, fusion distante ni écriture financière réelle. Les contrôles et limites ci-dessous distinguent code développé, vérifications et mise en service.

## 1. Analyse initiale

Le journal `saas_transfers`, les factures émises, les échéances et la résolution des abonnements existaient. La lecture du paiement du Module 19 ne confirmait pas des fonds. L'attribution administrative activait immédiatement une offre, et la résolution des abonnements pouvait activer une période prévue sans paiement. Décision utilisateur : toute nouvelle attribution payante, y compris administrative, attend désormais son règlement intégral ; les abonnements historiques ne sont pas rétroactivement désactivés.

## 2. Fonctionnalités développées

- Choix mensuel ou annuel d'une offre existante, prix contrôlé en base, période réservée et échéance intégrale unique.
- Remise d'espèces réellement reçues par un administrateur ; référence interne, constat privé, confirmation intégrale ou anomalie traçable.
- Dépôt externe JPEG/PNG/PDF par le propriétaire ; aucun droit à ce stade. Confirmation distincte des fonds réels par un administrateur.
- Recherche, filtre d'état, détail, facture, commerçant autorisé, preuve privée, refus motivé, rapprochement et audit.
- Validation atomique du paiement, de l'échéance et des droits ; période future payée restant prévue jusqu'à son début.
- Reçu PDF privé distinct de la facture fiscale, contrôle d'intégrité et reprise sans second paiement.
- Anomalies de montant ou de réception supplémentaire conservées, sans fractionnement, crédit, virement ni remboursement automatique.

## 3. Architecture réellement utilisée

Blade/Flux → route centrale et middleware → Form Request/Policy → Controller → service métier → Eloquent central/MySQL → redirection et message unique → Blade.

`SubscriptionPurchaseService` réserve l'offre ; `SaasInvoiceService` et `SaasInvoiceIssuanceService` existants préparent/émettent la facture sous leurs permissions propres. `SaasPaymentService` traite déclaration/décision ; `PaidSubscriptionActivation` applique la période et réutilise `TenantStatusService`. `PaymentDocumentService` conserve les pièces et le reçu via le stockage privé existant. Aucun DTO, table de paiement, moteur de quota ou système fiscal parallèle.

## 4. Fichiers créés

La liste exacte des fichiers de production figure en fin de document. Le test est `tests/Central/Feature/Admin/Billing/SaasPaymentManagementTest.php`.

## 5. Fichiers modifiés

Raccordement aux Policies, catalogue de droits, routes, sidebar, factures et abonnements. Correction de deux erreurs de fusion préexistantes : méthode `BillingRule::isActiveAt()` dupliquée et accolade manquante dans `SubscriptionPolicy`. Les anciens tests de facturation utilisent maintenant une factory d'abonnement historique actif ; les nouveaux tests couvrent explicitement l'attribution en attente, au lieu de simuler une activation gratuite par le service courant.

## 6. Migration et compatibilité

`2026_11_05_052718_support_manual_full_payments.php` conserve les méthodes 1–4 et ajoute 5 = espèces sur place. Le CHECK et le commentaire SQL sont adaptés. Deux triggers protègent les métadonnées et la suppression des preuves référencées et des reçus vérifiés/corrigés. Aucune table ni donnée supprimée, aucun historique réécrit. Le `down()` retire les nouveaux triggers, mais conserve la possibilité de stocker les espèces déjà enregistrées : un retour de code ne doit pas effacer un encaissement.

Les champs de contrepassation et contraintes historiques restent en place. Le module n'ajoute pas d'écran de remboursement ni de contrepassation financière automatique. Une erreur sur un encaissement vérifié exige une correction comptable autorisée et tracée, jamais une édition silencieuse.

Migration exécutée seulement dans les bases temporaires de test. Son application à la base réelle et le seeder restent à autoriser séparément après revue.

## 7. Routes

- Administration : `central.payments.index/show`, `verify`, `reject`, `reconcile`, `resume-cash`, `proof`, `receipt` ; `central.payments.cash.create/store` depuis une facture.
- Propriétaire : `owner.subscriptions.index/store/show`, `owner.payments.index/show/create/store/resume-proof/proof/receipt`.
- Paramètres publics UUID, protections centrales héritées, téléchargements signés plus Policy. Les alias de domaines restent chargés par le mécanisme existant. Les fonctions de groupe Laravel ne sont pas des actions de route Closure.
- Aucun throttle administratif. Les soumissions du commerçant restent limitées selon les routes publiques/propriétaires concernées.

## 8. Permissions

`saas.payments.view`, `saas.payments.validate`, `saas.payments.reject`, `saas.payments.reconcile`. `PaymentPermissionSeeder` est additif : conserve les identités et attributions, ne donne aucun nouveau droit aux comptes existants. L'émission conserve les droits et prérequis fiscaux des Modules 18–19. Un propriétaire ne confirme jamais son propre paiement ; le personnel utilise les droits administratifs, sans l'espace de paiement personnel des commerçants.

## 9. Tests exécutés et résultats

Contrôles ciblés sur MySQL local temporaire, jamais Aiven ni une boutique réelle. Cas vérifiés : espèces intégrales/rejeu, insuffisance/excédent, dépôt sans activation, refus et motif, propriétaire étranger, permissions indépendantes, fichiers invalides et images converties, intégrité/immutabilité SQL, référence physique CCP/BaridiMob, rollback SQL, stockage indisponible et reprise, périodes futures/impayées/expirées, prix annuel, isolation d'une vraie base tenant et cache public invalidé après commit.

Concurrence : deux processus PHP/MySQL indépendants pour une même remise espèces, une même déclaration externe, puis deux réceptions distinctes sur une obligation. Une seule confirmation et un seul reçu ; la réception supplémentaire devient une anomalie, sans double activation. Les décisions utilisent les lignes réellement verrouillées, pas une relation relue depuis un ancien snapshot MySQL.

Navigateur réel sur serveur, base et fichiers isolés : espèces, connexion du propriétaire, paiement payé/abonnement actif, PDF privé HTTP 200 (18 716 octets) ; dépôt externe, état À vérifier/Prévu, connexion administrative et confirmation, puis reçu propriétaire HTTP 200 (19 187 octets). Aucun bouton administratif dans l'espace propriétaire ; `Cache-Control: no-store, private`.

Résultats observés (les séries ciblées se recouvrent, ne pas les additionner) :

| Contrôle | Résultat |
|---|---|
| Paiements et attributions/transitions d'abonnements | 65 réussis, 315 assertions, 2 scénarios navigateur ignorés dans cette commande ; 220 572 ms |
| Régressions du scénario de facturation historique, après adaptation de sa fixture | 19 réussis, 119 assertions ; 60 720 ms |
| Tests unitaires d'abonnement, schéma central documenté et gestion des fichiers privés | 31 réussis, 2 473 assertions ; 91 300 ms |
| Reprise après sauvegarde PDF, taxes TTC, renouvellement, concurrence et stockage | 8 réussis, 64 assertions ; 59 904 ms |
| Seeder additif, nouvelle attribution et période expirée | 3 réussis, 15 assertions ; 41 670 ms |
| Formulaires malformés et écrans espèces/externe/offres/image | 5 réussis, 55 assertions ; 33 705 ms |
| Rollback SQL après écriture des droits et de l'audit de confirmation | 1 réussi, 6 assertions ; 23 855 ms |
| Dernier contrôle après centralisation des constantes : espèces/externe/rapprochement | 3 réussis, 29 assertions ; 26 989 ms |
| Navigateur espèces, avec base isolée et contrôles SQL finaux | 1 réussi, 6 assertions ; 149 304 ms |
| Navigateur externe, avec base isolée et contrôles SQL finaux | 1 réussi, 6 assertions ; 274 727 ms |
| PHPStan ciblé services/contrôleurs/requests/modèles | Aucune erreur dans les contrôles ciblés |
| Pint | Conforme après formatage |
| Syntaxe PHP et diff | 47 fichiers PHP valides lors du contrôle dédié ; diff sans erreur d'espacement |
| Cache de routes isolé | Génération réussie, routes chargées, fichier temporaire supprimé |
| Build frontend | Réussi ; avertissement sur fontaine, dépendance optionnelle non installée |

La première série commune de 165 scénarios a produit 144 réussites, 19 échecs/erreurs et 2 ignorés. Les 19 provenaient du scénario historique utilisant le nouveau service d'attribution, qui crée désormais sa propre échéance en attente. La fixture historique est maintenant explicite, sans modification des assertions ; les 19 tests de ce fichier ont ensuite tous réussi. Les règles, compteurs et tests d'émission du Module 19 avaient passé dans cette série. Les vérifications ultérieures de paiement et d'abonnement sont celles du tableau.

Correspondance avec les scénarios demandés dans `module21` (tous testés sur données isolées) :

| Scénario | Preuve dans SaasPaymentManagementTest |
|---|---|
| T21-01 | Espèces 3 000 DZD : confirmation, échéance payée, activation et rejeu unique |
| T21-02 | Espèces 1 000 DZD : anomalie conservée, aucun droit |
| T21-03 | Dépôt HTTP externe : déclaration privée et abonnement prévu |
| T21-04 | Confirmation HTTP administrative : payé, actif et reçu |
| T21-05 | Confirmation reçue=false refusée, aucun reçu ni activation |
| T21-06 | Déclaration externe 2 500 DZD non activable |
| T21-07 | Espèces 3 500 DZD conservées comme anomalie |
| T21-08 | Zéro, négatif et montant invalide refusés |
| T21-09 | Autre propriétaire : déclaration et lecture interdites |
| T21-10 | Toutes les routes administratives refusées sans droit ; quatre droits indépendants |
| T21-11 | Même charge et double dépôt : même paiement et même preuve |
| T21-12 | Deux processus confirment la même déclaration : un seul fait vérifié |
| T21-13 | Deux réceptions distinctes sur la même période : une vérifiée, une anomalie |
| T21-14 | Octets de preuve altérés : confirmation refusée |
| T21-15 | Espèces avec constat interne et référence CASH, sans fichier bancaire |
| T21-16 | PDF de reçu privé, facture inchangée et une seule pièce |
| T21-17 | URL signée du reçu d'autrui : 403 |
| T21-18 | Renouvellement intégral : début à l'ancien terme, rejeu sans prolongation supplémentaire |
| T21-19 | Période impayée ignorée par DefaultSubscription |
| T21-20 | Offre gratuite conservée sans échéance payante artificielle |
| T21-21 | Erreur SQL contrôlée : confirmation, échéance, droits et audit annulés ensemble |
| T21-22 | Trois scénarios simultanés avec deux vrais processus MySQL |
| T21-23 | Vraie base tenant isolée, canari intact, aucune table financière créée |
| T21-24 | Http::fake/assertNothingSent sur les deux parcours complets ; aucun connecteur bancaire |

État : fonctionnalités **IMPLÉMENTÉES**, **TESTÉES** et **VALIDÉES LOCALEMENT** dans ce périmètre. Mise en service réelle **EN ATTENTE D'AUTORISATION**. Les contrôles hors périmètre exécuté restent **NON TESTÉS**, conformément au §10 ; ces résultats ne constituent pas une garantie absolue d'absence de défaut.

## 10. Contrôles non exécutés

Suite globale de tous les modules non lancée ; pas de recette sur la base réelle, de transfert bancaire réel, d'envoi réel ni d'audit juridique des données fiscales. Le PDF externe est contrôlé par type/taille/en-tête/fin ; il n'est ni désinfecté par un antivirus ni certifié authentique. L'administrateur doit contrôler les fonds et le justificatif. Aucune nouvelle dépendance n'est installée pour prétendre garantir cette authenticité.

## 11. Erreurs détectées

- Deux erreurs de syntaxe issues de la fusion 18–19.
- Activation prématurée des nouvelles attributions et périodes impayées ; offre gratuite empêchant une période future payée de devenir effective.
- Risque de lecture MySQL ancienne après attente d'un verrou lors de confirmations concurrentes.
- Clé d'attribution administrative devant vérifier aussi son propriétaire lors d'un rejeu.
- Fixtures historiques dépendantes de l'ancienne attribution immédiate ; absence du référentiel gratuit dans certains tests de transition.
- Reprise documentaire devant conserver un PDF déjà écrit avant un arrêt, même si une nouvelle conversion produirait des octets différents ; valeurs de formulaire non scalaires ne devant pas provoquer une erreur de rendu.

## 12. Corrections et garanties

Transactions natives avec tentatives conservées. Ordre SQL : propriétaire → abonnements ordonnés → échéance → facture → paiement. État attendu contrôlé sous verrou ; empreinte financière et contraintes SQL contre les doubles réceptions. CCP et BaridiMob partagent leur réseau pour éviter un doublon par changement de méthode. Aucun verrou global sur root. Génération PDF hors verrou SQL financier, verrou documentaire dédié contre les pièces doublées. Une panne du reçu ne remet pas en cause un paiement réellement confirmé ; le téléchargement reprend le reçu manquant.

Compte destinataire réel chiffré, empreinte technique non réversible et absence de coordonnées bancaires en clair dans l'audit. Montants traités en décimaux exacts, comparaison au total TTC émis ; aucun nouveau calcul de taxe au paiement. Les preuves de source ne sont pas remplacées après rattachement.

## 13. Dépendances et mise en service

Réutilisation de Dompdf, GD, Fileinfo, Brick Math, Spatie et Stancl installés. Renseigner les instructions réelles via `BILLING_CASH_INSTRUCTIONS` et `BILLING_PAYMENT_INSTRUCTIONS` ; elles sont vides par défaut. Configurer et valider les identités fiscales et la politique de taxe existantes avant émission. Sans ces validations, aucune facture émise n'est inventée.

Après autorisation d'appliquer à la base centrale réelle, depuis PowerShell à la racine :

```powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
& $aydraPhp artisan migrate --path=database/migrations/central/2026_11_05_052718_support_manual_full_payments.php --no-interaction
& $aydraPhp artisan db:seed --class='Database\Seeders\Central\PaymentPermissionSeeder' --no-interaction
```

Résultat attendu : méthode espèces acceptée, preuves protégées, quatre permissions présentes sans nouvelle attribution. Adapter les droits du personnel par l'interface existante. Renouveler le cache de routes/configuration actif selon le déploiement habituel ; ne pas exécuter ces commandes comme un test sur la base réelle.

## 14. Parcours manuel espèces

Le commerçant choisit l'offre. Administration → Abonnements → période → Échéances : préparer avec une règle valide, puis émettre sous les droits fiscaux existants. Depuis la facture émise, enregistrer les espèces effectivement reçues, leur montant/date et confirmer la réception. Un montant exact paie la période ; un autre montant reste une anomalie avec constat interne et note de rapprochement, sans activation.

## 15. Parcours manuel externe

Le propriétaire ouvre Mes factures → facture émise → Déposer un justificatif. Il renseigne le versement déjà effectué, sa date et son fichier. Administration → Paiements SaaS : examiner la pièce, contrôler réellement compte/référence/montant/date, puis confirmer ou refuser avec motif. Rapprocher n'est pas confirmer les fonds. Si le stockage du premier dépôt échoue, compléter le justificatif sur la même déclaration.

## 16. Reçu

Depuis le paiement payé, Télécharger le reçu privé. Référence `PAY-<uuid>` distincte de la numérotation fiscale, facture et période liées, montant/méthode/date, identités figées de la facture. Ce PDF n'est ni une preuve bancaire ni une nouvelle facture de type 3. Rejeu : même pièce existante, intégrité contrôlée. Une pièce altérée bloque le téléchargement et demande une intervention, sans remplacement silencieux.

## 17. Activation

`VERIFIED` et échéance `PAID` sont atomiques avec la mutation d'abonnement/quota. Une période future conserve `SCHEDULED` jusqu'à sa date ; la résolution existante active ensuite les droits. Les périodes nouvelles impayées restent bloquées. Les abonnements historiques actifs et les anciennes périodes prévues sans obligation conservent leur fonctionnement historique. Aucune prolongation par double clic et aucune création automatique de boutique.

La présentation publique des offres est mise en cache fichier central, isolée par base, 12 heures et invalidée après commit. Les prix acceptés, permissions, paiements et droits sont relus en base. Le test observe une requête pour trois lectures du catalogue hors transaction ; cela ne constitue pas une mesure de performance globale.

## 18. Explication simple

Choisir une offre réserve une période. La facture indique ce qu'il faut payer. Déposer un fichier indique seulement « j'ai payé ». L'administrateur contrôle l'argent réellement reçu. Si tout correspond, Aydra marque la période payée, active ses droits à la bonne date et fournit le reçu. Les essais répétés retrouvent la même opération.

## Inventaire de production

Voir la liste ci-dessous, issue du diff de ce module et excluant les tests, le fichier de demande utilisateur et le graphe généré.

- `.env.example` — modifié
- `app/Enums/Central/Billing/TransferMethodEnum.php` — créé
- `app/Enums/Central/Billing/TransferStatusEnum.php` — modifié
- `app/Http/Controllers/Central/Admin/Billing/SaasPaymentController.php` — créé
- `app/Http/Controllers/Central/Admin/Subscriptions/SubscriptionController.php` — modifié
- `app/Http/Controllers/Central/Owner/Billing/PaymentController.php` — créé
- `app/Http/Controllers/Central/Owner/Billing/SubscriptionPurchaseController.php` — créé
- `app/Http/Requests/Central/Billing/PaymentActionRequest.php` — créé
- `app/Http/Requests/Central/Billing/SearchSaasPaymentsRequest.php` — créé
- `app/Http/Requests/Central/Billing/StoreSaasPaymentRequest.php` — créé
- `app/Http/Requests/Central/Subscriptions/PurchaseSubscriptionRequest.php` — créé
- `app/Models/Central/Billing/BillingRule.php` — modifié
- `app/Models/Central/Billing/SaasInvoice.php` — modifié
- `app/Models/Central/Billing/SaasPayment.php` — modifié
- `app/Models/Central/Plans/Plan.php` — modifié
- `app/Models/Central/Subscriptions/Subscription.php` — modifié
- `app/Policies/Central/MediaPolicy.php` — modifié
- `app/Policies/Central/SaasInvoicePolicy.php` — modifié
- `app/Policies/Central/SaasPaymentPolicy.php` — créé
- `app/Policies/Central/SubscriptionPolicy.php` — modifié
- `app/Providers/AppServiceProvider.php` — modifié
- `app/Services/Central/Billing/PaymentDocumentService.php` — créé
- `app/Services/Central/Billing/SaasPaymentService.php` — créé
- `app/Services/Central/Plans/PublicPlanCatalog.php` — créé
- `app/Services/Central/Roles/CentralRoleCatalog.php` — modifié
- `app/Services/Central/Subscriptions/DefaultSubscription.php` — modifié
- `app/Services/Central/Subscriptions/PaidSubscriptionActivation.php` — créé
- `app/Services/Central/Subscriptions/SubscriptionAssignmentService.php` — modifié
- `app/Services/Central/Subscriptions/SubscriptionPurchaseService.php` — créé
- `app/Services/Central/Subscriptions/SubscriptionTransitionService.php` — modifié
- `config/central/billing.php` — modifié
- `database/migrations/central/2026_11_05_052718_support_manual_full_payments.php` — créé
- `database/seeders/Central/PaymentPermissionSeeder.php` — créé
- `resources/views/central/documents/payment-receipt.blade.php` — créé
- `resources/views/central/layouts/app/sidebar.blade.php` — modifié
- `resources/views/central/pages/admin/billing/invoices/show.blade.php` — modifié
- `resources/views/central/pages/admin/billing/payments/create.blade.php` — créé
- `resources/views/central/pages/admin/billing/payments/index.blade.php` — créé
- `resources/views/central/pages/admin/billing/payments/show.blade.php` — créé
- `resources/views/central/pages/admin/subscriptions/show.blade.php` — modifié
- `resources/views/central/pages/owner/billing/subscriptions/index.blade.php` — créé
- `resources/views/central/pages/owner/billing/subscriptions/show.blade.php` — créé
- `routes/central.php` — modifié
- `routes/central/admin/billing.php` — modifié

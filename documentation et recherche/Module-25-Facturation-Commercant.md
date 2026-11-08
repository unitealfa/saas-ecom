# Module 25 — Facturation du commerçant

Date : 8 novembre 2026. Livraison C-157, C-158, C-159, C-162 installée manuellement. Base de préparation déclarée : dev, `10329ec2ce0ae15bee64ba296ccb8a39f60266b9`. Aucune branche créée, fusionnée ou publiée pendant cette revue.

## Fonctionnement

- C-157 : périodes et factures du seul commerçant connecté, montants intégraux, dépôt via les parcours existants. Les demandes d’abonnement gardent leur workflow.
- C-158 : états calculés par InvoicePaymentService ; une facture ou un justificatif ne prouve pas un encaissement. Aucun paiement partiel confirmé.
- C-159 : signalement motivé dans activity_log, sans modification du paiement. Clé idempotente par paiement, propriétaire contrôlé, doublon inchangé ignoré et contenu différent refusé. Examen administratif sous les permissions de lecture des paiements existantes.
- C-162 : factures, avoirs, preuves et reçus via les routes privées autorisées existantes, avec signatures et contrôle du propriétaire selon le document.

Espèces reçues ou versement externe avec justificatif seulement ; aucune carte, passerelle ou restitution automatique. La validation financière et les reçus restent dans le Module 21 ; les corrections documentaires manuelles dans le Module 20. Aucune migration, permission, dépendance ou donnée financière nouvelle.

## Raccordements

- `GET /facturation` : owner.billing.index.
- `GET /facturation/corrections/{payment}` : owner.billing.corrections.create.
- `POST /facturation/corrections/{payment}` : owner.billing.corrections.store ; limite propriétaire de trois requêtes/minute, sans limiter les administrateurs.
- `GET /admin/billing/payments/correction-requests` : central.payments.corrections.index.

Les UUID sont publics ; les ID restent internes. Routes centrales protégées, Policy/Form Request/services existants, menu « Ma facturation » et boîte administrative des signalements. Aucun remplacement intégral des anciens fichiers effectué par cette revue ; les autres modifications présentes sont conservées.

## Corrections de cette revue

1. Vue déplacée de `resources/views/central/pages/owner/overview.blade.php` vers `resources/views/central/pages/owner/billing/overview.blade.php`, emplacement demandé par le Controller. L’ancien chemin provoquait une vue introuvable.
2. Test déplacé de `tests/Feature/Owner/OwnerBillingOverviewTest.php` vers `tests/Central/Feature/Owner/OwnerBillingOverviewTest.php`. Il bénéficie désormais du cadre central ; son chemin existant vers Support/ManualBillingFixtures.php est ainsi correct.
3. Validation UUID/motif partagée entre le service et le Form Request ; normalisation du UUID et du motif avant déduplication. Le service refuse aussi les appels directs invalides.
4. Lecture verrouillée du signalement antérieur dans la transaction, avec verrou du paiement : évite de dédupliquer sur un ancien snapshot MySQL après une attente concurrente. Aucun statut, montant ou date financière modifié.

Composants : OwnerBillingOverviewService, OwnerPaymentCorrectionService ; Controllers OwnerBillingOverviewController, OwnerPaymentCorrectionController, OwnerPaymentCorrectionReviewController ; ReportOwnPaymentCorrectionRequest ; vues overview/corrections ; routes centrales, sidebar et fiche de paiement existantes.

## Vérifications effectuées

Lecture ciblée des sources et raccordements ; Pint réussi ; PHPStan sur les six nouvelles classes applicatives : zéro erreur. Diff vérifié. Aucun test Laravel/Pest/MySQL, SMTP ou worker exécuté dans cette intervention ; aucune base réelle modifiée. Les résultats statiques de préparation fournis par l’utilisateur ne sont pas présentés comme des tests fonctionnels locaux.

Restent à valider fonctionnellement : isolation des commerçants, droits administratifs, POST répété/concurrent, motifs invalides, documents privés et affichage mobile. La suite complète et les erreurs PHPStan d’autres modules ne sont pas réévaluées ici. Aucune fusion vers dev effectuée ni validation exhaustive revendiquée.

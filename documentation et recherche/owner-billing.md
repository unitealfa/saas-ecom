# owner-billing — Facturation du commerçant

Date : 8 novembre 2026. Livraison C-157, C-158, C-159, C-162 installée manuellement. Base de préparation déclarée : dev, `10329ec2ce0ae15bee64ba296ccb8a39f60266b9`. Aucune branche créée, fusionnée ou publiée pendant cette revue.

## Fonctionnement

- C-157 : périodes et factures du seul commerçant connecté, montants intégraux, dépôt via les parcours existants. Les demandes d’abonnement gardent leur workflow.
- C-158 : états calculés par InvoicePaymentService ; une facture ou un justificatif ne prouve pas un encaissement. Aucun paiement partiel confirmé.
- C-159 : signalement motivé dans activity_log, sans modification du paiement. Clé idempotente par paiement, propriétaire contrôlé, doublon inchangé ignoré et contenu différent refusé. L'examen utilise `saas.payments.reconcile`, exige une note de 10 à 2 000 caractères et ajoute un événement distinct ; tout second traitement est refusé. Valeurs enregistrées : `examined_without_change` ou `corrected_with_credit`. Le second choix exige un avoir ISSUED rattaché à la facture et au commerçant du paiement. Le commerçant voit « En attente », « Examiné — aucune correction nécessaire » ou « Corrigé par avoir émis », avec la note administrative.
- C-162 : factures, avoirs, preuves et reçus via les routes privées autorisées existantes, avec signatures, contrôle du propriétaire et refus des documents non émis/liens expirés.

Espèces reçues ou versement externe avec justificatif seulement ; aucune carte, passerelle ou restitution automatique. La validation financière et les reçus restent dans le Module 21 ; les corrections documentaires manuelles dans le Module 20. Aucune migration, permission, dépendance ou donnée financière nouvelle.

## Raccordements

- `GET /facturation` : owner.billing.index.
- `GET /facturation/corrections/{payment}` : owner.billing.corrections.create.
- `POST /facturation/corrections/{payment}` : owner.billing.corrections.store ; limite propriétaire de trois requêtes/minute, sans limiter les administrateurs.
- `GET /admin/billing/payments/correction-requests` : central.payments.corrections.index.
- `POST /admin/billing/payments/correction-requests/{report}/review` : central.payments.corrections.review.

Les UUID sont publics ; les ID restent internes. Routes centrales protégées, Policy/Form Request/services existants, menu « Ma facturation » et boîte administrative des signalements. Aucun remplacement intégral des anciens fichiers effectué par cette revue ; les autres modifications présentes sont conservées.

## Corrections de cette revue

1. Vue déplacée de `resources/views/central/pages/owner/overview.blade.php` vers `resources/views/central/pages/owner/billing/overview.blade.php`, emplacement demandé par le Controller. L’ancien chemin provoquait une vue introuvable.
2. Test déplacé de `tests/Feature/Owner/OwnerBillingOverviewTest.php` vers `tests/Central/Feature/Owner/OwnerBillingOverviewTest.php`. Il bénéficie désormais du cadre central ; son chemin existant vers Support/ManualBillingFixtures.php est ainsi correct.
3. Validation UUID/motif partagée entre le service et le Form Request ; normalisation du UUID et du motif avant déduplication. Le service refuse aussi les appels directs invalides.
4. Lecture verrouillée du signalement antérieur dans la transaction, avec verrou du paiement : évite de dédupliquer sur un ancien snapshot MySQL après une attente concurrente. Aucun statut, montant ou date financière modifié.
5. Décision administrative append-only dans activity_log, verrouillée sur le paiement et liée au UUID du signalement ; tout rejeu est refusé.
6. Boîte admin et aperçu commerçant affichent l'état de décision ; le choix « Corrigé » est possible uniquement après émission d'un avoir de la facture du paiement.
7. Inclusion de `routes/central/admin/billing.php` dans le groupe central admin ; auparavant les routes de facturation présentes dans ce fichier n'étaient pas chargées.
8. Tests C-162 renforcés pour les liens signés expirés, les factures/avoirs non émis et les accès par un autre commerçant.

Composants : OwnerBillingOverviewService, OwnerPaymentCorrectionService ; Controllers OwnerBillingOverviewController, OwnerPaymentCorrectionController, OwnerPaymentCorrectionReviewController ; ReportOwnPaymentCorrectionRequest ; vues overview/corrections ; routes centrales, sidebar et fiche de paiement existantes.

## Vérifications effectuées

- `vendor/bin/pint --dirty --format agent` : réussi.
- Syntaxe PHP : réussie sur les services, contrôleurs et tests modifiés.
- `route:list` : les routes d’aperçu commerçant, de boîte admin et de décision POST sont chargées ; le lien admin requiert `saas.payments.reconcile`.
- Tests ciblés C-159/C-162 : **9 réussis, 106 assertions** (`OwnerBillingOverviewTest`, téléchargement de facture, workflow privé d’avoir).
- Vérifications de cette intervention : `SaasDocumentDeliveryManagementTest` **14 réussis, 87 assertions** ; `OwnerBillingOverviewTest` **12 réussis, 106 assertions** ; `SaasCreditNoteManagementTest` **42 réussis, 218 assertions, 1 ignoré**. Les trois scénarios de concurrence T20-10 (« reservations », « same-operation », « issue ») passent ; leurs processus enfants retournent les valeurs attendues (`conflict`/`ok`). Les trois sauts de ligne supplémentaires rapportés auparavant ne sont pas reproduits et leur cause n’est pas établie ; aucun test n’a été modifié pour forcer le résultat.
- `git diff --check` : réussi. Les tests utilisent des mails simulés et des bases MySQL temporaires isolées ; ils n’envoient aucun courriel SMTP. Le worker local `saas-documents` a ensuite été démarré à la demande ; le mailer chargé est SMTP et le worker peut transmettre les documents réellement en attente. Aucun déploiement ni changement de base réelle effectué.

La validation HTTP complète manuelle de C-157 et l'affichage mobile restent à effectuer. La suite complète et PHPStan ne sont pas réévalués ici. Aucune fusion vers `dev` effectuée ni validation exhaustive revendiquée.

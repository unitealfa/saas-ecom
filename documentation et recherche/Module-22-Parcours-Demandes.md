# Demandes d'abonnement, factures avant paiement et exclusion des remboursements

Mission : `module22etfixunpeutlesautre`, lue intégralement (1 620 lignes). Branche initiale `module22levrais`, HEAD `a47eba3`, aucun changement de code présent au départ. Tests MySQL locaux temporaires isolés autorisés explicitement pour cette mission. Base réelle en lecture seule ; aucune autorisation de migration ou seeder réel.

## Décisions actuelles

La demande existe avant le paiement. Une facture réelle est disponible dès que sa règle et ses preuves fiscales sont valides ; elle n'active aucun droit. Deux choix commerciaux : espèces au bureau ou versement externe avec justificatif. Seule la réception intégrale confirmée active la période et permet le reçu. Demande acceptée, facture émise, paiement confirmé et reçu restent historiquement protégés. Aucun remboursement commercial, virement sortant ou avoir automatique.

Décision explicitement confirmée : le changement de prix/période après émission nécessite d’abord une correction documentaire manuelle complète par un administrateur autorisé, puis une nouvelle facture. Aucune facture ISSUED n’est annulée ou réécrite ; aucun avoir automatique. Le même contrôle protège l’annulation commerciale d’une demande déjà facturée.

## Architecture retenue

Une table centrale `subscription_requests` suit la version courante, le choix espèces/externe, l'état et la décision administrative. Les montants/offres/périodes restent portés par les abonnements réservés, les factures et le journal existants, sans deuxième moteur financier. Un lien nullable des abonnements vers leur demande conserve les versions antérieures et permet de refuser tout paiement d'une facture remplacée. La demande pointe sa période courante ; Activity Log conserve les anciennes références et décisions. Les données historiques non liées à une demande restent compatibles.

Les Controllers/Requests/Models/Services suivent leurs dossiers Central et le module Subscriptions ; les systèmes Billing existants conservent leurs rôles. Une autorisation propriétaire spécifique, limitée à la génération contrôlée de la facture de sa demande, ne donne aucun droit d'administration SaaS. Les routes administratives restent sous leurs permissions explicites.

## Plan de réalisation

- [ ] Ajouter la demande et ses liens SQL : UUID, propriétaire, version, états, décision, clés uniques et protections ; tester les invariants et l'isolation.
- [ ] Créer/réviser/annuler/soumettre une demande avec les services d'abonnement existants, conserver les anciennes pièces et refuser les formulaires obsolètes.
- [ ] Réutiliser préparation, numérotation et émission pour la facture avant paiement ; gérer les données fiscales/configurations manquantes sans fausse facture ni faux droit.
- [ ] Réutiliser la vérification des paiements et rendre atomiques demande acceptée/paiement/droits/audit, avec reçu privé repris séparément après une panne.
- [ ] Raccorder les interfaces commerçant et Administration → Abonnements → Demandes, les preuves, refus/resoumission, impression et permissions existantes.
- [ ] Vérifier les exclusions commerciales des Modules 20/22, les historiques, la concurrence et les régressions 18/19/20/21.
- [ ] Figer le code avant les tests groupés, puis compléter la matrice T01–T50 et les résultats réels. Aucun test en cours ne sera faussé par une modification de ses classes.

## État des vérifications

Code implémenté. Vérifications arrêtées à la demande de l’utilisateur pour limiter la consommation. Aucun traitement financier ni migration sur la base réelle.

- Suite groupée Billing/Subscriptions/Plans : 289 scénarios, 280 réussis, 2 erreurs préexistantes d’import dans PlanReadingTest, 7 scénarios interactifs ignorés ; 1 557 assertions. Imports déplacés avant les tests, sans changer les assertions.
- Dernière reprise ciblée SubscriptionRequestWorkflowTest + PlanReadingTest : 32 scénarios, 30 réussis, 2 interactifs ignorés, 198 assertions. Les deux erreurs d’import sont réparées ; pas de nouvelle exécution de toute la suite après ces derniers changements.
- PHPStan : aucun diagnostic sur les fichiers applicatifs modifiés, puis contrôle ciblé réussi après les dernières corrections. Pint exécuté ; build frontend et cache de routes isolé réussis ; diff sans erreur d’espacement.
- Navigateurs isolés : parcours espèces réussi (6 assertions) ; parcours externe avec dépôt, refus motivé, resoumission, acceptation et PDF du reçu réussi (6 assertions). Le sélecteur OS a refusé le chemin local : le PDF fictif a été généré dans le navigateur, sans modifier les restrictions.
- Dernier essai supplémentaire d’impression : facture ouverte dans le lecteur PDF du navigateur, puis expiration du délai du pilote interactif avant son signal de fin. Ce dernier test est échoué par timeout ; aucune impression complète ni sortie papier validée. Ne pas le compter comme réussi.

Verdict de la mission complète : PARTIEL / NON VALIDÉ. Le code et les parcours principaux sont vérifiés ; la matrice exhaustive T01–T50 et la vérification complète de l’impression restent à terminer. Aucun commit, push ou déploiement réel effectué.

## Comportement raccordé

- Commerçant : `owner.subscriptions.store` crée la demande ; `owner.subscription-requests.index/show` montrent facture, état, motif, dépôt, choix, correction et annulation protégées. Les anciens abonnements restent consultables.
- Administration : `central.subscription-requests.*`, sous les middleware centraux existants. Lecture par les droits de consultation existants ; acceptation par `saas.payments.validate`, refus par `saas.payments.reject`. Aucun droit ajouté ni attribution modifiée.
- `SubscriptionAssignmentService` conserve son retour Subscription, mais crée désormais la demande de la période administrative. Un opérateur sans droit d’émission ne reçoit pas implicitement ce droit : la facture attend son traitement autorisé ou son propriétaire.
- Facture : préparation/réservation/émission communes, autorisations dédiées limitées à la demande ; aucun droit administratif accordé au commerçant. Règle active unique, éventuellement sélectionnée par `BILLING_REQUEST_RULE_CODE` ; aucune règle financière peuplée automatiquement.
- Versement : photo/PDF validé avant remplacement transactionnel ; anciennes déclarations et pièces conservées. Versions de demande et de paiement contrôlées séparément. Un montant erroné déclaré sans fonds signalés peut être refusé puis corrigé ; l’anomalie initiale reste conservée. Une réception de fonds signalée bloque les corrections commerciales, même avant vérification finale.
- Acceptation : paiement VERIFIED, échéance et abonnement, demande ACCEPTED et audit dans la transaction financière existante. Reçu hors transaction longue ; une panne de reçu après confirmation ne réencaisse rien et propose sa reprise via le téléchargement autorisé.
- Présentation des offres : cache fichier central existant étendu aux fonctionnalités/quotas, invalidé après commit sur offres, compositions et fonctionnalités ; aucun cache de prix décisionnel, droit ou état financier.

## Migration et limites de déploiement

La seule nouvelle migration est `database/migrations/central/2026_11_07_081723_create_subscription_requests_table.php`. Elle ajoute la demande et ses liens sans convertir les abonnements historiques ni réécrire les migrations déployées. L’application réelle exige cette migration avant utilisation de ces pages ; son application réelle n’est pas autorisée pour cette mission. Aucun seeder, compte, facture, paiement ou permission réel n’est créé.

## Point de reprise

Reprise ciblée demandée avec vérification minimale : les liens de facture et reçu ouvrent maintenant les PDF privés dans le lecteur du navigateur (`inline=1` signé), avec impression native disponible. Le téléchargement habituel reste inchangé ; les justificatifs fournis par les utilisateurs restent téléchargés, jamais affichés inline. Test ciblé réellement exécuté : 1 réussi, 21 assertions, 26 secondes ; contrôle des octets, des en-têtes, des signatures, des accès propriétaire/administrateur et du refus d’un autre compte. Pint et diff contrôlés, graphe actualisé. Pas de nouvelle suite longue ni validation d’impression papier. Aucune modification de la base réelle ; la migration reste à autoriser/appliquer.

Code : Controllers et Requests Central/Subscriptions, SubscriptionRequestService/PaymentState/Policy/Model, enums, migration, vues requests, raccords achat administratif/propriétaire, facturation existante et cache des offres. Deux corrections préexistantes : fermeture du tableau PAYMENT_PERMISSIONS de CentralRoleCatalog et ordre des imports de PlanReadingTest. Restent : matrice détaillée des 50 scénarios, impression complète, revue finale de déploiement et autorisation distincte pour appliquer la migration centrale réelle. Aucune nouvelle fonctionnalité à développer avant de reprendre ces contrôles.

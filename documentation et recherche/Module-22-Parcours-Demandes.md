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

## Complément demandes, statuts et documents — 15 novembre 2026

- Les listes administratives conservent toutes les demandes, avec référence UUID, commerçant, offre, montant, méthode, date, statut et consultation. `RequestFilterEnum` définit Toutes / En cours / Acceptées / Refusées / Annulées. Le statut technique INVOICE_PENDING est conservé ; son libellé utilisateur reste « En attente de paiement », avec un avertissement distinct tant que la facture n'est pas émise. La pagination et la recherche ne suppriment aucun historique.
- Le détail distingue demande, paiement, abonnement et document. Il affiche l'offre active actuelle, la période demandée, l'auteur/date de décision, le motif courant, les motifs audités et les liens privés vers justificatif, facture et reçu. Refuser exige toujours un motif et les empreintes courantes sous verrou ; corriger ne supprime ni anciennes preuves ni anciens événements.
- Factures : filtres avant pagination Toutes / Payées / Non payées / Corrigées par avoir / Brouillons annulés / En préparation. Les lectures du journal de paiements et des avoirs sont effectuées en SQL sans charger toutes les factures en mémoire ; une dette entièrement corrigée n'est pas présentée comme payée. Aucun état financier n'est mis en cache. Les préparations et demandes sans facture définitive restent accessibles séparément.
- Les envois sont intégrés dans les détails des factures et avoirs, via le fragment partagé billing/document-delivery. Le menu, les deux pages indépendantes, les routes GET index/show, leurs noms et leur recherche inutilisée sont supprimés : les anciennes URL répondent 404, sans redirection de compatibilité. Les seules routes d'envoi restantes sont les POST send/retry/resend utilisés depuis les documents. Les permissions Spatie existantes restent conservées. Le renvoi explicite exige send, un état attendu et un résultat précédent SENT/DELIVERED ; la relance après échec garde retry. Aucun renvoi d'un résultat UNCERTAIN, aucune réinitialisation du compteur ni suppression des tentatives ; budget existant de trois tentatives conservé.
- `SubscriptionWorkflowNotifier` programme après commit les notifications de réception, facture prête, preuve en vérification, acceptation/paiement intégral, refus motivé, reçu prêt et activation effective d'une période programmée. Les administrateurs avertis des nouvelles demandes/preuves doivent disposer du droit de lecture des demandes. Une preuve n'est annoncée qu'après son stockage et son rattachement réussis ; un reçu seulement après sa création. Rejouer la création ou télécharger un reçu existant ne renvoie pas ces notifications.
- Les notifications natives Laravel sont chiffrées dans la file `database` / `saas-documents`, déjà utilisée par le worker de documents. Compte actif, adresse vérifiée et droits de lecture administratifs sont contrôlés de nouveau à l'envoi. Une panne de notification est journalisée sans annuler une décision financière déjà validée. Aucun e-mail de suivi ne prétend qu'un document a été remis ou lu. Les PDF émis restent immuables, privés et accessibles à leur propriétaire pour ses anciennes périodes.
- Aucune migration, nouvelle permission, donnée financière de seeder ou écriture réelle n'est nécessaire pour ce complément. SMTP et worker sont nécessaires pour les notifications réelles ; les tests utilisent exclusivement des bases temporaires, fichiers et transports simulés.

### Vérifications de ce complément

- `SubscriptionRequestWorkflowTest.php`, `SaasDocumentDeliveryManagementTest.php`, `SubscriptionRequestAdministrationTest.php` : 45 scénarios, 43 réussis, 356 assertions, 2 scénarios navigateur ignorés (pilote interactif non activé).
- Après ajout des contrôles de rollback et activation programmée : `SubscriptionRequestAdministrationTest.php` et `SaasInvoiceIssuanceTest.php` : 40 scénarios, 39 réussis, 265 assertions, 1 scénario navigateur ignoré. Ces deux passes se recoupent ; ne pas additionner leurs scénarios comme des tests distincts.
- Après alignement du filtre « En attente de paiement » avec l'état technique de préparation : scénario ciblé des justificatifs et annulations rejoué avec succès, 11 assertions.
- PHPStan ciblé sur les services, notifications, Controllers, Requests, Policies, modèles et enums modifiés : zéro erreur. Pint, diff et routes contrôlés ; graphe actualisé. Les erreurs initiales (mauvais constructeur du double de reçu, ancien statut HTTP des pages d'envoi et alias de modèle dans les assertions de notification) ont été corrigées puis les scénarios rejoués.
- Pas de validation navigateur interactive, SMTP distant, remise réelle ni impression papier. Aucun worker réel redémarré et aucune migration ou donnée réelle modifiée.
- Nettoyage final des anciens écrans : GET supprimés (404), recherche et Request inutilisées retirées, fragment d'envoi déplacé vers billing/document-delivery et liens supprimés. Trois tests ciblés rejoués : réussis, 27 assertions ; PHPStan ciblé et Pint passent. La suppression physique du dossier désormais vide billing/deliveries a été bloquée par la protection automatique ; aucun fichier d'écran n'y subsiste.


## Décision courante — demande.txt, 15 novembre 2026

Cette décision remplace les mentions antérieures de facture définitive avant paiement. La demande conserve une préparation interne DRAFT, sans numéro ni PDF. Le versement externe exige sa preuve dès la création ; les données déclarées sont chiffrées dans submitted_payment et les fichiers privés restent dans media, y compris si la configuration fiscale bloque la préparation.

La réception intégrale des fonds est une étape durable et idempotente : le paiement VERIFIED ne doit pas être effacé si la génération documentaire échoue. L’émission produit ensuite le PDF définitif, puis le reçu ; acceptation de la demande, activation des droits et audit sont finalisés atomiquement. Les reprises ne collectent pas un second paiement. Le contrôle automatique des périodes programmées exige une demande acceptée ; un reçu manquant ne doit pas ouvrir les droits.

L’attribution administrative exige attribution (ou changement sur l’abonnement ciblé) et validation d’encaissement. Espèces : constat réel ; versement externe : preuve et compte/référence réellement contrôlés. Remplacement immédiat ou différé au tarif intégral, sans prorata ni remboursement automatique. Les droits précédents restent en place avant finalisation ; une relation centrale de remplacement conserve l’abonnement d’origine et son propriétaire.

Un refus sans fonds signalés ni facture émise annule uniquement la réservation et son échéance. La demande, le motif, la preuve et les événements restent consultables ; une nouvelle demande devient possible. Les anciennes factures émises et les anomalies financières exigent toujours leur traitement documentaire ou administratif explicite.

Interfaces : Factures regroupe échéances, paiements, preuves, rapprochement, reçus et signalements. Le commerçant utilise le tableau existant owner/billing/overview. Les anciennes pages de règles versionnées, compteurs et facture manuelle sont supprimées (404). Les anciennes lectures de paiements restent des redirections autorisées vers leurs dossiers, sans écran autonome. Les envois restent dans le document. Le formulaire de paramètres utilise les droits existants update + activate ; il conserve les versions et validations internes, sans les exposer comme étapes manuelles. La première configuration est explicite, jamais peuplée par un seeder financier.

Notifications : canal database Laravel central en plus du mail, liste privée et indicateur non lu, marquage individuel idempotent. Les droits du destinataire sont revérifiés ; aucun accès aux notifications d’un autre compte, même avec le rôle root.

Trois migrations additives sont préparées : notifications natives, submitted_payment chiffré et choix de transition/référence de remplacement. Elles ne sont pas appliquées à la base réelle. Le catalogue ne recrée plus les trois anciens droits de création/versionnement des règles et lecture des compteurs ; les anciennes lignes et attributions réelles ne sont pas supprimées automatiquement.

Les références actives du modèle Examples/Test, de SchemaBlueprint, des diagnostics locaux et des API de plans ont été conservées. Le seul index généré graphify-out/cache/stat-index.json est retiré du suivi Git, sans supprimer le fichier local.

Validation en cours : contrôles ciblés et suites MySQL locales temporaires autorisés ; aucune écriture sur les bases réelles, aucun SMTP réel, aucune dépendance installée. Les résultats des sections précédentes concernent l’ancienne version et ne constituent pas la validation de cette refonte.


### Finalisation des raccordements — 15 novembre 2026

Autorisation d’accepter contrôlée sous verrou avant l’activation ; la projection finale contrôle le paiement confirmé, la facture émise, l’échéance réglée et le reçu existant. La lecture du statut de paiement utilise le droit de consultation du dossier, sans étendre les droits sur les données fiscales ou les PDF. Le test de reprise initialise désormais son offre gratuite explicitement.

Tests arrêtés à la demande de l’utilisateur : dernier résultat disponible, 269 réussis, 12 échecs, 7 erreurs et 7 ignorés sur 295 tests. Ces derniers raccordements ne sont pas retestés ; validation fonctionnelle non acquise. Aucune migration ni écriture appliquée à la base réelle.

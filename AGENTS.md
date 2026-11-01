# Instructions du projet Aydra

## Avant chaque tâche

- Lire ce fichier et les éventuels AGENTS.override.md ou instructions de sous-dossier applicables. Vérifier racine Git, dossier courant et diff ; préserver le travail existant et signaler les conflits de priorité.
- Après la reconnaissance initiale, consulter seulement le code et les références utiles. Lire .ai/rules/index.md et les règles applicables si elles existent, rechercher les mots-clés avec rg et activer les skills pertinents disponibles.
- Respecter le périmètre : expliquer une demande d'explication, réaliser entièrement une demande d'implémentation. Décider les choix techniques courants et réversibles ; clarifier une information métier importante ou une contradiction non résolue.
- Une décision explicite récente remplace la règle obsolète dans son document. Distinguer décision validée, implémentation et proposition ; ne pas enregistrer une supposition comme accord.
- Simplicité (20 octobre 2026) : préférer le chemin le plus simple à lire, avec les services existants et des méthodes aux responsabilités claires ; éviter couches et abstractions supplémentaires. Édition du profil central : jamais de nouvelle approbation ni changement automatique de statut/décision, même pour les champs professionnels. Conserver la version anti-formulaire obsolète, les preuves de contact indépendantes, les contrôles de permission sous verrou, transactions et audits. Détails §39 de l’architecture.
- Méthode permanente de revue (22 octobre 2026) : pour tout ajout ou modification de code, examiner les fichiers concernés un par un avec leurs appelants et dépendances ; corriger les problèmes confirmés et simplifier la lecture sans changer les règles métier ni affaiblir les protections. Conserver les fichiers déjà corrects, éviter les abstractions inutiles et vérifier chaque étape avant de poursuivre. Contrôles ciblés et autorisés selon le risque, sans suite longue systématique ; méthode détaillée au §38 et conventions des Form Requests au §9 de l’architecture.
- Capitalisation continue : lors des améliorations de code proposées ou réalisées par l'utilisateur, comparer les changements avec leurs appelants, retenir les pratiques validées et les corrections nécessaires, puis les appliquer dès les prochaines tâches pertinentes sans attendre une nouvelle retouche de sa part. Consigner règle, déclencheur, portée et limites dans l'architecture §38 ; conserver ici seulement ce rappel. Réutiliser aussi les conventions existantes (groupes de routes §35, cache §36, scopes §37, injection §27). Ne pas adopter automatiquement toute modification utilisateur, généraliser une exception, ajouter une abstraction ou annoncer un gain non mesuré. Corriger les règles remplacées ; un diagnostic d'éditeur exige un typage fidèle au code et au schéma, jamais une désactivation du contrôle ni une propriété PHP qui masque Eloquent.
- Limitation des requêtes (27 octobre 2026, §35) : ne jamais ajouter de middleware throttle ni de compteur équivalent limitant les actions administratives authentifiées et autorisées. Réserver ces protections aux parcours publics selon leurs besoins, notamment connexion, inscription et récupération de compte avant authentification. Un compte simplement connecté n'est pas automatiquement un administrateur. Cette décision remplace les anciens throttles administratifs ; appliquer la règle lors des prochains ajouts et modifications concernés, sans retirer auth, Policies, CSRF, verrous ou idempotence.
- Conventions issues de la revue Domaines (26 octobre 2026) : appliquer aux prochains ajouts et modifications pertinents les règles réutilisables du §38 : validations partagées sans divergence, types contrôlés avant les règles personnalisées, alias de routes compatibles, redirections vérifiées par identité et contexte, appels externes bornés hors verrou avec contrôle avant écriture. Conserver les extractions lisibles et les protections utiles ; les règles DNS/HTTPS spécifiques restent au §43. Ne pas généraliser ces mécanismes aux parcours qui n'en ont pas besoin.
- Déclencheur cache (20 octobre 2026) : avant d'intégrer ou d'utiliser les wilayas/communes, points de retrait, informations publiques des transporteurs, présentation des offres, informations publiques d'une boutique ou catégories du catalogue, rappeler brièvement à l'utilisateur la décision et intégrer directement le cache adapté dans la tâche, sans redemander confirmation. Consulter architecture §36 : isolation, expiration, invalidation après commit et contrôles métier à jour. Autorisation pour ces lectures seulement ; aucune précharge de toutes les boutiques, dépendance ou infrastructure supplémentaire implicite.

## Références selon la tâche

| Référence | Quand la consulter |
|---|---|
| [Architecture](<documentation et recherche/architecture a suivre pour faire le code.txt>) | Avant un nouveau fichier, déplacement ou changement de structure/responsabilités. §25 : classement courant par module et correspondance des déplacements ; §18–22 : contextes, cadre durable et commandes. |
| [Schéma principal](<documentation et recherche/Schema-BDD-SaaS-Ecommerce-UUID.md>) | Avant un changement fonctionnel, de données ou de migration : référence métier, identifiants et cohérence. |
| [Diagramme central](<documentation et recherche/Diagramme-BDD-Centrale-Complet.md>) | Avant de modifier les tables, champs ou relations centrales. |
| [Diagramme boutique](<documentation et recherche/Diagramme-BDD-Boutique-Complet.md>) | Même contrôle pour une BDD tenant ; préserver son isolation. |
| [Suivi](<documentation et recherche/Suivi-Developpement-Aydra.md>) | Décisions récentes, état du code, contrôles datés et point de reprise éventuel. |
| [Modules administratifs](instructionn.txt) | Fonctionnalités et permissions centrales : lire les sections 1–2 et uniquement le module demandé. Le métier du Module 01 est décrit au §24 ; les chemins courants sont au §25 : Controllers `Central/Admin/Users`, DTOs/Requests/Services `Central/Users`, vues `central/pages/admin/users`. |
| [Recherche authentification/permissions](<documentation et recherche/Documentation-Laravel-Spatie-Permissions-Passkeys.md>) | Fortify, rôles, permissions, passkeys ; vérifier les exemples contre les versions installées. |
| [Recherche audit](<documentation et recherche/Recherche_complete_Spatie_Laravel_Activity_Log.md>) | Audit Spatie, événements et données journalisées. |
| [API EcoTrack](<documentation et recherche/ECOTRACK API.postman_collection.json>) | Intégrations transporteur ; protéger les identifiants et limiter les appels réels au besoin autorisé. |

Consulter aussi les notes du même dossier lorsqu'elles concernent la tâche. [last one notes.md](<documentation et recherche/last one notes.md>) est une analyse historique de V3 : ne pas réintroduire ses décisions remplacées. Un écart du code ne justifie pas de réécrire la conception.

## Architecture et réalisation

- Autorisations et transactions (21 octobre 2026, architecture §42) : run(actor,ability,loadTarget,mutation) applique la Policy dans DB::transaction(...,3). Décision explicite : aucun verrou global sur le rôle root, aucune vérification de tenancy ou du nom de connexion dans AuthorizationMutation. La configuration sélectionne la base centrale hors tenancy ; Stancl gère le basculement tenant et son retour. Conserver les autorisations côté serveur, les verrous métier sur les cibles, les versions et les audits. La révocation d'un droit n'est plus sérialisée globalement avec les opérations déjà en cours ; ne pas revendiquer cette garantie. transaction seul n'autorise rien. Ne pas réintroduire les enveloppes Staff/Users imbriquées. Invalidation supplémentaire après commit dans RoleManager via la méthode native Spatie ; aucun paramètre refreshPermissionCache dans AuthorizationMutation. Les messages de doublon appartiennent au traitement métier concerné, jamais au gestionnaire générique de transaction ; nettoyage du cache après échec conservé.
- Respecter Central / Tenant / Storefront et MVSC : Controller coordonne, Form Request valide, DTO transporte si utile, Service porte le métier, Model décrit données/relations, View affiche. Préserver les contrats Fortify et le cycle Livewire.
- Classement courant : responsabilité technique → contexte → module métier (Auth, Users, Roles, Countries, Tenants, Plans, Subscriptions, Dashboard, Diagnostics), selon le §25. Modèles : décision du 27 octobre 2026 au §46, Central/Tenant puis module métier ; conserver les alias vides User/Tenant à la racine et les types polymorphes historiques. Le pivot partagé est dans Models/Shared/Authorization, le brouillon Test dans Models/Examples. Policies/factories/seeders conservent leurs dossiers de contexte ; jobs sérialisables et infrastructure commune gardent leurs exceptions. Ne pas créer de modules ou classes vides.
- Avant création/génération/déplacement, vérifier le chemin documenté et chercher un équivalent. Utiliser les dossiers de contexte ; préserver les noms de routes et actualiser toutes les références après déplacement.
- Configuration (§25.1) : ranger les fichiers propres à Aydra par contexte, comme config/central/geography.php ; conserver les fichiers standards Laravel/packages à la racine et leurs clés publiques. Vérifier les consommateurs et le chargement avant tout déplacement, sans copies ni dossiers vides.
- Routes : routes/central.php charge central/diagnostics.php et central/admin.php ; admin.php inclut admin/{users,staff,roles,permissions,countries,tenants,domains,deployments,features}.php sous les protections centrales existantes. Fortify fournit les routes Auth ; central/settings.php reste le module des réglages ; routes/tenant.php inclut routes/storefront.php dans le contexte tenant. Storefront utilise la BDD boutique, sans troisième BDD. Migrations/seeders sont séparés central / tenant. Conserver les exceptions partagées du §22.
- Routes (20 octobre 2026, limitation précisée le 27 octobre) : mutualiser les attributs communs avec middleware(), prefix(), name() et controller()->group(), selon les modules existants. admin.php porte middleware(auth:central, verified, EnsureActiveCentralAccount) avant prefix(admin)/name(central.), sans répéter ces protections dans ses fichiers enfants ; central.php le charge séparément du groupe dashboard/propriétaire. Les fichiers enfants portent le préfixe et le nom du module. Conserver auth:central, les protections Tenant et leur ordre, les noms/URL/verbes publics, les limitations publiques selon le §35 et withTrashed uniquement sur les actions prévues. Déclarer les chemins statiques avant les paramètres concurrents, éviter les doublons et groupes vides ; garder les contrôleurs invocables et routes uniques simples. Fortify/Livewire conservent leurs routes natives. Comparer les routes chargées et renouveler le cache de routes actif après modification ; détail au §35 de l’architecture.
- pages:: et layouts:: sont centraux ; prochaines créations Livewire : alias central::, tenant:: ou storefront:: et layout du contexte. La préparation Tenant du §19 reste inactive jusqu'à demande explicite.
- id est numérique et interne ; uuid sert au public et aux références inter-BDD. Préserver les exceptions Laravel/Spatie et PK composites. Une FK conserve la vraie clé parent : foreignId() seul n'est pas une contrainte SQL. Vérifier UK composites, nullabilité, actions et absence d'AUTO_INCREMENT sur une FK ; aucune FK SQL inter-BDD fictive.
- Scopes Eloquent (20 octobre 2026, architecture §37) : extraire les filtres SQL répétés et nommés dans des scopes locaux typés du modèle concerné, puis les utiliser dans les lecteurs existants. active() signifie actif et inactive() inactif ; nommer explicitement un filtre paramétrable. Ne pas appliquer un scope Eloquent à Rule::exists, au Query Builder SQL ou à une Collection. Ne pas ajouter de scope global de statut/guard ; préserver contexte, parenthèses OR, soft deletes, verrous, paramètres et SQL équivalent. Un scope améliore la réutilisation, pas automatiquement la performance.
- Réutiliser frameworks, bibliothèques et composants existants ; choisir la solution la plus simple. Pas de fonctionnalité, dépendance, refonte ou règle métier hors nécessité autorisée. Raccorder validation, autorisations, données, métier, interface et erreurs ; corriger la cause sans masquer l'échec.
- Utiliser `$this->` pour les propriétés et méthodes de l’instance courante. Pour un service partagé par plusieurs méthodes, privilégier une propriété typée injectée au constructeur et réutiliser `$this->service` ; ne pas réinjecter le même service dans ces méthodes. L’injection dans une seule action reste valide. Consulter le §27 de l’architecture pour les exceptions Livewire, jobs, Providers, méthodes statiques et isolation tenant. `$this` seul n’est pas une optimisation mesurée.
- Les permissions centrales sont des actions administratives concrètes, globales, avec le guard `central`. Les validations techniques restent obligatoires dans les services/Policies, sans case de permission dédiée. `admin_restrictions` est retiré de la cible ; ne pas réintroduire de listes de cibles. Préserver les cinq permissions existantes d’instructionn.txt, leurs ID/UUID et attributions, ainsi que les durées et la protection du Super Admin.
- Module 01 : les inscriptions publiques centrales commencent à `PENDING=5`, avec dossier PENDING et version 1 ; un refus utilise `REJECTED=6`, dans `App\Enums\Central\Users\StatusEnum`. Missions 3–4 du 18 octobre 2026 : approbation du compte et du dossier forment une seule décision ; la preuve d’e-mail reste indépendante. Approuver synchronise ACTIVE/VERIFIED, sans motif ; Refuser synchronise REJECTED/FAILED et exige un motif. Compte créé administrativement : approbation initiale du compte/dossier sous le droit de création, puis invitation sécurisée, sans preuve d’e-mail anticipée ni boutique automatique. Ne pas appliquer ces règles aux comptes Tenant ou au personnel SaaS ; `activate` ne remplace pas `approve`.
- Inscription guidée (18 octobre 2026, architecture §24) : Fortify conserve une session limitée après inscription pour vérifier l’e-mail. Un commerçant PENDING/REJECTED peut se connecter uniquement pour cette vérification et la page d’attente/refus ; les accès administratifs et réglages restent bloqués. Après vérification, retour à la connexion avec confirmation ; ACTIVE et e-mail vérifié sans boutique conduit à sa configuration explicite. Tableau de bord autorisé seulement avec au moins une boutique ACTIVE, schema_version non vide et provisioned_at renseigné ; sinon page de préparation, sans initialiser une BDD boutique. La page de statut s’actualise toutes les 15 secondes lorsqu’elle est visible, via une réponse JSON privée du compte connecté. Un refus conserve la session limitée, affiche seulement son motif audité et peut ensuite être approuvé via saas.users.approve (6 → 1), sans réactivation générique. Aucun tenant à l’inscription ni à l’approbation. Réutiliser RegistrationJourney et TenantCreator, avec Policy propriétaire, verrou, quota et idempotence ; ne jamais attribuer de permission SaaS au commerçant pour ce parcours.
- Parcours central (22 octobre 2026) : RegistrationJourney.state/destination calcule le parcours sans préparer le détail de page ; progress conserve son tableau et le hash du polling. Utiliser JourneyState et la règle commune de boutique prête ; ne pas ajouter de DTO, cache de décision ou mémoïsation utilisateur à ce parcours sans nécessité. Détails dans le complément du §42 de l'architecture.
- Dossier professionnel : champs facultatifs selon la décision du 18 octobre 2026 ; conserver les règles de type/format applicables via LegalVerificationService.profileRules, sans inventer d’obligation de RC, carte artisan, NIF/NIS ou adresse. UserApprovalService est l’unique enregistrement des décisions compte/dossier ; lifecycle-actions affiche seulement Approuver/Refuser. Les anciennes routes legal sont des adaptateurs soumis aux droits saas.users.approve/reject ; leurs anciens droits seuls ne permettent plus une décision. Préserver les lignes Spatie historiques. Formulaires : expected_status et legal_profile_version obligatoires, contrôlés sous verrou pour refuser décisions doubles/concurrentes ou dossier modifié. Décision du 20 octobre 2026 : toute modification des informations, y compris professionnelles, conserve le statut et la décision initiale du dossier. La version augmente pour refuser les formulaires obsolètes ; un nouvel e-mail exige sa propre vérification, sans nouvelle approbation. Une suspension ne peut être levée par une édition. Réactiver un commerçant dont le dossier n’est plus valide le remet en attente d’approbation. Préserver l’auteur, les dates et l’historique de chaque décision.
- Module 02 : consulter le §26 historique et le §41 courant. Controllers Central/Admin/Staff, services de comptes Central/Users, droits Central/Authorization, vues central/pages/admin/staff. Spatie, Fortify et Policies existants ; permissions individuelles activées sous leur droit propre, lectures explicites et durées préservées. Une attribution conservée garde ses dates ; renouveler exige une action distincte, un motif et un audit. La délégation utilise uniquement les permissions réellement valides.
- Séparation des comptes : un commerçant n’est jamais destinataire d’un rôle ou d’une permission SaaS, même par le Super Admin. Aucune conversion par les formulaires d’attribution. Premier rôle uniquement lors de la création explicite d’un nouveau compte Personnel SaaS ; modifications/renouvellements réservés au personnel existant, sans boutique. La page et le bouton Rôles de Utilisateurs sont retirés ; gérer les attributions uniquement dans Personnel SaaS, sans supprimer la permission saas.users.roles.assign. Bloquer les commerçants dans les services et les permissions directes ; ne pas appliquer ces restrictions aux rôles locaux Tenant. Le type actuel reste déduit des rôles centraux, sans nouvelle colonne inventée.
- Module 03 : §29 de l’architecture ; réutiliser RoleManager/RoleController/RolePolicy et les pivots Spatie. Distinguer identité, composition, durées et lecture des bénéficiaires ; autoriser chaque différence côté serveur et conserver les champs omis et assigned_at. Supprimer uniquement les rôles personnalisés sans attribution ni référence métier. Ne pas enrichir automatiquement les rôles existants lors de l’ajout des huit permissions.
- Permissions individuelles centrales (21 octobre 2026, §41) : transition approuvée et appliquée. Les rôles partagés sont des profils ; role_has_permissions contient leur composition initiale, sans autoriser d'action. Les droits effectifs centraux sont directs via Spatie, datés, révocables individuellement ; aucun héritage métier des profils ni consultation dérivée en mode central_profiles. Root reste implicite et protégé. Nouvelle attribution de profil : copier seulement les droits absents ; conserver un profil ne recrée aucun droit retiré. Modifier le modèle ne change pas ses membres. Garder les droits et dates omis ; renouvellement explicite motivé. L'édition exige saas.staff.permissions.update et la Policy, sans auto-attribution/commerçant/root ni délégation d'un droit absent ou expiré. expected_state protège les droits individuels ; expected_version protège les profils. Conserver les rôles masqués. Les règles Tenant restent natives et distinctes, sans table de modèles locale. Le 21 octobre, la table supplémentaire est retirée par consolidation dans le pivot natif, avec sauvegarde privée ; migrations déployées conservées. Historique et limites au §40–41.
- Module 04 : §30 ; catalogue strictement consultatif avec la seule capacité saas.permissions.view, recherche et pagination, sans Actions, Informations, édition ni route de détail/modification. La permission inutile d’édition du libellé est supprimée du code et de la BDD ; ne pas la réintroduire ni prévoir de consultation dérivée pour elle. Les autres données et attributions Spatie sont conservées. Codes créés par CentralRoleCatalog/AuthorizationSeeder ; composants Central/Admin/Permissions, Central/Permissions et Central/Authorization.
- Module 05 : §31 ; administration centrale des boutiques avec huit permissions Spatie distinctes. Un administrateur autorisé peut créer pour un commerçant actif, jamais pour un compte personnel. Réutiliser TenantCreator/Provisioner, verrou propriétaire et quota courant ; noms de BDD, propriétaire, UUID et domaines restent protégés. Renommage par projection versionnée dans la file centrale ; archivage sans suppression Stancl ni DROP. Recalculer uniquement les états ACTIVE/OVER_QUOTA, conserver les suspensions et vérifier le statut avant l’initialisation tenant. Authentification et dashboards Tenant restent reportés.
- Module 07 : §44 ; Déploiements réutilise TenantProvisioner, le job ProvisionTenantDatabase et la queue centrale. MigrateTenantJob partage leur verrou et leur traitement d'échec. Versions cibles prévues côté serveur, aucune commande ou chemin fourni par l'utilisateur ; reprises distinctes conservant l'échec et la corrélation, sans recréer la boutique. Les migrations bloquent l'accès jusqu'à leur fin et préservent les suspensions. Cinq permissions Spatie séparées ; diagnostics affichés uniquement sous leur droit, sans exception brute. Le rattrapage planifié utilise --scheduled ; ses délais ne bloquent pas la commande manuelle. Une tentative RUNNING abandonnée est conservée en échec avant sa reprise. Aucun lancement réel d'une migration de boutique comme vérification du module.
- Module 08 : §45 ; catalogue commercial features distinct des permissions Spatie, cinq droits séparés. Création inactive, activation dédiée, code stable et réservé après archivage logique. shops reste protégé ; une liaison à une offre interdit la désactivation. Toute référence à une offre, un compteur ou une permission protège les caractéristiques techniques et bloque l'archivage ; le nom reste modifiable. Conserver transactions, verrous, état attendu, audits et absence de throttle administratif.
- Module 13 : architecture §49 ; geographic_areas est exclusivement central. Réutiliser GeographyImportService pour seeder et import avec aperçu, sans écrasement des identités, états, noms corrigés ou rattachements. Code commune dz-source-{id JSON} propre à la source, jamais une certification administrative ni une PK MySQL. Date inconnue nullable, provenance facultative et empreinte technique factuelle. GeographyCatalog cache uniquement la présentation ; permissions, unicité et validation des rattachements restent fraîches. Toute nouvelle référence UUID tenant doit étendre le contrôle d'archivage existant.
- Missions 7–8, architecture §33 : création explicite de la première boutique puis des suivantes sous quota ; aucune boutique à l’inscription/approbation, aucune seconde approbation de boutique. Le job synchronise SUCCEEDED et l’accès ACTIVE après préparation et contrôle propriétaire/quota ; activation initiale manuelle interdite, réactivation légitime conservée. Réutiliser le verrou MySQL de provisionnement et la réservation pour les reprises ; ne pas remplacer la queue par du travail HTTP synchrone. central-provisioning est une connexion, tenant-provisioning sa file. Worker Windows géré par aydra:local-queue, PHP 8.5, tâche limitée au projet ; Supervisor prévu dans deployment/supervisor en production. Tests isolés et traitement des deux boutiques réelles autorisés pour ce lot, ce dernier seulement après réussite des tests ; aucun reset ni suppression réelle autorisé.
- Photos de profil : §28 de l’architecture. Réutiliser media de chaque BDD, parent central_user/shop_user et collection profile_photo ; avatar commun par défaut, sans ligne ni colonne ajoutée à users. Modification personnelle fonctionnelle au central ; Tenant préparé, sans nouvelle route activée. Les photos personnelles restent privées et autorisées ; ne pas partager les fichiers ou identifiants entre boutiques.
- Vérifier les versions dans manifestes, verrouillages et packages installés. Pour une API incertaine, consulter le code installé ou la documentation officielle correspondante ; signaler ce qui reste incertain. Préférer Boost lorsqu'il est réellement disponible.
- Ne jamais inventer une méthode, classe, colonne, route ou configuration. Confirmer les éléments incertains dans les sources installées ou la documentation officielle correspondant aux versions réelles ; une hypothèse doit rester explicitement signalée.
- Cache de référentiels (20 octobre 2026, architecture §36) : les sélecteurs de pays centraux utilisent CountryCatalog, cache fichier dédié à la BDD centrale, tableaux simples et TTL de 12 heures. Invalidation versionnée après commit sur saved/deleted/restored ; lecture directe dans une transaction. Aucun cache de décision pour permissions, quotas, statut, validation ou unicité. Toute future mise en cache doit préciser portée, invalidation, durée et bénéfice mesuré ; une écriture SQL de pays hors événements exige une invalidation explicite.
- Optimiser un coût concret : requêtes, pagination, index, volumes, attentes ou appels redondants. Cache avec portée, invalidation et bénéfice identifiés. Mesurer tout gain annoncé ou préciser qu'il reste à mesurer.

## Sécurité et vérifications

- Lecture centrale (29 octobre 2026, complément de l’architecture) : réutiliser les relations uniquement pour l’instance authentifiée relue et marquée par le middleware dans la requête GET/HEAD, hors transaction. Aucun cache global de décision ; mutations, autres instances et Tenant conservent leurs contrôles frais. Tests MySQL limités à loopback, sans DB_URL ; .env.testing privé. MySQL distant : CA officiel et vérification du certificat, contrôler Ssl_cipher ; ne pas retirer TLS pour accélérer.

- Coordonnées centrales, décision du 19 octobre 2026 : un seul e-mail et au maximum un téléphone par compte, directement dans users ; téléphone obligatoire à l’inscription publique, facultatif pour les comptes historiques/administratifs selon les formulaires existants. Cette décision remplace les coordonnées multiples des Missions 9–10. user_contacts et ses deux triggers sont retirés par une nouvelle migration, sans réécrire les migrations déployées. Conserver UNIQUE(email), UNIQUE(phone), la normalisation E.164 avec libphonenumber, phone_country_id indépendant du pays du profil, les pays actifs pour les nouvelles sélections et les références historiques. Fortify utilise le provider Eloquent standard, uniquement l’e-mail actuel pour connexion, notifications et récupération ; tout changement d’e-mail exige une nouvelle preuve. Les comptes Tenant gardent leur portée locale indépendante. Le retrait réel est autorisé pour cette intervention, avec sauvegarde privée préalable ; aucune autorisation générale de suppression de données ou de tests réels n’en découle. Voir architecture §32–34 et suivi.

- Autoriser côté serveur, valider les entrées et protéger sorties, CSRF et téléversements concernés. Un bouton masqué ou un UUID ne remplace pas un contrôle d'accès. Aucun secret ou donnée personnelle inutile dans réponses, logs, instructions ou commits.
- Quand une action existe dans plusieurs modules, vérifier tous ses points d’entrée et appliquer la même autorisation métier dans les vues, Controllers et services. En particulier, le renvoi d’une invitation au personnel exige saas.staff.invite.resend, y compris depuis Utilisateurs.
- Pour les futures décisions administratives, conserver un seul traitement métier par ressource, des actions cohérentes avec son état réel, une validation serveur, les autorisations, la protection contre les formulaires obsolètes et un audit atomique ; ne pas ajouter une seconde décision indépendante pour une projection synchronisée.
- Vérifier tenant, connexion et portée des requêtes, jobs, fichiers et caches ; aucune fuite entre boutiques. Utiliser transactions, contraintes et idempotence selon le besoin. Préserver les migrations déployées et les contrats non concernés.
- Tests et caches : si un cache local de configuration empêche APP_ENV=testing (419), isoler APP_CONFIG_CACHE/APP_ROUTES_CACHE dans le processus de test avec des chemins temporaires inexistants, sans vider la configuration du site. Garder tests/TestCase.php et ses protections de bases MySQL isolées ; détails §39.
- Vérifications BDD : MySQL existant en lecture seule. Les tests créant des bases, migrant, peuplant ou modifiant les données nécessitent une nouvelle autorisation explicite ; ne jamais déplacer leurs écritures vers les bases réelles.
- Ajouter/adapter les tests de comportement nécessaires, puis choisir les contrôles pertinents autorisés. Pas de tests longs, Pint, PHPStan, compilation ou suite complète systématiques ; réutiliser les résultats encore valables. Le §22 précise les effets de tests/TestCase.php et des scripts Composer.
- Relire chaque étape avant de poursuivre, puis le diff final : périmètre, références, erreurs, secrets et régressions. Vérifier séparément fichiers écrits, comportement chargé, MySQL, HTTP et navigateur. Corriger les échecs introduits et distinguer les défauts préexistants ; ne supprimer ni tests ni assertions pour fabriquer un succès.
- Préserver données, configuration globale et sécurité Windows. Suppression de données, production, publication ou action irréversible : autorisation précise requise, réutilisable si encore applicable. Pour un travail complexe, plan bref puis progression autonome ; en cas de blocage, donner cause, travail terminé et élément manquant.

## Commandes et communication

Utiliser PowerShell et exclusivement ce PHP 8.5 ; jamais le PHP ou le wrapper Composer de herd-lite. Commandes vérifiées et autres exemples au §22 :

~~~powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
& $aydraPhp artisan --version
& $aydraPhp artisan list --format=json --no-interaction
~~~

Fournir le contexte avant génération Artisan, avec --no-interaction ; vérifier les options. Pour Livewire, --emoji=false est disponible. Aucun emoji ajouté aux textes, commentaires, interfaces ou nouveaux noms ; préserver les anciens noms jusqu'à un renommage demandé et vérifié. Utiliser Flux/Lucide pour les icônes.

Répondre en français, simplement, sans longs préambules ni journal de micro-actions. Conserver accents et noms techniques du projet. Fin de tâche : résultat réalisé/raccordé, contrôles autorisés effectués, décisions durables documentées ; compte rendu bref avec fichiers, résultats réels, limites et action manuelle exacte si nécessaire. Pour une interruption longue, laisser un point de reprise bref dans le suivi (objectif, décisions, fichiers, contrôles, reste), sans secrets.

AGENTS.md n'est pas une mémoire absolue : son chargement dépend de la racine, du dossier courant, des fichiers prioritaires et de la limite du client. Vérifier ces conditions si une session ignore les règles ; ne pas modifier la configuration globale pour les contourner.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.

# Aydra — Lot T-012 à T-015 — Profil de boutique

## Source et périmètre

Source inspectée : `unitealfa/saas-ecom`, branche `dev`, 14 novembre 2026. Les six fichiers modifiés sont fournis **en entier** dans `02_MODIFICATIONS/`. Les nouveaux fichiers sont dans `01_NOUVEAUX/`. Les deux textes complémentaires `append_*.md` sont appliqués **en fin** des deux documentations existantes, sans les écraser. Aucune modification directe du dépôt GitHub, d'une base réelle, d'un rôle déjà attribué, ni des migrations déployées.

**T-001 : non raccordé.** Les routes admin Tenant et l'authentification de production ne sont pas opérationnelles dans le `routes/tenant.php` original ; garde `auth:tenant` + Policies strictes conservées. Compte Tenant ACTIVE, appartenance ACTIVE, joined_at et email_verified_at sont requis. T-010 : l'activité Spatie existe, mais l'ensemble du module de consultation/audit reste partiel. Ne pas ouvrir des routes non authentifiées en attendant.

## Interdictions et garanties

- Pas de `social_links` : une seule table `shop_addresses` typée et `softDeletes` ; pas de nouvelle migration.
- Aucun changement de nom local : `shop_name` reste projeté par le Central.
- Pas de `store` public pour les médias, pas de chemin saisi : disque privé `local` tenant, images PNG/JPEG/WebP uniquement, tailles et dimensions limitées. Anciennes images conservées pour audit, pas d'accès public aux anciennes pièces.
- La boutique et les lignes sont chargées du tenant courant et chaque écriture se fait sous transaction et verrou `shop`; les versions obsolètes sont détectées par empreinte d'état. Les UUID publics sont seuls exposés par les formulaires.
- `media.model_type` vaut le nouvel alias `shop`; `shop_address` et `shop_social_link` sont les alias polymorphes d'audit. Pas de changement des alias historiques.
- Références géographiques centrales contrôlées avant écriture, sans FK inter-bases fictive ; sélecteurs wilaya/commune affichés depuis le référentiel central actif. Une mutation concurrente du référentiel central entre vérification et commit Tenant ne peut pas être verrouillée atomiquement entre BDD.
- Cache public fichier isolé par UUID + BDD ; invalidation après commit depuis les services et `TenantProfileProjection`. Pas de cache des autorisations et statuts. Premier accès non préchargé.
- Ne pas modifier les champs financiers/fiscaux ni l'abonnement. Le thème n'est pas un éditeur de code ; `theme_code` est un réglage et aucune nouvelle bibliothèque UI n'est ajoutée.

## Permissions Tenant créées, aucune attribution automatique

`shop.profile.view`, `shop.profile.update`, `shop.branding.update`, `shop.addresses.view`, `shop.addresses.create`, `shop.addresses.update`, `shop.addresses.archive`, `shop.social-links.view`, `shop.social-links.create`, `shop.social-links.update`, `shop.social-links.archive`.

Le seeder additif est appelé sur les nouvelles boutiques par le provisionneur ; pour les bases déjà existantes, exécuter le seeder Tenant ciblé **après sauvegarde et sur un environnement de test**. Il ne modifie pas les permissions déjà présentes et n'accorde aucune autorisation au commerçant ou à un personnel central. Les permissions doivent être affectées par le mécanisme d'équipe Tenant de T-001/T-004.

## Matrice de tests (non exécutés à la livraison)

1. `ShopProfileTest.php` : isolation entre deux boutiques ; cache avant/après mutation ; formulaire obsolète ; refus sans permission ; image stockée sur disque Tenant.
2. `ShopAddressTest.php` : horaires traversant minuit et chevauchement semaine ; principal unique ; archivage soft delete ; test manuel de création avec wilaya/commune et rejet croisé.
3. `ShopSocialLinkTest.php` : URLs secrètes refusées ; plusieurs liens d'un même réseau ; rattachement local ; archivage.
4. Compléter en environnement T-001 : GET non connecté, compte central seul, suspension, CSRF, vérification d'e-mail, approbation/invitation et rôle local ; GET/POST/PUT/DELETE navigateur ; refus d'un UUID étranger, tri et visibilité.
5. Compléter T-015 : image tronquée, SVG masqué, fichier non-image renommé, ancien logo non accessible, favicon public, cache après rollback et projection centrale ; migrations déjà appliquées et sécurité des URLs.

**Important :** les fichiers de test fournis sont des tests de base proposés, pas une certification de fonctionnement. Tester avec MySQL 8.4 sur bases temporaires comme dans `tests/TestCase.php`. Aucun `php artisan test`, aucune migration et aucun seeder n'ont été exécutés dans cette préparation ; seul `php -l` était disponible pour contrôles statiques. Dépendances Composer du dépôt non installées dans l'environnement de génération.

## Mise en service

Voir `README_INSTALLATION.md` à la racine de ce package. Installer sur une **branche neuve depuis `dev`**, après validation des empreintes du dépôt. Ne pas fusionner vers `dev` avant intégration avec T-001 et T-010, tests sur MySQL et parcours UI complet.

## Revue d'integration T-012 a T-015 — 13 novembre 2026

- Les permissions shop.* appartiennent au guard tenant et a chaque BDD boutique. ShopProfilePermissionSeeder est volontairement local et appele dans le contexte initialise par TenantProvisioner. CentralRoleCatalog reste exclusivement SaaS ; aucun deplacement vers le central ni attribution automatique de droits au proprietaire invite.
- Les controleurs utilisent un acteur Tenant type et refusent explicitement une valeur absente/incompatible. Les services relisent cet acteur sous verrou dans la BDD courante avant la Policy ; une instance obsolete ou issue d'une autre boutique ne suffit pas.
- Catalogue public : listes d'adresses chargees une seule fois, aucun acces SQL par lien social. Cache isole par UUID, serveur, port et BDD ; invalidation apres commit avec store/prefixe captures avant un changement de contexte. Les selecteurs geographiques reutilisent GeographyCatalog ; validation metier toujours fraiche.
- SpatieContextBootstrapper selectionne deja Activity Tenant puis restaure le central. Ne pas modifier globalement activitylog pour un module, ni dupliquer ce mecanisme.
- Logo/favicon : conserver la validation MIME/dimensions et la propriete du media ; verifier sa visibilite publique avant diffusion. Nettoyer les fichiers d'une tentative SQL annulee avant une nouvelle tentative.

### A reprendre dans les prochains prompts externes

Avant de generer : examiner les versions installees, le code reel, les appelants et le provisionnement. Ne pas confondre le catalogue de permissions SaaS centrales avec les permissions privees Tenant. Verifier les bootstrappers avant d'affirmer qu'un contexte ou un audit est incorrect. Garder les controles d'acteur, Policies, verrous et etat attendu cote serveur ; une annotation PHPDoc ne remplace pas instanceof. Cacher seulement la presentation avec invalidation apres commit et isolation complete, sans N+1 ni cache de decision. Les tests doivent prouver isolation, refus d'acces, rollback et absence de fuite d'audit. Signaler les prerequis d'authentification Tenant encore reportes ; ne pas les contourner ni attribuer de droits automatiquement. Respecter les migrations deployees et documenter uniquement les controles vraiment executes.
- Incoherence confirmee entre formulaire et SQL : la contrainte ck_shop_addresses_4c7351308c exige une wilaya ET une commune pour ADDRESS. Le formulaire et le service les exigent maintenant ; les migrations existantes sont conservees. Les fixtures de tests creent de vraies references dans la base centrale temporaire. Le test logo capture explicitement son tenant dans la closure.
- Avant de generer formulaire, service ou fixtures : lire les migrations de creation ET leurs contraintes CHECK, cles composites et triggers ulterieurs. Nullable dans le CREATE TABLE ne signifie pas facultatif pour tous les record_type. Ne pas affaiblir une contrainte metier pour faire passer un formulaire ou un test incorrect.

### Controles executes pendant cette revue

Premier passage des sept tests : quatre reussites, trois erreurs (deux adresses sans geographie obligatoire et une variable de closure absente). Apres corrections, scenario profil/isolation/rollback/audit rejoue avec succes ; les trois scenarios adresses/liens/logo rejoues ensemble passent (19 assertions). Le controle des liens inclut un budget de cinq requetes maximum pour le profil public, independamment des deux liens lies au meme lieu. PHPStan cible sur services, controleurs, Requests et seeder du module, ainsi que GeographyCatalog : zero erreur. Pint et diff verifies. Routes administratives Tenant inspectees avec auth:tenant. Aucune ecriture dans les bases ou fichiers reels ; tests MySQL locaux temporaires uniquement. La suite globale et le parcours navigateur avec authentification Tenant ne sont pas valides par cette revue.

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

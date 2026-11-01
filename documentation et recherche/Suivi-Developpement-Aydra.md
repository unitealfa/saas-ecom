# Suivi de développement et audit Aydra

Ouvert le 6 octobre 2026 pour exécuter `instruction.txt`. Ce fichier distingue les spécifications, le code et les vérifications réelles. Une case non cochée signifie que le travail reste à faire ; une migration écrite ne prouve pas son exécution.

## 1er novembre 2026 — Module 09, consultation backend

Ajout des seules lectures administratives JSON des plans et de leurs versions, y compris archivées, et de leur composition. Permission centrale explicite saas.plans.view, sans attribution automatique aux rôles. Pas d'interface ni de mutation des plans, abonnements ou boutiques. PHP 8.5.12, au chemin local explicitement fourni, valide la syntaxe des dix fichiers concernés ; route:list confirme les deux routes sur les trois domaines centraux. Les tests fonctionnels correspondants sont écrits mais n'ont pas été exécutés : ils créent des bases MySQL isolées et exigent une autorisation distincte. L'ajout de la permission à la base centrale existante reste à exécuter explicitement après autorisation ; aucun seeder réel n'a été lancé.

## 1er novembre 2026 — Module 09, création backend

Ajout de POST admin/plans et de la permission saas.plans.create dans le catalogue et le seeder dédié existants. La création choisit une version 1 inactive et vide, conserve free, refuse les codes existants même archivés et journalise l'opération dans la transaction. Validation partagée par la Form Request et le service ; réponse limitée à l'UUID, sans droit de consultation implicite. Aucune interface ni modification d'offre, d'abonnement ou de boutique existants.

PHP 8.5.12 : syntaxe des sept fichiers PHP concernés et chargement des routes vérifiés. Les tests de comportement sont écrits mais pas exécutés : ils nécessitent des bases MySQL temporaires et leur autorisation explicite. Aucun seeder n'a été exécuté sur la base centrale existante ; saas.plans.create reste à y enregistrer après autorisation, sans enrichir les attributions existantes.

## 1er novembre 2026 — Module 09, composition backend

Ajout de PUT admin/plans/{plan}/features sous saas.plans.features.update. Remplacement complet protégé par état attendu, verrous, contrôles de fonctionnalités et d'abonnement, préservation des UUID conservés et audit atomique. Le plan gratuit et les versions utilisées restent protégés. PHP 8.5.12 : syntaxe des neuf fichiers concernés valide, routes chargées ; tests unitaires du jeton d'état et de la validation réussis (2 tests, 6 assertions), sans MySQL. Les tests fonctionnels nécessitent une autorisation explicite pour créer des bases MySQL isolées et ne sont pas exécutés. Aucun seeder ni changement sur les données existantes n'a été exécuté.

## Références et priorité

1. `Schema-BDD-SaaS-Ecommerce-UUID.md`, V4.9 : 28 tables centrales et 59 tables boutique, hors tables techniques Laravel.
2. `Diagramme-BDD-Centrale-Complet.md` et `Diagramme-BDD-Boutique-Complet.md`.
3. `architecture a suivre pour faire le code.txt`.
4. `Documentation-Laravel-Spatie-Permissions-Passkeys.md`, recherches Activity Log et collection EcoTrack.
5. Code réellement présent, puis MySQL vérifié séparément.

Les notes historiques ne réintroduisent pas les fonctionnalités retirées : exceptions individuelles de permissions/quotas, comptes acheteurs, sauvegarde/restauration SaaS, preuve de livraison, messages commerciaux aux acheteurs ou PDF d'accord téléphonique.

## Diagnostic initial vérifié

- Le dépôt contient le starter kit Laravel/Livewire et la documentation ; aucun modèle Tenant, fournisseur Tenancy, `config/tenancy.php` ou `routes/tenant.php` n'est installé.
- Versions installées : PHP 8.5, Laravel 13.34.0, Fortify 1.40.0, Livewire 4.4.7, Pest 5.3.0. Tenancy et Spatie Permission/Activity Log ne sont pas encore installés.
- Les migrations du starter kit sont toutes dans `database/migrations`. Le seeder crée seulement `test@example.com`.
- Requête MySQL via Laravel Boost : erreur 1049, base `aydra` inexistante. Ce n'est pas une base existante avec des tables vides.
- L'existence de `botique_1`/`botique_2` mentionnée dans l'instruction n'est pas encore vérifiée. Aucune base préexistante n'est supprimée ou renommée.
- `.ai/rules` est absent. Les règles AGENTS et les guides Laravel, Fortify et tests s'appliquent.

## Décisions et incohérences

| Écart | Décision et justification |
|---|---|
| `owner_user_id` dans l'instruction, `tenants.user_id` dans C1 | Colonne physique `user_id` selon la priorité explicite du §22 ; relation Eloquent `owner`. L'appartenance ne peut pas être transférée. |
| `name=user_1/user_2`, mais identité V4.9 en prénom/nom | Stocker `last_name=user_1/user_2` ; `name` reste un attribut de compatibilité du starter kit, pas une seconde colonne. |
| Noms dans `tenants.data` | `shop_name`, `slug` et les autres colonnes C1 sont dédiés ; `data` est réservé aux métadonnées Tenancy. |
| `botique_<id>` dans l'instruction, `tenant_<uuid>` dans §3.4/C1 | Appliquer le nom physique `tenant_<uuid>` du schéma prioritaire ; ne pas corriger silencieusement `botique_` en `boutique_`. Les anciens noms éventuels restent intacts. |
| Migrations `central` dans l'exemple d'architecture, `centrale` dans l'instruction | La nouvelle demande explicite retient `database/migrations/central` et `database/migrations/tenant`. Le dossier initial `centrale` a été renommé sans changer les noms des migrations déjà exécutées. |
| `tenants` et `domains` initialement réservés au central | La nouvelle demande explicite ajoute aussi ces deux tables dans chaque BDD boutique. Elles restent vides localement ; les modèles Tenancy et la résolution des domaines utilisent le registre central. |
| `App\Models\Tenant` demandé, classes par contexte dans l'architecture | Implémentation dans `App\Models\Central`; point d'entrée compatible `App\Models\Tenant` conservé. Même principe pour le User existant, afin de préserver le starter kit. |
| `customers` dans les exemples, mais absent du schéma boutique | Aucune table clients inventée : acheteurs invités et snapshots de commande selon T8. |
| Passkeys Spatie dans les recherches, Fortify déjà intégré | Conserver le système Fortify installé et ses migrations réelles ; ne pas installer deux fournisseurs WebAuthn concurrents. |
| Objectif final complet, développement module par module demandé | Ce lot exécute les demandes concrètes d'installation, migrations, deux comptes/tenants/domaines et vérifications. Le commerce et les autres modules restent des lots identifiés, pas des fonctionnalités déclarées terminées. |

Les données de démonstration ne constituent pas une vérification juridique de l'identité vendeur. Aucun tenant n'est rendu commercialement actif avant les prérequis du §7.1, notamment identité locale, rôle protégé, quotas, audit et profil professionnel vérifié.

## TODO de ce lot : instructions concrètes

- [x] Lire l'instruction et confronter ses exemples aux références prioritaires et au starter kit.
- [x] Créer ce journal avant le code métier et consigner les divergences.
- [x] Installer une version Tenancy compatible avec Laravel 13 et verrouiller les dépendances.
- [x] Séparer modèles centraux et locaux ; garder le starter kit fonctionnel.
- [x] Séparer migrations centrales/tenant ; enregistrer les chemins sans mélanger les tables.
- [x] Implémenter PK numériques et UUID v4 publics ; interdire les mutations de propriétaire/UUID par modèle et triggers SQL.
- [x] Créer les cinq pays de référence et les colonnes d'identité centrale prévues.
- [x] Configurer TenantWithDatabase, HasDatabase, HasDomains et `id_generator=null`.
- [x] Faire déclencher création/migration de BDD par TenantCreated, hors transaction centrale de DDL.
- [x] Créer les deux utilisateurs de test avec mots de passe hachés, uniquement en développement/test.
- [x] Créer les deux tenants avec Tenant::create et leurs domaines par la relation domains.
- [x] Prévoir des comptes locaux distincts sans copier les mots de passe centraux. Leur accès reste en attente, pas activé.
- [x] Résoudre les domaines avec InitializeTenancyByDomain ; isoler les routes centrales.
- [x] Vérifier MySQL réel, les migrations appliquées, utilisateurs, propriétaires, domaines et BDD locales.
- [x] Vérifier l'idempotence du seeder et les limites de provisionnement ; consigner ce qui reste incomplet.
- [x] Exécuter les tests pertinents, Pint et l'analyse statique ; inscrire les résultats exacts.

## Modules encore prévus, non implémentés

| Lot | Contenu de la référence | État initial |
|---|---|---|
| Fondations restantes | Spatie Permission : 5 tables par contexte, durées 1–9999 jours, signatures, absence de recoupement, rôles protégés ; Activity Log ; intentions de déploiement ; quotas et activation | À faire |
| SaaS central | Offres/version/features, abonnements/échéances, restrictions administrateurs, référentiels géographiques/livraison, médias, facturation/transferts manuels | À faire |
| Vitrine et catalogue | Profil public, adresses/liens sociaux, contenu, médias, catégories, variantes/options, promotions et avis | À faire |
| Commerce local | Visiteurs, paniers, checkout invité, révisions et confirmation téléphonique atomique | À faire |
| Stock et SAV | Réservations, mouvements, colis entiers, retours/inspection, incidents et renvois liés | À faire |
| Transport et finance | Comptes locaux, adaptateurs EcoTrack/DHD, opérations idempotentes, tarifs/retours, encaissement et reversement séparés | À faire |
| Documents | Snapshots immuables, factures/avoirs, obligations et corrections économiques | À faire |
| Comptes boutique | Activation par jeton, invitations, permissions datées, sessions et récupération indépendantes ; aucun accès d'assistance central | À faire |

Les écarts EcoTrack déjà constatés restent à traiter dans le lot transport : tarifs de retour par zone/mode, choix précis d'un bureau non documenté par l'API, succès partiels de lots et absence de garantie d'idempotence distante. Aucun appel à un compte transporteur n'est exécuté pendant ce lot.

## Journal et preuves

### 2026-10-06 — Lecture et diagnostic

Comparaison des références, dépendances, modèles, migrations, configuration et tests. Interrogation réelle de MySQL avec Laravel Boost : base configurée manquante. L'état historique du §15 de l'instruction ne décrit pas le dépôt actuel. Aucune fonctionnalité métier n'est déclarée vérifiée à ce stade.

### Résultats d'exécution

### 2026-10-06 — Installation et organisation

- Installation `stancl/tenancy` **3.10.1**, compatible avec Illuminate 13. Composer a ajouté quatre paquets, sans mettre à jour les dépendances existantes. `composer.lock` conserve les versions résolues.
- Classes métier séparées : `App\Models\Central\Country`, `User`, `Tenant`, `Domain` et `App\Models\Tenant\User`. Les deux classes racines demandées sont des points d'entrée de compatibilité, pas deux implémentations différentes.
- Migrations existantes déplacées dans `database/migrations/centrale`, en conservant leurs noms. La migration users du starter kit a été adaptée avant sa première exécution sur la base centrale manquante. Aucune ancienne base partagée n'a été vidée.
- Migrations ajoutées pour countries, tenants, domains, contact_verifications et les comptes locaux. Les migrations techniques Fortify/passkeys existantes sont conservées. Les trois colonnes 2FA sont techniques et s'ajoutent aux 30 champs métier users de C1.
- UUID v4 générés explicitement, PK BIGINT UNSIGNED auto-incrémentées, FK locales numériques, références inter-BDD par UUID. La clé Tenancy est `uuid` pour le contexte et les jobs ; la PK Eloquent reste `id`, y compris pour `domains.tenant_id`.
- Les changements d'UUID, du propriétaire central, de l'identité de provisionnement et du lien propriétaire local sont protégés. Les triggers SQL sont construits par migrations, pas par édition manuelle des tables.

### 2026-10-06 — Provisionnement et comptes de démonstration

- `DatabaseSeeder` appelle les seeders centraux organisés par contexte. Le seeder de démonstration contient `Tenant::create(...)` et `$tenant->domains()->create(...)` ; il n'est exécuté qu'en environnement local/testing.
- Un verrou de la ligne propriétaire protège la réservation idempotente `(user_id, creation_key)`. Le hash de la demande est comparé au retry. Cette réservation de démonstration n'est pas encore le service commercial de création sous abonnement/quota.
- Le fournisseur Tenancy écoute TenantCreated et lance le provisionneur après commit central. Il utilise le job natif CreateDatabase et la commande native `tenants:migrate` pour l'étape MigrateDatabase, en contrôlant son code de sortie. Aucun DDL MySQL n'est exécuté dans la transaction centrale.
- Une base déjà créée pour ce tenant est réutilisée au retry ; aucune suppression de base n'est enregistrée sur TenantDeleted. Échec → statut 6, sans activation ; reprise → installation des migrations manquantes.
- Les comptes centraux ont `last_name=user_1/user_2`, le `name` compatible correspondant, les e-mails demandés et le mot de passe `password` **haché**. Un nouveau seed ne réinitialise pas un mot de passe existant.
- Chaque compte propriétaire local est lié par central_user_uuid, avec un autre UUID et un hachage d'un secret aléatoire indépendant. Aucun mot de passe central n'est copié, aucun secret local n'est présenté comme identifiant de connexion.
- Ces comptes locaux ont `membership_status=2`, `joined_at=NULL`. Ce sont des comptes préparés pour une activation ultérieure, pas des propriétaires ayant déjà un rôle shop-owner fonctionnel.
- Les tenants restent `status=1 PROVISIONING`, `provisioned_at=NULL`. `schema_version=foundation-2026-10-06` décrit le lot de migrations installé, pas la totalité des 59 tables boutique.

### 2026-10-06 — Isolation et routage

- Routes séparées central/tenant et InitializeTenancyByDomain. Le domaine central canonique provient d'APP_URL, actuellement `aydra.test`. Les autres hôtes centraux réservés ont des noms de routes distincts, pour éviter une redirection accidentelle vers 127.0.0.1.
- Fortify est attaché au domaine canonique et au guard central ; provider/guard/broker tenant sont distincts. Le login, le dashboard et les endpoints Livewire centraux ne sont pas utilisables depuis un domaine boutique.
- Sessions locales : cookie propre au UUID, domaine de cookie non partagé, stockage local et réinitialisation du SessionManager/session.store à chaque changement. Les guards en mémoire sont purgés.
- Cache : préfixes centraux et `tenants:<uuid>:` ; support du cache database existant, sans dépendre de tags Redis. Les fichiers et verrous file ont leurs chemins séparés.
- Fichiers privés/publics séparés par UUID. Les jobs database sont stockés sur la connexion centrale et leur contexte contient le UUID du tenant.
- Le middleware termine le contexte dans finally. Les tests contrôlent le retour au contexte central, y compris après refus ou erreur.
- La route publique boutique répond **503**, avec « Cette boutique est en préparation ». Aucune vitrine commerciale ni administration locale n'est annoncée comme prête. Domaine inconnu, retiré ou non vérifié → 404.

### 2026-10-06 — MySQL réel : état final vérifié

Serveur **MySQL 8.0.46**, base configurée **aydra**. La création de la base a été effectuée par `php artisan migrate --force --no-interaction`, mécanisme Laravel natif pour une base absente. Toutes les tables ont été créées par migrations Laravel. Aucun `migrate:fresh`, DROP DATABASE, renommage ou suppression des données existantes n'a été exécuté.

| Élément vérifié avec Laravel Boost | Résultat réel |
|---|---|
| Pays | 5 ; DZ actif, FR/SA/SD/EG inactifs |
| Comptes centraux | 2 ; `boutique_1@gmail.com`, `boutique_2@gmail.com` ; hachages présents |
| Boutiques et propriétaires | 2 ; Boutique 1 liée à user_1, Boutique 2 à user_2 |
| Domaines | 2 ; `boutique1.aydra.localhost`, `boutique2.aydra.localhost` ; FK tenant_id correctes |
| BDD Boutique 1 | `tenant_83b64956-cace-47d2-a240-a6caf5c02099` |
| BDD Boutique 2 | `tenant_c606bfd5-75d4-40ea-96f6-4664b22e1b1e` |
| Compte local dans chaque BDD | 1 propriétaire préparé, lien UUID central présent, accès en attente |
| Colonnes id/FK centrales | BIGINT UNSIGNED ; PK id auto-incrémentée et NOT NULL |
| Colonnes uuid centrales | CHAR(36), ASCII/ascii_bin, NOT NULL |
| Domaine principal | Colonne STORED générée et unicité conditionnelle installées |
| Triggers | 4 au central, 1 dans chaque boutique ; installation confirmée dans information_schema |
| Anciens noms botique_1/botique_2 | Absents lors de la vérification réelle de ce serveur |
| Deuxième exécution du seeder | Réussie ; toujours 2 utilisateurs centraux, 2 tenants, 2 domaines et 5 pays |
| Statut des migrations central/tenant | 10 migrations centrales exécutées ; `tenants:migrate` confirme qu'aucune migration n'est en attente dans les deux BDD boutique |

**Tables métier réellement installées :** au central, `countries`, `users`, `tenants`, `domains`, `contact_verifications` (**5/28**). Dans chaque boutique, `users` et `contact_verifications` (**2/59**). Les autres tables métier restent à créer par lots.

**Tables techniques centrales :** `migrations`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `passkeys`. Dans chaque boutique : `migrations`, `password_reset_tokens`, `sessions`.

### 2026-10-06 — Vérifications et corrections constatées

| Vérification exécutée | Résultat |
|---|---|
| `composer check-platform-reqs --no-interaction` | PHP 8.5 et exigences des paquets satisfaits |
| Tests ciblés auth/profil et Tenancy | Réussis après corrections des écarts ci-dessous |
| `php vendor/bin/pint --dirty --format agent` | Réussi, fichiers formatés |
| `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` | Réussi, aucune erreur au niveau 7 |
| `composer test --no-interaction` | **Réussi : Pint, PHPStan et 47 tests / 206 assertions** |
| `git diff --check` | Aucune erreur d'espacement |
| HTTP réel avec MySQL : central `/login` | 200 |
| HTTP réel : chaque domaine boutique `/` | 503, préparation explicite |
| HTTP réel : boutique `/login`, domaine inconnu `/` | 404 |

Herd Desktop était arrêté lors du contrôle. Les requêtes HTTP ont été exécutées avec un serveur Laravel temporaire et les en-têtes Host des domaines attendus. Ce serveur a ensuite été arrêté. La résolution DNS et la configuration des alias dans Herd ne sont pas déclarées vérifiées ; les enregistrements domains et le routage Laravel le sont.

Les tests utilisent SQLite pour ne pas vider MySQL. Ils vérifient la création automatique et les migrations locales, les comptes séparés, les retries sans doublons ni reset de mot de passe, commit/rollback du provisionnement, les échecs/reprises, refus des accès centraux depuis les boutiques, domaines retirés/non vérifiés, propriété/UUID immuables, unicité du domaine principal, cache/session/disques séparés et UUID des jobs stockés au central. MySQL a été contrôlé séparément par schéma, requêtes et HTTP ; les tentatives d'UPDATE interdites ont été testées sur SQLite, pas sur les données de démonstration MySQL.

Corrections nécessaires pendant le lot :

- SQLite ne connaît pas ascii_bin : migrations portables avec BINARY sur SQLite, ascii_bin sur MySQL.
- Le test du kit attendait une suppression physique ; il vérifie maintenant la suppression logique users prévue par C1. Aucun test existant n'a été supprimé.
- TenantCreated hors transaction peut survenir avant la synchronisation des attributs Eloquent : le provisionneur recharge la réservation persistée avant ses modifications techniques.
- La limite PHP initiale de 128 Mo faisait échouer PHPStan ; `composer types:check` utilise maintenant 512 Mo pour que le contrôle Composer complet soit reproductible.
- Les anciens/nouveaux schémas principaux et diagrammes n'ont pas été réécrits. Le seul document ajouté/modifié dans le dossier de recherche pour ce lot est ce suivi.

### 2026-10-06 — Structure Central/Tenant et cinq tables de base par contexte

Correction demandée après le premier lot : les cinq tables `users`, `cache`, `jobs`, `tenants` et `domains` doivent exister au central et dans chaque boutique. Cette demande remplace, pour les tables locales `tenants`/`domains`, la séparation initialement décrite dans les références. Les schémas et diagrammes de recherche n'ont pas été modifiés par ce correctif.

- Renommage de `database/migrations/centrale` en `database/migrations/central` et mise à jour du chargement dans `AppServiceProvider`. Les noms des migrations centrales déjà appliquées restent identiques.
- La migration locale `2026_10_06_064410_create_local_accounts_tables` contient déjà `users`, `contact_verifications`, `password_reset_tokens` et `sessions`. Elle conserve son nom et son contenu pour préserver l'historique des deux boutiques existantes.
- Ajout, via Artisan, de quatre migrations tenant : `create_cache_table`, `create_jobs_table`, `create_tenants_table`, `create_domains_table`. Elles reprennent les définitions centrales, avec les FK vers les tables de la même BDD. Les migrations cache/jobs incluent aussi les tables Laravel associées `cache_locks`, `job_batches` et `failed_jobs`.
- L'ordre des migrations crée les utilisateurs avant `tenants`, puis `tenants` avant `domains`. Les migrations locales passent par le chemin tenant explicite ; elles ne sont pas chargées par la migration centrale.
- Les tables locales `tenants`/`domains` sont présentes mais vides. Aucun tenant imbriqué ni copie automatique du registre central n'est créé. `App\Models\Central\Tenant` et `Domain` restent connectés au central. Le cache avec préfixes et la file de jobs restent stockés au central conformément à leur configuration actuelle, même si les tables correspondantes sont désormais disponibles localement.

| Partie | Dossiers présents |
|---|---|
| Modèles | `app/Models/Central`, `app/Models/Tenant` |
| Contrôleurs | `app/Http/Controllers/Central`, `app/Http/Controllers/Tenant` |
| Requêtes | `app/Http/Requests/Central`, `app/Http/Requests/Tenant` |
| DTOs | `app/DTOs/Central`, `app/DTOs/Tenant` |
| Services | `app/Services/Central`, `app/Services/Tenant` |
| Policies | `app/Policies/Central`, `app/Policies/Tenant` |
| Vues | `resources/views/central`, `resources/views/tenant` |
| Migrations | `database/migrations/central`, `database/migrations/tenant` |
| Seeders | `database/seeders/Central`, `database/seeders/Tenant` |
| Tests | `tests/Central`, `tests/Tenant` |

Les dossiers vides contiennent un `.gitkeep` pour être conservés par Git. Les tests du kit ont été déplacés dans `tests/Central/Feature` et `tests/Central/Unit`, et les tests de séparation dans `tests/Tenant/Tenancy`. `phpunit.xml` et `tests/Pest.php` suivent les nouveaux chemins ; les tests tenant restent sans `RefreshDatabase` pour vérifier les callbacks après commit réels. Aucun test n'a été retiré.

Vérifications de ce correctif :

| Contrôle | Résultat |
|---|---|
| Tests tenant ciblés | 13 tests réussis, 151 assertions |
| Suite complète | 47 tests réussis, 234 assertions |
| Pint et PHPStan niveau 7 | Réussis, aucune erreur |
| `php artisan migrate --no-interaction` | Aucune migration centrale en attente après le renommage du dossier |
| `php artisan tenants:migrate --no-interaction` | Les quatre nouvelles migrations appliquées dans chacune des deux boutiques |
| Lecture réelle de `information_schema.TABLES` via Boost | `cache`, `domains`, `jobs`, `tenants`, `users` présents dans `aydra` et les deux BDD boutique |
| Comptes et registre après migration | Central : 2 utilisateurs, 2 tenants et 2 domaines ; chaque boutique : 1 utilisateur, 0 tenant et 0 domaine local |

Ces résultats complètent la photographie MySQL du premier lot ci-dessus. Les nouvelles tables locales sont installées ; leur présence n'active pas les fonctionnalités métier encore prévues.

### 2026-10-06 — Migrations complètes des deux schémas documentés

Demande : créer les migrations selon `Diagramme-BDD-Centrale-Complet.md`, `Diagramme-BDD-Boutique-Complet.md` et `Schema-BDD-SaaS-Ecommerce-UUID.md`, puis comparer et corriger. Ces trois documents restent inchangés.

| Contexte | Tables documentées | Champs documentés | Nouvelles migrations |
|---|---:|---:|---:|
| Central | 28 | 464 | 23 créations + 5 migrations de contraintes |
| Boutique | 59 | 1 005 | 57 créations + 5 migrations de contraintes |

**90 nouveaux fichiers**, soit 80 créations de tables manquantes et 10 migrations pour les index ordinaires, FK simples/composites, contraintes de ligne, protections d'identité/historique et index conditionnels. Avec les fichiers du lot précédent, les dossiers contiennent 38 migrations centrales et 68 migrations tenant. Les noms et le contenu des migrations déjà appliquées sont conservés.

Les cinq tables demandées dans chaque contexte — `users`, `cache`, `jobs`, `tenants`, `domains` — et les tables techniques Laravel associées restent présentes. Les `tenants`/`domains` locales restent des tables supplémentaires demandées explicitement ; le registre et le routage demeurent centraux. Les extensions existantes du kit, notamment les passkeys, la double authentification et le discriminant calculé du domaine principal, sont conservées.

La traduction conserve les PK numériques, UUID ASCII/ascii_bin, types signés/non signés, tailles de texte, précision microseconde, nullable, champs générés STORED, clés composées des pivots, valeurs des enums, durées de 1 à 9 999 jours et défaut de 9 999 jours. Les montants utilisent DECIMAL(14,2). Poids, dimensions et quantité de contenu utilisent DECIMAL(14,3), choix de précision pour les grandeurs dont le document demande une échelle appropriée sans donner de valeur numérique.

Les index parents précèdent les FK ; les relations circulaires sont ajoutées après toutes les créations. Les références UUID inter-BDD et les liens polymorphes ne deviennent pas des FK fictives. Les CHECK et triggers reprennent les formes typées, identités immuables, historiques protégés, projections de réservation et contrepassations documentés. Les sommes inter-lignes, plafonds concurrents, chevauchements de rôles/périodes, validation des JSON métier et autorisations effectives restent des responsabilités des services transactionnels prévus dans le schéma ; créer les tables n'implémente pas ces modules.

La comparaison automatisée lit directement les trois documents et inspecte les bases migrées : tables, ensemble exact des champs, types, nullable, PK/UK, colonnes calculées, FK simples et les tableaux de FK composites des §§6.1, 6.2, 6.3 et 6.7. Aucun champ FK dessiné ne manque de relation SQL locale. Les tests de contraintes exercent aussi les refus de doublons de rôle, durées invalides, mauvais guard, capacité SaaS locale, journal réécrit, mauvaise région/produit/axe/révision, stock insuffisant et mutation d'une réservation terminale.

Corrections détectées par les tests : déclarer explicitement `nullable(false)` sur les colonnes générées obligatoires ; créer les index d'expression après les FK pour éviter leur altération pendant les reconstructions SQLite ; protéger le compte racine depuis ses rôles existants et le propriétaire local depuis `central_user_uuid`, sans inventer de drapeau dans `users`.

**Portée d'exécution :** ces nouvelles migrations ne sont pas appliquées aux BDD MySQL de démonstration `aydra` et `tenant_*`. Les tests SQLite sont isolés ; le test MySQL crée des bases `aydra_migration_test_<contexte>_<UUID>`, vérifie les deux schémas et leur rollback, puis supprime uniquement ces bases qu'il a créées. MySQL installé : 8.0.46 ; les capacités utilisées sont testées sur ce serveur, distinct de la cible documentaire 8.4. Le contrôle en lecture du registre réel conserve 2 utilisateurs, 2 tenants et 2 domaines centraux.

| Vérification | Résultat |
|---|---|
| Comparaison et contraintes SQLite | 2 tests réussis, 5 248 assertions |
| Suite complète `php artisan test --compact` | 49 tests réussis, 5 482 assertions ; test MySQL volontairement exclu par défaut |
| Test MySQL isolé, création/contraintes/rollback des deux contextes | Réussi : 1 test, 6 826 assertions, avec `AYDRA_TEST_MYSQL_MIGRATIONS=1` |
| Pint et PHPStan niveau 7 | Réussis |
| `git diff --check` | Réussi |

### 2026-10-06 — Contrôle des migrations exécutées et accès aux deux boutiques de démonstration

Après les commandes de migration exécutées par l'utilisateur, les trois bases MySQL existantes ont été inspectées directement. Ce contrôle est en lecture seule : aucun `migrate:fresh`, rollback, effacement ou modification des tables métier n'est exécuté pour l'audit.

| Base | Tables présentes | Migrations appliquées | CHECK appliqués | Triggers présents |
|---|---:|---:|---:|---:|
| `aydra` | 37 | 38/38 | 85 | 76 |
| `tenant_83b64956-cace-47d2-a240-a6caf5c02099` | 69 | 68/68 | 151 | 205 |
| `tenant_c606bfd5-75d4-40ea-96f6-4664b22e1b1e` | 69 | 68/68 | 151 | 205 |

La comparaison avec les trois documents de référence réussit pour les champs, types, nullable, PK/UK, colonnes calculées et FK locales simples/composites : **11 428 assertions réussies**. Le test `tests/Tenant/Database/LiveMysqlSchemaAuditTest.php` est désactivé par défaut ; son exécution exige `AYDRA_AUDIT_EXISTING_DATABASES=1` et le nom explicite de la base centrale dans `AYDRA_AUDIT_CENTRAL_DATABASE`. Il vérifie aussi l'historique complet des migrations et la présence des CHECK et triggers. Les chiffres du premier lot ci-dessus restent une photographie historique ; toutes les migrations documentées sont maintenant appliquées aux trois bases.

Le seeder de démonstration existant a été adapté et exécuté deux fois avec `php artisan db:seed --no-interaction`. Le registre conserve **2 utilisateurs centraux, 2 boutiques et 2 domaines**, sans doublons ni réinitialisation des mots de passe existants. Chaque boutique conserve son propriétaire local indépendant. Les UUID des utilisateurs, boutiques et domaines sont conservés.

| Propriétaire central | Boutique | Domaine principal | Message de démonstration |
|---|---|---|---|
| `user_1`, `boutique_1@gmail.com` | Boutique 1 | `boutique1.aydra.test` | `This is your multi-tenant application. The id of the current tenant is 1` |
| `user_2`, `boutique_2@gmail.com` | Boutique 2 | `boutique2.aydra.test` | `This is your multi-tenant application. The id of the current tenant is 2` |

Le domaine de base dérive maintenant d'`APP_URL`, sauf configuration explicite de `SAAS_BASE_DOMAIN`. Ici, `.env` conserve `APP_URL=http://aydra.test` ; les domaines de démonstration ont été alignés avec cette adresse en conservant leurs UUID. Le seeder peut être relancé sans créer des domaines supplémentaires. `central_domains` réserve uniquement le domaine d'`APP_URL`, `localhost` et `127.0.0.1` au SaaS central. Un domaine de base boutique distinct n'est pas automatiquement déclaré central ; les domaines boutique sont résolus depuis la table centrale `domains`.

La route publique renvoie le message demandé uniquement en environnement local/testing pour les deux boutiques du seeder, avec un propriétaire central actif, un domaine vérifié et un tenant en préparation ou actif. Les autres boutiques conservent la réponse de préparation ; la démonstration n'est pas exposée en production, pour un propriétaire désactivé ou une boutique suspendue. Les statuts commerciaux et l'activation des propriétaires locaux ne sont pas modifiés.

Herd a été remis en fonctionnement. Le projet et les deux alias `boutique1.aydra`/`boutique2.aydra` sont configurés avec PHP 8.5 ; les réponses HTTP utilisent PHP 8.5.10. Les deux requêtes réelles via Herd, avec résolution locale forcée vers `127.0.0.1`, retournent **HTTP 200** et l'identifiant correct. La résolution normale des noms reste non fonctionnelle lors du contrôle ; elle relève de Herd et du réseau local. `central_domains` choisit les routes centrales après réception d'une requête et ne crée pas de résolution DNS. La consigne précédente de modification manuelle de la configuration Windows est retirée à la demande de l'utilisateur.

| Vérification de ce correctif | Résultat |
|---|---|
| Audit des trois bases existantes | 1 test réussi, 11 428 assertions, lecture seule |
| Tests Tenancy concernés | 15 tests réussis, 166 assertions |
| Pint et PHPStan niveau 7 | Réussis |
| HTTP réel via Herd avec résolution forcée | 200 pour les deux boutiques, identifiants 1 et 2 |
| Ouverture avec résolution normale | Non fonctionnelle lors du contrôle ; configuration des noms locale distincte du routage Laravel |

Les migrations, `.env`, le schéma principal et les deux diagrammes ne sont pas modifiés par ce correctif. La création des tables et cette démonstration d'isolation ne constituent pas une implémentation des modules commerciaux.

Correctif demandé ensuite pour `central_domains` : séparation du domaine de base boutique et des domaines réservés au central, retrait de la consigne de modification Windows et nettoyage du cache de configuration Laravel. Aucun enregistrement Aydra n'avait été ajouté dans la configuration Windows. Les deux tests ciblés réussissent avec 11 assertions, dont le refus de considérer une base boutique distincte comme domaine central et les frontières HTTP entre les deux contextes. Pint et PHPStan réussissent.

### 2026-10-06 — Démonstration avec ID numériques et domaines localhost

La nouvelle demande remplace le domaine de démonstration et l'identifiant interne Tenancy des premiers lots : `central_domains` contient explicitement `127.0.0.1`, `localhost` et `monpremiersaaslaravel.localhost`, avec `id_generator=null` et le modèle Domain existant. `.env` et `.env.example` utilisent `APP_URL=http://monpremiersaaslaravel.localhost:8000` pour l'essai demandé avec le serveur Artisan lancé par l'utilisateur.

Les colonnes métier `id` étaient déjà des BIGINT auto-incrémentés, distincts des UUID publics. `Tenant::getTenantKeyName()` utilise maintenant `id` ; les commandes Tenancy, jobs, cookies et préfixes de cache utilisent donc l'identifiant numérique. Les UUID documentés restent disponibles pour les références publiques/inter-BDD. Les identifiants techniques Laravel des sessions et lots de jobs conservent leur format prévu par le framework.

Le préfixe des nouvelles bases devient `boutique`, donc une boutique d'ID 1 reçoit `boutique1` et une boutique d'ID 2 reçoit `boutique2`. Une base existante enregistrée dans `tenancy_db_name` est conservée lorsqu'elle existe. Le contrôle MySQL actuel constate que les deux anciens noms `tenant_<uuid>` du registre ne correspondent plus à des bases présentes : le seeder doit réparer ces références avant leur reprovisionnement. Aucune base existante n'est supprimée ou renommée.

Le seeder reste dans `database/seeders/Central/DemoTenantSeeder.php`, appelé par `DatabaseSeeder`. Il prévoit deux propriétaires `Owner Boutique 1`/`Owner Boutique 2`, les e-mails `boutique1@test.com`/`boutique2@test.com` et le mot de passe initial `password`, haché par le modèle. Les anciens comptes de démonstration sont réutilisés sans changer leurs ID, UUID ou mots de passe. Les comptes locaux gardent des secrets indépendants. Les colonnes réelles sont `tenants.shop_name` et `tenants.user_id`, et non les noms d'exemple `name`/`owner_user_id`.

Les domaines attendus sont `boutique1.monpremiersaaslaravel.localhost` et `boutique2.monpremiersaaslaravel.localhost`, avec le port `8000` dans les URL de l'essai Artisan. Les doublons de création Boutique 1 de l'exemple ne sont pas reproduits : deux boutiques seulement, avec des domaines uniques. La présence du schéma et le message de démonstration n'activent pas les fonctions commerciales.

Le seeder a été exécuté deux fois sur MySQL. La première exécution a créé les deux bases manquantes sous leurs nouveaux noms et installé leurs migrations ; la deuxième réussit sans doublons. Les utilisateurs, boutiques et domaines centraux conservent leurs ID et UUID existants. Les anciens noms physiques n'étaient plus présents ; aucune base existante n'a été supprimée ou renommée pendant ce lot.

| ID boutique | Propriétaire | Base réelle | Domaine réel |
|---:|---|---|---|
| 1 | `Owner Boutique 1`, `boutique1@test.com` | `boutique1` | `boutique1.monpremiersaaslaravel.localhost` |
| 2 | `Owner Boutique 2`, `boutique2@test.com` | `boutique2` | `boutique2.monpremiersaaslaravel.localhost` |

| Vérification exécutée | Résultat |
|---|---|
| Tests Tenancy après passage aux ID numériques | 16 tests réussis, 183 assertions |
| Test de reprise après réparation d'une ancienne référence de base absente | 1 test réussi, 16 assertions ; identités et mots de passe conservés |
| Audit en lecture des trois bases MySQL | 1 test réussi, 11 428 assertions ; migrations, colonnes, FK, CHECK et triggers conformes |
| Tables réelles | Centrale : 37 ; `boutique1` : 69 ; `boutique2` : 69 |
| Deuxième seeding | Toujours 2 utilisateurs, 2 tenants et 2 domaines centraux |
| Colonnes `id` métier dans les trois bases | AUTO_INCREMENT ; seules `sessions.id` et `job_batches.id` gardent le format technique Laravel |
| Pint, PHPStan et `git diff --check` | Réussis |
| Serveur Artisan sur le port 8000 | Aucun serveur présent lors du contrôle ; à lancer par l'utilisateur pour l'essai navigateur |

Les URL prévues sont `http://boutique1.monpremiersaaslaravel.localhost:8000` et `http://boutique2.monpremiersaaslaravel.localhost:8000`. Les tests HTTP Laravel confirment les messages d'identification 1 et 2 ; une réponse HTTP réelle du serveur Artisan n'est pas déclarée vérifiée puisqu'il n'est pas lancé. La photographie précédente reste historique et ne décrit pas les bases actuelles.

### 2026-10-06 — Correction de la configuration réelle et séparation des données d’essai

Les exemples `monpremiersaaslaravel.localhost:8000` des lots précédents sont remplacés par la configuration réelle confirmée : `APP_URL=http://aydra.localhost`. Le code dérive le domaine central et, sauf `SAAS_BASE_DOMAIN` explicite, la base des sous-domaines depuis cette variable. Aucun nom de boutique d’exemple n’est enregistré dans la configuration de production.

La convention confirmée pour les nouvelles bases est `boutique_{slug_initial}_{id}`, par exemple `boutique_nour_17`. Le nom est enregistré dans `tenants.data.tenancy_db_name` lors de la réservation, puis conservé si le nom affiché ou le slug changent. Pour respecter les 64 caractères d’un identifiant MySQL, seul le slug peut être raccourci ; le préfixe et le suffixe numérique restent présents. Une base déjà renseignée n’est pas renommée automatiquement. Cette décision remplace la convention historique `tenant_<uuid>`.

Le seeder normal conserve uniquement les cinq pays documentés. Les deux propriétaires et boutiques d’essai sont déplacés dans `tests/Tenant/Tenancy/TenantTestSeeder.php`, réservé à l’environnement testing et appelé explicitement par les tests. La route publique ne contient plus de branche dédiée à ces comptes et n’affiche plus leur ID interne. `schema_version` est lu dans l’historique réel des migrations appliquées.

**État MySQL constaté pendant ce lot :** `boutique1` et `boutique2` existent encore, mais la base centrale `aydra` est absente (`Unknown database 'aydra'`). Aucune base n’est supprimée, renommée ou recréée. La correction des domaines existants ne peut pas être exécutée sans leur registre central ; elle n’est pas déclarée effectuée. Les résultats des audits des lots précédents restent historiques.

Le schéma et les deux diagrammes passent en V4.10 : `id` numérique auto-incrémenté, `uuid` public distinct, `tenants.id` pour le contexte technique Tenancy et UUID conservés pour toutes les références métier inter-BDD. Les annotations Mermaid rendent ces rôles explicites ; les tables, champs et relations restent identiques. Les documents de recherche Spatie et les notes historiques portent un renvoi vers ces conventions actuelles. Les tables techniques Laravel gardent leurs identifiants natifs ; aucun UUID métier n’est supprimé.

L’utilisateur confirme explicitement `id` pour le contexte Tenancy et `uuid` pour les interfaces publiques et les liens entre bases. La numérotation est indépendante par table/BDD et peut comporter des trous ; les UUID ne remplacent pas les PK numériques.

| Vérification de ce lot | Résultat |
|---|---|
| Ensemble des tests Tenancy adaptés | 21 tests réussis, 206 assertions |
| Contrôles finaux APP_URL, réservation/reprise et nouveaux cas de configuration | 7 tests réussis, 105 assertions, dont 3 nouveaux cas ; aucun appel à MySQL réel |
| Comparaison des migrations avec le schéma principal et les deux diagrammes | 2 tests réussis, 5 248 assertions sur SQLite ; tables/champs/types/nullable/PK/UK/FK conservés |
| Nommage long, ID distincts, nom existant et renommage avant/après commit | Réussis ; suffixe numérique conservé et nom réservé stable |
| Seeder normal en local et production, exécuté deux fois | 5 pays, aucun utilisateur, boutique ou domaine d’essai |
| Configuration/routes effectives | APP_URL et routes centrales sur aydra.localhost ; base de sous-domaines dérivée, id_generator null |
| Pint, PHPStan niveau 7 et git diff --check | Réussis |
| Correction des domaines dans MySQL | Non exécutée : registre central aydra absent ; bases boutique1/boutique2 préservées |

Les validations SQLite et de configuration ne constituent pas un nouvel audit MySQL des bases existantes ni un contrôle navigateur/DNS. Aucun serveur ou alias Herd n’est lancé/modifié par ce lot. La prochaine vérification des domaines réels exige de retrouver la base centrale ; aucune donnée d’essai n’est recréée pour la remplacer.

### 2026-10-06 — Recontrôle des changements et liste centrale explicite

Le contrôle du fichier réel constate un écart avec la forme demandée : `central_domains` était calculé depuis APP_URL. Il est corrigé en liste littérale dans `config/tenancy.php` : `127.0.0.1`, `localhost`, `aydra.localhost`. Le cache de configuration est vidé ; la liste chargée et le contenu écrit sont vérifiés séparément. Le test de configuration et les descriptions actives du schéma/diagramme sont adaptés. APP_URL reste `http://aydra.localhost`, et la base des sous-domaines reste dérivée de cette variable sauf SAAS_BASE_DOMAIN explicite.

L’état MySQL a changé depuis le lot précédent : la base centrale `aydra` existe maintenant, avec 37 tables, 38 migrations, 5 pays, aucun utilisateur, aucune boutique et aucun domaine. `boutique1` et `boutique2` existent toujours, avec 69 tables chacune. Aucun domaine central existant n’est donc disponible à corriger ; aucune boutique de démonstration n’est recréée. Le contrôle des colonnes `id` confirme leur auto-incrémentation, sauf les identifiants techniques natifs `sessions.id` et `job_batches.id`.

À la demande de l’utilisateur, `AGENTS.md` impose désormais sa lecture à chaque demande, la consultation des références pertinentes, la relecture des fichiers/diffs à chaque étape et la vérification effective avant de passer à une autre tâche. Les règles distinguent code écrit et configuration chargée, tests isolés et état réel, résultat constaté et point non vérifié. Les données existantes restent protégées.

| Recontrôle final | Résultat |
|---|---|
| Suite complète `php vendor/bin/pest --compact` | 60 tests réussis, 5 541 assertions ; 2 tests MySQL optionnels ignorés par défaut |
| Audit MySQL explicite des bases aydra, boutique1 et boutique2 | 1 test réussi, 11 428 assertions ; lecture seule, aucune migration exécutée |
| Historique des migrations réel | 38/38 au central, 68/68 dans chacune des deux bases boutiques |
| ID et UUID | PK métier auto-incrémentées ; UUID et relations conformes aux trois documents ; Tenancy utilise id |
| Dossiers séparant les contextes | 20 dossiers Central/Tenant présents |
| Tables de base demandées | users, cache, jobs, tenants et domains présents dans les trois bases ; users local créé par create_local_accounts_tables |
| Configuration écrite et chargée | Liste centrale littérale vérifiée, APP_URL correct, id_generator null et modèle Domain conservés |
| Seeders et code de production | Seeder normal limité aux pays ; fixtures uniquement dans tests ; aucun ancien domaine d’exemple ou branche de démonstration dans app/config/database/routes |
| Pint, PHPStan niveau 7 et git diff --check | Réussis |

L’audit en lecture seule peut inclure des bases boutiques sans enregistrement central au moyen de `AYDRA_AUDIT_TENANT_DATABASES`, avec une liste explicite et validée ; les bases ne sont jamais devinées depuis les ID. Les deux bases existantes sont contrôlées sans réinsérer leurs comptes ou domaines dans le central. Les résultats des tests HTTP sont ceux de Laravel, sans prétendre vérifier une ouverture navigateur réelle ou le DNS.

### 2026-10-06 — Remise à zéro autorisée et jeu fonctionnel local

L’utilisateur confirme des données d’essai réalistes dans MySQL, un volume de 20 produits et 50 commandes par boutique, un administrateur central et un employé local par boutique. Cette décision autorise un seeder local explicite en complément des fixtures de tests ; le `DatabaseSeeder` normal reste limité aux cinq pays. Les diagrammes et le §3.4 du schéma documentent cette exception sans changement de tables ou de colonnes.

Après validation sur SQLite puis dans trois bases MySQL temporaires, les bases inspectées `aydra`, `boutique1` et `boutique2` sont supprimées sur autorisation explicite. `aydra` est recréée, les 38 migrations centrales sont exécutées, puis `Central\LocalDevelopmentSeeder` crée les boutiques par le mécanisme Tenancy et leurs 68 migrations chacune. Aucun autre schéma MySQL n’est supprimé.

| Propriétaire central | Tenant | Domaine enregistré | Base persistée |
|---|---|---|---|
| ID 1, Karim Benameur | ID 1, Boutique 1 | boutique1.aydra.localhost | boutique_boutique1_1 |
| ID 2, Nadia Benali | ID 2, Boutique 2 | boutique2.aydra.localhost | boutique_boutique2_2 |

Le troisième compte central est `admin@example.test`. Chaque base boutique contient un propriétaire local ID 1 et un employé ID 2 ; l’ID 1 du propriétaire local de la deuxième boutique n’est pas son ID central 2. La liaison exacte utilise `users.central_user_uuid`, et `shop.tenant_uuid` égale le UUID du tenant central. Les mots de passe de test sont distincts selon le contexte : central `LocalTest!2026-Owner`, propriétaire boutique `LocalTest!2026-Shop`, employé `LocalTest!2026-Team`.

Chaque boutique contient un singleton `shop`, 20 produits, 40 variantes avec options/catégories/tags, 50 commandes et révisions/lignes, des visiteurs/paniers, 28 expéditions, 16 encaissements et reversements simulés, deux retours physiques complets, deux renvois impayés, 16 factures et un avoir, une correction de revenu et un remboursement de 100 DA, une dépense et les activités associées. Le retour payant coûte 300 DA au commerçant ; le retour gratuit comporte un montant explicite zéro et son snapshot. Un renvoi récupère manuellement 300 DA, l’autre zéro ; chacun conserve le destinataire du dossier original. Les statistiques mensuelles centrales comptent les commandes réellement présentes dans leur période, au lieu de confondre stock historique et usage du mois. Au central : une offre fictive, ses quotas, deux abonnements et échéances, deux factures et deux avoirs, deux paiements et deux remboursements simulés. Tous les PDF sont des fichiers valides privés marqués comme essais locaux ; aucun document fiscal ou justificatif bancaire authentique n’est prétendu créé.

Les seeders refusent la production et le mélange avec des données non identifiées comme fixtures. Les marqueurs d’activité transactionnels permettent un deuxième passage sans doublons, sans réinitialiser les UUID ou mots de passe. Les comptes transporteurs fictifs restent désactivés, sans clés API ni appel réseau. Les tables techniques et les scénarios non représentés restent vides, notamment les passkeys, jobs et créances transporteur ; aucun faux credential ou paiement supplémentaire n’est fabriqué pour remplir une table.

Les pages dédiées `/_dev/database` affichent les identifiants, liens, noms des bases, effectifs de toutes les tables et, en boutique, trois mesures de requêtes et le plan MySQL EXPLAIN. Elles exigent `local`/`testing`, debug actif et une adresse de boucle locale, avec réponse non mise en cache. Leur lecture de métadonnées cible explicitement la base courante : le test MySQL a détecté que la lecture sans schéma pouvait inclure d’autres bases accessibles. Les fichiers des tests sont isolés sous un dossier unique de `storage/framework/testing` et supprimés après contrôle de leur chemin ; les fichiers runtime `storage/tenant_*` sont ignorés par Git.

| Vérification exécutée | Résultat |
|---|---|
| Nouveau seeder, données et diagnostics sur SQLite + MySQL temporaire | 9 tests réussis, 427 assertions lors du contrôle MySQL ; dernier contrôle SQLite : 8 réussis, 413 assertions, test MySQL optionnel ignoré |
| Suite complète après corrections | 68 tests réussis, 5 954 assertions ; 3 tests MySQL optionnels ignorés par défaut |
| Audit des trois bases MySQL réelles recréées | 1 test réussi, 11 428 assertions ; tables, colonnes, PK/UK/FK, historique de migrations, CHECK et triggers |
| Données réelles | 5 pays, 3 utilisateurs centraux, 2 tenants, 2 domaines ; par boutique : 2 utilisateurs, 1 shop, 20 produits, 40 variantes, 50 commandes, 2 retours, 17 documents fiscaux simulés |
| Réconciliation MySQL du stock | Aucun écart entre compteurs P/R/Q, sommes des mouvements et réservations actives |
| Réconciliation MySQL des révisions | Aucun écart entre lignes, sous-total, livraison, récupération du retour et total |
| Deuxième seeding local sur MySQL réel | Réussi sans doublons, mêmes comptes/boutiques/domaines |
| Pint, PHPStan niveau 7 et diff | Réussis |
| Accès HTTP réel aux trois diagnostics sans port | HTTP 200 en IPv4 et IPv6, PHP 8.5.10 ; base centrale et bases boutiques correctes, volumes et liens vérifiés |

Le premier contrôle HTTP réel retournait 404 : Herd déclare le site historique `aydra.test`, alors que les domaines confirmés sont `aydra.localhost` et ses sous-domaines ; Apache/Laragon recevait ces requêtes sans configuration pour Aydra. Les tests HTTP Laravel réussis ne suffisaient donc pas à prouver leur accès réel. L’utilisateur a ensuite confirmé la correction de Herd/Laragon pour les domaines sans port ; cette correction et ses contrôles sont décrits ci-dessous.

### 2026-10-06 — Domaines locaux sans port : correction Herd/Laragon

Deux configurations locales propres à Aydra sont ajoutées, sans changer les tables, les domaines enregistrés ou le code métier :

- `C:\Users\habou\.config\herd\config\valet\Nginx\aydra.localhost.conf` : hôtes `aydra.localhost` et `*.aydra.localhost`, racine `C:/Users/habou/Herd/aydra/public`, service PHP 8.5 existant de Herd ; écoute sur `127.0.0.1:80` et sur `127.0.0.1:8081` pour la liaison locale avec Apache.
- `C:\laragon\etc\apache2\sites-enabled\aydra.localhost.conf` : hôte central et sous-domaines transmis à `http://127.0.0.1:8081/`, avec conservation du Host original pour que Tenancy choisisse la boutique. Le proxy direct est désactivé ; l’accès à ce virtual host exige une connexion locale. Cette restriction en entrée empêche qu’un client distant accède aux diagnostics via l’adresse de boucle du proxy. La configuration Apache par défaut reste présente.

La syntaxe des deux configurations est contrôlée avant rechargement. Nginx est redémarré par `herd restart nginx --no-interaction`. Apache fonctionne en processus Laragon, sans service Windows `Apache2.4` : la tentative standard `httpd -k restart` échoue pour cette raison ; le rechargement du processus existant est ensuite effectué par son événement natif `ap{pid}_restart`, après vérification du PID et du chemin de l’exécutable. Le journal Apache confirme le rechargement réussi. Aucun serveur Artisan n’est lancé.

Les requêtes HTTP réelles, sans ajout de port, sans résolution forcée et sans proxy système, confirment :

| Adresse ou contrôle | Résultat constaté |
|---|---|
| `http://aydra.localhost/_dev/database` | HTTP 200 via IPv4 et IPv6 ; base `aydra`, deux propriétaires, bonnes bases et liens boutiques sans port |
| `http://boutique1.aydra.localhost/_dev/database` | HTTP 200 via IPv4 et IPv6 ; tenant 1, base `boutique_boutique1_1`, 20 produits et 50 commandes, aucune donnée du propriétaire 2 |
| `http://boutique2.aydra.localhost/_dev/database` | HTTP 200 via IPv4 et IPv6 ; tenant 2, base `boutique_boutique2_2`, 20 produits et 50 commandes, aucune donnée du propriétaire 1 |
| Runtime des diagnostics | PHP 8.5.10 ; réponse `Cache-Control: no-store, private` |
| `/` central | HTTP 200 |
| `/` des deux boutiques | HTTP 503 avec le message applicatif existant « Cette boutique est en préparation. » ; la vitrine reste à développer |
| Boutique inconnue | HTTP 404 |
| `/.env`, `/composer.json`, chemin de seeder | HTTP 403 pour le fichier caché ; HTTP 404 pour les autres chemins, aucun contenu exposé |
| Fichier Windows `hosts` | Empreinte SHA-256 identique avant et après correction ; aucune modification |

Ces réglages concernent cette machine de développement. Herd conserve son TLD global `test` ; le fichier Aydra ajouté couvre les domaines `.localhost` et leurs sous-domaines sans changer ce réglage global. Le port 8081 sert uniquement à la liaison locale entre les serveurs : les adresses à ouvrir dans le navigateur restent les trois URL sans port ci-dessus. PHP 8.3 d’Apache n’exécute pas Aydra : les réponses réelles passent par PHP 8.5 de Herd. Le mécanisme de proxy et la conservation du Host suivent la [documentation Apache](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html) ; la restriction locale suit [mod_authz_host](https://httpd.apache.org/docs/2.4/mod/mod_authz_host.html).

### 2026-10-06 — Noms des bases boutiques sans suffixe d’ID

L’utilisateur remplace la convention `boutique_{slug_initial}_{id}` par `boutique_{slug_initial}` et confirme aussi son application aux deux bases existantes, en conservant leurs données. Les lots précédents décrivent l’état historique avant ce changement. Le code, le schéma, les deux diagrammes et les annotations actives des notes sont synchronisés avec la nouvelle convention ; aucune table ou colonne n’est ajoutée ou supprimée.

`TenantDatabaseName` n’ajoute plus l’ID au nom. Le nom est réservé dans les données centrales dès la création du tenant et demeure stable après changement du nom affiché ou du slug. Le préfixe `boutique_` compte neuf caractères : le slug initial est limité à 55 caractères en MySQL, sans troncature. La création refuse les noms déjà réservés, y compris par un tenant renommé ou supprimé logiquement, ainsi que les bases physiques déjà présentes ; une autre boutique ne peut pas être adoptée implicitement. Les ID, UUID, domaines, dossiers, sessions et clés de jobs conservent leur rôle antérieur.

MySQL 8.0.46 ne propose pas de renommage direct de base. Une opération locale explicite utilise les clients MySQL 8.0.46, sauvegarde chaque source avec ses données et triggers, importe sous le nouveau nom, compare les résultats, puis met à jour uniquement les références `tenancy_db_name` dans une transaction centrale. La collation réelle des sources, `utf8mb4_unicode_ci`, est conservée. Les origines restent présentes jusqu’à réussite de l’audit et des contrôles HTTP ; elles sont ensuite supprimées. Aucun seeder ne recrée les comptes, produits ou commandes lors de ce transfert.

| Tenant / propriétaire central | Nom avant transfert | Nom actuel | Domaine conservé |
|---|---|---|---|
| 1 / 1 | `boutique_boutique1_1` | `boutique_boutique1` | `boutique1.aydra.localhost` |
| 2 / 2 | `boutique_boutique2_2` | `boutique_boutique2` | `boutique2.aydra.localhost` |

La comparaison vérifie les 69 tables et 205 triggers de chaque boutique, les nombres de lignes, les empreintes SHA-256 de toutes les valeurs de chaque ligne ordonnées par PK, les définitions de tables et les propriétés des triggers. MySQL réécrit certaines déclarations d’encodage explicite sans changement de métadonnées ; cette seule différence textuelle est normalisée. Une empreinte physique `CHECKSUM TABLE` diffère pour `product_options` après reconstruction, malgré des valeurs identiques : la comparaison finale utilise les valeurs complètes et non l’agencement interne du stockage. Les sauvegardes SQL et le manifeste restent privés dans `storage/app/private/database-renames/2026-10-06-remove-id/`, ignorés par Git ; le script et le fichier de connexion temporaires sont retirés après exécution.

| Contrôle après correction | Résultat |
|---|---|
| Suite ciblée SQLite | 34 tests réussis lors du premier passage ; le seul échec concernait la borne de longueur d’un exemple. Les 4 cas de nommage passent après correction ; le test supplémentaire de refus d’une base non enregistrée passe également |
| Seeder et diagnostics sur MySQL temporaire, avec les nouveaux noms | 1 test réussi, 19 assertions ; bases temporaires supprimées par le test |
| Audit en lecture seule des trois bases réelles après bascule | 1 test réussi, 11 428 assertions ; tables, colonnes, PK/UK/FK, CHECK, triggers et historique de migrations conformes |
| Diagnostics réels des deux boutiques | HTTP 200 en IPv4 et IPv6, nouveaux noms sélectionnés, tenant 1/2 et volumes 20 produits / 50 commandes conservés |
| Diagnostic central réel | HTTP 200, nouveaux noms et domaines correctement associés |
| Présence des schémas MySQL | Les deux nouveaux noms existent ; `boutique_boutique1_1` et `boutique_boutique2_2` n’existent plus |
| Pint et PHPStan niveau 7 | Réussis |

## Prochaine étape et limites explicites

La fondation, la configuration, les migrations et le jeu fonctionnel local sont contrôlés selon les résultats ci-dessus. Le central contient les deux boutiques d’essai et leurs domaines correctement reliés aux nouvelles bases. Les trois diagnostics sont accessibles en HTTP réel sans port après correction du routage Herd/Laragon ; la page publique standard de boutique reste « en préparation », et les identifiants sont consultables sur le diagnostic local dédié. **L'ensemble du SaaS n'est pas terminé.** Le §25 de l'instruction et le chapitre 15 de l'architecture demandent un développement progressif, module par module.

Avant toute activation commerciale : implémenter Spatie Permission et ses règles datées/signatures/absence de recoupement, le rôle propriétaire protégé, Activity Log transactionnel, les intentions/historiques de déploiement, les plans/features/abonnements et quotas concurrents, le singleton shop, les contrôles complets d'état de compte et d'accès, puis l'activation locale par jeton et le profil professionnel vérifié. Ajouter les services, Requests/DTOs/Controllers dans leurs dossiers de contexte au fur et à mesure des véritables fonctionnalités ; ne pas créer de faux endpoints métier ni de tables d'exemples hors des demandes explicites.

Le provisionneur actuel est un amorçage technique synchronisé après commit pour le développement. Une création commerciale devra réserver domaine/quota/intention durable dans la même transaction centrale et posséder un rattrapage après crash. Les seeders de démonstration ne remplacent pas ce workflow de production. Les formulaires nom/prénom distincts et les règles professionnelles complètes sont aussi à terminer ; `name` est seulement la compatibilité du kit.

### 2026-10-11 — Administration centrale, Spatie et création automatique des bases boutiques

Cette entrée actualise l’état décrit ci-dessus, sans réécrire les résultats historiques. L’utilisateur confirme la gestion centrale des rôles, du personnel SaaS, des comptes commerçants et la création technique des boutiques depuis le compte Super Admin. Les fonctionnalités internes Tenant restent reportées ; leur préparation inactive est décrite au §19 de l’architecture.

Les rôles et permissions utilisent Spatie Laravel Permission et ses cinq tables existantes. Les modèles Aydra héritent des modèles Spatie ; les attributions utilisent ses relations et `syncRoles`, enrichis par les règles datées déjà documentées. Aucun système parallèle ni nouvelle table d’autorisation n’est créé. Le Super Admin crée/modifie des rôles de gestion des utilisateurs avec cases à cocher et durée propre à chaque action, entre 1 et 9 999 jours, 9 999 par défaut. Noms et compositions identiques sont refusés ; plusieurs rôles ne peuvent partager de permission. Conserver une attribution conserve sa date de départ. Les rôles système et Super Admin restent protégés.

Les administrateurs délégués munis des droits d’invitation et d’attribution peuvent gérer le personnel SaaS, sans déléguer de permission qu’ils ne possèdent plus. Les restrictions centrales restent vérifiées côté serveur. Les Policies et middlewares protègent les URL et les écritures, indépendamment de l’affichage des menus. Les droits de consultation, création et attribution restent utilisables indépendamment de leurs dates d’expiration.

Les formulaires centraux séparent prénom/nom, pays, compte commerçant/personnel et rôles. Le destinataire reçoit une invitation mise en file, avec payload chiffré, pour choisir lui-même son mot de passe via le broker central Fortify, puis se connecter et vérifier son e-mail. Aucun mot de passe partagé n’est créé par le formulaire et les tokens d’invitation ne figurent pas dans l’audit. Les entrées malformées sont rejetées par les Form Requests avant écriture. Le lot ne comprend pas encore de formulaire de modification/suspension des comptes.

L’offre gratuite par défaut a le code `free`, prix zéro et fonctionnalité `shops`, à portée propriétaire, période totale, avec deux boutiques maximum. Le seeder normal ajoute ce référentiel aux pays sans créer de comptes d’essai. Les nouveaux comptes et l’inscription publique reçoivent un abonnement type 1 actif, sans facture fictive. Les abonnements payants valides sont préservés ; les versions d’offres déjà utilisées ne sont pas réécrites. Le schéma et le diagramme central reprennent le quota de deux boutiques.

La création d’une boutique réserve atomiquement le tenant, son domaine, son quota et une intention dans `tenant_schema_deployments`, avec les vrais ID parents. Le nom physique reste `boutique_{slug_initial}`, sans ID final. Une clé de création et son empreinte permettent une reprise sans doublon. Un job durable central crée/migre ensuite la BDD tenant par `TenantProvisioner`, initialise son unique profil `shop` et lie le propriétaire local à son UUID central. Les paramètres initiaux acceptent les défauts confirmés `OTHER`, `default`, bleu/blanc et panier de sept jours. Les bases et profils existants sont réutilisés lors d’une reprise. Le statut de préparation est conservé ; ce provisionnement n’active pas l’accès commercial ou l’authentification Tenant.

Pour traiter les créations de bases et les invitations, garder un worker actif avec PHP 8.5 :

```powershell
& 'C:\Users\habou\.config\herd\bin\php85\php.exe' artisan queue:work central-provisioning --queue="tenant-provisioning,default"
```

La connexion centrale dédiée possède un délai de réservation de 360 secondes, supérieur au timeout de provisionnement de 300 secondes. La commande `aydra:retry-tenant-provisioning` remet en file les intentions en attente, en échec ou abandonnées. Le diagnostic public d’échec est minimisé ; les détails techniques restent privés.

Les classes respectent la chaîne Controller → Form Request → DTO → Service → Model et les dossiers de contexte. Le fragment partagé `resources/views/partials/head.blade.php` est rétabli pour les layouts Central et Tenant, sans requête ni logique de compte. Aucune migration ou table Spatie n’est modifiée dans ce lot. Les comptes et les deux boutiques réels existants sont conservés.

| Contrôle réellement exécuté | Résultat |
|---|---|
| Dernier passage gestion et accès centraux | 29 tests réussis, 188 assertions ; Spatie, durées, délégation, protection Super Admin, invitations, quota, reprise et validation malformée |
| Création automatique de deux bases et préparation Tenant, sur MySQL isolé | 11 tests réussis, 56 assertions ; création/migrations par le worker, liens UUID, profil unique, défauts et reprise contrôlés |
| Audit en lecture seule du central et des deux boutiques existantes | 1 test réussi, 12 924 assertions ; structures, relations, contraintes et historique conformes aux références |
| Seeder `Central\FreePlanSeeder` sur le central existant | Réussi ; plan `free` version 1, prix zéro, quota `shops` de deux ; cinq comptes et deux boutiques conservés |
| Authentification SMTP réelle | Réussie, sans envoyer de message ; la réception effective d’une invitation n’est pas prétendue vérifiée |
| Requête HTTP réelle de la page de connexion centrale | HTTP 200 ; ce contrôle ne remplace pas un parcours navigateur connecté |
| Pint, PHPStan et diff | Réussis ; aucune erreur PHPStan |
| Compilation des assets et vues Blade | `npm.cmd run build` et `artisan view:cache` réussis |

La suite complète n’est pas présentée comme exécutée par ces contrôles ciblés. Elle peut être lancée avec PHP 8.5 et `artisan test --compact` ; les tests utilisent MySQL et leurs bases isolées, sans remplacer les données existantes.

### 2026-10-11 — Invitations immédiates et fiches des comptes

L’absence d’envoi est vérifiée dans MySQL : deux puis trois jobs AccountInvitation en attente, sans tentative ni worker actif. Le SMTP Gmail est configuré. Le worker temporaire a traité les trois invitations et s’est arrêté une fois la file vide ; le SMTP a accepté les envois, sans prétendre vérifier leur réception dans les boîtes destinataires.

Les nouvelles invitations utilisent maintenant `notifyNow` de Laravel après commit, au moyen de `AccountMailDelivery`, sans worker. Les anciens jobs chiffrés restent compatibles. Un échec de transport conserve le compte et affiche un avertissement explicite ; l’interface permet de renvoyer une invitation avec un nouveau token, sous contrôle d’autorisation et limitation des requêtes. Le job de création d’une BDD boutique conserve sa file dédiée et nécessite toujours son worker.

La liste centrale propose Informations et Modifier selon les Policies. La fiche présente identité, contacts, pays, état, vérification, rôles et, pour un commerçant, boutiques et informations professionnelles. Le formulaire modifie prénom, nom, e-mail, téléphone et pays. Les mots de passe, statut, rôles et preuves de vérification ne sont pas éditables par ce formulaire. Un nouvel e-mail invalide ses anciens tokens et demande sa vérification ; pour un compte encore invité, un nouveau lien de choix du mot de passe part vers la nouvelle adresse. Un téléphone modifié perd ses preuves. Les identités professionnelles déjà versionnées sont revalidées sous verrou.

Spatie fournit toujours les rôles, permissions et relations ; les Policies Laravel protègent les boutons, URL et services. `saas.users.update` autorise la modification d’un commerçant, `saas.staff.invite` celle du personnel, avec contrôle des droits encore possédés et protection du Super Admin. La Policy Tenant refuse la création de boutique pour toute cible possédant un rôle SaaS, même expiré. Aucune base, boutique ou attribution existante n’est supprimée pour réaliser cette correction ; aucune migration n’est modifiée.

Vérifications : les onze nouveaux tests ont chacun réussi sur MySQL isolé. Dix ont réussi lors du passage ciblé ; le dernier contrôle de validation a réussi après correction de sa comparaison avec les valeurs réellement chargées depuis MySQL (un test, 17 assertions). La sortie finale du passage combiné sur trois fichiers n’a pas pu être récupérée : ce passage n’est donc pas présenté comme réussi. Pint, PHPStan, compilation des assets, compilation Blade et contrôle du diff ont réussi. MySQL réel confirme zéro invitation en attente, zéro job en échec et les deux boutiques existantes conservées.

La consigne de limiter les commandes longues et répétitives est enregistrée dans `AGENTS.md` : conserver les résultats encore valables, cibler les changements ou erreurs restantes, sans lancement ni demande systématique de suite complète.


### 2026-10-11 — Installation du cadre durable de développement

Périmètre confirmé : instructions et documentation nécessaires uniquement. Les nouvelles règles encadrent les prochaines demandes ; cette installation ne modifie ni le code applicatif, ni les migrations, ni les données, ni la configuration globale ou les permissions Windows.

La reconnaissance initiale identifie la racine C:\Users\habou\Herd\aydra. Git était propre, avec HEAD 6c43155 (redirection de l'accueil vers le login). AGENTS.md existait localement mais était ignoré par .gitignore ; cette exclusion est retirée pour le rendre versionnable. Aucun AGENTS.override.md ni instruction de sous-dossier n'a été trouvé dans le dépôt hors dépendances, et aucun fichier global AGENTS applicable n'a été trouvé dans le profil Codex consulté. .ai/rules est absent. Aucun commit n'est créé par cette installation.

AGENTS.md conserve les règles opérationnelles et les liens vers les références existantes. Les détails compatibles de l'ancien fichier sont consolidés aux §21–22 de l'architecture. Les consignes devenues obsolètes sur la relecture intégrale et les contrôles systématiques sont remplacées : lecture selon la tâche, décisions récentes explicites, MySQL existant en lecture seule pour les vérifications, aucune création de BDD de test sans nouvelle autorisation, aucun lancement systématique de tests/Pint/PHPStan. Les scripts et tests existants sont conservés.

Les références sont distinguées : schéma principal pour le métier, diagrammes pour les structures et relations, architecture §18–20 pour les contextes validés, suivi pour les décisions et preuves datées. Les recherches Spatie/Fortify et notes restent des ressources à adapter. last one notes.md annonce une analyse historique de V3 ; ses prescriptions remplacées ne deviennent pas des décisions courantes. Les documents DOCX et autres notes ont été inventoriés, sans prétendre avoir audité chaque fonctionnalité qu'ils évoquent.

État constaté dans les fichiers : routes centrales séparées, routes publiques dans le groupe tenant, alias Livewire par contexte, guard et broker Fortify centraux, modèles Spatie étendus et migrations d'extension distinctes. La préparation des dashboards/équipes Tenant reste inactive selon le §19 et les routes présentes. Ce constat de code n'est pas une preuve de conformité exhaustive des BDD ni d'achèvement de toutes les fonctions du schéma.

Versions vérifiées : PHP 8.5.10, Composer 2.10.2, Laravel 13.34.0, Fortify 1.41.0, Livewire 4.4.7, Flux 2.20.1, Permission 8.3.0, Activitylog 5.0.0, Tenancy 3.10.1, Pest 5.3.0, Pint 1.32.1 et Larastan 3.12.3. Les versions PHP verrouillées correspondent aux paquets installés contrôlés. Node 26.9.0 et npm 11.19.1 fonctionnent ; package-lock.json et node_modules correspondent pour Tailwind 4.3.3, Vite 8.3.3, Vite Plus 0.3.0 et le client passkeys 0.2.0. Ces observations sont datées, pas des versions à supposer immuables.

Commandes exécutées pour la reconnaissance : Git en lecture, PHP/Composer/Node/npm --version, artisan --version et artisan list --format=json --no-interaction. Les générateurs et options documentés ont été vérifiés dans les définitions installées, dont make:livewire --emoji=false. Aucun fichier applicatif n'a été généré. Le wrapper Composer du PATH vise herd-lite ; le cadre prescrit le composer.phar Herd exécuté avec PHP 8.5. Les erreurs de lecture JSON dues à PowerShell ont été corrigées par une lecture sur entrée standard, sans modification des verrouillages.

Limites conservées : aucun test, migration, seeder, worker, Pint, PHPStan ou build n'est lancé pour cette installation ; aucun contrôle MySQL, SMTP, DNS ou navigateur réel n'est présenté comme exécuté. tests/TestCase.php crée/supprime des BDD MySQL isolées et composer test ajoute formatage/analyse : ces commandes ne conviennent pas à une simple vérification en lecture seule. La nouvelle chaîne d'instructions devra être chargée lors de la prochaine ouverture de session ; sa prise en compte par ce futur client n'est pas vérifiée ici. AGENTS.md n'est pas une mémoire absolue.

Vérifications finales effectuées : les neuf liens locaux d'AGENTS.md pointent vers des fichiers existants ; les §18–22 de l'architecture sont présents ; AGENTS.md n'est plus exclu de Git et reste à ajouter à un commit. Sa taille passe de 21 137 à 8 289 octets. git diff --check réussit après correction des lignes vides finales ; le diff reste limité à AGENTS.md, .gitignore, l'architecture et ce suivi. Aucune réussite d'un contrôle fonctionnel n'est déduite de cette installation.

## Gestion des pays et cohérence des comptes — 11 octobre 2026

Décisions et réalisation :
- Permissions Spatie regroupées dans CentralRoleCatalog : Commerçants, Personnel SaaS, Pays. Chaque action garde sa permission et sa durée propres. Une action de gestion valide donne accès à la consultation du module dans sa portée, sans ajouter un pivot ou un droit permanent. Les restrictions de consultation et de l’action source restent appliquées dans les Policies et la requête de liste.
- Invitation du personnel et modification du personnel sont désormais distinctes : saas.staff.invite / saas.staff.update. Suspension, réactivation et suppression ont des permissions séparées pour commerçants et personnel. Les rôles système existants ne reçoivent aucune nouvelle action automatiquement.
- Pays : CountryController, StoreCountryRequest, ChangeCountryStatusRequest, CountryService, CountryPolicy, vues countries et routes centrales. Création désactivée, activation séparée, noms FR/EN/AR, code ISO stable dès utilisation, refus du retrait d’un pays lié à des comptes même archivés ou à des zones. Retrait logique seulement. CountrySeeder préserve les données existantes et ne recrée pas les codes archivés.
- Inscription Fortify : liste des pays actifs et validation de country_uuid avec relecture/verrou du pays actif lors de la création. Les formulaires administratifs conservent leur règle de sélection active ; un compte peut garder son pays devenu désactivé.
- Fiche utilisateur : identité, contacts, langue, état, vérifications/dates, rôles, boutiques, huit champs professionnels et abonnements paginés avec leurs plans, y compris archivés. Aucun ID interne ou secret affiché. Édition identité/contact/langue/profil professionnel ; rôles, état, validations et abonnement ne sont pas des champs libres de ce formulaire. Changement matériel du dossier vendeur : version augmentée et validation retirée.
- Boutons Suspendre / Réactiver / Supprimer raccordés côté serveur. UserPolicy protège toujours le Super Admin et interdit ces actions sur le compte de l’acteur ; les délégués ne prennent pas le contrôle d’un personnel plus privilégié. Transactions/verrous et audit Spatie. Les sessions centrales, remember_token et liens de réinitialisation sont invalidés sur suspension/suppression.
- Suppression logique : status=DELETED et deleted_at ; conservation des comptes historiques, boutiques, bases et pièces. Aucun développement de l’authentification Tenant ni modification de ses tables.

Vérifications réellement exécutées :
- Syntaxe PHP des fichiers créés/modifiés, compilation Blade en mémoire suivie d’un contrôle de syntaxe PHP sur 11 vues.
- Routes centrales chargées et méthodes HTTP, middlewares d’authentification/vérification/compte actif, Policy Country enregistrée, résolution des modèles Country/User par uuid.
- MySQL existant dans une transaction READ ONLY terminée par ROLLBACK : cinq pays DZ, FR, SA, SD, EG, seul DZ actif ; share_capital en DECIMAL(14,2), conforme à SchemaBlueprint. Douze nouvelles permissions manquent encore au catalogue réellement enregistré.
- Tests préparés : CountryManagementTest et UserLifecycleTest ; adaptations RegistrationTest et UserAccountManagementTest pour les nouvelles règles. Non exécutés : l’utilisateur a explicitement choisi les contrôles sans écriture en BDD. Aucun test créant une base, seeding, migration, worker, Pint, PHPStan ou build exécuté.

Action restante pour utiliser les nouveaux droits : lancer Database\Seeders\Central\AuthorizationSeeder sur la base centrale depuis PHP 8.5. Il enregistre les permissions sans créer de comptes ni modifier les compositions de rôles existantes. Affecter ensuite les nouvelles actions aux rôles personnalisables voulus ; la modification du personnel est séparée de son invitation. Les parcours HTTP avec écritures et le rendu navigateur n’ont pas été vérifiés.

## 11 octobre 2026 — Module 01, travail en cours (instructionn.txt)

Périmètre : sections 1–2 et Module 01 seulement. Modules 02 et suivants non développés ; boutiques, bases, migrations déployées et configuration globale inchangées.

Réalisé dans les fichiers :
- Les cinq permissions existantes sont conservées par le catalogue Spatie (mêmes lignes/UUID). Libellés demandés ; nouvelles actions du Module 01 et groupes correspondants. Le seeder peut renommer les anciens droits restore/delete en activate/archive en conservant ID/UUID, pivots et signatures ; une collision est refusée sans écrasement. Aucun seeding exécuté.
- Retrait des listes de cibles et des lectures d’admin_restrictions dans CentralUserAccess ; contrôles Spatie datés, guard central, protection du Super Admin et délégation restent en place. Schéma/diagramme cible : 27 tables centrales, 450 champs dans le diagramme ; table historique MySQL conservée et inutilisée par les autorisations. Aucun DROP exécuté.
- UserController déplacé dans Central/Admin ; UserManager déplacé/renommé Users/UserManagementService ; vues regroupées dans central/pages/admin/users, liste SFC central::pages.admin.users.index. Routes existantes conservées, références et tests adaptés. Archive logique consultable ; réactivation distincte ; protection des comptes archivés contre les mutations.
- Dossier professionnel : permissions propres de consultation/validation/rejet, service et Form Request, vérification sous verrou de legal_profile_version et complétude déjà appliquée par le profil, identité du validateur et audit Spatie. Historique administratif paginé par sujet, sans propriétés brutes ou secrets.
- Invitation Fortify : jeton du broker central lié à l’e-mail courant, vérifié/consommé sous verrou. Une invitation confirmée renseigne email_verified_at et émet Verified sans seconde notification ; un reset ordinaire d’un compte public n’est pas transformé en vérification d’e-mail. Une ancienne adresse ne valide pas la nouvelle. Aucun mail réel envoyé pour vérifier.
- Inscription : champs first_name/last_name et téléphone reliés aux colonnes existantes ; création administrative peut renseigner le téléphone.

Clarification résolue le 12 octobre 2026 : l’utilisateur confirme 5 PENDING et 6 REJECTED au central uniquement. Le parcours est désormais raccordé dans les fichiers ; voir la suite ci-dessous. Les comptes existants et les statuts Tenant ne sont pas convertis.

Contrôles effectués à ce stade :
- Syntaxe PHP des 23 fichiers créés/modifiés et compilation en mémoire des huit vues : réussies avant la dernière relecture ; contrôles de syntaxe renouvelés à la fin.
- Routes des utilisateurs, historique et dossier professionnel chargées avec les nouveaux Controllers ; chemins d’AGENTS.md présents. Diagramme cible compté : 27 tables / 450 champs.
- MySQL READ ONLY avec ROLLBACK : sept comptes existants actifs, les cinq permissions présentes, admin_restrictions vide, CHECK de statut 1–4 ; consultation/modification existantes autorisées, actions sensibles refusées sur le Super Admin et pagination réellement exécutée. Cache de ce contrôle limité au processus avec le store array, sans changement de configuration persistante.
- Tests de régression ajoutés/adaptés mais NON exécutés : ils créent/modifient des bases MySQL, ce que l’utilisateur n’autorise pas pour les vérifications actuelles.
- Un passage Pint demandé par les consignes a corrigé le format des fichiers et signalé une ligne syntaxiquement invalide dans LegalVerificationService ; cette ligne a été retirée, puis contrôle ciblé Pint et syntaxe réussis. Aucun PHPStan/build/migration/seeder/worker exécuté.

État historique de cette première étape : seuls les fichiers indépendants de la clarification étaient terminés. La suite du 12 octobre résout ce point ; migration et seeding restent à appliquer séparément.


### 12 octobre 2026 — Module 01, parcours d’approbation raccordé

- Décision validée : comptes centraux 1 ACTIVE, 2 INACTIVE, 3 SUSPENDED, 4 DELETED, 5 PENDING, 6 REJECTED dans app/Enums/Central/Users/StatusEnum.php. Les comptes Tenant conservent leur enum 1–4 ; HasTimedPermissions distingue les deux guards sans modifier les durées Spatie.
- Inscription publique : CreateNewUser choisit 5 et trace la demande. RegistrationResponse implémente le contrat Fortify et retire sa connexion automatique, avec retour au login et message d’attente ; JSON retourne 201 et le statut 5, sans identité interne. L’e-mail de vérification natif reste indépendant.
- Administration : UserApprovalController, UserApprovalRequest, UserApprovalService et pending.blade.php sont raccordés. Coordonnées et pays paginés, actions Approuver/Refuser autorisées par leurs permissions Spatie ; contrôle de la demande courante sous verrou et audit central. Une décision déjà prise ne peut être écrasée par une seconde décision.
- Protection : seuls les comptes actifs accèdent aux routes et réglages centraux. Middleware de statut persistant Livewire et callback natif Passkeys vérifiés dans les versions installées. Réactivation ordinaire, attribution de rôles et création de boutique ne contournent pas l’approbation. Le dashboard commerçant approuvé indique désormais que la gestion boutique est en préparation, sans prétendre attendre encore son approbation.
- Dossier/historique/invitation de la première étape conservés ; import CentralRoleCatalog corrigé dans le service déplacé et seconde requête incorrecte après le verrou du dossier retirée.
- Migration d’évolution préparée : database/migrations/central/2026_10_12_050215_extend_central_user_statuses_for_approval.php. Elle remplace atomiquement le CHECK central 1–4 par 1–6, sans conversion de données. Le rollback refuse des comptes à 5/6. Aucune migration historique modifiée.
- Schéma, diagramme central, architecture et AGENTS.md actualisés ; diagramme Tenant et fonctionnalités des autres modules préservés.

Contrôles effectivement réalisés :
- Syntaxe PHP : 39 fichiers contrôlés, aucune erreur ; les trois fichiers ensuite ajustés pour le contrôle Livewire/passkey ont été contrôlés à nouveau.
- Compilation Blade en mémoire puis syntaxe PHP : 10 vues, aucune erreur. Ce contrôle ne prouve pas un rendu navigateur complet.
- Routes chargées et route pending prioritaire au binding utilisateur ; contrat RegisterResponse résolu vers la classe Aydra ; middleware Livewire persistant chargé. Casts central 5 et tenant 1 vérifiés ; le modèle tenant refuse 5.
- Syntaxe du remplacement de CHECK relue dans le [manuel officiel MySQL](https://dev.mysql.com/doc/refman/8.4/en/alter-table.html) et son [test officiel de remplacement d’une contrainte portant le même nom](https://github.com/mysql/mysql-server/blob/8.4/mysql-test/t/constraints.test). Cela ne remplace pas l’exécution de la migration sur Aydra, volontairement non réalisée ici.
- MySQL transaction READ ONLY puis ROLLBACK : sept comptes existants, cinq permissions requises présentes, aucun approve/reject déployé et CHECK toujours 1–4. Consultation paginée, liste pending vide, rôles délégables et protections du Super Admin vérifiés sur ces données existantes. Le callback passkey autorise un compte actif existant. Cache limité au processus (array).
- Pint exécuté sur les fichiers PHP concernés puis uniquement sur les trois fichiers ajustés ; relecture du diff et git diff --check. Aucun PHPStan, build, mail réel, migration, seeder, worker ou test écrivant en BDD exécuté.
- Tests préparés pour inscription pending/JSON, champs privilégiés ignorés, refus de login, approbation distincte de la preuve d’e-mail, permissions indépendantes/expirées, double décision, absence de contournement, sessions anciennes et 2FA/passkey. Ils restent NON exécutés selon la préférence de contrôles sans écriture en BDD ; leur réussite n’est pas affirmée.

État final : réalisation du Module 01 terminée dans les fichiers ; activation dans MySQL et validation fonctionnelle de bout en bout restent à faire. Lancer depuis Aydra avec le PHP 8.5 autorisé :

~~~powershell
$aydraPhp = 'C:\Users\habou\.config\herd\bin\php85\php.exe'
& $aydraPhp artisan migrate --path=database/migrations/central --no-interaction
& $aydraPhp artisan db:seed --class='Database\Seeders\Central\AuthorizationSeeder' --no-interaction
~~~

Ces commandes écrivent dans la base centrale configurée. Elles ne réinitialisent pas les données et ne migrent pas les boutiques. Les rôles existants ne reçoivent pas automatiquement les nouvelles actions ; les attribuer ensuite depuis le formulaire des rôles, en conservant les durées et règles de délégation. La table historique admin_restrictions est physiquement conservée et inutilisée ; aucun DROP de données n’est autorisé par cette tâche de vérification.

## 12 octobre 2026 — Refactorisation de l’architecture par module

Périmètre : responsabilité technique, puis contexte Central/Tenant/Storefront, puis module métier. Les changements du Module 01 déjà présents avant cette demande sont conservés. Aucun changement métier supplémentaire, aucune nouvelle fonctionnalité Tenant, aucune écriture dans les bases existantes, aucun changement de dépendance, de configuration globale ou de sécurité Windows. L’utilisateur autorise pour cette refactorisation les tests sur des bases MySQL temporaires isolées ; cette exception ne devient pas une règle permanente.

### Organisation réalisée

- Audit des fichiers, dépendances, namespaces, routes, vues, configurations et tests ; lecture des références et versions installées. La correspondance ancien chemin / nouveau chemin des 70 fichiers a été inscrite au §25.2 de l’architecture avant leurs déplacements. Les 70 destinations existent et les anciennes sources sont absentes.
- Regroupement des 52 classes déplacées dans Auth, Users, Authorization, Roles, Countries, Plans, Subscriptions, Tenants, Dashboard et Diagnostics, selon leur contexte. Déplacement des vues et tests concernés ; les Services Users déjà correctement rangés restent en place. L’enum de statut des comptes Tenant rejoint Enums/Tenant/Users sans changement de valeurs.
- Six fichiers de routes centrales ajoutés : central/admin.php, central/admin/{users,tenants,roles,countries}.php et central/diagnostics.php. central.php reste le point d’entrée ; les groupes et protections existants entourent les inclusions. Fortify continue de fournir les routes d’authentification. settings.php, Tenant et Storefront gardent leurs contrats publics.
- Références corrigées dans les imports, dépendances auparavant implicites entre Services voisins, Providers, bindings, callbacks, middleware, configuration Fortify, composants Blade/Livewire, routes, modèles, factories, seeders et tests. Les Models/Policies et factories déjà classés par contexte, les jobs sérialisables, morph maps, classes de compatibilité et infrastructure partagée ne sont pas déplacés.
- AGENTS.md indique le classement durable et les exceptions ; l’architecture §25 décrit les raisons, les déplacements et l’arborescence finale (§25.5). Les références de classes affectées dans le schéma et le diagramme central sont actualisées, sans changement de structure SQL dû à cette refactorisation. Les entrées historiques du suivi sont conservées ; leurs anciens chemins ne sont pas les chemins courants.

### Tests réellement exécutés

Avant déplacement : 41 tests d’authentification réussis. Après déplacement, les cas ciblés des différents lots donnent 84 réussites et 2 échecs, sans prétendre avoir exécuté toute la suite :

| Lot | Réussites | Échecs |
|---|---:|---:|
| Authentification ciblée | 5 | 0 |
| Utilisateurs centraux | 31 | 2 |
| Autorisations centrales | 10 | 0 |
| Rôles | 6 | 0 |
| Pays | 5 | 0 |
| Plan gratuit | 1 | 0 |
| Abonnements | 1 | 0 |
| Création/provisionnement de boutiques | 2 | 0 |
| Dashboard central | 2 | 0 |
| Diagnostics Central/Tenant | 5 | 0 |
| Dashboard Tenant préparé et profils shop | 11 | 0 |
| Écrans et formulaires après réorganisation | 5 | 0 |

Les deux échecs sont dans tests/Central/Feature/Admin/Users/UserAccountManagementTest.php :

- Le test « readers can inspect escaped account information but cannot edit or resend invitations » attend l’affichage du UUID du compte ciblé (assertion ligne 103), absent de la fiche actuelle.
- Le test « staff edits require the staff permission and cannot take over a more privileged account » attend le refus d’édition d’un compte personnel au gestionnaire (assertion ligne 154). La règle globale du Module 01 autorise actuellement cette édition lorsqu’il possède les droits nécessaires et ceux du compte ciblé.

Ces deux échecs ont été reproduits après retour temporaire aux chemins et imports antérieurs des lots Auth/Users, puis les déplacements ont été rétablis. Ils existaient donc avant cette refactorisation. Aucune assertion supprimée, aucun changement de règle métier pour masquer ces divergences. Leur résolution relève d’une demande fonctionnelle distincte.

Deux erreurs introduites pendant les déplacements ont été corrigées avant la fin : namespace du Service Dashboard, puis nom de route du diagnostic central confondu avec le nouveau nom de vue. Les tests concernés et la comparaison des routes réussissent après correction.

### Vérifications finales et limites

- Comparaison des 172 routes avant/après, en normalisant uniquement les nouveaux noms de classes : méthodes HTTP, URL, domaines, noms, actions et middlewares identiques.
- Les 116 fichiers PHP de app respectent leurs chemins PSR-4. Comparaison syntaxique des corps de classes après normalisation des namespaces, imports et références de vues : identiques à l’état initial de cette tâche.
- Syntaxe de 195 fichiers PHP dans app, routes, config, factories, seeders et tests : aucun échec. Recherche des anciennes références de classes : aucun usage restant hors déclarations de namespace et historique documenté.
- Empreintes des 107 fichiers protégés, incluant toutes les migrations, manifestes/verrouillages, .env, phpunit.xml et tests/TestCase.php : inchangées après le formatage final. Les dix liens locaux d’AGENTS.md existent. Relecture des fichiers écrits et git diff --check réussis.
- Un passage final Pint, régénération Composer de l’autoload avec --no-scripts --no-interaction et artisan view:clear --no-interaction exécutés avec PHP 8.5. Les contrôles de routes et de structure des classes réussissent après ces commandes. Aucun PHPStan, build, suite complète, mail réel ou worker lancé. Les migrations/seeders des tests ne concernent que leurs bases temporaires ; aucun migrate/db:seed lancé contre les bases existantes.
- MySQL existant contrôlé en lecture seule : sept comptes, deux boutiques, aucune tâche en attente. Les classes de jobs et leurs contrats sérialisables sont préservés. Aucun contrôle DNS ou rendu de navigateur réel n’est présenté comme réalisé.

La suppression facultative des anciens dossiers vides a été refusée par le contrôle automatique avec le motif « blocked by policy ». Ils restent sur disque ; aucune suppression ni tentative de contournement n’a été effectuée. Ce refus n’empêche pas l’utilisation des fichiers déplacés.

État final : réorganisation effectuée et vérifiée dans le périmètre autorisé. Les deux divergences de tests préexistantes restent signalées. Aucune migration n’est nécessaire pour ces seuls déplacements ; les actions de déploiement du Module 01 mentionnées dans l’entrée précédente ne sont pas exécutées par cette tâche.

## 12 octobre 2026 — Validation du Module 01 avant le Module 02, en cours

Demande : valider entièrement le Module 01 avant de développer le Module 02 d’instructionn.txt. Lecture renouvelée d’AGENTS.md, des sections 1–2 et Modules 01–02, des passages d’architecture/schéma/diagramme concernés et du code réel ; skills Laravel, Fortify et tests consultés. Les changements non commités précédents sont conservés.

Deux attentes historiques dans tests/Central/Feature/Admin/Users/UserAccountManagementTest.php sont adaptées à la règle globale du Module 01, sans changement applicatif : absence de UUID dans le texte visible de la fiche, même si les liens utilisent le UUID public ; saas.users.update permet la modification d’un personnel moins privilégié. Le test conserve les contrôles d’échappement, de protection des secrets et d’accès interdit ; il vérifie aussi l’édition HTTP, la persistance des informations, la conservation du rôle et le refus d’édition du Super Admin par le gestionnaire. Cela corrige des attentes obsolètes identifiées au lot précédent ; la réussite des tests n’est pas encore affirmée.

Contrôles exécutés : syntaxe PHP du test adaptée, formatage Pint ciblé sur ce seul fichier, git diff --check. MySQL existant consulté via Boost en lecture seule : la migration centrale d’extension des statuts d’approbation n’est pas enregistrée ; le catalogue effectif ne contient pas encore toutes les permissions du Module 01. Aucun test MySQL, migration, seeder ou envoi réel de mail lancé pour cette nouvelle tâche.

Point de reprise : trois questions sont en attente. L’autorisation précédente des tests MySQL temporaires était limitée à la refactorisation ; une autorisation est demandée pour valider ces deux modules. L’application à la base centrale existante est proposée uniquement pour 2026_10_12_050215_extend_central_user_statuses_for_approval.php et Database\\Seeders\\Central\\AuthorizationSeeder, après validation des tests, sans réinitialisation. Pour le Module 02, préciser si l’interface autorise les permissions directes natives Spatie, sans recoupement avec les rôles, ou si tous les droits restent attribués uniquement via les rôles : instructionn.txt emploie les deux formulations. Ne pas commencer l’implémentation du Module 02 avant validation du Module 01 ; ne pas exécuter les opérations soumises à confirmation sans réponse.

## 12 octobre 2026 — Module 01 validé et déployé ; Module 02, partie rôles disponible

L’utilisateur autorise explicitement les tests MySQL temporaires pour ces deux modules et l’application des ajouts manquants dans la base centrale. Il confirme l’utilisation exclusive de Spatie pour les autorisations. La réponse « uniquement Spatie » ne tranche pas le choix entre les rôles Spatie seuls et les rôles plus permissions directes natives ; une question précise, avec ces deux choix, reste en attente. Ne pas présenter les actions directes comme réalisées.

### Module 01

- Les 33 tests de tests/Central/Feature/Admin/Users réussissent, avec 247 assertions, après adaptation des deux attentes anciennes. Aucun test ni assertion supprimé pour masquer un défaut.
- La migration 2026_10_12_050215_extend_central_user_statuses_for_approval.php est exécutée seule sur la base centrale, puis Database\Seeders\Central\AuthorizationSeeder. MySQL confirme son enregistrement et le CHECK users.status IN (1,2,3,4,5,6). Les cinq permissions historiques conservent leurs ID et UUID ; sept comptes actifs et deux boutiques restent présents.
- Une autre attente ancienne de CentralManagementTest est alignée sur la consultation dérivée des actions de gestion : l’action users.create encore valide laisse accéder à la liste même si users.view a expiré. Le test vérifie aussi que le droit Spatie users.view lui-même est expiré.
- Un défaut de délégation est corrigé : la consultation dérivée ne permet plus de déléguer une permission expirée ou jamais détenue. CentralUserAccess.ownsPermission relit checkPermissionTo sur le compte actif dans le contexte central ; ownsRolePermissions/mayDelegateRole/mayManageStaff s’appuient sur cette possession native. Six tests ciblés de CentralManagementTest réussissent, avec 48 assertions, y compris la délégation limitée aux droits encore possédés.

### Module 02 disponible

La nouvelle entrée Personnel SaaS utilise le même layout central et ses contrôles Laravel/Spatie. Liste Livewire paginée/recherche/filtre d’état ; fiches sans identifiants internes ni secrets ; invitation Fortify avec rôles Spatie ; modification de l’identité, contacts et langue ; renvoi d’invitation avec permission propre ; inspection des permissions natives, sources et échéances ; attribution/remplacement/retrait des rôles ; renouvellement explicite motivé ; suspension, réactivation et archivage logique. Le personnel se définit par ses rôles centraux, même expirés ; retirer le dernier rôle conserve le compte dans Utilisateurs, sans suppression de données.

Les Controllers sont dans Central/Admin/Staff, les cinq Form Requests Staff dans Central/Users, StaffManagementService/StaffInvitationService dans Services/Central/Users et CentralRoleAssignmentService/CentralPermissionResolver dans Services/Central/Authorization. Les validations et DTOs Users, la livraison des mails, le broker central, les mutations transactionnelles et la Policy User existants sont réutilisés. Les nouvelles vues sont dans central/pages/admin/staff. Aucun modèle, table, colonne, package ou fonctionnalité Tenant ajouté.

Garanties :
- Les permissions de gestion donnent la consultation nécessaire au module, sans ajouter de pivot de lecture ni permettre de déléguer ce droit dérivé. Le menu, les formulaires, les routes, les actions Livewire et les services appliquent les autorisations ; accès direct interdit en 403.
- Une attribution gardée conserve assigned_at ; le renouvellement vérifie la permission renew et la possession des droits du rôle, sans exiger l’attribution d’un nouveau rôle. expected_assigned_at compare l’attribution affichée ; le nouveau début vient seulement du serveur et respecte la précision seconde. Une attribution absente/obsolète est refusée ; une seconde déjà courante n’est pas réécrite.
- Les remplacements auditent les attributions avant/après ; les renouvellements auditent le rôle, les dates et le motif, via Activitylog. Les rôles ordinaires ne se recoupent pas ; Root et l’auto-attribution privilégiée restent protégés. Suspendre/archiver réutilise l’invalidation des accès centraux existante.
- L’échec SMTP après modification est signalé sans masquer l’enregistrement ; aucun mot de passe administrativement imposé. Les tests utilisent uniquement des notifications/transport simulés, sans mail réel.
- Les anciens codes saas.staff.restore/delete sont renommés en saas.staff.activate/archive par le catalogue, avec conservation des ID/UUID et pivots ; les rôles existants ne reçoivent pas automatiquement de nouveaux droits.

Après les tests, AuthorizationSeeder est relancé sur la base centrale pour enregistrer les dix permissions/actions disponibles du personnel, y compris saas.users.roles.assign. Les cinq permissions initiales et les deux permissions du personnel renommées conservent leurs identifiants, vérifiés via Boost. Sept comptes actifs et deux boutiques sont toujours présents ; aucune base boutique migrée, renommée ou recréée. La modification d’instructionn.txt et le commit 1bee130 apparus pendant le travail sont préservés ; aucun commit créé par l’agent.

### Vérifications

- Un premier passage Staff donne 13 réussites et deux échecs : précision seconde de la date testée, puis classe du modèle employée dans NotificationFake. La date serveur est normalisée à la précision SQL, le test utilise une horloge à la seconde, et l’assertion de notification cible le modèle Central réellement notifié. Les trois tests ciblés après correction réussissent, avec 24 assertions.
- Le contrôle combiné final à ce stade exécute 52 cas : 33 Users, 16 Staff et 3 Registration, tous réussis, 397 assertions. Il vérifie notamment le refus après expiration d’une action Livewire, la conservation des dates, le double renouvellement obsolète, la protection des secrets et la panne de livraison simulée. Avec les six cas supplémentaires CentralManagement, 58 cas distincts ont réussi ; les relances ne sont pas comptées comme des cas supplémentaires.
- Les trois tests ciblés sur le remplacement/retrait des rôles réussissent après ajout des propriétés d’audit, avec 19 assertions. Aucun élargissement automatique à toute la suite.
- Pint ciblé sur les fichiers PHP concernés réussit ; les deux fichiers ajustés pour l’audit sont formatés à nouveau. Les nouvelles routes sont chargées : 211 entrées au total, 13 routes canoniques Staff, toutes sous auth:central, avec verified et EnsureActiveCentralAccount via le groupe parent. Les anciens chemins publics restent présents.
- Dernière relecture : syntaxe de 20 fichiers PHP correcte, tous les nouveaux chemins présents, liens d’AGENTS.md valides et git diff --check réussi. Les blocs Mermaid du schéma principal et du diagramme central sont identiques à ceux du commit courant ; seules leurs notes de fonctionnement/déploiement sont actualisées.
- Contrôles MySQL effectués sur la base réelle en lecture seule après les écritures autorisées ; aucun reset, seeding de démonstration, SMTP réel, worker, PHPStan, changement Windows ou dépendance. Aucun rendu de navigateur réel ni test global de toutes les fonctionnalités du SaaS n’est affirmé.

### Point restant

Le Module 02 n’est pas déclaré entièrement terminé : les actions saas.staff.permissions.grant/revoke et le renouvellement d’une attribution directe dépendraient du choix encore attendu. Aucune interface d’attribution directe ni permission sans fonctionnalité correspondante n’est ajoutée pour combler cette ambiguïté. Les attributions directes existantes peuvent être consultées avec les rôles, sans écrire dans model_has_permissions. À la reprise, lire la réponse explicite, mettre à jour le §26 et les documents concernés, puis terminer uniquement ce chemin selon la décision. Les fonctionnalités via rôles déjà livrées restent utilisables et validées ; ne pas recommencer la migration ni les tests inchangés inutilement.

## 13 octobre 2026 — Audit des Modules 01 et 02 et corrections

Demande : vérifier les deux modules et corriger les problèmes constatés. Consignes, instructionn.txt, références métier et architecture consultées ; code des Controllers, Requests, services, Policies, modèles, routes et vues concernés relu. PHP 8.5 de Herd utilisé. L’autorisation des tests MySQL temporaires couvre toujours ces deux modules ; les bases existantes sont uniquement consultées. Les modifications utilisateur d’instructionn.txt et de LegalVerificationService ainsi que le commit 6955822 apparu pendant le travail sont préservés.

Deux défauts reproduits puis corrigés :

- Sur l’ancienne route Utilisateurs, renvoyer une invitation au personnel exigeait seulement le droit de modification, contournant saas.staff.invite.resend. À l’inverse, son détenteur sans droit de modification ne voyait pas le bouton. Des destinataires suspendus/inactifs pouvaient aussi être réinvités. UserPolicy.resendInvitation applique désormais resendStaffInvitation au personnel ; Controller, service sous verrou et bouton utilisent cette capacité. Les règles du renvoi au commerçant, y compris l’erreur de validation d’un compte déjà vérifié, sont conservées.
- Une permission expirée sans rapport avec l’édition empêchait un personnel encore autorisé de modifier sa propre identité : mayManageStaff comparait tous ses droits à ses propres droits valides. La comparaison hiérarchique reste obligatoire pour les autres comptes ; pour soi, l’état actif est relu et la Policy exige toujours le droit de modification. L’auto-attribution des rôles et la modification de son propre état restent interdites. Mot de passe, rôles et échéances sont préservés.

Fichiers applicatifs modifiés : app/Policies/Central/UserPolicy.php, app/Services/Central/Authorization/CentralUserAccess.php, app/Services/Central/Users/UserManagementService.php, app/Http/Controllers/Central/Admin/Users/UserController.php et resources/views/central/pages/admin/users/show.blade.php. Six cas de régression ajoutés à tests/Central/Feature/Admin/Staff/StaffManagementTest.php, dont trois états de destinataire ; le refus dans le service est également vérifié indépendamment du Controller. AGENTS.md, le §26 et les notes d’invitation du schéma sont actualisés ; aucune migration ou structure Mermaid modifiée.

Contrôles réellement exécutés :

- État initial : 100 cas Users/Staff/Auth/Authorization réussis, 566 assertions. Les six nouveaux cas ont ensuite échoué en reproduisant les défauts, puis réussi après correction, avec 34 assertions avant ajout du contrôle de service.
- Passe finale après corrections et formatage : 55 cas Users/Staff réussis, 417 assertions ; trois cas supplémentaires de CentralManagementTest réussis, 19 assertions, sur l’invitation Fortify, la délégation limitée et Root protégé avant sa date de début. Ces contrôles couvrent 109 cas distincts au total ; les relances ne sont pas comptées comme des cas supplémentaires. La passe finale porte sur 58 cas, sans répéter toute l’application.
- Routes chargées : 19 routes canoniques central.users.* (y compris les routes boutique historiques) et 13 central.staff.*, toutes sous auth:central, vérification d’e-mail et EnsureActiveCentralAccount. Les capacités propres aux mutations restent contrôlées dans leurs Requests/services/Policies.
- MySQL existant en lecture seule : sept comptes actifs, deux boutiques, migration d’approbation enregistrée, catalogue Users/Staff sous guard central. Aucun pays parent, utilisateur de pivot central, rôle ou permission orphelin dans les contrôles exécutés ; aucun chevauchement entre rôles ni entre rôle et attribution directe ; toutes les durées de rôle sont entre 1 et 9 999 jours. Les FK locales des utilisateurs et des tables Spatie sont présentes ; model_id conserve le contrat polymorphe sans FK fictive.
- Un passage Pint ciblé sur les cinq fichiers PHP concernés, syntaxe PHP et git diff --check réussis. Aucun PHPStan, build, reset, migration/seeder réel, worker, mail réel ou changement système lancé dans cet audit. Les tests utilisent des notifications simulées et des bases MySQL temporaires isolées.

Limites : les parcours implémentés sont validés par les contrôles ci-dessus, sans promettre l’absence de tout défaut. Livraison réelle SMTP, DNS et parcours dans un navigateur réel non testés. Le point restant de l’attribution directe Spatie dans l’interface du Module 02 demeure ouvert ; aucune décision n’est déduite de la demande de vérification. L’inspection des droits et leur gestion via rôles sont opérationnelles ; le Module 02 complet d’instructionn.txt n’est pas déclaré terminé tant que ce choix n’est pas confirmé et traité.

## 13 octobre 2026 — Contrôle individuel de $this et des dépendances

Demande : vérifier toutes les classes une par une, expliquer l’usage de $this et conserver la convention pour les prochaines interventions. Lecture des instructions, de l’architecture et du suivi ; skills Laravel, Fortify et Livewire consultés. Versions relues dans les packages installés : Laravel 13.34.0, Livewire 4.4.7, Fortify 1.41.0, Spatie Permission 8.3.0 et Tenancy 3.10.1.

Contrôle structurel du code PHP propre au projet : 385 fichiers analysés avec PHP 8.5, sans erreur de syntaxe ; 259 déclarations de classes, traits et enums examinées individuellement par analyse syntaxique, dont 128 dans app, 119 dans database, 10 composants anonymes dans les vues et deux déclarations dans tests. Les modèles, leurs parents et leurs traits sont également confrontés par réflexion pour les références directes à $this. Les accès des traits HasRoleMetadata/HasTimedPermissions proviennent de leurs modèles hôtes Eloquent/Spatie ; guard_name est déclaré dans les modèles utilisateurs des deux contextes. Le nom du modèle User utilise son attribut Eloquent. Aucun accès à $this dans une méthode/fermeture statique trouvé dans ce contrôle.

Le seul cumul trouvé entre dépendance au constructeur et dépendance de méthode concerne UserController : history, update, resendInvitation, changeStatus et destroy réinjectaient UserManagementService. Ces cinq méthodes réutilisent désormais $this->userServices, déjà utilisé par create et store. Aucun appel direct à ces anciennes signatures trouvé dans le projet ; les routes conservent leurs noms et paramètres. Le contrôle final ne trouve plus ce cumul dans les 259 déclarations. Aucun changement de données, schema SQL, permission, package, URL ou architecture des contextes.

Contrôles après correction : cinq tests existants ciblés réussissent, 51 assertions, sur édition, renvoi d’invitation, suspension/réactivation, archivage et historique administratif. Un passage Pint sur le seul Controller concerné réussit. Les deux fichiers JS existants sont de simples points d’entrée/imports, sans classe JavaScript utilisant this. Les stubs sont des modèles de génération, conservés ; vendor, node_modules, caches et vues compilées ne constituent pas des classes métier à refactoriser.

Relecture finale : les 259 chemins de l’inventaire existent, les sept appels réutilisent effectivement la propriété injectée du Controller, sa syntaxe est valide et les 19 routes canoniques central.users.* se chargent. Le diff est contrôlé sans modifier les autres changements déjà présents. Aucune base existante, donnée ou configuration système n’est modifiée.

Convention durable ajoutée dans AGENTS.md et au §27 de l’architecture : utiliser $this pour les membres d’instance et réutiliser les services injectés ; injection de méthode, DTOs, enums, facades, Providers, jobs et cycle Livewire conservent leurs contrats. Aucun singleton ou cache métier ajouté. Aucun gain de performance mesuré ni annoncé. Cet inventaire vérifie la structure et l’accès aux instances ; il ne constitue pas une nouvelle validation fonctionnelle de toutes les fonctionnalités du SaaS.

### Inventaire individuel

Chaque ligne correspond à une déclaration réellement analysée, identifiée par son fichier. Le nombre d’accès à $this est un relevé syntaxique du corps de la déclaration, sans les méthodes héritées et sans l’affectation implicite des propriétés promues. Zéro accès n’est pas une anomalie : le corps peut ne manipuler que des paramètres, des données statiques ou les mécanismes du framework.

| Fichier | Déclaration | Accès à $this | Contrôle structurel |
|---|---|---:|---|
| `app/Actions/Central/Auth/Fortify/CreateNewUser.php` | Classe | 2 | Accès d’instance |
| `app/Actions/Central/Auth/Fortify/PasswordValidationRules.php` | Trait | 0 | Trait sans accès direct à l’instance |
| `app/Actions/Central/Auth/Fortify/ResetUserPassword.php` | Classe | 1 | Accès d’instance |
| `app/Actions/Central/Auth/Fortify/UpdateUserPassword.php` | Classe | 1 | Accès d’instance |
| `app/Actions/Central/Auth/Fortify/UpdateUserProfileInformation.php` | Classe | 1 | Accès d’instance |
| `app/Concerns/HasActivityMetadata.php` | Trait | 0 | Trait sans accès direct à l’instance |
| `app/Concerns/HasPermissionMetadata.php` | Trait | 0 | Trait sans accès direct à l’instance |
| `app/Concerns/HasPublicUuid.php` | Trait | 0 | Trait sans accès direct à l’instance |
| `app/Concerns/HasRoleMetadata.php` | Trait | 33 | Accès du trait dans sa classe hôte |
| `app/Concerns/HasTimedPermissions.php` | Trait | 47 | Accès du trait dans sa classe hôte |
| `app/Concerns/LogsSafeActivity.php` | Trait | 0 | Trait sans accès direct à l’instance |
| `app/Concerns/PasswordValidationRules.php` | Trait | 0 | Trait sans accès direct à l’instance |
| `app/Concerns/ProfileValidationRules.php` | Trait | 2 | Accès du trait dans sa classe hôte |
| `app/Console/Commands/Central/RetryPendingTenantProvisioning.php` | Classe | 2 | Accès d’instance |
| `app/DTOs/Central/Roles/RoleData.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/DTOs/Central/Tenants/TenantData.php` | Classe | 3 | Accès d’instance |
| `app/DTOs/Central/Users/UserData.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/DTOs/Central/Users/UserProfileData.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/DTOs/Central/Users/UserSaveResult.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/DTOs/Tenant/Dashboard/DashboardData.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/DTOs/Tenant/Shop/ShopSettings.php` | Classe | 4 | Accès d’instance |
| `app/Enums/ActivityOriginEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Deployments/OperationEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Deployments/StatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Domains/CertificateStatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Domains/TypeEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Features/PeriodEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Features/QuotaScopeEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Features/ValueTypeEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Subscriptions/PeriodEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Subscriptions/StatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Tenants/StatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Central/Users/StatusEnum.php` | Enum | 1 | Lecture de la valeur de l’enum |
| `app/Enums/Tenant/Users/MembershipStatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/Tenant/Users/StatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Enums/VerificationStatusEnum.php` | Enum | 0 | Valeurs/cas sans accès à l’instance |
| `app/Http/Controllers/Central/Admin/Countries/CountryController.php` | Classe | 4 | Accès d’instance |
| `app/Http/Controllers/Central/Admin/Roles/RoleController.php` | Classe | 2 | Accès d’instance |
| `app/Http/Controllers/Central/Admin/Staff/StaffController.php` | Classe | 6 | Accès d’instance |
| `app/Http/Controllers/Central/Admin/Staff/StaffPermissionController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Central/Admin/Staff/StaffRoleController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Central/Admin/Tenants/TenantController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Central/Admin/Users/LegalVerificationController.php` | Classe | 2 | Accès d’instance |
| `app/Http/Controllers/Central/Admin/Users/UserApprovalController.php` | Classe | 2 | Accès d’instance |
| `app/Http/Controllers/Central/Admin/Users/UserController.php` | Classe | 7 | Service du constructeur réutilisé ; cinq réinjections retirées |
| `app/Http/Controllers/Central/Admin/Users/UserRoleController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Central/Diagnostics/DatabaseDiagnosticsController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Controller.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Storefront/StorefrontController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Tenant/Admin/Dashboard/DashboardController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Controllers/Tenant/Diagnostics/DatabaseDiagnosticsController.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Middleware/Central/Auth/EnsureActiveCentralAccount.php` | Classe | 2 | Accès d’instance |
| `app/Http/Middleware/EnsureApplicationDomain.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Middleware/EnsureLocalDiagnostics.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Middleware/Tenant/FinishTenantRequest.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Http/Requests/Central/Countries/ChangeCountryStatusRequest.php` | Classe | 2 | Accès d’instance |
| `app/Http/Requests/Central/Countries/StoreCountryRequest.php` | Classe | 5 | Accès d’instance |
| `app/Http/Requests/Central/Roles/SaveRoleRequest.php` | Classe | 6 | Accès d’instance |
| `app/Http/Requests/Central/Tenants/StoreTenantRequest.php` | Classe | 6 | Accès d’instance |
| `app/Http/Requests/Central/Users/AssignRolesRequest.php` | Classe | 2 | Accès d’instance |
| `app/Http/Requests/Central/Users/AssignStaffRolesRequest.php` | Classe | 2 | Accès d’instance |
| `app/Http/Requests/Central/Users/ChangeStaffStatusRequest.php` | Classe | 3 | Accès d’instance |
| `app/Http/Requests/Central/Users/ChangeUserStatusRequest.php` | Classe | 3 | Accès d’instance |
| `app/Http/Requests/Central/Users/LegalVerificationRequest.php` | Classe | 3 | Accès d’instance |
| `app/Http/Requests/Central/Users/RenewStaffAssignmentRequest.php` | Classe | 2 | Accès d’instance |
| `app/Http/Requests/Central/Users/StoreStaffRequest.php` | Classe | 1 | Accès d’instance |
| `app/Http/Requests/Central/Users/StoreUserRequest.php` | Classe | 8 | Accès d’instance |
| `app/Http/Requests/Central/Users/UpdateStaffRequest.php` | Classe | 2 | Accès d’instance |
| `app/Http/Requests/Central/Users/UpdateUserRequest.php` | Classe | 7 | Accès d’instance |
| `app/Http/Requests/Central/Users/UserApprovalRequest.php` | Classe | 3 | Accès d’instance |
| `app/Http/Responses/Central/Auth/RegistrationResponse.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Jobs/Central/ProvisionTenantDatabase.php` | Classe | 4 | Accès d’instance |
| `app/Livewire/Central/Auth/Actions/Logout.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Activity.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Country.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Domain.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Feature.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Permission.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Plan.php` | Classe | 1 | Accès d’instance |
| `app/Models/Central/PlanFeature.php` | Classe | 2 | Accès d’instance |
| `app/Models/Central/Role.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Central/Subscription.php` | Classe | 2 | Accès d’instance |
| `app/Models/Central/Tenant.php` | Classe | 5 | Accès d’instance |
| `app/Models/Central/TenantSchemaDeployment.php` | Classe | 1 | Accès d’instance |
| `app/Models/Central/User.php` | Classe | 5 | Accès d’instance |
| `app/Models/PermissionAssignment.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Tenant.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Tenant/Activity.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Tenant/Permission.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Tenant/Role.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Tenant/Shop.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Tenant/User.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/Test.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Models/User.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Notifications/Central/Auth/AccountInvitation.php` | Classe | 2 | Accès d’instance |
| `app/Policies/Central/CountryPolicy.php` | Classe | 5 | Accès d’instance |
| `app/Policies/Central/RolePolicy.php` | Classe | 3 | Accès d’instance |
| `app/Policies/Central/TenantPolicy.php` | Classe | 3 | Accès d’instance |
| `app/Policies/Central/UserPolicy.php` | Classe | 58 | Accès d’instance |
| `app/Providers/AppServiceProvider.php` | Classe | 3 | Accès d’instance |
| `app/Providers/FortifyServiceProvider.php` | Classe | 5 | Accès d’instance |
| `app/Providers/TenancyServiceProvider.php` | Classe | 4 | Accès d’instance |
| `app/Services/Central/Auth/AccountMailDelivery.php` | Classe | 2 | Accès d’instance |
| `app/Services/Central/Authorization/AuthorizationMutation.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Authorization/CentralPermissionResolver.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Authorization/CentralRoleAssignmentService.php` | Classe | 4 | Accès d’instance |
| `app/Services/Central/Authorization/CentralUserAccess.php` | Classe | 10 | Accès d’instance |
| `app/Services/Central/Countries/CountryService.php` | Classe | 5 | Accès d’instance |
| `app/Services/Central/Dashboard/Dashboard.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Diagnostics/DatabaseDiagnostics.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Plans/FreePlanCatalog.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Roles/CentralRoleCatalog.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Roles/RoleManager.php` | Classe | 2 | Accès d’instance |
| `app/Services/Central/Subscriptions/DefaultSubscription.php` | Classe | 1 | Accès d’instance |
| `app/Services/Central/Tenants/TenantCreator.php` | Classe | 2 | Accès d’instance |
| `app/Services/Central/Tenants/TenantDatabaseName.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Tenants/TenantProvisioner.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Central/Users/LegalVerificationService.php` | Classe | 1 | Accès d’instance |
| `app/Services/Central/Users/StaffInvitationService.php` | Classe | 2 | Accès d’instance |
| `app/Services/Central/Users/StaffManagementService.php` | Classe | 6 | Accès d’instance |
| `app/Services/Central/Users/UserApprovalService.php` | Classe | 1 | Accès d’instance |
| `app/Services/Central/Users/UserManagementService.php` | Classe | 22 | Accès d’instance |
| `app/Services/SchemaBlueprint.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `app/Services/Tenant/Dashboard/Dashboard.php` | Classe | 2 | Accès d’instance |
| `app/Services/Tenant/Diagnostics/DatabaseDiagnostics.php` | Classe | 3 | Accès d’instance |
| `app/Tenancy/Bootstrappers/AccountContextBootstrapper.php` | Classe | 12 | Accès d’instance |
| `app/Tenancy/Bootstrappers/NamespacedCacheBootstrapper.php` | Classe | 10 | Accès d’instance |
| `app/Tenancy/Bootstrappers/SpatieContextBootstrapper.php` | Classe | 4 | Accès d’instance |
| `database/factories/Central/CountryFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/DomainFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/FeatureFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/PlanFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/PlanFeatureFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/SubscriptionFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/TenantFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/TenantSchemaDeploymentFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Central/UserFactory.php` | Classe | 2 | Accès d’instance |
| `database/factories/Tenant/ShopFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/factories/Tenant/UserFactory.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/0000_01_01_000000_create_countries_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/0001_01_01_000000_create_users_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/0001_01_01_000001_create_cache_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/0001_01_01_000002_create_jobs_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2024_01_01_000000_create_passkeys_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2025_08_14_170933_add_two_factor_columns_to_users_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_064409_create_tenants_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_064410_create_domains_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_064411_create_contact_verifications_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090509_create_features_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090510_create_permission_tables.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090511_extend_permission_tables_with_aydra_metadata.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090515_create_admin_restrictions_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090516_create_plans_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090517_create_plan_features_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090518_create_subscriptions_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090519_create_feature_usage_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090520_create_geographic_areas_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090521_create_activity_log_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090521_extend_activity_log_with_aydra_metadata.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090522_create_tenant_schema_deployments_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090523_create_saas_invoices_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090524_create_saas_invoice_lines_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090525_create_saas_billing_settings_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090526_create_saas_document_deliveries_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090527_create_saas_transfers_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090528_create_media_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090529_create_shipping_carriers_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090530_create_carrier_geo_mappings_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_090531_create_pickup_points_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_091357_add_documented_foreign_keys.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_06_091358_add_documented_row_constraints.php` | Classe anonyme | 2 | Accès d’instance |
| `database/migrations/central/2026_10_06_093039_protect_documented_identities_and_history.php` | Classe anonyme | 2 | Accès d’instance |
| `database/migrations/central/2026_10_06_093555_add_documented_conditional_indexes.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/central/2026_10_12_050215_extend_central_user_statuses_for_approval.php` | Classe anonyme | 5 | Accès d’instance |
| `database/migrations/tenant/2026_10_06_064410_create_local_accounts_tables.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_070257_protect_local_identifiers_and_ownership.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_074030_create_cache_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_074031_create_jobs_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_074032_create_tenants_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_074033_create_domains_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090515_create_shop_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090516_create_shop_addresses_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090517_create_content_pages_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090518_create_media_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090519_create_categories_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090520_create_products_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090521_create_product_variants_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090522_create_product_options_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090523_create_variant_option_values_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090524_create_product_tags_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090525_create_product_promotions_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090526_create_product_reviews_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090527_create_visitors_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090528_create_visit_sessions_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090529_create_navigation_events_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090530_create_carts_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090531_create_cart_items_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090532_create_orders_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090533_create_order_revisions_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090534_create_order_items_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090535_create_order_history_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090536_create_stock_movements_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090537_create_order_returns_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090538_create_return_items_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090539_create_shipping_providers_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090540_create_shipping_rates_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090541_create_free_shipping_rules_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090542_create_shipments_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090543_create_shipment_events_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090544_create_carrier_operations_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090545_create_carrier_operation_attempts_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090546_create_collections_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090547_create_remittance_statements_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090548_create_carrier_settlement_lines_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090549_create_expenses_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090550_create_customer_adjustments_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090551_create_order_documents_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090552_create_activity_log_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090552_extend_activity_log_with_aydra_metadata.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090553_create_carrier_fees_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090554_create_carrier_receivables_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090555_create_collection_entries_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090556_create_invoices_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090557_create_order_incidents_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090558_create_order_incident_details_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090559_create_billing_rules_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090600_create_sales_terms_acceptances_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090601_create_billing_obligations_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090602_create_commercial_corrections_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090603_create_commercial_correction_lines_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090604_create_permission_tables.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090605_extend_permission_tables_with_aydra_metadata.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090609_create_team_invitations_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090610_create_carrier_accounts_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_090611_create_carrier_remittance_batches_table.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_091358_add_documented_foreign_keys.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/migrations/tenant/2026_10_06_091359_add_documented_row_constraints.php` | Classe anonyme | 2 | Accès d’instance |
| `database/migrations/tenant/2026_10_06_093039_protect_documented_identities_and_history.php` | Classe anonyme | 2 | Accès d’instance |
| `database/migrations/tenant/2026_10_06_093556_add_documented_conditional_indexes.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `database/seeders/Central/AuthorizationSeeder.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/seeders/Central/CountrySeeder.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/seeders/Central/FreePlanSeeder.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `database/seeders/Central/LocalAuthenticationSeeder.php` | Classe | 3 | Accès d’instance |
| `database/seeders/Central/LocalDevelopmentSeeder.php` | Classe | 42 | Accès d’instance |
| `database/seeders/DatabaseSeeder.php` | Classe | 2 | Accès d’instance |
| `database/seeders/LocalFixtureSeeder.php` | Classe | 1 | Accès d’instance |
| `database/seeders/Tenant/LocalDevelopmentSeeder.php` | Classe | 265 | Accès d’instance |
| `resources/views/central/pages/admin/staff/index.blade.php` | Classe anonyme | 6 | Accès d’instance |
| `resources/views/central/pages/admin/users/index.blade.php` | Classe anonyme | 6 | Accès d’instance |
| `resources/views/central/pages/settings/two-factor/⚡recovery-codes.blade.php` | Classe anonyme | 5 | Accès d’instance |
| `resources/views/central/pages/settings/⚡appearance.blade.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `resources/views/central/pages/settings/⚡delete-user-form.blade.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `resources/views/central/pages/settings/⚡delete-user-modal.blade.php` | Classe anonyme | 3 | Accès d’instance |
| `resources/views/central/pages/settings/⚡profile.blade.php` | Classe anonyme | 5 | Accès d’instance |
| `resources/views/central/pages/settings/⚡security.blade.php` | Classe anonyme | 25 | Accès d’instance |
| `resources/views/central/pages/settings/⚡two-factor-setup-modal.blade.php` | Classe anonyme | 22 | Accès d’instance |
| `resources/views/central/pages/⚡dashboard.blade.php` | Classe anonyme | 0 | Aucun accès direct requis par le corps existant |
| `tests/Tenant/Tenancy/TenantTestSeeder.php` | Classe | 0 | Aucun accès direct requis par le corps existant |
| `tests/TestCase.php` | Classe | 1 | Accès d’instance |

## 13 octobre 2026 — Photos de profil avec avatar commun par défaut

Demande : permettre une photo facultative pour tous les utilisateurs et afficher un avatar par défaut si elle n’est pas choisie ou est retirée. Périmètre explicitement confirmé : central fonctionnel, boutique préparée sans activer sa connexion/dashboard. Tests MySQL temporaires ciblés autorisés ; les bases existantes restent en lecture seule. La conception, les tables media et leurs contraintes, les interfaces et versions installées ont été vérifiées avant création ; Laravel/Fortify/Livewire/Flux/tests et la documentation officielle du stockage, de la validation et des signatures sont consultés.

### Réalisation

- Modèles Media Central/Tenant avec leur connexion explicite ; HasProfilePhoto partagé sur les deux modèles User. La relation profilePhoto utilise model_type/model_id, collection profile_photo et principal actif dans media de la même BDD. Le modèle ne crée pas un nouvel ID parent. Le getter profile_photo_url fournit l’image personnelle au central ou public/default-avatar.svg en son absence. Aucun champ, migration, table, ligne par défaut ou compte de démonstration ajouté.
- Profil central : formulaire facultatif d’image, remplacement et retrait via Controller Central/Settings, Form Request Central/Users, service Central/Users et routes du fichier settings existant. Formats JPG/PNG/WebP, MIME réel, 2 Mo et 4096 pixels par côté ; SVG et contenu invalide refusés. Les propriétés du parent, le disque et la collection proviennent du serveur.
- Avatar dans les menus mobile/desktop, les listes et fiches Utilisateurs/Personnel. Les listes préchargent la relation minimale pour éviter une requête de photo par ligne. Aucune mesure globale de performance n’est annoncée.
- Un compte actif modifie uniquement sa propre photo ; consulter celle d’un autre compte exige l’autorisation existante Users ou Staff. Fichiers sur le disque local privé, URL Laravel signée relative conservant l’hôte/port courant, auth:central/compte actif, signature et Policy lors de la lecture ; réponse privée sans cache avec MIME maîtrisé. Aucun storage:link, nouveau package ou configuration Windows nécessaire.
- Remplacement/retrait sous verrou utilisateur et transaction ; ancien média archivé, octets supprimés après commit. Une erreur de sauvegarde restaure l’ancien état et supprime le nouveau fichier. Nettoyage impossible signalé dans les logs avec le UUID du média et sans chemin/contenu privé. Activitylog trace profile_photo_updated/removed ; identité, statut, contacts, mot de passe, rôles et leurs dates ne changent pas.
- Tenant : modèle, relation et getter préparés ; composant account-summary affiche l’avatar commun. Aucune route d’authentification, dashboard ou mutation de photo Tenant activée. Même si un média de test existe localement, le getter renvoie le défaut tant que la route Tenant est absente ; aucune photo centrale n’est copiée en boutique.
- Schéma principal, diagrammes central/boutique, §28 de l’architecture et AGENTS.md actualisés. Nouvelle relation POLY Users/media par contexte, sans FK SQL universelle ; tous les blocs de champs existants restent identiques. Le comptage réel donne 29/73 relations POLY au central/en boutique. Le texte central annonçait déjà 29 avant cet ajout, alors que le Mermaid en contenait 28 ; le comptage final est vérifié contre les relations dessinées. Les modifications préexistantes des autres tâches sont conservées.

### Vérifications et limites

- Première passe : 16 cas, 13 réussis ; trois assertions comparaient les attributs partiels d’une factory au modèle complet relu en BDD. La référence est relue avant modification, puis les trois cas et l’isolation Tenant réussissent : quatre cas, 100 assertions. Aucun comportement ni assertion retiré pour masquer un défaut.
- Passe centrale après correction : 24 cas réussis, 199 assertions, incluant les photos, les cinq tests du profil existant et trois parcours d’affichage Users/Staff. Après raccordement de la signature obligatoire, six cas de lecture/upload/accès/signature réussissent, 91 assertions. Après renforcement du cas parent réel (users.id=2 et media.id=1), les trois formats réussissent, 78 assertions.
- Au total, 26 cas distincts ont été validés, sans compter les relances : 18 dédiés à la photo/préparation Tenant et huit parcours existants. Ajout/remplacement/retrait, état du compte, accès refusé, injection d’un autre parent ignorée, formats/poids/dimensions, panne disque, rollback SQL, fichier absent, signature modifiée et réelle isolation de deux BDD boutiques avec mêmes IDs locaux sont couverts. Le test Tenant utilise le stockage de fixtures isolé et nettoie ses deux bases via le contrôle de préfixe existant.
- PHP 8.5 exclusivement. Pint ciblé, syntaxe de 14 fichiers PHP, SVG vérifié comme XML, routes chargées avec leurs protections et diff relu. Comparaison des champs des trois documents avec Git : identiques ; relation photo présente une fois par document, migrations inchangées. Les générateurs Artisan ont créé les fichiers aux chemins documentés.
- MySQL réel consulté en lecture seule : sept comptes, deux boutiques, aucune ligne profile_photo ni parent de photo orphelin. Les comptes existants ont donc automatiquement l’avatar commun via le code, sans écriture SQL. Aucun reset, migrate/db:seed réel, SMTP, worker, build, PHPStan ou modification système exécuté.

Fonctionnalité centrale raccordée et vérifiée par tests HTTP/Laravel ; aucun navigateur réel ni résolution DNS n’est présenté comme testé. Aucun déploiement ou commit créé. La future interface Tenant reste à développer uniquement lorsqu’elle sera demandée. Aucune migration à lancer pour utiliser la photo centrale puisque media existe déjà.

## 13 octobre 2026 — Module 03, gestion des rôles SaaS

Demande : ajouter le Module 03 d’instructionn.txt en conservant les règles métier, les cinq permissions historiques et les tables Spatie. Git propre au début de cette demande ; les travaux précédents sont préservés. Versions installées vérifiées : Laravel 13.34.0, Permission 8.3.0, Activitylog 5.0.0, Livewire 4.4.7 et Flux 2.20.1 ; PHP 8.5 exclusivement.

- CentralRoleCatalog ajoute les huit actions roles.* et leur groupe. VIEW_ACTIONS donne la consultation nécessaire aux actions de gestion sans lui conférer une possession délégable. Le seeder existant conserve les lignes et les attributions historiques ; aucun rôle existant n’est automatiquement enrichi.
- RolePolicy distingue consultation du rôle, composition et bénéficiaires, création, identité, composition, durées et suppression. Les rôles système/protégés restent exclus des mutations, même pour le Super Admin. Ses droits implicites existants restent utilisables avant l’amorçage du nouveau catalogue. Toute manipulation déléguée exige la possession actuelle des droits du rôle.
- RoleController/SaveRoleRequest/RoleData/RoleManager réutilisés dans leurs dossiers Roles. Ajout de DeleteRoleRequest, de la fiche show et de deux routes show/destroy ; noms et URL existants conservés. Liste et bénéficiaires paginés ; compositions chargées seulement lorsque consultables. Pas de nouvelles tables, migrations, dépendances ou fonctionnalités Tenant.
- L’édition conserve les champs omis et contrôle séparément chaque différence. La modification du nom ne peut pas remplacer les permissions ; modifier une composition conserve les durées des droits gardés et donne 9999 jours aux ajouts sans durée explicite. Changer une durée ne modifie jamais assigned_at. La version change seulement si la composition/durée change ; une répétition identique ne crée pas un nouvel audit de mutation.
- Mutations sous AuthorizationMutation et verrou central existant, contrôles de noms/signatures, bornes 1..9999, recoupements et droits réellement possédés. Les bénéficiaires archivés sont aussi verrouillés et contrôlés. Suppression inutilisée : refus des attributions, même expirées ou archivées, et des références restantes. La référence historique admin_restrictions.target_role_id existe réellement ; elle est seulement protégée contre une suppression, sans réintroduire ce module dans la cible. Spatie supprime les pivots de composition ; Activitylog conserve un instantané filtré du rôle supprimé.
- AGENTS.md, architecture §29 et schéma principal C2 actualisés ; les champs, migrations et diagrammes SQL sont inchangés.

### Contrôles effectués et point de reprise

- Syntaxe de neuf fichiers PHP vérifiée ; routes chargées, 21 déclarations couvrant les sept routes sur les trois domaines centraux ; diff contrôlé. Un seul passage Pint sur les fichiers modifiés.
- Rendu Blade des pages liste, création et fiche Root exécuté avec le compte central existant en mémoire et des lectures SQL seulement. Le premier essai Console omettait le partage du sac d’erreurs assuré normalement par le middleware web ; le second fournit ce contexte de rendu et les trois pages passent. Ce contrôle n’est pas un test HTTP ni une preuve navigateur.
- Avant accord : RoleManagementTest.php contient 30 cas reconnus par Pest --list-tests : capacités distinctes, accès refusés, expiration, droits dérivés non délégables, limites de durée, champs protégés, conservation des attributions, identité/composition uniques, recoupement direct, suppression inutilisée/référencée et préservation par seeder. Ils sont alors préparés, mais pas encore exécutés.
- Avant accord : MySQL réel consulté en lecture seule, sept comptes, deux boutiques, quatre rôles, deux attributions de rôle ; zéro permission saas.roles.*. Aucun migrate, db:seed, test créant une base, envoi SMTP, worker ou changement système exécuté durant cette préparation.

### Finalisation après accord explicite

L’utilisateur confirme « tas mon accord asy », couvrant les deux demandes : tests MySQL temporaires ciblés et application du seeder central. Cette autorisation concerne le Module 03 ; elle ne devient pas une autorisation générale de réinitialisation ou de seeding de démonstration.

- Première exécution des 30 cas : 29 réussis, 176 assertions ; un cas échoue dans sa préparation, car le CHECK central interdit déjà guard_name=tenant. Correction du test uniquement : vérifier les deux refus SQL réels pour permissions/roles, puis fournir le modèle non persisté au binding Laravel pour vérifier les mêmes refus HTTP. Aucune contrainte, assertion de refus ou protection applicative retirée. Ce cas corrigé passe seul : neuf assertions.
- Dix cas existants de CentralManagementTest.php passent, 67 assertions : rendu des formulaires, unicité des noms/compositions, recoupements entre rôles, changement de durée sans remise à zéro, consultation expirée et délégation valide, absence d’élévation de privilèges, attributions conservées, lecteur/e-mail non vérifié, attribution Root protégée et délégation globale. Au total, 40 cas distincts validés sur des bases MySQL temporaires, sans relancer les 29 cas inchangés.
- PHP 8.5 utilisé, syntaxe et diff contrôlés ; Pint limité au seul fichier de test corrigé. Pas de PHPStan, build, suite complète ou modification de l’application nécessaire après ces tests.
- Connexion effective avant seeder : environnement local, mysql, base aydra, tenancy non initialisé. Commande exécutée avec succès : `php artisan db:seed --class="Database\Seeders\Central\AuthorizationSeeder" --database=mysql --no-interaction`, avec l’exécutable PHP 8.5 documenté.
- Comparaison SQL avant/après : huit permissions saas.roles.* ajoutées, guard central ; 29 permissions précédentes conservées intégralement, dont les cinq historiques avec leurs ID/UUID. Les sept comptes et deux boutiques restent identiques pour les champs d’identité/état comparés. Les quatre rôles sont intégralement inchangés ; six lignes role_has_permissions, deux attributions model_has_roles avec leurs dates et zéro attribution directe également identiques. Aucune durée hors de 1..9999.
- Après seeder, les pages liste/création/fiche Root sont rendues avec les données réelles en lecture seule ; les huit nouvelles cases de permissions sont effectivement présentes dans le formulaire. Pas de navigateur réel ni de résolution DNS annoncés comme testés.
- Les bases créées par ces exécutions temporaires sont nettoyées. Une ancienne base de test dont les tables datent du 12 octobre 2026 reste présente ; elle ne provient pas de cette intervention et n’est pas supprimée. Aucun compte/boutique de démonstration recréé, aucune migration réelle, envoi SMTP, modification système ou modification des BDD boutiques.

Module 03 raccordé et validé dans ce périmètre. Aucun seeder ni migration supplémentaire requis pour les huit permissions centrales maintenant présentes. La gestion directe de permissions du Module 02 conserve sa clarification ouverte ; elle n’est pas ajoutée par ce module.

## 13 octobre 2026 — Module 04, catalogue des permissions

Historique : le catalogue central a été installé avec recherche, pagination, autorisations et réutilisation des tables Spatie. Dix cas avaient été vérifiés sur cette version initiale. Ses anciens parcours d’édition et de détail ont ensuite été retirés le 14 octobre ; leurs résultats historiques ne prouvent pas le comportement actuel. Les règles applicables sont maintenant le catalogue consultatif du §30 et les décisions ci-dessous. Seule la capacité de consultation reste prévue, sans recréer de données ni de système d’autorisation parallèle.

## 14 octobre 2026 — Module 05, administration des boutiques

Demande : réaliser le Module 05 d’instructionn.txt. L’utilisateur autorise les tests MySQL temporaires ciblés et le seeder des nouvelles permissions centrales, en conservant les bases boutiques existantes. Le développement commence sur un Git propre après le Module 04. Versions conservées : Laravel 13.34.0, PHP 8.5, Tenancy 3.10.1, Permission 8.3.0 et Activitylog 5.0.0.

- Menu Boutiques, liste/recherche paginée par nom, slug, propriétaire ou domaine, filtre d’état incluant les archives. Fiche centrale : propriétaire, domaines, statut, abonnement applicable, nom de base et préparation technique. Aucun secret ni catalogue/commande local affiché. Consultation de l’historique administratif avec Activitylog et capacité propre.
- Huit permissions saas.tenants.* ajoutées à CentralRoleCatalog, groupe Boutiques. Chaque action de gestion donne la consultation nécessaire sans donner de lecture délégable ; aucune attribution automatique aux rôles existants. Les contrôles sont présents dans les Form Requests, Policies, services et boutons.
- La décision ancienne interdisant toute création par le personnel est remplacée : un administrateur doté de saas.tenants.create peut choisir un commerçant actif et créer pour lui. Le personnel SaaS reste exclu des propriétaires. Les anciennes routes central.users.tenants.* sont conservées. TenantCreator conserve l’idempotence et le quota ; sa lecture courante compte désormais les lignes parentes verrouillées, y compris les réservations en attente/en échec. Le provisionneur existant renseigne aussi provisioned_at à la réussite, sans activer l’authentification locale.
- Modification limitée à shop_name/internal_label avec comparaison de profile_version. UUID, propriétaire, slug/domaines, nom de base et identité de création restent protégés. Renommage et intention SyncTenantProfile enregistrés dans la même transaction centrale ; projection des seuls nom/version dans le singleton exact, messages anciens ou rejoués sans régression. Commande aydra:sync-tenant-profiles pour réenfiler un rattrapage technique.
- Suspension, activation et archivage sous verrou central/propriétaire/boutiques ; comparaison de l’état affiché et refus d’une opération technique en cours. Réactivation : compte commerçant actif, e-mail et dossier professionnel vérifiés, schéma/profil et référence UUID du propriétaire local préparés, quota applicable. Les priorités classent toutes les boutiques non archivées d’un propriétaire, sans doublon. Le recalcul applique le repli gratuit si le payant a expiré et ne réactive pas une suspension/préparation/échec.
- Archivage : status=9/deleted_at, annulation des déploiements en attente, conservation des domaines, de la BDD et de ses données. Aucun appel Tenant::delete ni pipeline de suppression physique Stancl. EnsureTenantAvailable contrôle domaine/tenant/propriétaire avant InitializeTenancyByDomain ; refus des archives/suspensions/comptes inactifs et réponse 503 pour une préparation sans BDD. Storefront et authentification Tenant restent en préparation.
- Classement MVSC : Controller Central/Admin/Tenants, Requests et Services Central/Tenants, Policy centrale existante, job et commande techniques centraux, middleware Tenant, vues central/pages/admin/tenants et routes admin/tenants.php. Architecture §31, schéma §7.1 et AGENTS.md actualisés. Aucun changement de tables, migrations, diagrammes ou dépendances.

### Vérifications et état réel

- Dix-sept cas distincts validés : treize centraux du nouveau module, un scénario d’intégration MySQL et trois régressions existantes de CentralManagementTest. Couverture : huit capacités indépendantes, droits expirés, accès interdit, recherche littérale, champs/identités protégés, quota/idempotence, état obsolète, préparation manquante, déploiement en cours, priorités invalides/d’un autre propriétaire et historique séparé.
- Première passe centrale : onze des douze cas passent. Le dernier comparait l’e-mail du compte connecté, présent dans le menu, à la liste des propriétaires ; correction de l’assertion pour contrôler réellement cette liste. Cas relancé avec les trois régressions existantes : quatre réussites, 47 assertions.
- Première intégration : les trois bases sont préparées, puis l’assertion d’activation échoue car le changement de contexte oublie les guards et efface la connexion simulée actingAs. Le test authentifie explicitement chaque requête centrale et vérifie la redirection précise. Relance réussie : un cas, 88 assertions. Trois BDD temporaires réellement migrées par le worker central ; singleton/compte propriétaire UUID, passage de trois boutiques payantes à deux gratuites après expiration, suspension conservée, réactivation sous priorité, deux renommages/rejeu/mauvais UUID, isolation des profils et archivage sans DROP vérifiés. Les erreurs sont corrigées dans les tests, sans retirer les assertions ni modifier les protections de Tenancy.
- La dernière relecture relève le contrat historique de l’accueil public : conserver 503 pour une boutique suspendue ou un propriétaire inactif. Le middleware bloque la connexion locale, conserve ce message sans identifiants et refuse les autres routes en 403 ; archive/domaine non valide restent en 404. Cas central supplémentaire validé, 8 assertions, sans BDD locale créée ; son fixture utilise forceFill pour modifier le statut protégé. Scénario d’intégration relancé après cette correction et validé avec 90 assertions, incluant l’accueil 503 et le diagnostic interdit. Aucun ancien test ni assertion supprimé.
- PHP 8.5 : syntaxe des 28 fichiers PHP/Blade concernés vérifiée. Pint sur les fichiers suivis modifiés, puis sur les nouvelles classes/tests non suivis ; pas de PHPStan, build ou suite complète. Routes vérifiées : dix nouvelles routes centrales, trente déclarations pour les trois domaines, plus les deux routes historiques conservées.
- Connexion réelle vérifiée avant seeder : local, mysql, aydra, contexte tenant inactif. AuthorizationSeeder exécuté avec succès, --database=mysql --no-interaction. Comparaison intégrale SQL avant/après : exactement huit permissions centrales ajoutées, total 47 ; les 39 permissions antérieures restent identiques, ainsi que les sept users, deux tenants, deux domains, huit lignes subscriptions, quatre roles, six compositions, deux attributions de rôle et zéro attribution directe. Aucune permission automatiquement ajoutée aux rôles existants.
- Six vues du module rendues avec les données réelles en lecture seule, dont les boutons de suspension/archivage et le formulaire de priorités. Le formulaire de rôle contient les huit permissions et leurs UUID. Le contrôle bloque tout SQL autre que SELECT/SHOW/EXPLAIN ; cache en mémoire uniquement dans ce processus de vérification pour éviter des écritures cache. Ce contrôle n’est pas un test de navigateur ou de DNS.
- Bases temporaires de ces exécutions nettoyées ; seule l’ancienne base de test datée du 12 octobre reste présente et n’est pas supprimée. File réelle vide après intervention. Aucun worker réel, migration réelle, création/renommage/archivage d’une boutique existante, SMTP ou changement système exécuté.

Module 05 raccordé et validé dans ce périmètre. Accès : http://aydra.localhost/admin/tenants, URL résolue par Boost. La création physique et la projection des noms utilisent la file centrale existante ; un worker php artisan queue:work central-provisioning doit la traiter. Aucune migration ni relance de seeder supplémentaire requise pour les huit permissions désormais présentes. Le Module 06 et les interfaces Tenant restent hors de cette intervention.

## 14 octobre 2026 — Catalogue des permissions consultatif

Décision explicite : dans le Catalogue des permissions, supprimer Actions, Informations et Modifier ; seule la consultation avec recherche/pagination reste disponible. Cette décision remplace l’édition des libellés décrite dans l’entrée historique du Module 04, sans retirer la gestion distincte des rôles SaaS.

- La liste contient uniquement Groupe, Code technique et Libellé. Anciennes routes, méthodes d’édition, vue de détail et Form Request d’édition retirés, sans supprimer de test. PermissionPolicy.update refuse toute édition, y compris pour le Super Admin. La conservation transitoire de la capacité d’édition a été remplacée par sa suppression complète à la demande explicite suivante ; seule la capacité de consultation demeure.
- AGENTS.md, architecture §30, schéma C2 et instructionn.txt mis à jour. Les neuf tests du catalogue sont adaptés au comportement consultatif, sans exécution de Pest : aucune nouvelle autorisation de tests MySQL temporaires n’a été demandée ni utilisée.
- Contrôles exécutés : six fichiers PHP/Blade vérifiés en syntaxe, Pint ciblé, absence d’anciennes références dans app/routes/resources/tests, diff et routes relus. Seule la route GET/HEAD index existe pour les trois domaines centraux.
- Vérification HTTP Laravel avec données existantes et blocage de toute écriture SQL : catalogue filtré en 200, trois colonnes, zéro lien/bouton dans le tableau, aucun champ d’édition du libellé, Policy de modification refusée ; anciennes URL GET/PUT/PATCH/DELETE en 404 et libellé conservé. Cache et sessions en mémoire uniquement dans le processus de contrôle. Aucun navigateur réel, seeder, migration, nouvelle BDD ou modification de données existantes exécuté.

## 14 octobre 2026 — Suppression complète de la capacité inutile du catalogue

L’utilisateur demande explicitement de retirer aussi la permission d’édition du libellé du code, de la BDD et de toute la documentation. Cette décision remplace sa conservation transitoire. Le catalogue, son groupe et les formulaires de rôle proposent seulement saas.permissions.view ; aucune consultation dérivée de l’ancienne capacité n’est conservée. Les tests sont adaptés et les références obsolètes retirées des instructions, du schéma, de l’architecture et du suivi historique.

Suppression ciblée effectuée dans MySQL aydra via le modèle central Spatie et AuthorizationMutation, sous verrou et après vérification d’absence de rôle/attribution directe. Les événements natifs et l’invalidation du cache sont conservés ; l’audit de sécurité n’est pas effacé. Aucun seeder, migration ou changement des BDD boutiques exécuté.

Vérifications : une ligne supprimée, total 47 → 46 ; les 46 autres permissions sont intégralement identiques, ainsi que les quatre rôles, six compositions, deux attributions de rôle et zéro attribution directe. Le formulaire réel de création de rôle est rendu en lecture seule et propose uniquement la consultation du catalogue ; le catalogue du seeder fait de même. Recherche globale du dépôt hors dépendances, Git et stockage généré : aucune ancienne référence technique restante. Syntaxe, Pint ciblé et diff vérifiés ; pas de tests MySQL temporaires exécutés.

## 14 octobre 2026 — Comptes commerçants séparés du personnel et navigation des rôles

Décisions : un commerçant ne reçoit aucun rôle ou permission SaaS et ne devient pas personnel par les formulaires d’attribution. Les premiers rôles sont appliqués uniquement à la création explicite d’un nouveau compte Personnel SaaS. Conserver le type actuel déduit des rôles centraux, sans colonne inventée. Modifier/renouveler les rôles exige un destinataire déjà personnel, sans boutique même archivée ; le Super Admin suit cette restriction. Les droits et rôles locaux Tenant restent indépendants.

- UserPolicy bloque l’attribution aux commerçants dans Utilisateurs et Personnel ; UserManagementService relit/verrouille le compte et autorise avant la résolution des rôles. Une création commerçant ne passe plus par syncRoles vide. StoreUserRequest interdit les rôles d’un commerçant ; création/édition refusent les champs de permissions, guard et changement de type incompatibles. Les permissions directes Spatie sont refusées pour un central_user sans rôle et ne lui donnent aucun accès effectif. Retirer le dernier rôle du personnel retire aussi les permissions directes résiduelles, avec audit dans la même transaction.
- Fiche commerçant : section Rôles SaaS retirée, boutons d’attribution masqués par la Policy ; la liste générale emploie Type de compte comme en-tête. Les routes directes et services refusent la conversion. Le parcours de création/invitation du personnel et la modification de ses rôles existants restent disponibles.
- Navigation demandée pendant cette intervention : lien Rôles de [nom] sur la page Permissions de [nom], sous assignStaffRoles ; bouton Rôles retiré du partiel des actions Personnel SaaS ; bouton Créer un rôle dans le catalogue, sous RolePolicy.create et vers le Module 03. Aucune nouvelle route ni mutation de permission ajoutée.
- Contrôles en lecture seule sur MySQL aydra, avec SQL d’écriture bloqué, cache/sessions en mémoire et contexte HTTP Laravel de test dans le seul processus de contrôle : les cinq commerçants présents à l’instant du contrôle refusent vingt capacités de rôles/inspection et cinq vérifications de permission administrative. Les quatre GET/PUT des deux interfaces retournent 403 ; les deux services d’attribution et les appels natifs d’ajout/synchronisation de permissions directes sont refusés. Fiche sans section/lien de rôles ; validation de création commerçant avec rôle rejetée, validation d’une création personnel avec rôle acceptée, sans enregistrer de compte ni envoyer de mail.
- Rendus et navigation vérifiés : page des permissions du personnel en 200 avec son lien Rôles, formulaire de rôles du personnel en 200, ancien lien absent du partiel des actions, catalogue en 200 avec Créer un rôle. Un commerçant reçoit 403 sur Utilisateurs, Rôles, Personnel et Catalogue. Une première tentative HTTP en environnement local était arrêtée par CSRF avant la Policy sur PUT ; les contrôles suivants emploient le contexte de test Laravel en mémoire, sans désactiver CSRF dans l’application ou modifier sa configuration persistante.
- Tests existants adaptés et cas de refus ajouté pour éviter les anciennes attentes de conversion commerçant/personnel ; tests de navigation et retrait des permissions directes lors du dernier rôle complétés. Pest/MySQL temporaires non exécutés faute d’accord pour cette nouvelle intervention ; pas de seeder, migration, modification des comptes/boutiques existants ou commande destructive. Syntaxe de vingt et un fichiers PHP/Blade concernés vérifiée, Pint et diff contrôlés. Architecture §26/§30, schéma et AGENTS.md actualisés.

## 18 octobre 2026 — Priorités des boutiques dans l’édition du commerçant

- Décision utilisateur : déplacer le classement depuis Informations de la boutique vers Modifier l’utilisateur. Le formulaire indépendant affiche le nom du commerçant et uniquement ses boutiques non archivées, avec leur état et leur priorité ; il est absent pour le personnel SaaS et les comptes sans boutique. La permission saas.tenants.priority.update reste nécessaire en plus de l’accès à l’édition du compte.
- UserController charge la relation tenants du compte concerné ; TenantController ne charge plus ce classement pour la fiche boutique. Le traitement existant conserve ses validations, ses autorisations et son contrôle du propriétaire. Après enregistrement, retour à l’édition du propriétaire si autorisée, sinon à la fiche boutique. Architecture §31 actualisée ; aucun changement de schéma ou de permission.
- Contrôles réalisés : rendu des dix pages de modification commerçant et des quatre fiches boutique présentes, avec SQL limité à SELECT/SHOW/EXPLAIN et cache/session en mémoire. Champs de priorité comparés aux boutiques du propriétaire, ancien formulaire absent, formulaire absent pour le Super Admin. Aucune écriture en BDD. Syntaxe des deux contrôleurs, Pint et diff vérifiés. Aucun test créant une base MySQL temporaire ni test d’enregistrement réel exécuté pour cette intervention.

## 18 octobre 2026 — Informations professionnelles facultatives

- Décision explicite : forme juridique, activité, NIF, NIS, registre de commerce, carte artisan et adresse professionnelle restent facultatifs, même pour valider le dossier. Capital également facultatif comme auparavant. L’édition affiche des descriptions et des exemples fictifs en placeholders, sans les enregistrer comme données.
- LegalVerificationService supprime le contrôle de complétude ; la validation/rejet garde les permissions, le verrou, la comparaison de version et l’audit. Un dossier sans version est maintenant accessible à la décision depuis la fiche : 0 représente uniquement la version NULL affichée, la première décision persiste la version 1 et l’audite. Les décisions obsolètes restent refusées. Les modifications matérielles passent en PENDING et retirent les preuves de validation, même avec des champs vides ; les anciens dossiers INCOMPLETE sont validables. Les conditions techniques et de quota pour activer une boutique restent inchangées.
- Tests MySQL temporaires ciblés expressément autorisés : huit cas réussis, 115 assertions, couvrant dossier vide nouveau/historique, version obsolète, identité du validateur/audit, consultation sans permission, suppression de toutes les données professionnelles puis revalidation, modification d’identité et validation des entrées. Rendus des champs d’aide et du bouton sur dossier vide vérifiés dans les tests. Pint et diff contrôlés. Les bases temporaires de cette exécution ont été nettoyées ; seule l’ancienne base de test déjà présente reste conservée. Aucun compte/dossier/boutique réel validé, aucune migration ni préparation des boutiques réelles lancée.
- Références mises à jour : AGENTS.md, architecture §24, schéma principal et descriptions du diagramme central. Les colonnes étant déjà nullables, aucune modification SQL n’est nécessaire.

## 18 octobre 2026 — Actions de validation du dossier dans la liste Utilisateurs

- Ajout de Valider le dossier/Rejeter le dossier dans Actions de la liste et réutilisation du même partiel legal-actions dans Informations. Chaque action possède une modale Flux avec nom du commerçant, motif facultatif, annulation et confirmation. Les actions sont masquées pour VERIFIED, les archives et le personnel ; les permissions existantes contrôlent chaque bouton et le POST. Aucun changement des règles d’approbation des inscriptions.
- UserManagementService charge l’état professionnel, la version et deleted_at dans sa projection paginée ; la liste affiche les erreurs de validation. Routes, Request, service de décision et enregistrement du motif dans Activitylog sont réutilisés.
- Contrôles sans écriture MySQL : rendu de la liste Livewire (18 formulaires autorisés au moment du contrôle), quatre états simulés uniquement en mémoire sur la fiche et le partiel (NULL/PENDING/FAILED/VERIFIED), absence d’actions pour archive/personnel/acteur non autorisé, présence des motifs et des versions. Aucun POST métier, changement de statut réel, migration, seeder ou nouvelle BDD de test. Le contrôle porte sur le rendu serveur, pas sur un clic navigateur automatisé. Architecture §24 actualisée.

Correction demandée ensuite : masquer aussi les deux boutons après refus du dossier (FAILED) ou lorsque le compte est Refusé (REJECTED). Le partiel commun applique la même condition dans Actions et Informations. Six combinaisons compte/dossier vérifiées en mémoire sur le partiel et la fiche, sans écriture MySQL ; dossiers validés/refusés et comptes refusés sans boutons, dossiers à examiner avec leurs actions autorisées. Aucun changement des statuts enregistrés ni des services de décision.

## 18 octobre 2026 — Explication de l’absence du bouton d’activation

La fiche Informations de la boutique affiche désormais la cause connue de l’absence du bouton : préparation non terminée, préparation échouée, permission manquante, boutique déjà active ou archivée. Les messages s’appuient sur les mêmes métadonnées et permissions que l’affichage des actions, sans vérifier ni créer une BDD en ouvrant la page. La préparation terminée rend le bouton disponible, puis les contrôles métier existants restent appliqués lors de l’activation. Six cas de rendu vérifiés sur des modèles modifiés uniquement en mémoire, sans écriture MySQL. Une première vérification a échoué sur l’encodage d’un texte accentué transmis par PowerShell ; comparaison corrigée et contrôle relancé avec succès. Aucun worker, migration ou changement d’état réel exécuté.

## 18 octobre 2026 — Mission 1 : inscription commerçant guidée

Cause : RegistrationResponse déconnectait le nouveau compte alors que Fortify exige une session pour vérifier l’e-mail ; l’authentification et son middleware refusaient ensuite tout compte PENDING/REJECTED. L’interface n’expliquait pas la succession vérification, approbation puis configuration.

- Fortify conservé : Registered déclenche l’envoi, routes natives de vérification/renvoi, signature expirante, contrôle compte/hash, Verified et preuve email_verified_at. RegistrationVerificationNotification et VerificationNotificationController réutilisent AccountMailDelivery, avec erreur SMTP visible et compte conservé. La modale de confirmation distingue envoi réussi/échoué. Les liens invalides/expirés et les renvois trop rapprochés ont un message explicatif ; le lien déjà utilisé ne redéclenche pas Verified.
- RegistrationJourney et les réponses centrales de connexion/inscription/vérification guident le commerçant. Une session PENDING/REJECTED est limitée à la vérification et au statut, avec refus serveur des réglages et fonctions administratives. Après vérification, retour à la connexion avec confirmation ; destinations attente/refus ou première boutique après approbation. Les liens internes administratifs restent repris pour les comptes ayant accès au dashboard. Comptes suspendus/inactifs/supprimés toujours refusés ; réponse native de vérification du personnel préservée.
- Première boutique : Controller Central/Owner/Tenants/FirstTenantController, Request Central/Tenants/StoreFirstTenantRequest, vue central/pages/owner/tenants/first-shop ; capacité métier createFirst dans TenantPolicy, sans permission SaaS. TenantCreator partage son traitement existant, relit/verrouille le propriétaire, vérifie approbation et e-mail, applique quota et idempotence, puis enfile le provisionnement uniquement après le POST explicite. Inscription et UserApprovalService ne créent aucune boutique. DashboardController Central/Dashboard applique le parcours aux accès directs ; texte du dashboard actualisé.
- Références mises à jour : AGENTS.md, architecture §24, schéma C1 et instructionn.txt. Aucun changement des tables, migrations, permissions ou bases existantes.
- Contrôles : une première version a passé 8 tests ciblés d’inscription/vérification (29 assertions) sur MySQL isolé avec notifications simulées. L’utilisateur a ensuite demandé, exceptionnellement pour aujourd’hui, de continuer sans tests : aucun nouveau test exécuté après cette consigne. Les attentes des tests existants ont été adaptées au nouveau parcours, sans suppression de cas. Relecture des fichiers/routes/contrats et du diff ; syntaxe de 21 fichiers PHP valide, 9 nouvelles classes chargées par Composer sans démarrer Laravel ni accéder à MySQL, git diff --check sans erreur de contenu. Les dernières corrections et les nouvelles pages n’ont pas été vérifiées par exécution HTTP ou navigateur ; aucun envoi SMTP réel de contrôle, worker, migration ou seeder lancé.

## 18 octobre 2026 — Mission 2 : états réels du commerçant et boutique prête

Causes restantes après la Mission 1 : le refus supprimait les sessions du commerçant, la Policy interdisait une approbation après refus, le motif restait uniquement dans l’audit et la présence d’un tenant suffisait pour afficher le tableau de bord. La page d’attente ne suivait pas les changements administratifs.

- RegistrationJourney calcule verification/pending/rejected/setup/preparing/ready et la destination depuis le compte connecté et ses seuls tenants centraux. Une boutique prête est ACTIVE avec schema_version non vide et provisioned_at renseigné ; une préparation terminée qui attend l’activation reste sur la page de préparation. Aucun accès aux BDD boutiques pour ce calcul. Le middleware central protège aussi l’accès direct et les mises à jour Livewire du dashboard ; les réglages personnels restent possibles pour un commerçant ACTIVE.
- RegistrationStatusController et central/pages/auth/registration-status.blade.php présentent les messages d’attente/refus/préparation, les erreurs de préparation et le motif de la dernière décision d’inscription du compte, sans autres propriétés d’audit. Réponse HTML/JSON privée non stockable ; actualisation Alpine toutes les 15 secondes seulement lorsque l’onglet est visible, sans requêtes simultanées, avec nettoyage du minuteur, message de panne et actualisation manuelle. Un changement d’état/destination redirige ou recharge automatiquement la bonne page.
- UserApprovalService conserve les sessions limitées lors du refus, invalide toujours remember_token et les jetons de réinitialisation, et audite le véritable statut précédent. UserPolicy accepte REJECTED → ACTIVE uniquement via approve et saas.users.approve ; suspendre/réactiver ne contourne pas cette décision. Le partiel administratif lifecycle-actions ajoute une modale de motif facultatif, explicitement visible au commerçant ; la même Policy rend Approuver disponible pour un compte refusé dans les listes et sa fiche.
- FirstTenantController confirme la création explicite puis dirige vers la préparation. Le nom reste obligatoire dans le Request existant ; verrou, quota et idempotence restent dans TenantCreator. EnsureTenantAvailable bloque avant InitializeTenancyByDomain un tenant non ACTIVE ou sans les métadonnées de préparation ; diagnostics locaux préservés dans leurs conditions antérieures. Aucun tenant créé par l’approbation, aucun nouveau rôle/permission et aucune modification des tables.
- Tests ajoutés dans tests/Central/Feature/Auth/MerchantJourneyTest.php : identifiants incorrects sans distinction de compte, états e-mail/approbation/boutique, session limitée conservée, motif et nouvelle approbation, absence de création automatique, nom obligatoire et création idempotente, refus des URL directes, isolation entre propriétaires et blocage du domaine avant initialisation. Assertion de la fiche de refus adaptée dans CentralUserModuleTest.php. Ces tests ne sont pas exécutés : la consigne ponctuelle de l’utilisateur pour aujourd’hui reste de vérifier seulement le code.
- Contrôles exécutés : syntaxe PHP valide sur l’ensemble des 25 fichiers PHP actuellement modifiés/nouveaux, syntaxe du bloc Alpine vérifiée par node --check avec les seules valeurs Blade remplacées par des constantes, relecture des routes/guards/Policies/contrats installés et git diff --check sans erreur de contenu. Aucun HTTP/navigateur, test Pest, Pint, PHPStan, migration, seeder, worker ou envoi SMTP de contrôle exécuté pour cette mission. AGENTS.md, architecture §24, schéma C1 et instructionn.txt actualisés ; les changements des tâches précédentes sont conservés.

## 18 octobre 2026 — Missions 3 et 4 : décision administrative unique et création approuvée

Ces décisions remplacent la validation professionnelle indépendante et le motif de refus facultatif des étapes précédentes. Les champs professionnels restent facultatifs selon la décision explicite antérieure ; leurs règles de type, longueur, montant et version restent obligatoires. La preuve d’e-mail demeure distincte de l’approbation administrative.

- UserApprovalService est le seul enregistrement des décisions compte/dossier. Approuver écrit ACTIVE/VERIFIED avec version, date et validateur, sans motif ; Refuser écrit REJECTED/FAILED avec un motif non vide contrôlé dans le Request et le service. L’auteur et les deux projections de la décision sont journalisés dans Activity Log dans la même transaction. Une nouvelle approbation après refus conserve l’ancien motif et son auteur dans l’historique, sans le présenter comme un refus actuel. Aucun tenant n’est créé.
- Contrôle de concurrence : les formulaires et l’API du service exigent explicitement l’état et la version attendus ; le compte est relu sous verrou dans AuthorizationMutation, avec nouvelle autorisation et comparaison des valeurs avant écriture. Un formulaire antérieur à un refus ne peut donc approuver ce compte implicitement. Les doubles décisions sur un compte déjà approuvé/validé sont refusées. Les anciens comptes ACTIVE au dossier non validé restent examinables via la même décision, sans approbation rétroactive automatique des données existantes.
- Interfaces : lifecycle-actions est l’unique partiel des décisions dans la liste, la fiche et les inscriptions en attente ; suppression du partiel legal-actions et de ses inclusions. Refuser ouvre une modale avec motif obligatoire ; Approuver ne demande aucun motif. Anciennes routes legal conservées comme adaptateurs : LegalVerificationController/Request utilisent désormais UserApprovalService et les droits saas.users.approve/reject, sans décision indépendante ni privilège accordé par les anciennes permissions seules. Les lignes et attributions Spatie historiques ne sont pas supprimées ou converties. Le catalogue dérive la lecture juridique nécessaire des droits d’approbation/refus.
- LegalVerificationService fournit uniquement les règles partagées et les attributs de décision, sans mutation indépendante. StoreUserRequest et UpdateUserRequest réutilisent ces règles. UserData transporte les seuls champs professionnels autorisés. Les formulaires de création/édition partagent professional-fields.blade.php, avec descriptions et exemples fictifs jamais enregistrés par défaut. Les champs d’état, preuves, dates, rôle ou mot de passe arbitraires restent protégés.
- Création administrative : UserManagementService valide les données/pays puis appelle approveCreatedMerchant dans sa transaction, uniquement pour un nouveau commerçant sans rôle SaaS et sous le droit de création. Compte ACTIVE et dossier VERIFIED immédiatement, sans passage en file publique. E-mail non vérifié, mot de passe initial aléatoire inconnu, invitation envoyée après commit par AccountMailDelivery. Le broker central existant conserve expiration, unicité et consommation du lien ; ResetUserPassword prouve l’adresse seulement après contrôle du jeton lié à l’e-mail courant. Renvoi sécurisé existant conservé. La première connexion conduit à la configuration explicite de la première boutique. Personnel SaaS exclu de la validation juridique commerçant.
- Cohérence des modifications : changement matériel d’identité/dossier → version suivante, preuve juridique effacée, dossier PENDING et compte ACTIVE/REJECTED remis en PENDING. Une suspension/inactivité n’est pas levée par une édition ; réactiver un commerçant dont le dossier n’est plus validé le remet en attente, sans approbation implicite. Le profil personnel passe aussi par UserManagementService.update avec Policy updateOwnProfile et transaction centrale dédiée, sans imposer le verrou des rôles à une modification personnelle. Les photos et le parcours personnel SaaS conservent leurs contrôles existants. Nouvelle inscription publique : compte/dossier PENDING, version 1.
- Tests ajoutés dans tests/Central/Feature/Admin/Users/MerchantApprovalWorkflowTest.php : approbation, refus avec motif, refus vide/absent sur les deux points d’entrée, nouvelle approbation, synchronisation et boutons, deux formulaires opposés issus du même état, refus de l’ancien droit seul, création administrative/invitation/choix du mot de passe/preuve d’e-mail/première connexion, doublons et droits, invitation expirée/renvoyée, données professionnelles invalides et réactivation sans contournement. Tests existants adaptés dans CentralUserModuleTest, UserAccountManagementTest, UserLifecycleTest et MerchantJourneyTest, sans suppression de cas. ProfileUpdateTest protège aussi l’enregistrement sans changement : conserver prénom/nom séparés et ne pas invalider l’approbation à cause d’une conversion du nom affiché. Les formulaires concurrents sont simulés par deux demandes portant le même état/version ; aucune véritable exécution parallèle n’a été réalisée.
- Contrôles : syntaxe valide sur les 40 fichiers PHP actuellement modifiés/nouveaux, vérification du PHP du composant SFC de profil, chargement de 11 classes par Composer sans démarrer Laravel, recherche des anciens boutons/inclusions dans app/routes/resources sans résultat, vérification des appels au service et git diff --check sans erreur de contenu. Aucun test exécuté conformément à la consigne ponctuelle d’aujourd’hui ; aucun HTTP, navigateur, envoi SMTP réel, worker, migration, seeder ou changement des bases existantes. AGENTS.md, architecture §24, schéma C1 et instructionn.txt actualisés. Les validations HTTP, MySQL et navigateur restent à exécuter lors d’une prochaine autorisation.

## 18 octobre 2026 — Missions 5–6 : coordonnées et téléphone international

- Décisions après clarification : un seul e-mail et un seul numéro par compte, sans coordonnées secondaires ; téléphone obligatoire à l’inscription publique. L’utilisateur corrigera lui-même les conflits existants, aucune suppression/fusion automatique. Le fonctionnement multi-e-mail/multi-téléphone demandé initialement est donc explicitement abandonné.
- Audit réel central en lecture seule : 16 comptes, 12 avec téléphone ; aucun doublon d’e-mail après normalisation. Bilan téléphonique final : deux numéros invalides et deux groupes de doublons (trois comptes dans un groupe, deux dans l’autre). Deux représentations valides nécessitent une normalisation. Un ancien numéro international provient d’un pays différent du profil : ce cas est conservable grâce au pays propre au téléphone et ne justifie pas de modifier le profil. Les UUID concernés sont affichés par la commande d’audit, sans inscrire les coordonnées personnelles dans ce suivi.
- Dépendance giggsey/libphonenumber-for-php 9.0.41 et giggsey/locale 2.9.0 installées avec PHP 8.5 et composer.phar Herd, --no-scripts ; aucune mise à jour des autres packages. Référence officielle : https://github.com/giggsey/libphonenumber-for-php. PhoneNumberNormalizer valide la région ISO explicite, les formats locaux/internationaux et E.164, refuse les extensions ; la reprise historique ne déduit un pays international distinct que pour un indicatif sans ambiguïté et présent dans countries. Les indicatifs partagés ne sont jamais assimilés à un pays unique.
- Modèle central User : phone_country_id et relation phoneCountry, normalisation avant écriture, contrôle d’appartenance incluant les archives, erreurs de validation pour les collisions SQL concurrentes. Country expose l’indicatif calculé sans nouvelle colonne. UserManagementService conserve les transactions/verrous et les Policies, distingue les deux pays, préserve les preuves lors d’un simple changement de représentation, invalide les preuves du téléphone changé, et synchronise l’objet utilisateur de l’appelant après édition pour ne pas garder un ancien e-mail vérifié en mémoire. Les recherches Users/Staff acceptent le téléphone formaté ou local dans leurs espaces autorisés. Requests et DTOs transportent phoneCountryUuid. Le contrat Fortify de mise à jour du profil réutilise désormais le service commun.
- Interfaces : inscription avec pays/indicatif intégré au champ téléphone, sans second choix de pays indépendant ; profil personnel avec téléphone, pays du profil et pays du numéro ; formulaires et fiches Users/Staff adaptés. Pays actifs pour un nouveau numéro ; conserver un pays historique inactif uniquement pour le même numéro. Renvoi personnel de vérification limité, erreur réelle en cas de transport SMTP et message conservé sur la page de vérification. Les invitations, connexions et réinitialisations restent sur l’unique e-mail ; aucun e-mail marqué vérifié automatiquement lors de son remplacement.
- Migration préparée par Artisan : database/migrations/central/2026_10_18_105836_add_international_phone_identity_to_users_table.php. Audit avant DDL, ajout d’une vraie FK phone_country_id, reprise sous transaction, UNIQUE(phone), CHECK du couple téléphone/pays et de la forme E.164. Retour arrière automatique refusé pour éviter la perte du pays du numéro. Aucune migration ancienne modifiée. Commande aydra:audit-user-contacts ajoutée dans Console/Commands/Central : lecture seule, code de sortie 1 tant que les conflits subsistent. Les créations/modifications de coordonnées sont temporairement refusées avec une erreur compréhensible si la colonne manque, plutôt qu’une erreur SQL ; les connexions et décisions administratives sans changement de coordonnées restent disponibles.
- Tests préparés dans tests/Central/Feature/Settings/InternationalContactsTest.php : formats DZ, saisies invalides, indicatifs partagés, reprise historique, unicité/archives, arbitrage SQL d’une seconde attribution, changement des pays séparés, modification administrative et personnelle, refus sans autorisation, atomicité, nouvelle adresse non vérifiée et ancien token invalidé, identité authentifiée actualisée, recherche autorisée, inscription obligatoire et pays inactif, audit sans écriture et refus de reprise d’une donnée invalide. RegistrationTest, CountryManagementTest et StaffManagementTest adaptés avec des numéros valides. La collision SQL est exercée sans réellement lancer deux processus parallèles. Tests NON exécutés selon la consigne ponctuelle d’aujourd’hui.
- Documentation : AGENTS.md, architecture §32, schéma principal et diagramme central actualisés. Comptes locaux Tenant audités séparément : connexion/guard et identité propres à leur BDD, éventuel lien central_user_uuid ; aucun nouveau champ, migration ni contrainte inter-BDD Tenant.
- Contrôles exécutés : audit MySQL en lecture seule et commande d’audit (conflits signalés, aucune correction automatique) ; syntaxe PHP des fichiers modifiés/nouveaux, PHP du composant de profil, compatibilité des méthodes contre les sources installées ; composer validate (valide, avertissement préexistant sur le verrouillage exact de stancl/tenancy) ; git diff --check. Aucun test, Pint, PHPStan, HTTP, navigateur, SMTP réel, worker, migration ni seeder exécuté. Les contrôles de syntaxe ne valident pas le comportement fonctionnel.
- Reste nécessaire : correction manuelle des conflits, sauvegarde et déploiement coordonné du code et de la migration avec écritures/workers arrêtés, puis tests isolés et parcours HTTP lors d’une autorisation ultérieure. Ne pas annoncer la migration appliquée ni les fonctionnalités validées sur MySQL réel. Commandes exactes et précautions dans architecture §32 ; ne pas utiliser les nouveaux formulaires avant application de l’évolution SQL.
## 18 octobre 2026 — Missions 7–8 : boutiques automatiques après préparation et worker supervisé

- Causes vérifiées : deux jobs tenant-provisioning en attente, aucun worker actif, aucun failed_job ; composer dev écoutait la file ordinaire et non la connexion dédiée. central-provisioning est bien la connexion et tenant-provisioning sa file ; la commande initiale queue:work central-provisioning était correcte. Autre blocage réel : TenantProvisioner laissait PROVISIONING même après initialisation réussie, imposant ensuite une activation manuelle distincte.
- Métier : première boutique explicitement configurée après approbation compte/dossier et vérification d’e-mail ; aucun tenant créé à l’inscription ou à l’approbation. RegistrationJourney contrôle aussi la preuve du dossier et interdit le dashboard sans une boutique réellement prête. Création volontaire de boutiques supplémentaires via Controller/Request Owner et TenantCreator, sous verrou propriétaire, quota courant, clé et empreinte d’idempotence. Aucun remplissage automatique du quota. La création initiale et les créations supplémentaires partagent la même implémentation et le même formulaire.
- Fin de provisionnement : le job utilise un verrou GET_LOCK central par base/tenant, crée/migre et initialise avec TenantProvisioner hors transaction centrale, puis TenantStatusService.completeProvisioning synchronise l’opération SUCCEEDED et l’état d’accès sous verrou. ACTIVE si éligible, OVER_QUOTA si le quota/droit est indisponible, INACTIVE si le propriétaire n’est plus éligible. PROVISIONING n’est plus activable administrativement ; seules les réactivations légitimes INACTIVE/SUSPENDED/OVER_QUOTA conservent leur route et leur permission. Aucun droit Spatie, UUID, attribution ou schéma SQL supprimé.
- Reprises : erreurs publiques nettoyées et failed() terminal, trois tentatives/backoff conservés, verrou SQL libéré à la fin ou à la fermeture du processus. TenantProvisioningRetry remet la même opération en file sans recréer la boutique ni perdre ses paramètres. Boutons de reprise autorisés dans la page de suivi propriétaire et la fiche administrative ; refus d’accès à la boutique d’un autre propriétaire. La réservation/job reste atomique dans la même transaction MySQL avant commit, et le travail lourd commence uniquement après visibilité du commit dans un processus worker distinct.
- Fichiers principaux : app/Jobs/Central/ProvisionTenantDatabase.php ; app/Services/Central/Tenants/{TenantCreator,TenantStatusService,TenantProvisioningRetry}.php ; app/Policies/Central/TenantPolicy.php ; app/Services/Central/Auth/RegistrationJourney.php ; app/Http/Controllers/Central/Owner/Tenants/TenantController.php ; app/Http/Requests/Central/Tenants/StoreOwnedTenantRequest.php ; routes/central.php ; vues owner/tenants/first-shop, auth/registration-status, dashboard et admin/tenants/{actions,show}. Documentation : AGENTS.md, architecture §33, schéma et diagramme centraux. Aucun déplacement, migration nouvelle ou modification des fichiers de migration dans ce lot.
- Supervision locale : commande app/Console/Commands/Central/LocalProvisioningWorker.php. Tâche Aydra-Provisioning-54a25334e8a4 installée pour l’utilisateur courant en droits limités, initialement désactivée ; démarrage ensuite autorisé explicitement après validation des tests. Elle est maintenant Running et Enabled, en arrière-plan masqué, avec déclenchement aux connexions Windows, redémarrage configuré après panne et une seule instance. PHP du listener et de son worker contrôlé : C:\Users\habou\.config\herd\bin\php85\php.exe. queue:listen central-provisioning --queue=tenant-provisioning, timeout Symfony 300 et retry_after 360. Les sorties inattendues normales du listener sont aussi converties en panne pour la supervision. Aucune modification de Device Guard, ExecutionPolicy, hosts, permissions globales ou configuration Herd.
- composer dev démarre la tâche gérée via @php puis Vite ; Herd continue de servir HTTP. Préparation production : deployment/supervisor/aydra-provisioning.conf, valeurs d’environnement pour racine/PHP/compte de service, autorestart et arrêt groupé, délai d’arrêt 420, rotation des journaux. Fichier préparé uniquement, aucun service de production installé. Procédure et commandes dans architecture §33.
- Tests autorisés : MySQL temporaire isolé et vrais jobs pour ces missions, puis autorisation distincte de traiter les deux boutiques réelles après réussite. 45 cas distincts validés par exécutions ciblées : MerchantJourneyTest + TenantManagementTest, 35/35 (412 assertions) ; CentralShopProvisioningTest, 1/1 avec deux vrais tenants (30 assertions) ; ShopCreationWorkflowTest, 9 cas validés au total par la passe initiale et les reprises ciblées. Couverture : aucune boutique à l’inscription/approbation, formulaire et refus du dashboard initial, création volontaire, double soumission, quota, refus d’accès, échec/reprise conservant les réglages, rollback réservation/job, verrou concurrent contrôlé avec deux connexions SQL et tentative de reprise étrangère renvoyant 403.
- Corrections des tests durant la vérification : fixture VERIFIED complétée avec son validateur pour respecter le CHECK existant, sans affaiblir la contrainte. Deux ajustements du parcours intégré dus au worker exécuté dans le même processus de test : AccountContextBootstrapper réinitialise guards et SessionManager lors du changement de contexte. Réauthentifier l’acteur de la requête HTTP suivante simule la séparation réelle navigateur/worker ; vérifier le refus de quota via la réponse JSON 422 et le champ shop_name évite de dépendre de la session array réinitialisée. Les assertions de disponibilité, compte des boutiques et quota sont conservées et renforcées. Dernière reprise du parcours complet : 1/1, 27 assertions. Le changement de contexte n’a pas été affaibli pour faire passer le test.
- Contrôles réels après démarrage autorisé : les deux boutiques unite alpha 2 et boutique alpha sont ACTIVE, opérations SUCCEEDED ; 69 tables dans chacune, singleton shop correspondant au bon tenant, exactement un propriétaire local lié par central_user_uuid au bon compte et version enregistrée égale à la dernière migration réelle. File vide, failed_jobs vide ; quatre opérations SUCCEEDED au total, dont les deux anciennes et les deux traitées pendant cette intervention. Aucune suppression de données ou de base réelle, aucun envoi de mail ni seeder réel. Les anciennes bases restent présentes.
- Autres contrôles : syntaxe PHP, chargement des nouvelles routes et protections centrales, composer validate valide (avertissement préexistant sur le verrouillage exact de stancl/tenancy), git diff --check. Aucun Pint, PHPStan ou build systématique. Rendu Blade traversé par les requêtes HTTP des tests ; aucun parcours navigateur réel authentifié ni crash forcé du worker réel. Le redémarrage est configuré dans Windows, pas annoncé comme un scénario de panne forcée exécuté.
- Limite indépendante conservée : la migration des coordonnées du §32 n’est toujours pas appliquée dans la BDD centrale (phone_country_id absent, vérifié en lecture seule). Les nouvelles créations/modifications de comptes restent bloquées par sa protection tant que l’utilisateur n’a pas corrigé les conflits et appliqué cette migration. Aucune correction automatique des numéros ni migration centrale forcée pendant ces missions. Les boutiques des propriétaires déjà éligibles sont maintenant préparées et actives ; les dashboards/authentifications internes Tenant restent reportés comme prévu.
## 18 octobre 2026 — Missions 9–10 : audit des unicités et parcours intégré

État : code et contrôles ciblés terminés ; déploiement réel bloqué par les conflits de coordonnées conservés.

### Décisions confirmées et périmètre

- L’utilisateur remplace explicitement la règle d’une coordonnée par type : plusieurs e-mails et téléphones, un principal par type ; tous les e-mails secondaires vérifiés permettent connexion et récupération du même compte.
- Autorisation de tests MySQL temporaires, notifications simulées et vrais jobs isolés. Les bases existantes sont consultées uniquement en lecture. Aucun compte, téléphone ou boutique réel créé, fusionné, supprimé ou modifié pour ce lot.
- Références : architecture §32–34, schéma et diagramme central synchronisés ; aucune évolution des comptes locaux Tenant ni de leurs permissions.

### Anomalies et corrections

| Anomalie | Correction et fichiers |
|---|---|
| MySQL réel ne possède pas encore l’unicité du téléphone normalisé | Ancienne migration téléphonique bloquée par des conflits réels, pas forcée. Audit readonly ContactMigrationAudit/AuditUserContacts. Deux numéros invalides et deux groupes de doublons (3 et 2 comptes), zéro groupe d’e-mails normalisés en double. Correction réelle laissée à l’utilisateur. |
| Coordonnées secondaires absentes | Migration additive create_user_contacts_table, modèle UserContact, UserContactService ; principales dans users projetées atomiquement vers le registre par triggers INSERT/UPDATE. UNIQUE(kind,value) et UNIQUE(user_id,kind,primary_slot), FK réelles et CHECK. Même un UPDATE users SQL direct ne peut prendre le secondaire d’un autre propriétaire. |
| Alias non disponibles pour Fortify | ContactUserProvider central uniquement, FortifyServiceProvider et config/auth ; secondaire non vérifié refusé, secondaire vérifié accepté. ResetUserPassword et User routent la récupération vers l’adresse demandée, contrôlent à nouveau sa possession et invalident les autres tokens du compte après changement de mot de passe. |
| Risque de perte d’un ancien principal | L’ancien reste secondaire ; suppression explicite des seuls secondaires, révocation des tokens associés ; principal non supprimable avant remplacement. |
| Pays d’un secondaire non pris en compte dans les protections | CountryService contrôle aussi les références user_contacts ; PhoneNumberNormalizer reste le moteur E.164. Pays actifs contrôlés sous verrou pour un nouvel ajout. |
| Secondaires absents des recherches | UserManagementService et StaffManagementService recherchent aussi les coordonnées secondaires, sous les mêmes permissions. |
| Domaine déjà occupé provoquant une exception Stancl visible | TenantCreator traduit DomainOccupiedByOtherTenantException et la collision SQL en erreur du champ slug ; transaction annule la réservation et aucun job n’est envoyé. |
| Renvoi d’invitation avec deux classes de modèle différentes | UserManagementService.resendInvitation recharge le modèle d’authentification App/Models/User, comme la création ; le scénario d’invitation expirée puis remplacée réussit. |
| Tests historiques supposant qu’ACTIVE prouve un dossier approuvé | AuthenticationTest fournit maintenant explicitement la preuve professionnelle et son auteur pour les cas « approuvé sans boutique ». Aucune règle métier assouplie. |
| Audit de schéma rejetant la table historique conservée | tests/Pest.php distingue admin_restrictions, conservée physiquement selon la documentation, des tables cibles. L’égalité exacte des listes reste contrôlée ; aucune table supprimée. |
| Comparaison incorrecte d’un modèle avant/après audit | InternationalContactsTest recharge le même état SQL avant la comparaison, incluant les colonnes par défaut. L’assertion d’absence de modification reste complète. |

### Déploiement réel bloqué, sans correction automatique

L’état MySQL contrôlé en lecture seule contient 16 utilisateurs et 4 boutiques. phone_country_id et user_contacts sont absents ; les deux migrations de coordonnées n’ont PAS été appliquées. Zéro job en attente et zéro failed job lors du contrôle. Le worker local supervisé est Running/Enabled ; aucun crash volontaire ni redémarrage forcé effectué.

L’utilisateur doit fournir/corriger les coordonnées authentiques des comptes signalés par :

| Conflit réel | UUID publics des comptes à examiner (aucun numéro personnel recopié) |
|---|---|
| Numéros invalides pour leur région | f68f2e70-5689-403f-96a9-5337a502fd31 ; 5b823a45-427a-4bdd-9886-0b36ba92ab87 |
| Même numéro normalisé, groupe de trois | 0156bd5d-a381-4188-b1db-cd5770daa256 ; faac7a56-e8a7-42b1-b8aa-4d450afe187c ; 4aca283f-5753-457a-b75c-ad669ed7c9d6 |
| Même numéro normalisé, groupe de deux | ae2b7e70-a125-413b-91e4-a697b81e8162 ; 9f34aa75-e52d-4456-984e-f46e63050722 |

Les fiches administratives sont accessibles via /admin/users/{uuid}. Les écritures applicatives de coordonnées restent bloquées tant que l’évolution SQL n’est pas déployée ; une correction préalable dans MySQL ou une intervention précisément autorisée est nécessaire. Les UUID ci-dessus servent uniquement à identifier les conflits, pas à autoriser un accès.

~~~powershell
& 'C:\Users\habou\.config\herd\bin\php85\php.exe' artisan aydra:audit-user-contacts --no-interaction
~~~

Ne pas inventer de numéros, retirer des comptes ou supprimer des contacts pour faire passer la migration. Si une correction nécessite retrait/transfert/fusion, obtenir un accord précis. Après correction : audit sans conflit, sauvegarde, fenêtre sans écritures ni worker, puis migrations centrales d’évolution ; rétablir le worker après validation. Aucun lancement de cette procédure réelle pendant cet audit.

### Portée des unicités

- Central : e-mails normalisés et téléphones E.164 réservés par toutes les coordonnées, comptes archivés compris ; pays ISO, slugs, domaines et références documentaires selon leurs index.
- UUID : unicité SQL dans chaque table/base, génération v4 ; aucune fausse contrainte SQL inter-BDD. Les clés entières restent locales et internes.
- Boutiques : nom affiché non unique ; slug global central, domaine global central, clé de création par propriétaire. Le nom physique est réservé par le mécanisme Tenant existant. Idempotence et quota restent sous verrou.
- Noms/prénoms, nom public d’une boutique, pays de profil, NIF/NIS et autres informations professionnelles : aucune unicité globale inventée lorsqu’elle n’est pas documentée. Les règles de format et preuves restent indépendantes.
- Tenant : identités et codes locaux réservés dans leur propre BDD ; aucune unicité de téléphone ou e-mail centrale imposée aux comptes/acheteurs locaux.
- Les index ci-dessous sont ceux réellement lus dans information_schema ; ils ne signifient pas que toutes les fonctionnalités futures de leurs tables sont déjà développées.

### Inventaire des contraintes UNIQUE effectivement présentes

| Contexte / table | Clés UNIQUE réelles (hors PK) |
|---|---|
| Central / `activity_log` | `operation_key` ; `uuid` |
| Central / `admin_restrictions` | `uuid` ; `admin_id,permission_id,normalized_target_type,normalized_target_id,effect,active_slot` |
| Central / `cache` | PK uniquement |
| Central / `cache_locks` | PK uniquement |
| Central / `carrier_geo_mappings` | `uuid` ; `carrier_id,geographic_area_id` |
| Central / `contact_verifications` | `uuid` |
| Central / `countries` | `code` ; `uuid` |
| Central / `domains` | `domain` ; `tenant_id,primary_slot` ; `uuid` |
| Central / `failed_jobs` | `uuid` |
| Central / `feature_usage` | `uuid` ; `user_id,feature_id,period_starts_at` |
| Central / `features` | `code` ; `uuid` |
| Central / `geographic_areas` | `uuid` ; `country_id,type,parent_key,code` ; `id,country_id,type` ; `id,type` ; `id,parent_id,type` |
| Central / `job_batches` | PK uniquement |
| Central / `jobs` | PK uniquement |
| Central / `media` | `storage_key` ; `uuid` ; `model_type,model_id,collection_name,primary_slot` |
| Central / `migrations` | PK uniquement |
| Central / `model_has_permissions` | PK uniquement |
| Central / `model_has_roles` | PK uniquement |
| Central / `passkeys` | `credential_id` |
| Central / `password_reset_tokens` | PK uniquement |
| Central / `permissions` | `name,guard_name` ; `uuid` |
| Central / `pickup_points` | `uuid` ; `carrier_id,external_code` |
| Central / `plan_features` | `uuid` ; `plan_id,feature_id` |
| Central / `plans` | `uuid` ; `code,version` |
| Central / `role_has_permissions` | PK uniquement |
| Central / `roles` | `name,guard_name` ; `super_admin_slot` ; `uuid` ; `guard_name,permission_signature` |
| Central / `saas_billing_settings` | `operation_key` ; `uuid` ; `id,document_type,fiscal_year,record_type` ; `id,record_type` ; `document_type,fiscal_year,sequence_slot` ; `code,version` |
| Central / `saas_document_deliveries` | `operation_key` ; `uuid` |
| Central / `saas_invoice_lines` | `operation_key` ; `uuid` ; `document_id,line_number` ; `id,document_id,user_id,document_type` ; `document_id,original_invoice_line_id` |
| Central / `saas_invoices` | `number` ; `operation_key` ; `uuid` ; `id,user_id,document_type` ; `id,installment_id,subscription_id,user_id,document_type` ; `id,original_invoice_id,user_id,document_type` ; `sequence_id,sequence_number` |
| Central / `saas_transfers` | `active_transaction_fingerprint` ; `operation_key` ; `uuid` ; `id,document_id,user_id,original_payment_id,record_type` ; `id,document_id,user_id,record_type` ; `reversal_of_id` |
| Central / `sessions` | PK uniquement |
| Central / `shipping_carriers` | `code` ; `uuid` |
| Central / `subscriptions` | `installment_number` ; `operation_key` ; `uuid` ; `id,user_id,record_type` ; `user_id,active_owner_slot` ; `id,parent_subscription_id,user_id,record_type` |
| Central / `tenant_schema_deployments` | `uuid` ; `tenant_id` ; `operation_key` |
| Central / `tenants` | `document_prefix` ; `id,user_id` ; `slug` ; `user_id,creation_key` ; `uuid` |
| Central / `users` | `email` ; `uuid` |

Les quatre bases boutique présentes ont les mêmes index uniques : 256 index (PK comprises) sur 69 tables chacune.

| Table tenant | Clés UNIQUE réelles (hors PK) |
|---|---|
| `activity_log` | `operation_key` ; `uuid` |
| `billing_obligations` | `operation_key` ; `uuid` ; `invoice_id` |
| `billing_rules` | `uuid` ; `id,document_type,fiscal_year,record_type` ; `id,record_type` ; `document_type,fiscal_year,sequence_slot` ; `code,version` |
| `cache` | PK uniquement |
| `cache_locks` | PK uniquement |
| `carrier_accounts` | `uuid` ; `carrier_uuid,external_account_id` |
| `carrier_fees` | `uuid` ; `id,shipment_id,provider_id` ; `operation_key` ; `reversal_of_id` |
| `carrier_operation_attempts` | `uuid` ; `operation_id,attempt_number` |
| `carrier_operations` | `uuid` ; `operation_key` |
| `carrier_receivables` | `uuid` ; `id,provider_id` ; `operation_key` ; `reversal_of_id` |
| `carrier_remittance_batches` | `operation_key` ; `uuid` ; `reversal_of_id` ; `carrier_account_id,external_reference` ; `id,carrier_account_id` |
| `carrier_settlement_lines` | `operation_key` ; `uuid` ; `id,record_type,provider_id,shipment_id` ; `id,record_type,provider_id,carrier_fee_id` ; `id,record_type,provider_id,receivable_id` ; `id,record_type,provider_id` ; `id,record_type,provider_id,collection_id` ; `id,provider_id,record_type` ; `reversal_of_id` |
| `cart_items` | `uuid` ; `cart_id,variant_id,customization_signature` |
| `carts` | `uuid` ; `visitor_id` |
| `categories` | `uuid` ; `id,record_type` ; `record_type,slug` |
| `collection_entries` | `uuid` ; `id,collection_id` ; `operation_key` ; `reversal_of_id` |
| `collections` | `uuid` ; `id,shipment_id` ; `shipment_id` |
| `commercial_correction_lines` | `uuid` ; `correction_id,order_item_id` |
| `commercial_corrections` | `operation_key` ; `uuid` ; `id,order_id,source_revision_id` ; `id,source_revision_id` ; `correction_of_id` |
| `contact_verifications` | `uuid` |
| `content_pages` | `uuid` ; `id,product_id` ; `page_kind,slug` ; `id,page_kind` |
| `customer_adjustments` | `uuid` ; `id,order_id,incident_id` ; `operation_key` ; `reversal_of_id` |
| `domains` | `domain` ; `tenant_id,primary_slot` ; `uuid` |
| `expenses` | `uuid` ; `operation_key` ; `reversal_of_id` |
| `failed_jobs` | `uuid` |
| `free_shipping_rules` | `uuid` |
| `invoices` | `uuid` ; `number` ; `id,order_id,revision_id,document_type` ; `id,order_id,revision_id,document_type,original_invoice_id` ; `sequence_id,sequence_number` ; `operation_key` ; `id,order_id` |
| `job_batches` | PK uniquement |
| `jobs` | PK uniquement |
| `media` | `storage_key` ; `uuid` ; `model_type,model_id,collection_name,primary_slot` |
| `migrations` | PK uniquement |
| `model_has_permissions` | PK uniquement |
| `model_has_roles` | PK uniquement |
| `navigation_events` | `uuid` |
| `order_documents` | `uuid` ; `number,document_version` |
| `order_history` | `uuid` |
| `order_incident_details` | `uuid` |
| `order_incidents` | `operation_key` ; `order_item_id` ; `uuid` ; `id,order_id` |
| `order_items` | `uuid` ; `id,variant_id` ; `id,product_id` ; `id,revision_id` |
| `order_returns` | `uuid` ; `id,shipment_id` ; `shipment_id` ; `id,order_id` ; `id,shipped_revision_id` |
| `order_revisions` | `uuid` ; `id,order_id,pickup_point_uuid` ; `id,order_id,delivery_mode` ; `order_id,revision_number` ; `id,order_id` |
| `orders` | `uuid` ; `number` ; `original_return_id,unpaid_resend_slot` ; `submission_key` ; `cart_id` |
| `password_reset_tokens` | PK uniquement |
| `permissions` | `name,guard_name` ; `uuid` |
| `product_options` | `uuid` ; `product_id` ; `id,parent_id,product_id,record_type` ; `parent_id,identity_code` ; `id,product_id,record_type` ; `parent_id` |
| `product_promotions` | `uuid` |
| `product_reviews` | `uuid` |
| `product_tags` | `uuid` ; `product_id,tag_id` |
| `product_variants` | `uuid` ; `product_id,combination_signature` ; `id,product_id` ; `sku` |
| `products` | `uuid` ; `slug` |
| `remittance_statements` | `uuid` ; `id,provider_id` ; `operation_key` ; `reversal_of_id` ; `provider_id,number` |
| `return_items` | `uuid` ; `id,variant_id` ; `return_id,order_item_id` |
| `role_has_permissions` | PK uniquement |
| `roles` | `name,guard_name` ; `super_admin_slot` ; `uuid` ; `guard_name,permission_signature` |
| `sales_terms_acceptances` | `operation_key` ; `uuid` |
| `sessions` | PK uniquement |
| `shipment_events` | `uuid` ; `deduplication_key` |
| `shipments` | `uuid` ; `provider_id,tracking` ; `id,provider_id` ; `order_id` ; `id,order_id` ; `id,order_id,shipped_revision_id` ; `provider_id,merchant_reference` |
| `shipping_providers` | `uuid` ; `carrier_account_id` |
| `shipping_rates` | `uuid` ; `record_type,carrier_account_id,starts_at` ; `record_type,provider_scope_id,province_uuid,municipality_scope_uuid,delivery_mode,service_type,current_slot` ; `id,carrier_account_id,record_type` |
| `shop` | `singleton` ; `uuid` ; `tenant_uuid` |
| `shop_addresses` | `uuid` ; `id,shop_id,record_type` ; `shop_id,primary_slot` |
| `stock_movements` | `uuid` ; `id,variant_id` ; `variant_id,variant_sequence` ; `operation_key` ; `reversal_of_id` |
| `team_invitations` | `token_hash` ; `uuid` |
| `tenants` | `document_prefix` ; `id,user_id` ; `slug` ; `user_id,creation_key` ; `uuid` |
| `users` | `central_user_uuid` ; `email` ; `uuid` |
| `variant_option_values` | `variant_id,option_id` ; `uuid` |
| `visit_sessions` | `uuid` |
| `visitors` | `token_hash` ; `uuid` |

### Résultats finaux des scénarios

Bilan détaillé ci-dessous. Les limites non exécutées restent explicitement identifiées.

### Résultats observés, scénario par scénario

Les résultats « réussi » ci-dessous correspondent aux tests réellement exécutés sur MySQL temporaire ou aux lectures SQL indiquées. Ce n’est pas une exécution sur les données réelles ni un essai de navigateur.

| Scénario | Attendu | Observé | Résultat | Fichiers / contrôles |
|---|---|---|---|---|
| Inscription publique | Compte PENDING, e-mail non vérifié, aucun tenant | États et absence de boutique contrôlés | Réussi | CreateNewUser ; RegistrationTest ; ShopCreationWorkflowTest |
| Confirmation d’inscription | Modale explicative et guidage vers la vérification | HTML de la modale, message et bouton de renvoi présents | Réussi côté HTTP ; ouverture visuelle non exécutée | RegistrationResponse ; verify-email.blade.php ; RegistrationTest |
| Envoi de vérification | Notification envoyée au compte inscrit | Notification émise et destinataire contrôlé, envoi simulé | Réussi côté application | RegistrationVerificationNotification ; RegistrationTest |
| Vérification native | Preuve d’e-mail, événement Verified, retour connexion | Preuve enregistrée et session commerçant fermée | Réussi | EmailVerificationTest ; VerifiedEmailResponse |
| Lien principal expiré/invalide | Refus, pas de preuve anticipée, page d’explication | 403 et preuve inchangée ; vue d’erreur vérifiée | Réussi | EmailVerificationTest ; bootstrap/app.php |
| Lien déjà consommé | Ne pas rejouer Verified | Aucun second événement | Réussi | EmailVerificationTest |
| Renvoi de vérification | Nouvelle notification, limitation des demandes | Notification simulée puis 429 au dépassement | Réussi | VerificationNotificationController ; EmailVerificationTest |
| Identifiants incorrects | Erreur générique, pas de session | Refus contrôlé pour compte inconnu/mauvais mot de passe | Réussi | FortifyServiceProvider ; AuthenticationTest |
| E-mail principal non vérifié | Redirection vers la vérification | Accès limité, pas de dashboard | Réussi | RegistrationJourney ; AuthenticationTest |
| Compte en attente | Session limitée, page d’attente | Pages métier et créations directes bloquées | Réussi | EnsureActiveCentralAccount ; MerchantJourneyTest |
| Compte refusé | Motif visible, pas d’accès commerçant | Page de refus et motif propres au compte | Réussi | MerchantJourneyTest |
| Changement de décision pendant une session | État actualisé et destination cohérente | JSON privé et version passent attente → refus → configuration | Réussi côté HTTP/JSON | RegistrationJourney ; MerchantJourneyTest |
| Actualisation toutes les 15 secondes | Le navigateur suit l’état automatiquement | Code conservé et endpoint testé ; horloge navigateur non parcourue | Non exécuté dans un navigateur | registration-status.blade.php |
| Approuvé sans boutique | Configuration obligatoire | Dashboard redirige vers première boutique | Réussi | MerchantJourneyTest ; ShopCreationWorkflowTest |
| Nom de la première boutique | Nom obligatoire avant réservation | Formulaire vide refusé et réservation explicite seulement | Réussi | StoreTenantRequest ; MerchantJourneyTest |
| Approuver sans motif | ACTIVE et dossier VERIFIED ensemble, aucun tenant | États/auteur/date/historique synchronisés | Réussi | UserApprovalService ; MerchantApprovalWorkflowTest |
| Refuser avec/sans motif | Motif obligatoire, REJECTED/FAILED cohérents | Motif vide refusé aux différents points d’entrée | Réussi | UserApprovalRequest ; MerchantApprovalWorkflowTest |
| Approuver après refus | Reprendre le parcours et conserver l’historique | Ancien motif conservé dans l’audit, absent du refus courant | Réussi | UserApprovalService ; MerchantJourneyTest |
| Boutons de décision | Actions adaptées au statut réel | Absence des anciennes doubles validations vérifiée | Réussi | MerchantApprovalWorkflowTest |
| Deux décisions simultanées | Une seule décision acceptée, aucune désynchronisation | Deux processus PHP/MySQL : une réussite, un conflit ; un seul événement de décision | Réussi | UserContactConcurrencyTest |
| Création administrative | Compte et dossier approuvés, pas de tenant | Champs fournis validés et invitation créée après transaction | Réussi | UserManagementService ; MerchantApprovalWorkflowTest |
| Invitation / mot de passe | Token limité et consommable une fois, pas de mot de passe envoyé | Expiration, renvoi et activation vérifiés ; preuve d’e-mail au bon moment | Réussi | ResetUserPassword ; MerchantApprovalWorkflowTest |
| Modification administrative/personnelle | Garder ses coordonnées, refuser celles d’un autre | Mises à jour et rollback contrôlés, permissions appliquées | Réussi | MultipleContactsTest ; InternationalContactsTest ; UserAccountManagementTest |
| Même e-mail sur deux comptes | Refus après normalisation | Création publique/admin et collision avec secondaire refusées | Réussi | MultipleContactsTest |
| Même téléphone local/international | 0779107446 et +213779107446 sont identiques en DZ | E.164 identique, refus public/admin/model/SQL et archives | Réussi | PhoneNumberNormalizer ; InternationalContactsTest ; MultipleContactsTest |
| E-mails/téléphones secondaires | Plusieurs valeurs liées au bon propriétaire | Ajout personnel/admin, normalisation et pays conservés | Réussi | UserContactService ; MultipleContactsTest |
| Nouveau secondaire non vérifié | Pas d’authentification ni de récupération via cet alias | Provider ne retourne pas le compte | Réussi | ContactUserProvider ; MultipleContactsTest |
| Vérification secondaire | URL signée, expirante, limitée au propriétaire | Autre compte/expiration/altération refusés ; preuve propre à l’adresse | Réussi | VerifyContactEmail ; MultipleContactsTest |
| Session limitée et secondaire | Prouver son adresse sans ouvrir le profil ni prouver le principal | Vérification permise ; ajout interdit ; principal reste non vérifié | Réussi | EnsureActiveCentralAccount ; MultipleContactsTest |
| Secondaire devenu principal avant utilisation du lien | Conserver Verified et le retour Fortify | Preuve acquise, événement unique, retour connexion, statut PENDING conservé | Réussi | MultipleContactsTest |
| Connexion par alias vérifié | Même compte, mêmes restrictions | Login accepté par Fortify | Réussi | MultipleContactsTest |
| Récupération via alias vérifié | Message au bon alias, token sécurisé | Adresse destinataire, reset et refus de réutilisation contrôlés | Réussi | ResetUserPassword ; MultipleContactsTest |
| Principal / conservation | Un principal par type, ancien conservé | E-mail vérifié conservé, téléphone et sa preuve conservés lors de la sélection | Réussi | UserContactService ; MultipleContactsTest |
| Suppression secondaire | Supprimer le secondaire et révoquer ses tokens | Récupération par l’ancien alias devenue impossible | Réussi | MultipleContactsTest |
| Suppression du principal | Ne pas laisser des coordonnées sans principal | Suppression directe et remise à vide du téléphone principal refusées | Réussi | MultipleContactsTest |
| Recherche par secondaire | Résultat unique, espaces autorisés seulement | Propriétaire retrouvé, lecture/écriture non autorisées refusées | Réussi | UserManagementService ; StaffManagementService ; MultipleContactsTest |
| Pays actifs | Refuser les nouveaux choix inactifs | Ajouts refusés pour FR inactive ; ancienne donnée conservable | Réussi | CountryManagementTest ; InternationalContactsTest |
| Téléphone étranger au pays de profil | Pays propre au numéro sans changement du profil | Secondaire FR normalisé +33 et profil DZ conservé | Réussi | MultipleContactsTest |
| Formats et indicatif partagé | Valider le numéro pour le pays ISO explicite | Formats DZ, séparateurs, invalides et cas US/CA +1 contrôlés dans le normaliseur | Réussi | InternationalContactsTest |
| Unicité SQL des coordonnées | Interdire les collisions même hors validation Laravel | INSERT registre et UPDATE users concurrents ne peuvent partager la valeur | Réussi | UserContactConcurrencyTest ; MultipleContactsTest |
| Unicité UUID / principal | UUID unique et au plus un principal par type | Violations UNIQUE réellement rejetées par MySQL | Réussi | MultipleContactsTest |
| Réservation de première boutique simultanée | Même clé → un tenant et un job | Deux processus acceptés, une seule réservation persistée | Réussi | UserContactConcurrencyTest |
| Deux créations pour la dernière place | Ne pas dépasser le quota | Une création acceptée, l’autre refusée ; deux boutiques au total sous offre gratuite | Réussi | UserContactConcurrencyTest |
| Slug / domaine déjà pris | Erreur compréhensible, pas de réservation orpheline | Collision slug et exception Stancl de domaine traduites ; aucun job ajouté | Réussi | TenantCreator ; TenantManagementTest |
| Provisionnement en cours | Pas de dashboard avant disponibilité | Redirection préparation et blocage du domaine indisponible | Réussi | MerchantJourneyTest ; ShopCreationWorkflowTest |
| Première boutique prête | Migration tenant, singleton, propriétaire local et activation | Vrai job isolé ; références UUID et accès central corrects | Réussi | ShopCreationWorkflowTest |
| Boutiques suivantes / quota | Création sur demande, quota respecté | Deux boutiques actives puis troisième refusée, pas de précréation | Réussi | ShopCreationWorkflowTest |
| Double soumission / rollback | Pas de duplication ou job orphelin | Réservation idempotente et annulation transactionnelle contrôlées | Réussi | ShopCreationWorkflowTest |
| Échec / reprise | Garder la réservation et ses réglages, reprise possible | Échec injecté puis vrai job réussi avec mêmes réglages | Réussi | TenantProvisioningRetry ; ShopCreationWorkflowTest |
| Parcours complet joint | Inscription → vérification → approbation → boutique → dashboard | Scénario HTTP continu exécuté avec vrai provisionnement isolé et liens propriétaire/shop vérifiés | Réussi | ShopCreationWorkflowTest |
| Isolation et sécurité | Refuser URLs d’autres comptes/boutiques et privilèges non détenus | Refus d’accès, guard Tenant invité, contexte avant initialisation et références testés | Réussi sur les parcours concernés | MerchantJourneyTest ; ShopCreationWorkflowTest ; StaffManagementTest |
| Schéma central cible | Tables, types, FK et UK conformes aux deux documents | Comparaison sur MySQL temporaire réussie ; table historique explicitement distinguée | Réussi | DocumentedCentralSchemaTest ; tests/Pest.php |
| Métadonnées des bases existantes | Auditer sans les modifier | 119 index uniques centraux ; mêmes 256 index dans les 4 bases tenant | Réussi en lecture seule | information_schema ; inventaire ci-dessus |
| Données réelles sans doublons | Pas de conflit avant migration | Deux numéros invalides et deux groupes partagés restent présents | Échoué sur les données actuelles, déploiement bloqué | AuditUserContacts ; correction métier requise |
| Réception SMTP dans une boîte réelle | Livraison de bout en bout | Aucun message réel envoyé ; notifications simulées conformément à l’accord | Non exécuté | SMTP réel hors ce test |
| Panne/redémarrage du worker Windows | Redémarrage supervisé | État Running/Enabled lu ; pas de crash volontaire | Non exécuté pour la panne | LocalProvisioningWorker |
| Dashboard / connexion interne des boutiques | Développement tenant séparé | Reportés selon la décision d’architecture | Hors périmètre ; non exécuté | Architecture §19 et §33 |
| Toute la suite historique / charge / performance | Contrôle exhaustif et mesures représentatives | Contrôles ciblés seulement, aucun benchmark ni test de charge annoncé | Non exécuté exhaustivement | Aucun gain de performance chiffré revendiqué |

### Exécutions et résultats chiffrés

- 66/66 tests, 471 assertions : AuthenticationTest, PasswordResetTest, MerchantJourneyTest et MerchantApprovalWorkflowTest.
- 35/35 tests, 273 assertions : TenantManagementTest, CountryManagementTest, ProfileUpdateTest, RegistrationTest et EmailVerificationTest.
- 53/53 tests, 434 assertions : MultipleContactsTest (16 cas à cet instant), StaffManagementTest, UserAccountManagementTest et les deux premiers scénarios de concurrence.
- InternationalContactsTest : les 25 cas ont réussi dans un lot arrêté ensuite sur une ancienne assertion de StaffManagementTest. L’erreur du lot n’est pas transformée en commande réussie ; le personnel a ensuite été repris dans le lot vert de 53 tests.
- DocumentedCentralSchemaTest : comparaison réussie dans le lot de 18 réussites avant arrêt sur la collision de domaine désormais corrigée ; l’égalité des tables/champs/types/FK/UK reste testée, aucune assertion retirée.
- ShopCreationWorkflowTest : 9/9 tests, 72 assertions, puis le nouveau parcours public complet 1/1, 26 assertions, chacun avec le vrai job dans les bases isolées.
- Reprise ciblée finale : 3/3 tests, 17 assertions, pour le lien secondaire devenu principal et la conservation des preuves des principaux.
- Dernière reprise : 5/5 tests, 55 assertions : secondaire international, vraie concurrence téléphone, vraie concurrence de décisions, vraie double création et vraie concurrence de quota.
- Total : **193 scénarios distincts exécutés et validés**, en plusieurs lots et reprises ciblées, sans compter plusieurs fois les reprises d’un même scénario. Ce n’est pas une suite complète verte lancée en une commande.
- Syntaxe PHP vérifiée sur les 57 fichiers PHP modifiés/nouveaux alors présents (incluant les changements préexistants des Missions 7–8) ; les dernières modifications de tests sont également chargées par Pest. Routes de coordonnées chargées et rendus Blade traversés par HTTP. git diff --check sans erreur, simples avertissements CRLF.
- Pas de Pint, PHPStan, build systématique, migration réelle, seeder réel ni test destructif sur les bases conservées.

### Fichiers principaux concernés

- Données : app/Models/Central/User.php ; app/Models/Central/UserContact.php ; database/migrations/central/2026_10_18_172424_create_user_contacts_table.php.
- Métier : app/Services/Central/Users/UserContactService.php ; UserManagementService.php ; StaffManagementService.php ; StaffInvitationService.php ; ContactMigrationAudit.php ; app/Services/Central/Countries/CountryService.php ; app/Services/Central/Tenants/TenantCreator.php.
- Authentification : app/Services/Central/Auth/ContactUserProvider.php ; app/Providers/FortifyServiceProvider.php ; config/auth.php ; app/Actions/Central/Auth/Fortify/{CreateNewUser,ResetUserPassword}.php ; app/Http/Middleware/Central/Auth/EnsureActiveCentralAccount.php.
- Validation : app/Http/Requests/Central/Users/{StoreUserContactRequest,StoreUserRequest,UpdateUserRequest}.php.
- Entrées : app/Http/Controllers/Central/Settings/UserContactController.php et app/Http/Controllers/Central/Admin/Users/UserContactController.php ; routes/central/settings.php et routes/central/admin/users.php.
- Notification : app/Notifications/Central/Auth/VerifyContactEmail.php.
- Interfaces : resources/views/central/pages/settings/contacts.blade.php ; resources/views/central/partials/user-contacts.blade.php ; profil personnel existant ; resources/views/central/pages/admin/{users,staff}/edit.blade.php ; resources/views/central/pages/auth/{registration-status,verify-email}.blade.php.
- Tests : tests/Central/Feature/Settings/{MultipleContactsTest,InternationalContactsTest,ProfileUpdateTest}.php ; tests/Central/Feature/Auth/{AuthenticationTest,RegistrationTest,EmailVerificationTest,MerchantJourneyTest}.php ; tests/Central/Feature/Admin/{Users/UserAccountManagementTest,Staff/StaffManagementTest,Tenants/TenantManagementTest}.php ; tests/Central/Integration/UserContactConcurrencyTest.php ; tests/Tenant/Feature/ShopCreationWorkflowTest.php ; tests/Pest.php ; phpunit.xml.
- Documentation durable : AGENTS.md, architecture §32–34, schéma principal, diagramme central et ce suivi. Les recherches historiques restent conservées, la dernière décision explicite remplace leur ancienne limitation.

Versions réellement installées contrôlées : PHP 8.5.10, Laravel 13.34.0, Fortify 1.41.0, Livewire 4.4.7, Flux 2.20.1, Permission 8.3.0, Activity Log 5.0.0, Stancl 3.10.1, libphonenumber 9.0.41. Aucun package ajouté ni mis à jour pendant ces missions.

### Point de reprise

Code et audit ciblé vérifiés ; reste bloquant pour le déploiement réel : faire corriger les coordonnées authentiques des sept comptes identifiés, relancer l’audit, puis organiser la sauvegarde et la migration centrale avec arrêt temporaire des écritures/workers. Ne pas forcer les migrations ni inventer des numéros. La source actuelle reste utilisable pour les comptes existants, mais ses créations/modifications de coordonnées sont protégées tant que le schéma n’est pas déployé. La gestion multiple affiche cette indisponibilité au lieu de prétendre avoir enregistré une modification.

## 19 octobre 2026 — Déblocage réel des coordonnées centrales

Le message « La mise à jour des coordonnées est temporairement indisponible » provenait du schéma central non migré : users.phone_country_id et user_contacts absents. Après confirmation explicite de l'utilisateur : maintenance HTTP et arrêt du worker local, sauvegarde privée vérifiée dans storage/app/private/contacts-before-migration-20261019-052447.json (exclue de Git), puis retrait transactionnel des téléphones et preuves téléphone/WhatsApp des seuls comptes 10, 12, 13, 14, 15, 16 et 17. Aucun transfert, numéro fictif, compte ou boutique supprimé.

Les deux migrations 2026_10_18_105836_add_international_phone_identity_to_users_table et 2026_10_18_172424_create_user_contacts_table sont exécutées avec succès sur MySQL aydra. Audit avant/après migration : aucun numéro invalide ni doublon restant. Contrôles en lecture seule : colonne et table présentes, index uniques téléphone/registre présents, deux triggers de synchronisation présents, projections principales cohérentes, e-mails inchangés, 16 comptes / 4 boutiques / 4 domaines conservés, 21 coordonnées initialisées. Site remis en ligne ; worker Running et Enabled ; GET /login répond HTTP 200.

Aucun changement PHP ni migration réécrite : le code existant retrouve le schéma attendu. Aucun nouveau compte de test créé dans la base réelle, aucun e-mail réel envoyé et aucune suite de tests relancée. La création et la modification complètes via navigateur ne sont pas annoncées comme testées. Les sept comptes restent à compléter avec leurs bons numéros. Le point de reprise bloquant du 18 octobre est résolu pour ce déploiement ; ses résultats historiques restent conservés.
## 19 octobre 2026 — Retour à un e-mail et un téléphone par compte

Nouvelle décision explicite : un seul e-mail et au maximum un téléphone dans users, sans coordonnées secondaires. Elle remplace les coordonnées multiples des Missions 9–10. Conserver la normalisation internationale, phone_country_id, les contraintes UNIQUE(email)/UNIQUE(phone), les vérifications réelles et la séparation Central/Tenant. Le téléphone reste obligatoire à l'inscription publique ; les comptes historiques sans téléphone sont préservés.

Intervention réelle autorisée : sauvegarde privée vérifiée du registre et des coordonnées utilisateurs dans storage/app/private/user-contacts-before-removal-20261019-054048.json (exclue de Git). Aucun secondaire présent. Nouvelle migration 2026_10_19_053933_remove_user_contacts_for_single_contact_accounts exécutée sur MySQL aydra : suppression des triggers users_contacts_insert/update puis de user_contacts. Migrations déployées historiques conservées. La nouvelle migration bloque sur projection incohérente ou présence de secondaires et refuse un retour arrière fictif.

Code adapté : User sans relation/alias de coordonnées, provider Eloquent Laravel standard, récupération Fortify sur le seul e-mail actuel, recherches Utilisateurs/Personnel sur les champs users. Pages/routes/formulaires/Controllers/Service/Request/notification spécifiques aux coordonnées multiples supprimés ; profil personnel et édition administrative existants conservés. CountryService protège toujours les références de pays du profil et du téléphone. Aucun changement aux comptes locaux ou bases Tenant. La modification préexistante de registration-status.blade.php est préservée.

Contrôles réellement exécutés : syntaxe de 18 fichiers PHP, git diff --check sans erreur, audit sans numéro invalide/doublon, contrôle de l'absence de table et de triggers, présence des deux index uniques, comparaison complète des champs utilisateurs sauvegardés, résolution du provider Eloquent et de l'e-mail actuel, récupération sur ce même e-mail. 18 utilisateurs, 6 boutiques et 6 domaines présents et inchangés depuis la sauvegarde de cette intervention. Aucune route de coordonnées multiples restante ; GET /login HTTP 200 ; site en ligne, worker Running/Enabled. Tests de comportement réécrits dans SingleContactTest et concurrence adaptée ; non exécutés (aucun nouvel accord de bases temporaires pour ce lot). Aucun test de création ou de changement de coordonnées écrit dans la base réelle, aucun e-mail de test envoyé. Vérification navigateur complète non exécutée.

## 20 octobre 2026 — Regroupement des déclarations de routes

Demande : mutualiser middleware/prefix/name/controller dans des groupes cohérents et rendre cette convention durable. Les modifications préexistantes de UserController et Country restent intactes. Le brouillon préexistant de routes/central/admin/users.php comportait des doublons, un groupe vide, un middleware auth supplémentaire, pending après le paramètre {user}, un nom d'invitation perdu et un nom concaténé sur legal/reject ; ce fichier est corrigé autour des contrats historiques du module.

Changements : routes/central/admin.php porte admin et central. ; chaque module administratif porte son préfixe/nom et partage ses contrôleurs. Groupes Owner dans central.php ; préfixes et contrôleur photo dans central/settings.php ; nom tenant. mutualisé dans tenant.php/storefront.php. web.php (domaines), diagnostics.php et console.php sont déjà simples et restent inchangés. Auth:central, verified, EnsureActiveCentralAccount, can, signed, throttles, withTrashed et ordre des protections Tenant conservés. Les routes Fortify/Livewire fournies par packages ne sont pas redéfinies. Convention ajoutée à AGENTS.md et architecture §35. Aucun changement de table ni donnée.

Contrôles exécutés : syntaxe des 14 fichiers de routes ; comparaison de 277 contrats de routes (domaine, verbes, URI, nom, action, middleware, archives, bindings), sans différence après regroupement et correction du brouillon Users. Les contrats attendus Users proviennent de la version Git initiale ; les autres viennent de la collection chargée avant modification. Noms generated:: variables du cache exclus de l'identité publique. Quinze vérifications de correspondance des chemins statiques sur les trois domaines centraux, aucune collision de nom. Ancien cache détecté, vidé puis régénéré avec succès ; nouvelle comparaison réussie cache actif. GET /login HTTP 200 ; git diff --check sans erreur. Aucun test avec écriture MySQL, suite complète, Pint ou PHPStan exécuté ; parcours authentifiés complets non annoncés comme testés.

Point préexistant signalé : UserController::countires() utilise $status sans le définir ; cette modification de l'utilisateur n'est pas corrigée dans une réorganisation de routes. Aucun gain de performance chiffré revendiqué : le bénéfice porte sur la lisibilité et l'absence de répétitions.

## 20 octobre 2026 — Cache des sélecteurs de pays centraux

Demande : cacher les valeurs de référence stables avec portée et invalidation définies. Audit des lecteurs : les pays sont actuellement le référentiel partagé réellement utilisé par plusieurs formulaires. Les listes dépendant de l'acteur, permissions/attributions, utilisateurs, abonnements, quotas et états restent directes. Aucun cache ajouté à des modules non implémentés ni aux constantes/enums déjà en mémoire.

CountryCatalog ajouté dans Services/Central/Countries : cache Laravel fichier de tableaux countries pendant 12 heures, chemin central distinct par identité de connexion, indépendante des chemins Tenant. Listes actives et variantes pour conserver le pays actuel de profil/téléphone ; aucune donnée personnelle. Version de génération pour éviter qu'un ancien remplissage concurrent redevienne courant. Country invalide sur saved/deleted/restored après commit ; lecture directe dans une transaction, rollback sans publication ni invalidation. La configuration serializable_classes=false reste intacte : les tableaux du cache sont réhydratés à la lecture. Toute écriture SQL/bulk hors événements exige une invalidation explicite. Architecture §36 et AGENTS.md actualisés.

Raccordements : UserController et StaffController utilisent une propriété CountryCatalog injectée ; FortifyServiceProvider l'utilise pour l'inscription ; profil Livewire pour les sélecteurs personnels. L'ancien brouillon countires() ($status indéfini, méthode innactive inexistante) est remplacé ; les scopes Country préexistants sont conservés. CountryService, Requests et services de création/édition gardent leurs vérifications actives et verrous SQL ; la liste administrative paginée des pays reste directe.

Contrôles réellement exécutés sans écriture de données : première lecture 1 requête SQL, trois lectures du cache 0 ; sélections comparées aux requêtes attendues pour les cinq pays ; résolution des deux Controllers ; invalidation par émission contrôlée de saved dans une transaction vide, 1 nouvelle requête après commit et 0 après rollback. Changer le chemin du cache fichier par défaut comme le bootstrapper Tenant ne change pas le cache central (0 requête supplémentaire). Comparaison des lignes countries avant/après identique. Syntaxe de tous les fichiers PHP touchés, GET /register HTTP 200 et git diff --check sans erreur. Aucune modification des pays réels ni de la configuration globale, aucun e-mail réel.

CountryCatalogTest ajouté dans tests/Central/Integration pour chargement unique, modèles indépendants, activation/édition/archivage/restauration, rollback et séparation du cache par défaut Tenant. Ces tests sur bases MySQL temporaires ne sont pas exécutés faute de nouvel accord pour ce lot ; les contrôles ci-dessus sont des sondes en lecture seule, pas une annonce de suite passée. Aucun benchmark de latence globale ni gain de performance chiffré hors nombre de requêtes. Cache fichier adapté au serveur local actuel ; un déploiement multi-instance devra partager le store ou propager l'invalidation.

## 20 octobre 2026 — Rappel et autorisation durables des futurs caches

L'utilisateur demande de rappeler la décision au moment d'intégrer/utiliser les données identifiées, puis de réaliser le cache directement sans nouvelle confirmation : wilayas/communes, points de retrait, informations publiques des transporteurs, présentation des offres, informations publiques de boutique et catégories. Déclencheur ajouté dans « Avant chaque tâche » d'AGENTS.md ; architecture §36 détaillée et schéma principal référencé. Chargement à la demande, isolation central/boutique, images hors cache de données, invalidation après commit, durée adaptée et mesure du stockage précisés. Les contrôles de droits/prix applicables/quota/état restent actuels ; aucune autorisation générale de migration, donnée réelle, test BDD ou infrastructure supplémentaire.

Intervention documentaire uniquement. Aucun nouveau cache ni fonctionnalité ajouté, aucune BDD modifiée. Les changements existants de code/routes sont conservés. Vérification des chemins de référence et du diff ; aucun test applicatif nécessaire pour cette consigne. État : cache pays implémenté ; les autres caches sont explicitement autorisés pour leurs futures intégrations, sans fausse annonce d'implémentation actuelle.

## 20 octobre 2026 — Scopes locaux pour filtres répétés

Demande appliquée dans les modèles centraux : Country active/inactive, Feature active, User withStatus(enum central), Role/Permission forCentralGuard. Déclarations scopeXxx typées Builder et usages réels dans inscription, comptes, approbations, administration des rôles/permissions et boutiques. Country::inactive() impose false ; l'ancien argument booléen n'avait aucun appel dans app. Les filtres identiques restent explicites dans les validations non Eloquent et les relations/traits génériques partagés avec Tenant. Aucun scope global, changement de table, données, connexion, Policy, cache ou règles d'expiration.

Contrôles réalisés : 17 vérifications positives, dont équivalence SQL/paramètres pour les filtres simples, pays actif ou courant, verrous, guards et les six statuts centraux, puis comparaison des lignes de pays actifs/inactifs en lecture seule. Syntaxe de 18 fichiers PHP modifiés ; diff relu et contrôlé. Pas de migration, test avec écriture, Pint ou PHPStan. Les modifications préexistantes des routes, du cache pays et les instructions antérieures sont conservées. Aucun gain de performance revendiqué : la requête SQL reste équivalente. Convention durable ajoutée dans AGENTS.md et architecture §37.

## 20 octobre 2026 — Capitalisation continue des pratiques validées

Demande : documenter progressivement les pratiques comme les caches et les scopes, pour les réappliquer lorsqu'elles sont nécessaires. Rappel ajouté dans « Avant chaque tâche » d'AGENTS.md et procédure détaillée en architecture §38, reliée aux sections existantes injection/routes/cache/scopes. Déclencheur, portée, limites, règles remplacées, distinction décision/proposition et résultats de contrôles doivent être maintenus au fil du travail. Application directe dans les prochaines tâches pertinentes, sans nouvelle confirmation de principe, en préservant les autorisations particulières et sans optimisation automatique hors besoin.

Changements documentaires uniquement ; chemins et diff vérifiés. Aucun code, cache, BDD ou réglage global modifié pour cette consigne.


## 20 octobre 2026 — Audit et simplification des utilisateurs centraux

### Décision finale confirmée pendant la mission

L'acceptation concerne l'inscription, pas les éditions ultérieures. Modifier nom, e-mail, téléphone, pays ou informations professionnelles conserve le statut, la décision du dossier, son auteur et sa date. Aucun retour devant un administrateur. Le nouvel e-mail doit être vérifié ; le nouveau téléphone perd ses anciennes preuves. Un refus ou une suspension ne disparaît pas par une édition. La version du dossier augmente pour refuser les anciens formulaires de décision. Les règles remplacées sont actualisées dans AGENTS.md, architecture §24/§39 et schéma principal.

### Problèmes confirmés et corrections

UserManagementService.update remettait ACTIVE/REJECTED en PENDING après toute identité/contact/champ professionnel modifié et effaçait la validation. Cette logique est retirée ; les valeurs par défaut et mutators du modèle n'étaient pas la cause. fillProfile regroupe liste blanche, pays, normalisation et validation professionnelle ; la détection du personnel est calculée une seule fois. LegalVerificationService.versionChangedProfile versionne sans décider. update garde transaction, compte verrouillé, Policy, sauvegarde, audit et notifications après commit.

UserApprovalService.decide gérait déjà les deux décisions : aucun nouveau UserStatusService parallèle créé. Approve/reject gardent leurs différences, motif obligatoire, états/version attendus, approbation après refus et protections root/système. Les Controllers d'approbation et de compatibilité legal utilisent une propriété injectée plutôt que réinjecter le même service par action ; UserApprovalRequest expose les données validées typées. Les deux événements historiques compte/dossier restent présents ; leur écriture commune est mutualisée dans une boucle, sans double décision.

AuthorizationMutation reste utile : transaction centrale, mutex du rôle root système/protégé pour sérialiser droits et décisions, trois tentatives et invalidation Spatie après commit. Policies contrôlées dans le Request puis à nouveau sous verrou, verrous du modèle et interdiction du contexte Tenant conservés. Le catch générique avec rethrow est remplacé par UniqueConstraintViolationException ; les exceptions sans rapport remontent normalement. Suspension/réactivation et archivage ne sont pas fusionnés avec l'approbation.

Interface : confirmation Flux/Tailwind ajoutée à Approuver dans lifecycle-actions, refus motivé et protections @can conservés. users/edit précise l'absence de nouvelle approbation ; le profil Livewire retire sa redirection devenue inutile vers un nouvel examen.

### Fichiers et flux

Services : app/Services/Central/Users/{UserManagementService,UserApprovalService,LegalVerificationService}.php et app/Services/Central/Authorization/AuthorizationMutation.php. Entrées : app/Http/Controllers/Central/Admin/Users/{UserApprovalController,LegalVerificationController}.php et app/Http/Requests/Central/Users/UserApprovalRequest.php. Vues : resources/views/central/pages/admin/users/{edit,lifecycle-actions}.blade.php et profil Livewire personnel existant. Tests adaptés dans Admin/Users, Settings et Integration/UserContactConcurrencyTest.php. Routes, modèle User, enums, Policies et middlewares analysés et conservés sans changement de contrat public. Aucun nouveau service, migration ou couche ajouté.

Décision : View/modal → route centrale → auth/verified/compte actif → Form Request + Policy → Controller → UserApprovalService.decide → transaction/mutex root → User verrouillé + nouvelle Policy + état/version contrôlés → attributs de décision → sauvegarde et audits Spatie → commit → Controller → Redirect → View. Les anciennes routes legal gardent show, approve/reject gardent pending.

Édition : View → route/middleware → UpdateUserRequest/Policy ou validation Livewire → Controller/composant → UserManagementService.update → compte verrouillé et Policy → fillProfile/versionChangedProfile → preuves de contact adaptées → save et audit → notification après commit → résultat → Redirect/View ou confirmation du profil. Aucune création automatique de boutique ; RegistrationJourney conserve e-mail, attente/refus et première boutique obligatoire.

### Tests réellement exécutés

Tests demandés explicitement pour ce lot, sur les seules bases MySQL temporaires de tests/TestCase.php avec e-mails simulés. Aucune donnée existante ni migration réelle modifiée.

| Lot | Résultat observé |
|---|---|
| MerchantApprovalWorkflowTest + UserAccountManagementTest + ProfileUpdateTest avant le dernier nouveau cas | 44/44 tests, 340 assertions, réussis. |
| MerchantJourneyTest | 22 scénarios réussis dans le lot de 28 arrêté ensuite sur l'ancienne assertion du sixième UserLifecycleTest ; ce lot n'est pas annoncé globalement vert. |
| Reprise UserLifecycleTest + ProfileUpdateTest | Les six cas de cycle de vie et les six cas personnels existants réussissent ; le nouveau treizième cas échoue d'abord sur une fixture VERIFIED sans auteur, correctement refusée par le CHECK SQL. |
| Nouveau profil personnel, fixture complétée avec son auteur | 1/1, 7 assertions, réussi : identité modifiée, approbation conservée, nouvelle preuve d'e-mail requise. |
| Deux décisions réellement simultanées | 1/1, 12 assertions, réussi : un succès, un conflit/refus, une seule décision auditée et états synchronisés. |
| Personnel : suspension et cibles protégées | 2/2, 27 assertions, réussis. |

Couverture : permissions manquantes, root/système protégé, refus motivé, approbation après refus, invitation et création administrative, anciennes routes legal, formulaires obsolètes, conservation des cinq états éditables après modification de tous les champs, unicité et preuves des contacts, rollback complet sur échec d'audit, absence de boutique automatique et première boutique. Les assertions ne sont pas retirées : les attentes de remise en attente sont adaptées à la nouvelle règle ; les instantanés comparent des lignes rechargées pour inclure les valeurs par défaut SQL.

Reprises : le premier test recevait 419 car le cache local masquait APP_ENV=testing. APP_CONFIG_CACHE/APP_ROUTES_CACHE sont isolés dans les processus des tests vers des chemins temporaires inexistants ; cache du site conservé et aucune protection CSRF de l'application désactivée. Le test concurrent reconnaît une AuthorizationException uniquement pour approve/reject : une première approbation peut rendre le refus concurrent interdit. Aucune Policy affaiblie ; un seul succès et un seul audit restent exigés.

Syntaxe des 16 fichiers PHP modifiés vérifiée, diff final relu, GET /login HTTP 200. Pas de Pint, PHPStan, build, suite complète, SMTP réel ou interaction navigateur annoncés. Les vues/modales sont rendues par les tests HTTP/Livewire. Aucun gain de vitesse chiffré revendiqué. Aucun ancien compte déjà en attente n'est approuvé automatiquement ; les états réels et l'historique restent conservés.

## 21 octobre 2026 — Spatie : corrections sûres et transition proposée

Source autorisée : spatie modiff.txt. Phases 1–3 uniquement ; le fichier autorise les tests sur bases isolées et interdit toute conversion sensible réelle avant validation. Les modifications déjà présentes avant ce lot (profil/approbation) sont conservées. Aucune attribution, table ou migration réelle modifiée ; aucune intervention dans les bases existantes des boutiques, aucun seeder central exécuté.

### Résultat et fichiers de ce lot

| Fichier | Correction et raison | Vérification |
|---|---|---|
| app/Services/Central/Authorization/AuthorizationMutation.php | Connexion du modèle central à la place de la connexion inexistante central ; transaction, mutex et afterCommit sur la même connexion | Mutations HTTP, rollback de droits/audit |
| app/Services/Central/Users/UserManagementService.php | Fusion des rôles non délégables conservés avant syncRoles ; retrait seulement des rôles éditables | Deux entrées Personnel/Utilisateurs ; dates et droits directs préservés |
| app/Services/Central/Roles/RoleManager.php | Comparaison de version sous verrou ; incrément pour toute vraie modification, y compris identité | Ancien formulaire refusé sans écrasement ni deuxième audit ; no-op préservé |
| app/Http/Requests/Central/Roles/SaveRoleRequest.php | expected_version obligatoire en modification, sans autoriser permission_version en entrée | Validation HTTP et champs forgés |
| app/DTOs/Central/Roles/RoleData.php | Transport de la version attendue jusqu'au service | Tests HTTP via le DTO |
| app/Services/Central/Authorization/CentralPermissionResolver.php | Délégation du résultat effectif à Gate, sources/dates historiques conservées | Comparaison avec can pour droits directs valides/expirés et compte suspendu |
| app/Services/Central/Authorization/CentralUserAccess.php | Une lecture fresh par allows, réutilisée dans ses branches dérivées | Matrices de permissions/délégation existantes |
| resources/views/central/pages/admin/roles/form.blade.php | Champ caché expected_version, gardé lors d'une erreur de validation | Rendu HTTP du formulaire et scénario obsolète |
| resources/views/central/pages/admin/staff/permissions.blade.php | Explication de l'héritage, lecture seule et distinction des consultations dérivées | Rendu dans les tests Personnel |
| resources/views/central/pages/admin/{staff,users}/roles.blade.php | Indication que les rôles non affichés sont conservés | Modifications de texte ; les deux routes sont testées |
| tests/Central/Feature/Admin/{Roles/RoleManagementTest,Staff/StaffManagementTest}.php | Cas de régression et nouveaux contrats de version | Exécutés, sans échec dans ces deux fichiers |
| tests/Central/Feature/CentralManagementTest.php | Ajout de expected_version aux deux requêtes de modification autorisée existantes | Deux scénarios passent ; trois autres défauts historiques ci-dessous |
| AGENTS.md, architecture §40, Diagramme-BDD-Centrale-Complet.md, ce suivi | Rappel durable, plan avant/après, documentation de la version centrale | Relecture et diff sans erreur d'espacement |

La duplication du calcul d'autorisation entre le trait et le resolver est retirée ; les sources historiques restent calculées pour l'affichage, sans décider d'un accès. Les transactions imbriquées sont conservées pour protéger l'audit avant/après et l'entrée autonome Utilisateurs ; pas de couche supplémentaire. Aucun gain de performance chiffré annoncé.

### État réel et proposition non exécutée

La relecture MySQL confirme mysql comme connexion centrale chargée, sans alias central, et les versions Laravel 13.34.0 / Spatie 8.3.0 / Stancl 3.10.1. PHP utilisé : exécutable Herd PHP 8.5.10 demandé. Cinq rôles, quarante-six permissions, cinq attributions de rôle, zéro permission directe ; profils/versions/bénéficiaires identiques à l'audit précédent. Neuf droits ordinaires hérités sur trois comptes, tous encore valides ; root reste à part. Les UUID et coordonnées personnelles ne sont pas publiés dans le rapport.

Approche recommandée : profils partagés avec modèles de départ, autorisations métier directes Spatie. L'architecture §40 contient la comparaison A/B, la matrice anonymisée actuelle/cible, le schéma proposé de role_permission_templates, les contraintes et la signature, les neuf conversions à échéance constante, la nouvelle capacité d'édition proposée, les effets sur les seeders et les nouveaux membres, les cas d'intervalles incompatibles et la procédure d'idempotence/retour arrière. Il n'y a encore ni migration de cette structure, ni route d'édition individuelle active, ni conversion réelle.

Décision supplémentaire requise : supprimer la consultation dérivée pour rendre les consultations individuellement révocables. La comparaison réellement exécutée trouve un seul écart : Personnel 3 consulte les utilisateurs par dérivation de create/update, sans permission Spatie users.view. Le plan §40 propose soit une dixième attribution directe users.view à la même échéance (changement explicite de can et de la possession délégable), soit neuf attributions seulement avec perte approuvée de cette consultation. Aucun choix implicite. Préserver l'accès actuel et garantir la révocation totale sont incompatibles si la dérivation continue de fournir l'accès retiré. Les règles centrales de rôles vides/compositions et la conservation des modèles doivent être approuvées avant de changer les traits. Ne pas appliquer ces modifications aux rôles Tenant. Le catalogue des permissions reste consultatif.

### Tests réellement exécutés

Commandes avec le PHP 8.5 demandé, vendor/bin/pest et uniquement les fichiers cités. APP_CONFIG_CACHE et APP_ROUTES_CACHE dirigés vers des chemins temporaires inexistants dans le processus, sans vider les caches du site. Bases créées et nettoyées uniquement par les protections de tests/TestCase.php ; e-mails simulés.

| Lot | Résultat réel |
|---|---|
| RoleManagementTest + StaffManagementTest + CentralManagementTest | 76 tests : 73 réussis, 3 échoués ; 514 assertions ; environ 230 secondes |
| PermissionCatalogTest + UserAccountManagementTest + SpatieIntegrationTest | 33 tests réussis sur 33 ; 315 assertions ; environ 172 secondes |
| Syntaxe des 10 fichiers PHP concernés par ce lot | Aucune erreur |
| git diff --check | Aucune erreur d'espacement ; avertissements Git préexistants de fin de ligne CRLF/LF |

Total : 106 réussites et 3 échecs sur 109 tests, 829 assertions. Les trois échecs sont dans CentralManagementTest, hors des nouveaux scénarios :

1. Ligne 53 : l'ancien test attend 200 pour l'attribution SaaS à un commerçant ; la Policy actuelle la refuse avec 403, conformément à la séparation validée des comptes.
2. Ligne 167 : l'ancien test exige le texte Créer un compte dans le dashboard actuel, qui propose le lien Consulter les utilisateurs. L'attente ne correspond plus au rendu existant.
3. Ligne 306 : l'ancien test appelle ProvisionTenantDatabase.handle avec un seul argument ; le job exige depuis avant ce lot TenantProvisioner et TenantStatusService. ArgumentCountError précède donc la LogicException attendue.

Ces assertions ne sont pas supprimées, la production n'est pas modifiée pour satisfaire des attentes obsolètes. Pas de Pint/PHPStan/build/suite complète exécutés. L'obsolescence est testée par deux soumissions successives d'un même état : pas de nouveau test de deux processus réellement simultanés annoncé pour ce lot. Les scénarios de conversion Ahmed/Mohamed/Karim et du nouveau formulaire restent non exécutés jusqu'à approbation et implémentation de cette transition ; ils ne sont pas présentés comme réussis.

### Point de reprise

Travail compatible effectué et vérifié ; attendre l'accord structurel du §40 demandé dans spatie modiff.txt. Après accord : implémenter et tester la structure additive et les profils centraux, le service/Request/Policy de droits directs raccordés au contrôleur existant, la suppression validée des dérivations, les seeders et la conversion avec aperçu complet récent. La conversion réelle doit conserver toutes les dates et demander un nouvel accord si la matrice diffère de l'aperçu validé. Ne pas interpréter un simple continue comme l'approbation de changements sensibles non encore présentés.

## 21 octobre 2026 — Phase 4 Spatie approuvée, réalisée et déployée

### Accord et résultat

Après présentation du plan §40 et des deux décisions, l'utilisateur répond « oui asy poursuis », puis demande de continuer. Cet accord couvre la transition présentée et la matérialisation de la seule consultation dérivée identifiée. Les tests MySQL isolés restent autorisés par spatie modiff.txt. Le point de reprise précédent est donc réalisé, et non toujours en attente.

La centrale utilise désormais les profils partagés comme modèles de départ, avec permissions métier effectives directes Spatie. Une révocation individuelle conserve le profil et ne touche pas les autres membres. Root reste privilégié/protégé ; les comptes commerçants restent exclus des attributions SaaS. Aucune migration/conversion des bases Tenant existantes, aucun package ajouté ou fichier vendor modifié.

### Fichiers de cette phase

| Fichier | Réalisation |
|---|---|
| database/migrations/central/2026_10_21_000000_create_role_permission_templates_table.php | Nouvelle table centrale, PK composite, vraies FK RESTRICT et CHECK 1..9999 ; rollback de schéma interdit si les modèles existent |
| app/Models/Central/Role.php | Relation templates et permissionComposition ; relation native permissions conservée pour le vrai héritage, sans pointer vers les modèles |
| app/Concerns/HasRoleMetadata.php | Compositions de modèles centrales et compositions natives Tenant, signatures/durées/caches conservés |
| app/Concerns/HasTimedPermissions.php | Attribution native du profil puis copie native des droits absents, sans renouveler/recréer les droits d'un profil conservé ; audit des dates initiales ; verrou capable de conserver les comptes archivés lors de la conversion |
| config/permission.php | Mode central_profiles actif après conversion et rechargement ; aucune option Teams ajoutée |
| app/Services/Central/Authorization/CentralPermissionAssignmentService.php | Lots individuels, contrôle d'état et de délégation sous verrou, dates conservées, renouvellement explicite et audit atomique |
| app/Http/Requests/Central/Users/UpdateStaffPermissionsRequest.php | UUID, booléens, durées, motif et empreinte attendue validés ; aucun ID interne ou date libre |
| app/Http/Controllers/Central/Admin/Staff/StaffPermissionController.php et routes/central/admin/staff.php | GET existant enrichi et PUT ajouté dans le même module |
| app/Policies/Central/UserPolicy.php | Consultation/édition distinctes, pas d'auto-attribution, root/commerçant/boutique/cible protégés ; suspension indépendante des droits |
| app/Services/Central/Authorization/CentralPermissionResolver.php | Catalogue administrable, sources/dates et résultat Gate, sans second moteur d'autorisation |
| app/Services/Central/Authorization/CentralRoleAssignmentService.php | Renouvellement explicite des seuls droits directs encore présents dans le modèle, avec dates auditées |
| app/Services/Central/Authorization/{CentralUserAccess,AuthorizationMutation}.php | Fin des lectures dérivées en mode courant ; invalidation du registrar aussi en cas de rollback |
| app/Services/Central/Roles/{RoleManager,CentralRoleCatalog}.php | Modèles initiaux, suppression sûre, capacité saas.staff.permissions.update ajoutée au catalogue sans enrichir les profils |
| app/Services/Central/Users/UserManagementService.php et app/Http/Controllers/Central/Admin/Roles/RoleController.php | Sélection/délégation et édition des compositions initiales, droits effectifs individuels conservés |
| app/Services/Central/Authorization/CentralPermissionTransition.php et app/Console/Commands/Central/ConvertPermissionProfiles.php | Aperçu, empreinte, sauvegarde chiffrée, conversion atomique/idempotente et retour arrière contrôlé |
| resources/views/central/pages/admin/staff/{permissions,roles,show}.blade.php et admin/users/roles.blade.php | Cases individuelles, dates/expiration, renouvellement motivé, textes de profils et protection des rôles masqués |
| resources/views/central/pages/admin/roles/{form,index,show}.blade.php | Composition du modèle affichée sans promettre une modification de tous les membres |
| Tests Admin/Staff/DirectPermissionManagementTest.php, Admin/Roles, Admin/Staff, Admin/Permissions, Admin/Users/CentralUserModuleTest.php, CentralManagementTest.php, Tenant/Tenancy/SpatieIntegrationTest.php et Integration/UserContactConcurrencyTest.php | Couverture de la nouvelle règle et attentes historiques adaptées sans suppression des tests |
| AGENTS.md, instructionn.txt, architecture §40–41, schéma et diagramme central | Règles actives, références et nouvelle table documentées ; cible centrale passe à 28 tables |

### Déploiement réel et contrôles des données

Inventaire source contrôlé avant écriture : 5 rôles avec noms/versions/protections identiques au plan approuvé ; 46 permissions, 9 compositions effectives, 5 attributions de rôle et 0 droit direct. La nouvelle table est créée par la seule migration additive demandée, après les tests isolés. L'aperçu retrouve 10 droits directs, conformément au plan : neuf droits convertis et une lecture auparavant dérivée.

Maintenance brève pendant l'application : down, migration centrale additive, commande aydra:convert-permission-profiles --apply avec l'empreinte revue, config:cache, route:cache, queue:restart puis up. La commande sauvegarde les six tables d'autorisation dans un fichier chiffré du disque local privé, sous authorization-transitions, exclu de Git. Son chemin exact et ses empreintes sont conservés dans l'activité central_permissions_converted ; aucun compte ou secret publié. La sauvegarde reste disponible et aucune purge n'est effectuée.

État après commit vérifié par lecture réelle : 5 rôles, 5 attributions, 9 modèles, 0 héritage effectif ordinaire, 10 droits directs et 47 codes de permission. Les deux FK de modèles sont présentes. Identités, signatures, versions, protections et dates des rôles sont contrôlées avant commit et inchangées. Les neuf échéances antérieures sont conservées ; la lecture matérialisée reprend sa période exacte. Root ne reçoit aucune ligne directe et conserve tous les codes existants, y compris le nouveau code de gestion.

Matrice d'accès relue avec can : Personnel 2 garde users.view/update ; Personnel 3 garde countries.view et users.create/update, avec users.view désormais explicite ; Personnel 4 garde ses quatre droits ; root reconnaît les 47 codes stockés. Les intitulés abrégés commencent par saas. Une seule activité de conversion, sauvegarde présente et empreinte finale identique à l'état réel. Mode central_profiles=true chargé, nouvelle route présente et maintenance désactivée. Les modèles/données des bases boutique n'ont pas été modifiés.

### Tests réellement exécutés et reprises

| Contrôle | Résultat |
|---|---|
| Nouveau DirectPermissionManagementTest initial | 10 réussites et une erreur sur 11 : le retour arrière tentait de vider les droits du root, correctement bloqué ; corrigé pour exclure root |
| Reprise conversion/idempotence/retour arrière | 1/1 réussi, 10 assertions |
| Premier lot Rôles/Personnel/Catalogue/Spatie | 49 réussites sur 71 ; attentes d'héritage/dérivation remplacées par les nouvelles règles, deux fixtures sélectionnant la mauvaise ligne directe corrigées |
| Lot actualisé avec les nouveaux tests directs | 81 réussites sur 82, 581 assertions ; un test Spatie retenait la date de la première permission au lieu du droit ciblé |
| Reprise du cas daté corrigé + expiration de la gestion + cache après rollback | 3/3 réussis, 17 assertions |
| UserAccountManagementTest | 19/19 réussis, 195 assertions |
| Deux clients MySQL réellement concurrents sur les permissions | 1/1 réussi, 12 assertions : un succès, un conflit, un seul audit |
| CentralManagementTest, quatre scénarios concernés par les profils/lectures explicites | 4/4 réussis, 36 assertions ; profils partagés, durées futures et création sans lecture expirée |
| Reprise du contrôle de délégation après filtrage du catalogue individuel | 1/1 réussi, 6 assertions : les droits non attribués et non administrables ne sont pas proposés, un lot interdit reste entièrement annulé |
| Syntaxe PHP des 18 fichiers techniques principaux | Aucune erreur |
| git diff --check | Aucune erreur ; seuls avertissements CRLF/LF préexistants |

La couverture finale réunit 108 scénarios distincts exécutés avec succès après les corrections, répartis entre ces lots et reprises ; aucune suite globale de 108 tests en une commande n'est revendiquée. Les scénarios des deux dernières modifications mineures (audit enrichi et présentation conditionnelle de la page lors du déploiement) sont relus ; le rendu réel de la page après déploiement est vérifié séparément. Pas de Pint, PHPStan, compilation, suite complète ou e-mail réel exécutés. Les autres scénarios historiques de CentralManagementTest ne sont pas annoncés validés : le défaut préexistant d'appel de job décrit dans le lot précédent reste hors périmètre.

Les tests vérifient Ahmed/Mohamed/Karim, retrait et réattribution réels avec can, profils conservés sans réactivation, droits distincts, échéances, refus de délégation, protection root/soi-même/commerçant, inspection seule, ancien formulaire, rollback d'un lot, évolution des profils pour les futurs membres, transition/retour arrière, cache et isolation de deux boutiques. Les tests Tenant continuent de vérifier l'héritage natif et la séparation des caches/activités locales ; aucune table de modèles locale n'est créée.

### HTTP, rendu et limites

PowerShell ne résout pas aydra.localhost dans cet environnement. Contrôle local effectué avec http://127.0.0.1/login et Host: aydra.localhost : HTTP 200 HTML. Aucun hosts, DNS ou paramètre Windows modifié. Ce contrôle confirme le service local mais ne prouve pas la résolution DNS du navigateur de l'utilisateur.

La page Permissions du personnel réel est rendue localement avec le guard central et un contexte de vue initialisé : catalogue, jeton d'état et contrôles d'enregistrement présents. Le premier rendu CLI manquait du sac d'erreurs normalement fourni par le middleware ; le contrôle est repris avec ce contexte, sans modifier l'application pour contourner l'erreur. Aucun clic navigateur ou mutation de droits réelle n'est effectué pour la vérification visuelle. Les mutations sont testées uniquement sur les bases MySQL temporaires.

Pour utiliser : Personnel SaaS → Permissions → cocher/décocher → Enregistrer les permissions. Seul un acteur habilité voit les contrôles ; les lectures nécessaires à une navigation doivent maintenant être sélectionnées explicitement. Le catalogue global reste consultatif. Aucun gain de performance chiffré revendiqué.

### Retour arrière

La commande --rollback exige le chemin privé associé à l'activité et refuse tout état modifié après conversion ; elle n'est pas exécutée sur les données réelles. Le retour exact à l'empreinte initiale est testé sur base isolée. Remettre ensuite permission.central_profiles=false et reconstruire la configuration avant de rouvrir le site, selon §41. Si des utilisateurs ont depuis été personnalisés, comparer et faire approuver un nouveau plan ; aucune restauration automatique qui écraserait ces changements.

## 21 octobre 2026 — Accès visible aux permissions individuelles

Problème confirmé : la page de modification existait, mais les actions du Personnel SaaS la nommaient uniquement « Permissions effectives ». La liste et la fiche Utilisateurs ne proposaient aucun lien vers cette page.

Correction : bouton « Modifier les permissions » soumis à updateStaffPermissions dans les actions du personnel et dans la liste/fiche Utilisateurs. Si seule la consultation est autorisée, conserver « Permissions effectives ». Réutilisation de la page, de la route, du service Spatie et des protections existants ; aucune modification de rôle, de permission ou de données réelles.

Fichiers : resources/views/central/pages/admin/staff/actions.blade.php ; resources/views/central/pages/admin/users/index.blade.php ; resources/views/central/pages/admin/users/show.blade.php. Convention d'accès précisée au §41 de l'architecture.

Contrôles exécutés sans mutation métier : routes GET/PUT chargées ; rendu local du bouton pour un personnel existant et du formulaire complet (cases, bouton d'enregistrement, expected_state) ; refus d'édition pour root et pour un commerçant ; compilation Blade et analyse syntaxique PHP des deux vues Utilisateurs. Tous ces contrôles sont positifs. Aucun test MySQL avec écriture ni clic navigateur exécuté ; aucune attribution réelle modifiée.

## 21 octobre 2026 — Consolidation dans le pivot Spatie natif et retrait de la page Rôles de Utilisateurs

### Décisions et périmètre

Demande explicite : transférer la composition de role_permission_templates dans role_has_permissions et supprimer la table supplémentaire, sans perdre les permissions individuelles. Complément pendant la tâche : retirer le bouton Rôles de Utilisateurs et sa page « Rôles de… ». Les rôles restent gérés dans Personnel SaaS ; saas.users.roles.assign reste une permission nécessaire à cette gestion.

### Réalisation

- Central/Role et HasRoleMetadata : permissionComposition lit le pivot natif ; suppression de la relation templates et renommage du prédicat en usesIndividualPermissions. Aucun modèle de table supplémentaire.
- HasTimedPermissions : attribution initiale individuelle conservée ; les contrôles de recoupement et l'héritage restent propres aux rôles Tenant. Au central, hasPermissionTo et les points d'entrée natifs hasPermissionViaRole/getPermissionsViaRoles excluent les compositions du rôle des droits effectifs. getAllPermissions ne réintroduit pas un droit retiré.
- CentralPermissionResolver, CentralPermissionAssignmentService, RoleManager, CentralRoleAssignmentService et UserManagementService adaptés : affichage, édition individuelle, signatures, durée, renouvellement et suppression d'un profil inutilisé continuent avec le pivot natif.
- Migration centrale 2026_10_21_051638_consolidate_central_role_permissions : sauvegarde privée chiffrée des six tables avant copie, refus des durées contradictoires, copie des paires absentes, contrôle exact et suppression de la source. Relance sans effet si la source a disparu ; down refuse une destruction aveugle. Ancienne migration déployée conservée pour une installation reproductible. Aucun reset et aucune migration Tenant.
- Ancien service CentralPermissionTransition et commande ConvertPermissionProfiles retirés ; sauvegardes et audits historiques conservés. Leur ancien retour arrière ne s'applique plus à ce schéma.
- Retrait de UserRoleController, resources/views/central/pages/admin/users/roles.blade.php, des routes central.users.roles.edit/update et des boutons Rôles dans la liste/fiche Utilisateurs. Les routes staff.roles et le lien depuis Permissions restent présents. AssignRolesRequest reste le parent utilisé par AssignStaffRolesRequest.
- Schéma principal, diagramme central, instructionn.txt, architecture et AGENTS.md actualisés. Diagramme central : 27 tables, sans doublon ni table supplémentaire. Les anciennes notes et migrations restent explicitement historiques.

### Vérifications exécutées

| Contrôle | Résultat réel |
|---|---|
| DirectPermissionManagementTest, premier lot | 12/14 réussis ; deux défauts de fixture/assertion corrigés : ordre de colonnes/lignes dans la comparaison d'instantanés, et DDL MySQL sous transaction de test |
| Reprise des deux scénarios de consolidation | Refus des conflits réussi ; dernière comparaison de sauvegarde nécessitait une comparaison explicite des triplets rôle/permission/durée |
| Reprise finale de la consolidation avec sauvegarde et relance | 1/1 réussi, 12 assertions ; avec le lot précédent, les 14 scénarios individuels sont validés par lots |
| RoleManagementTest + StaffManagementTest + SpatieIntegrationTest | 62/62 réussis, 539 assertions ; gestion des profils/personnel, héritage local et isolation de deux boutiques temporaires |
| Retrait de la page Utilisateurs et sauvegarde du formulaire de rôles Staff | 2/2 réussis, 15 assertions : anciennes URL GET/PUT en 404, liens retirés, page Staff conservée et droits préservés |
| Syntaxe PHP des fichiers principaux, routes et tests modifiés | Aucune erreur |
| Rendu local réel de la page Permissions | Cases individuelles, bouton d'enregistrement et lien vers Rôles du personnel présents ; ancienne vue Utilisateurs absente |
| HTTP local /login avec Host aydra.localhost | 200 |

Les tests MySQL temporaires sont explicitement autorisés pour ce lot et restent isolés des bases réelles. Aucun Pint, PHPStan, compilation, suite complète ou e-mail réel lancé. Les scénarios d'ancienne conversion retirée sont remplacés par la vérification de la nouvelle consolidation et de sa sauvegarde ; aucune assertion de sécurité n'est retirée pour masquer un échec.

### Application et comparaison MySQL réelles

Brève maintenance, migration limitée au fichier de consolidation, signal queue:restart et remise en ligne. La sauvegarde privée est présente et déchiffrable ; son contenu n'est pas publié.

Résultat relu dans MySQL : source absente ; neuf associations copiées exactement dans role_has_permissions. Comparaison avec la sauvegarde : roles, permissions, model_has_roles et model_has_permissions sont identiques, dates comprises. Les 893 résultats can() relevés avant la migration ont exactement la même empreinte après. Les boutiques existantes ne sont pas modifiées.

Cache des routes régénéré après suppression : anciennes routes Utilisateurs absentes, routes Rôles du personnel conservées ; application hors maintenance. Les mutations réelles de droits ne sont pas utilisées comme tests. Pas de parcours complet au navigateur revendiqué.

Contrôle final après suppression des anciennes routes : 8/8 scénarios concernés réussis, 58 assertions (attribution après expiration de la lecture, délégation limitée, dates conservées, Super Admin protégé, commerçants exclus et impossibilité de contourner l'approbation). Les références des anciens tests sont dirigées vers les routes Staff conservées ; aucun appel aux routes supprimées ne subsiste dans le code actif.

## 21 octobre 2026 — Transactions et autorisations centrales simplifiées (§42)

Décision : utiliser DB::transaction directement. La configuration effective était déjà mysql hors tenancy, avec la base centrale aydra ; aucune correction du .env réel ni des configurations database/tenancy n’était nécessaire. Seul .env.example abandonnait encore SQLite et est aligné sur MySQL. Stancl reste responsable du basculement tenant et du retour central ; les traits de connexion des modèles restent inchangés.

AuthorizationMutation.run reçoit Actor, action, chargeur de cible et traitement ; Gate applique la Policy dans la transaction avant écriture. Les services conservent leurs règles métier, cibles verrouillées, versions et audits. Les enveloppes Staff/Users imbriquées sont retirées. RolePolicy.assign et PermissionPolicy.delegate contrôlent les droits encore possédés ; aucun nouveau code de permission n’est ajouté en base. Le mutex sur la ligne root reste nécessaire pour sérialiser une révocation et une mutation concurrentes ; ce verrou n’est pas une autorisation. Les opérations groupées et les jobs gardent leurs contrôles adaptés.

Correction explicite demandée : suppression de la méthode publique assertCentralContext, de tous ses appels et des dépendances ajoutées uniquement pour cette méthode. Aucune comparaison répétée du nom de connexion dans les services. La garde tenant déjà présente dans les opérations centrales reste conservée ; les éditions personnelles et photos restent contrôlées par leurs Policies. Les catalogues, abonnements et transactions tenant utilisent DB::transaction dans leur contexte existant.

Cache : invalidation globale supplémentaire après commit uniquement pour les définitions de rôles ; nettoyage du registrar après rollback conservé. Les méthodes natives Spatie restent utilisées. Aucun cache de décision, aucune migration ou écriture sur les bases réelles pendant cette intervention.

Contrôles exécutés sur bases MySQL temporaires explicitement autorisées :

- Rôles, personnel et droits individuels : 74 scénarios, 568 assertions réussies avant le dernier retrait de la méthode de garde.
- Pays, boutiques, approbation, profil et photos : 62 scénarios, 530 assertions réussies avant ce dernier retrait.
- Concurrence réelle MySQL et séparation Spatie entre deux bases tenant : 4 scénarios, 64 assertions réussies ; révocation concurrente bloquante, refus après commit malgré un Actor déjà chargé, transactions centrale/tenant et rollback vérifiés.
- Après retrait final de la garde publique : 21 scénarios ciblés réussis, 182 assertions, couvrant notamment les mutations, l’autorisation avant écriture et les photos.
- Syntaxe PHP des fichiers modifiés vérifiée, diff sans erreur de whitespace. Le chemin historique du composant profil contenant un caractère technique a nécessité une sélection PowerShell par fichier plutôt que le chemin échappé par Git ; syntaxe vérifiée ensuite.

Anomalies corrigées pendant la vérification : SearchTenantsRequest imposait le droit de consultation à l’action de création/choix du propriétaire ; il utilise désormais la Policy create pour cette action et viewAny pour la liste. Trois anciennes attentes de tests supposaient une lecture dérivée des permissions de modification ; elles vérifient désormais le refus sans lecture explicite, puis l’accès après attribution. La fixture d’une permission au mauvais guard utilise un modèle non enregistré pour respecter la contrainte SQL réelle.

Limites : pas de suite complète, Pint, PHPStan, compilation, envoi réel d’e-mail ni mutation des comptes existants. Les grandes séries précèdent le dernier retrait de la garde publique ; seuls les 21 scénarios pertinents ont été relancés après cette correction. Aucun gain de performance chiffré revendiqué.
## 21 octobre 2026 — Retour à Utilisateurs après approbation/refus

Cause confirmée : UserApprovalController.decide redirigeait systématiquement vers central.users.pending, quel que soit le formulaire utilisé. Correction : retour natif Laravel à la page d'origine, avec repli sur central.users.index. Les filtres et la pagination de la liste restent dans l'URL de retour. Aucun statut ni contrôle métier modifié.

Fichiers : UserApprovalController.php et MerchantApprovalWorkflowTest.php ; architecture complétée. Six scénarios de régression ajoutés pour les deux décisions depuis la liste, les inscriptions en attente et la fiche ; l'attente sans origine utilise désormais la liste en repli. Syntaxe PHP vérifiée ; mécanisme Redirector exécuté sans accès BDD pour les retours à la liste filtrée et aux inscriptions en attente, résultats conformes. Tests MySQL ajoutés mais non exécutés pour cette demande, sans nouvel accord d'écriture de test ; aucune base réelle modifiée.
## 21 octobre 2026 — Retrait explicite du verrou global et de la garde tenancy

Demande validée : supprimer acquireAuthorizationLock, son appel et le contrôle tenancy()->initialized dans AuthorizationMutation.transaction. Imports Role et LogicException retirés. DB::transaction(..., 3), Gate/Policies dans run, chargement des cibles, verrous métier des Services, cache après commit et nettoyage après rollback conservés. Aucun contrôle de connexion déplacé dans un nouveau fichier ; vérification ponctuelle effectuée uniquement par le processus CLI.

Configuration relue : .env et .env.example DB_CONNECTION=mysql ; database.default et tenancy.database.central_connection utilisent cette valeur. Code Stancl installé : connexion tenant à initialisation, retour central à la fin. Contrôle effectif CLI : tenancy non initialisée, connexion par défaut mysql, connexion centrale mysql. Aucun changement de configuration nécessaire.

Limite fonctionnelle explicitée : sans mutex global, une mutation déjà engagée peut encore réussir pendant un retrait de droit non validé. Ne pas revendiquer la sérialisation précédente. Le scénario de concurrence est adapté pour vérifier l'absence de blocage sur root puis le refus d'une nouvelle action après commit du retrait. Le test Tenant vérifie que la transaction suit sa connexion courante sans garde ajoutée. Ces adaptations correspondent à la demande, pas à une sécurité équivalente au mutex retiré.

Contrôles : syntaxe PHP des trois fichiers concernés valide ; références et diff relus. Tests MySQL adaptés, non exécutés faute de nouvel accord pour les écritures de test de cette demande. Aucune base réelle, donnée, permission ou migration modifiée. AGENTS, architecture §42, schéma et diagramme central synchronisés avec la nouvelle décision ; les comptes rendus antérieurs restent historiques.
## 21 octobre 2026 — Cache et doublons à leur emplacement métier

Demande : retirer refreshPermissionCache du gestionnaire générique et déplacer son erreur de doublon vers les fonctions concernées. Le paramètre était utilisé uniquement par RoleManager.save/delete. Il est supprimé des signatures et appels ; RoleManager enregistre lui-même DB::afterCommit(fn () => role->forgetCachedPermissions()), méthode native Spatie. Invalidation après fin de composition préservée ; callbacks annulés si rollback. Aucun service ni abstraction supplémentaire créé.

AuthorizationMutation.transaction appelle désormais directement DB::transaction(callback, 3) ; son catch conserve uniquement le nettoyage du cache et relance l'exception d'origine. La traduction SQL générique « nom, e-mail ou composition » est retirée. RoleManager traduit les doublons près de role.save vers le champ name et son message de rôle existant ; CountryService traduit près de country.save vers code. User.save avait déjà ses messages e-mail/téléphone et TenantCreator ceux de slug/domaine : ils sont conservés sans duplication.

Vérifications exécutées : syntaxe des trois fichiers PHP valide ; scénario CLI isolé avec doubles Mockery, sans accès BDD : Policy appelée avec Actor/action/cible, callback et résultat conservés, transaction à trois tentatives demandée, même exception SQL relancée et nettoyage du registrar à l'échec. Méthode native forgetCachedPermissions confirmée dans le package installé. Références et diff relus ; aucun ancien argument dans le code. Ces doubles ne vérifient pas les commits/rollbacks MySQL réels : tests MySQL non exécutés pour cette demande, aucune base réelle modifiée. Architecture §42, AGENTS, schéma et diagramme central synchronisés.
## 22 octobre 2026 — Groupe administratif de middlewares explicite

Demande : déclarer les protections communes avant les préfixes sans répétitions. routes/central/admin.php porte maintenant middleware(auth:central, verified, EnsureActiveCentralAccount) avant prefix(admin)/name(central.). Son chargement sort du groupe dashboard/propriétaire de central.php pour éviter de dupliquer la protection. users.php ne répète plus auth/verified. Les six modules administratifs héritent du même groupe ; noms, URL, verbes, contrôleurs, Policies, throttles et withTrashed sont préservés. Routes publiques, statut limité, profil et contexte tenant inchangés. AGENTS et architecture complétés.

Contrôles exécutés : syntaxe des trois fichiers valide ; comparaison de 274 routes chargées depuis les sources avant/après, empreinte identique après normalisation du seul doublon auth vers le guard central et déduplication. Les 168 routes administratives, domaines centraux compris, ont auth:central, verified et EnsureActiveCentralAccount. Cache de routes existant régénéré avec succès et routes chargées depuis ce cache relues ; connexion, inscription et storefront gardent leur accès public prévu, profil et statut leurs exceptions. Aucun test MySQL, migration, seeder ou contrôle HTTP nécessitant une session réelle exécuté ; aucune donnée métier modifiée.
## 22 octobre 2026 — Nom explicite de la liste des routes limitées

Dans EnsureActiveCentralAccount, constante renommée en LIMITED_ACCESS_ROUTES et référence interne adaptée. Recherche dans app/tests/config/routes et documentation : aucune autre utilisation du nom précédent. Aucun changement de liste, autorisation, statut ou redirection. Syntaxe PHP vérifiée ; aucun test MySQL ni modification de données. Refactorisation déjà présente du middleware conservée.
## 22 octobre 2026 — Retrait des dossiers Enums imbriqués vides

Arborescence app/Enums/Enums/Central et ses sous-dossiers Deployments/Features/Subscriptions vérifiée, y compris éléments cachés : aucun fichier. Suppression limitée aux cinq dossiers vides, sans récursion destructive ni déplacement PHP. Les quinze fichiers enum existants restent aux mêmes chemins et namespaces. Aucune référence applicative ou documentaire à corriger ; rappel de classement ajouté à l'architecture. Disparition du doublon et diff vérifiés ; aucun test BDD nécessaire ni exécuté. Modification préexistante de EnsureApplicationDomain préservée.
## 22 octobre 2026 — Parcours central allégé et états typés

Implémentation autorisée : JourneyState ajouté à app/Enums/Central/Users, cases en majuscules et routes exhaustives. RegistrationJourney.state/destination sépare le choix du parcours du détail de page. Priorités communes dans accountState ; lecture des boutiques via User.tenants ; conditions de préparation et de disponibilité partagées. Aucun couple de résultats positionnels, DTO, cache de décision ou mémoïsation utilisateur ajouté. progress conserve son tableau, ses chaînes, le motif, les liens autorisés et le même calcul de version SHA-256 ; contrôleur et vues restent compatibles sans modification.

Bénéfice vérifié : une destination refusée ne lit plus activity_log ; une destination avec boutique en échec ne vérifie pas les droits de reprise et ne charge les boutiques qu'une fois. progress continue de produire le détail et le lien de reprise autorisé. La version précédente chargeait déjà les boutiques une seule fois par progress : aucun gain supplémentaire de requêtes sur ce point revendiqué. Aucune mesure de latence globale produite.

Tests MySQL temporaires explicitement autorisés pour cette tâche. MerchantJourneyTest : 25 scénarios réussis, 276 assertions. Ils couvrent les états/redirections, sessions limitées, décisions successives, première boutique explicite/idempotente, isolation entre propriétaires, indisponibilité avant initialisation, contrat JSON/hash et absence de travail de présentation dans destination. Syntaxe des deux fichiers applicatifs et du test valide ; diff relu sans erreur. Aucun changement aux bases existantes, permissions, migrations, configuration ou frontend. La modification préexistante de EnsureApplicationDomain est préservée. AGENTS et architecture mis à jour.
Contrôle complémentaire ciblé : connexion du Super Admin et accès au dashboard, 1 scénario réussi et 8 assertions. Total exécuté pour ce lot : 26 scénarios, 284 assertions. Les tests ont utilisé les chemins temporaires de configuration/routes, sans vider le cache du site. Aucune suite complète, Pint, PHPStan ou compilation lancée.

## 22 octobre 2026 — Audit des trois services Auth et restauration de l'envoi

Anomalie bloquante confirmée : AccountMailDelivery.php avait été remplacé par une classe LoginResponse dans le namespace Http/Responses. La classe attendue AccountMailDelivery n'était plus déclarée et une seconde déclaration LoginResponse pouvait provoquer une erreur. Le fragment comportait aussi JourneyState::Ready, alors que la case réelle est READY. Service restauré avec invitation(), verification() et gestion des exceptions de transport ; aucune copie de cette Response proposée dans le véritable LoginResponse. Les suggestions de amelioration.txt restent des notes, sans refonte hors du périmètre demandé.

RegistrationVerificationNotification : changements utilisateur conservés ; variables locales et retour anticipé équivalents, comportement natif hors central et flash de session préservés. RegistrationJourney : aucun écart par rapport à HEAD, aucune modification nécessaire ; refactoring des états et contrat de polling déjà présents.

Tests MySQL temporaires explicitement autorisés, e-mails simulés : 17 scénarios réussis, 96 assertions. Sélection ciblée dans RegistrationTest, EmailVerificationTest et UserAccountManagementTest : inscription normale, protection des champs privilégiés, SMTP indisponible en web/JSON, absence de faux indicateur d'envoi, compte conservé et non vérifié, absence d'envoi pour compte déjà vérifié, renvoi en échec, liens de vérification, invitation administrative immédiate et échec d'invitation sans exposition du message privé. Le transport était en mémoire ou remplacé par un double ; aucun SMTP réel utilisé.

Syntaxe des trois services et des deux fichiers de tests valide ; références de classes et diff vérifiés. Aucun changement de base existante, migration, configuration ou autre middleware. Les modifications utilisateur hors périmètre sont conservées. Pas de suite complète, Pint, PHPStan, compilation ni répétition des 26 tests de parcours inchangés déjà valides. Architecture complétée ; compte rendu fondé sur le code et les tests exécutés, sans garantie de réception réelle des e-mails.
## 22 octobre 2026 — Correction et validation des réponses Fortify

Audit demandé sur VerifiedEmailResponse, LoginResponse et RegistrationResponse. Deux erreurs confirmées dans les changements utilisateur : le fichier VerifiedEmailResponse contenait une copie de RegistrationVerificationNotification avec son namespace de service ; LoginResponse utilisait JourneyState::Ready au lieu de READY. Réponse de vérification rétablie à son emplacement/contrat natif, avec session commerçant terminée et comportement parent préservé pour le personnel. Vérification du type de compte central avant traitement dans les deux réponses concernées (401 si absent).

La nouvelle structure shouldFollowIntended de LoginResponse est conservée, mais reçoit JourneyState : le parcours est calculé une seule fois et partagé avec la destination. Cela retire le second calcul présent dans la proposition, sans changer l'ordre des priorités ni les règles des liens internes/vérification. RegistrationResponse était correcte et reste inchangée. Bindings de LoginResponse, TwoFactorLoginResponse, RegisterResponse et VerifyEmailResponse contrôlés dans FortifyServiceProvider ; aucune modification de vendor.

Tests MySQL temporaires explicitement autorisés et e-mails simulés : 35 scénarios réussis, 206 assertions, dans les tests ciblés AuthenticationTest, EmailVerificationTest et RegistrationTest. Ajouts : reprise d'URL interne pour compte prêt ; absence de contournement pour première boutique/attente/refus ; rejet des URL externes, hôtes ressemblants et domaines tenant ; reprise du lien de vérification ; un seul chargement des boutiques dans une réponse de connexion concernée ; vérification web/JSON du personnel sans déconnexion et vérification JSON du commerçant avec déconnexion. Les tests existants couvrent aussi inscription, vérification et échec SMTP. Le parcours complet 2FA n'a pas été rejoué ; son binding partagé est préservé.

Syntaxe des trois réponses et deux fichiers de tests valide ; déclarations uniques et références de cases enum contrôlées ; diff sans erreur. Documentation d'architecture complétée. Aucune migration, donnée existante, configuration, route ou vrai e-mail modifié/envoyé. Modifications utilisateur des middlewares conservées. Pas de suite complète, Pint, PHPStan ou compilation.

## 22 octobre 2026 — Audit individuel des 25 Form Requests centrales

Demande : appliquer la même revue et les corrections utiles à tous les fichiers de `app/Http/Requests/Central`, un par un. État Git initial propre. Lecture des 25 classes, de leurs héritages et des appelants concernés : contrôleurs, DTO, Policies, services et routes. Les contrôles métier restent dans les services existants ; aucune nouvelle couche, règle de permission ou migration.

Deux cas limites reproduits avant correction : `StoreUserRequest` convertissait le prénom non vide `"0"` en `null` avec `?: null` ; `SaveRoleRequest` refusait un UUID de permission valide en majuscules lors de la recherche SQL. La normalisation des UUID et des clés de durées est désormais cohérente avant validation. Deux écritures différentes du même UUID sont reconnues comme un doublon. Les durées omises, versions, contrôles de délégation et protections système sont conservés. Les autres changements améliorent uniquement la présentation des règles et la lecture des arguments de DTO.

Chemins ci-dessous relatifs à `app/Http/Requests/Central/` :

| Fichier examiné | Résultat de la revue |
|---|---|
| `Countries/ChangeCountryStatusRequest.php` | Autorisation d'activation et booléen conservés ; présentation nettoyée. |
| `Countries/StoreCountryRequest.php` | Code ISO normalisé, pays supportés, unicité et noms vérifiés ; règle du code présentée sur plusieurs lignes et import explicite. |
| `Permissions/SearchPermissionsRequest.php` | Consultation seule, recherche et pagination conservées ; un champ par ligne. |
| `Roles/DeleteRoleRequest.php` | Conservé : Policy sur la cible réelle ; contrôles de suppression dans RoleManager. |
| `Roles/SaveRoleRequest.php` | UUID et clés de durées normalisés ; doublons reconnus avant le DTO ; règles et arguments nommés plus lisibles. |
| `Tenants/ChangeTenantStatusRequest.php` | Actions autorisées, état attendu et motif conservés ; règles plus lisibles. |
| `Tenants/SearchTenantsRequest.php` | Distinction choix du propriétaire/création et consultation conservée ; règles plus lisibles. |
| `Tenants/StoreFirstTenantRequest.php` | Conservé : héritage de validation et Policy createFirst du propriétaire. |
| `Tenants/StoreOwnedTenantRequest.php` | Conservé : mêmes règles de données avec Policy createOwned distincte. |
| `Tenants/StoreTenantRequest.php` | Conservé : nom, slug technique, clé d'idempotence, clés autorisées des réglages et DTO ; quota/unicité vérifiés dans TenantCreator. |
| `Tenants/UpdateTenantPrioritiesRequest.php` | Entiers distincts et motif conservés ; contrôle de l'ensemble exact des boutiques sous verrou dans le service. Présentation clarifiée. |
| `Tenants/UpdateTenantRequest.php` | Identité, statut, propriétaire et champs techniques restent interdits ; version attendue conservée. Présentation clarifiée. |
| `Users/AssignRolesRequest.php` | Base de validation réutilisée par Staff ; guard, doublons et exclusion root conservés. Présentation clarifiée. |
| `Users/AssignStaffRolesRequest.php` | Conservé : autorisation spécifique assignStaffRoles, sans conversion d'un commerçant. |
| `Users/ChangeStaffStatusRequest.php` | Conservé : autorisations activateStaff/suspendStaff et règles de statuts héritées. |
| `Users/ChangeUserStatusRequest.php` | Conservé : seules activation et suspension sont saisissables ; transitions revérifiées dans le service. |
| `Users/LegalVerificationRequest.php` | Conservé : adaptateur des anciennes routes vers les mêmes règles d'approbation/refus. |
| `Users/RenewStaffAssignmentRequest.php` | Motif obligatoire et date attendue conservés ; comparaison effective sous verrou dans le service. Présentation clarifiée. |
| `Users/StoreStaffRequest.php` | Conservé : spécialisation de StoreUserRequest, création explicitement personnel et mêmes normalisations. |
| `Users/StoreUserRequest.php` | Prénom `"0"` conservé ; distinction commerçant/personnel et champs administratifs interdits maintenus ; DTO à arguments nommés. |
| `Users/UpdateProfilePhotoRequest.php` | Conservé : Policy personnelle, image, MIME, taille et dimensions conformes au service photo. |
| `Users/UpdateStaffPermissionsRequest.php` | Conservé : état attendu, clés autorisées, permissions centrales, booléens et durées ; délégation/renouvellement vérifiés dans le service. |
| `Users/UpdateStaffRequest.php` | Conservé : Policy spécifique et interdiction des champs professionnels du personnel. |
| `Users/UpdateUserRequest.php` | Unicité excluant le compte modifié, pays historiques, champs professionnels facultatifs et attributs sensibles vérifiés ; présentation et DTO clarifiés. Aucune décision administrative changée. |
| `Users/UserApprovalRequest.php` | Conservé : Policy approve/reject, état/version attendus, motif obligatoire au refus et interdit à l'approbation. |

Bilan des fichiers applicatifs : 25 examinés, 12 ajustés dont 2 avec correction de normalisation, 13 conservés sans modification. Conventions réutilisables consignées au §9 de l'architecture. Contrôles PHP 8.5 : syntaxe et chargement PSR-4/héritage valides pour les 25 classes ; syntaxe valide pour les deux fichiers de tests modifiés ; diff sans erreur.

Tests MySQL temporaires explicitement autorisés pour cette tâche : **168 scénarios réussis, 1 390 assertions**, en 323 secondes. Exécution des neuf fichiers CountryManagementTest, PermissionCatalogTest, RoleManagementTest, StaffManagementTest, DirectPermissionManagementTest, TenantManagementTest, UserAccountManagementTest, MerchantApprovalWorkflowTest et ProfilePhotoTest. Les trois nouveaux scénarios HTTP ont d'abord échoué avant correction, puis réussi dans cette sélection : conservation du prénom `"0"`, UUID de permissions en majuscules avec durées conservées à la création et à la modification, refus du même UUID répété avec une casse différente. Les scénarios existants vérifient notamment les autorisations, les comptes protégés, les champs interdits, les versions obsolètes, les décisions d'approbation/refus, l'absence de nouvelle approbation après édition, les attributions et leurs dates, les quotas et les photos privées.

Configuration et routes de test isolées par APP_CONFIG_CACHE/APP_ROUTES_CACHE temporaires ; cache du site préservé. Aucune écriture dans les bases existantes, aucun véritable e-mail envoyé, aucune modification de migration, de route, de service ou de Policy. Les Requests propriétaires de création de boutiques ont été relues avec leurs contrôleurs et TenantCreator ; aucun nouveau provisionnement réel ni test de concurrence parallèle n'a été exécuté pour cet audit. Pas de suite complète, Pint, PHPStan ou compilation.

## 22 octobre 2026 — Méthode de revue conservée pour les prochains développements

Demande explicite de conserver la méthode de cet audit pour tout futur ajout ou changement de code. Rappel ajouté dans AGENTS.md et procédure détaillée au §38 de l'architecture : revue des fichiers concernés un par un avec leurs dépendances, corrections confirmées, code simple, mécanismes existants, protections et métier préservés, vérification de chaque étape et du diff final. Conserver les fichiers déjà corrects. Les tests sont ciblés selon le risque et leurs autorisations ; le lot de 168 tests précédent n'est pas une obligation générale. Cette intervention modifie uniquement les instructions et la documentation ; les changements applicatifs du lot précédent sont conservés.

## 22 octobre 2026 — Contraintes UUID des routes publiques de modèles

Demande appliquée uniquement à `whereUuid()` : contrainte commune sur user/role/country/tenant dans `routes/central/admin.php`, contrainte tenant sur la reprise dans `routes/central.php` et contrainte user sur la lecture des photos dans `routes/central/settings.php`. Les modules administratifs héritent du groupe existant, sans répétition par route. Les paramètres natifs des packages, identifiants Tenancy, URL, noms, verbes, middlewares, signatures, throttles et accès aux archives sont préservés. Convention consignée au §35 de l'architecture. Les problèmes de redirection précédemment signalés restent hors de ce changement demandé.

Contrôles sans écriture en BDD, exécutés sur les routes sources puis après régénération du cache actif avec PHP 8.5 : 126 routes applicatives à identifiants contrôlées, en incluant les trois domaines centraux ; 252 correspondances avec UUID valides en minuscules/majuscules ; 387 recherches avec identifiant numérique, texte invalide ou UUID sans tirets retournent une 404 ; huit chemins statiques restent accessibles au routeur. Ces contrôles portent sur le matching Laravel, sans exécuter les actions ni les middlewares des requêtes. La première sélection de contrôle incluait par erreur une route Flux native : le contrôle a été limité aux actions Aydra, sans modifier les routes de Flux.

Syntaxe des trois fichiers de routes valide ; diff sans erreur. Les 274 routes et la signature comparant domaines, méthodes, URI, noms publics, actions, middlewares, archives et bindings sont identiques avant/après ; seules les contraintes de paramètres changent. `artisan route:cache --no-interaction` réussi ; mêmes contrôles de matching réussis avec le cache chargé. Aucune migration, base réelle ou base temporaire de test modifiée ; aucun test MySQL, Pint, PHPStan ou compilation exécuté.

## 22 octobre 2026 — Correction des redirections d'accueil et des réglages

Deux corrections demandées, réalisées en deux lignes applicatives : `routes/central.php` utilise APP_URL via `config('app.url')` pour la destination absolue de l'accueil vers `/login` ; `routes/central/settings.php` utilise le chemin `/settings/profile` depuis la racine. Auparavant, l'accueil de localhost/127.0.0.1 renvoyait vers leur propre `/login`, absent puisque Fortify est limité au domaine principal ; la destination relative `settings/profile` pouvait doubler le préfixe lorsque l'URL source se terminait par `/`. Les alias et routes existants sont conservés, sans duplication de l'authentification, nouvelle classe ou modification des autorisations. Les contraintes UUID du lot précédent sont préservées. Convention enregistrée au §35 de l'architecture.

Vérifications PHP 8.5 sans écriture en BDD : neuf cas de matching et d'exécution directe du RedirectController natif, sur les trois domaines centraux, pour `/`, `/settings` et `/settings/`. Les réponses sont des 302 avec la destination attendue ; chaque destination correspond à la route login ou profile.edit du domaine concerné, sans boucle ni double préfixe. Les neuf cas réussissent sur les sources puis avec le cache de routes régénéré. Les 274 routes restent présentes ; la racine d'un domaine boutique correspond toujours au storefront. Ces contrôles n'exécutent pas les middlewares d'authentification ni les pages finales. Syntaxe des deux fichiers et diff valides ; `artisan route:cache --no-interaction` réussi. Aucune base existante ou temporaire modifiée, aucun test MySQL, Pint, PHPStan ou compilation lancé.

## 22 octobre 2026 — Revue des sept DTO existants

Demande de vérification de `app/DTOs`. État Git initial propre. Sept classes lues une par une avec les Requests, services, modèles et contrôleurs qui les utilisent. Toutes sont final readonly et classées par contexte/module. Aucun déplacement, package DTO supplémentaire ou abstraction nécessaire.

| DTO, chemin relatif à app/DTOs | Résultat |
|---|---|
| `Central/Users/UserData.php` | Types et champs facultatifs cohérents avec StoreUserRequest et UserManagementService ; le DTO ne remplace pas leurs validations et autorisations. |
| `Central/Users/UserProfileData.php` | Identité, pays du téléphone et attributs professionnels cohérents ; distinction conservée entre champ professionnel omis et valeur null destinée à le vider. |
| `Central/Users/UserSaveResult.php` | User central et résultat d'envoi tri-état cohérents : true envoyé, false échec traité, null aucun résultat d'envoi disponible. readonly ne rend pas le modèle Eloquent contenu immuable. |
| `Central/Roles/RoleData.php` | Nullabilité, version attendue et durées cohérentes ; permissionDurations=null conserve la composition, une durée null conserve la durée existante selon RoleManager. |
| `Central/Tenants/TenantData.php` | Champs et référence ShopSettings cohérents ; cas limite confirmé dans fingerprint, détaillé ci-dessous. |
| `Tenant/Shop/ShopSettings.php` | Construction privée, valeurs par défaut et validations cohérentes avec les points d'entrée HTTP et internes du provisionnement. Validation locale sans SQL conservée. |
| `Tenant/Dashboard/DashboardData.php` | Deux libellés de présentation cohérents avec le service et le contrôleur Tenant préparés ; aucune fonctionnalité Tenant activée par cette revue. |

Contrôles exécutés sans tests/TestCase et sans base de test : syntaxe PHP 8.5 valide pour les sept fichiers ; 32 vérifications de valeurs par défaut, types/nullabilité, conservation des champs omis, états du résultat d'e-mail, immutabilité, données du tableau de bord, empreinte et réglages invalides. Compteur de requêtes SQL pendant ces vérifications : zéro. Aucune migration, donnée, invitation ou permission modifiée ; pas de Pint, PHPStan ou suite complète.

Anomalie observée, correctif non appliqué : TenantData.fingerprint encode directement l'objet colors avec json_encode. Deux réglages contenant les mêmes noms et valeurs de couleurs dans un ordre différent produisent deux hashes distincts. TenantCreator compare ce hash à tenants.creation_hash : une nouvelle soumission de la même clé peut donc être refusée uniquement pour cet ordre. Une correction devra rendre l'empreinte indépendante de l'ordre des clés tout en traitant explicitement la compatibilité des hashes historiques ; ne pas modifier aveuglément l'algorithme ou les valeurs stockées. Aucun DTO, service ou test permanent modifié pendant cette revue.

## 25 octobre 2026 — Corrections après revue des contrôleurs

Demande : corriger les défauts confirmés de l'audit des contrôleurs, sans modifier les règles métier ni les données existantes. Les décisions compte/dossier utilisent les capacités approve/reject existantes ; la consultation des droits du personnel utilise viewStaffPermissions, et le renouvellement utilise renewStaffAssignments. Le motif facultatif des permissions n'est plus lu comme une clé obligatoire. Les contrôles des services, transactions, verrous et versions sont conservés.

Les contrôleurs concernés choisissent après écriture une page autorisée ou le dashboard. L'approbation conserve le contexte Utilisateurs/inscriptions en attente/fiche et les paramètres de recherche, après comparaison avec une liste fermée d'URL internes ; une origine externe n'est pas reprise. Les éditions de comptes affichent un seul succès ou avertissement SMTP. Le parcours compare JourneyState ; les formulaires de boutique refusent proprement un acteur supprimé. Le contrôle local/debug/REMOTE_ADDR est partagé entre le middleware de diagnostic et le storefront.

RoleController réutilise centralActor(), son paginateur est typé, son argument inutilisé est retiré et le sélecteur de permissions utilise CentralUserAccess.ownedPermissions. En mode central_profiles, cette lecture groupe les requêtes sans mémoïsation ni cache des droits ; le test vérifie au maximum cinq requêtes, les révocations et l'expiration. Les mutations conservent la vérification actuelle des droits. CountryController réutilise également centralActor() ; phoneCountriesA, inutilisé par la vue d'édition, est retiré.

Vérifications PHP 8.5 : deux séries Pest ciblées via artisan test, 49 tests/388 assertions puis 10 tests/39 assertions, toutes réussies, soit 59 tests et 427 assertions. Couverture : décisions publiques et anciennes routes legal, refus sans motif, retour à la page d'origine, redirection extérieure refusée, acteur sans droit de consultation, lecture seule des permissions, modification sans motif facultatif, renouvellement sous son droit propre, message SMTP unique, statut conservé après édition, protection root, délégation et expiration, formulaire obsolète, acteur disparu, parcours commerçant et diagnostic/proxy. Tests MySQL temporaires explicitement autorisés, envois simulés et aucune modification des bases existantes ; aucun worker réel du site exécuté. Syntaxe des 24 fichiers PHP modifiés valide, Pint réussi et diff vérifié. Aucun schéma, migration, nom de route ou fonctionnalité Tenant activée ; la suite complète et le navigateur n'ont pas été exécutés.

## 25 octobre 2026 — Audit des services et de leurs dépendances

Demande : vérifier les services après des remplacements de code externes, avec leurs appelants et les règles métier actuelles. Autorisation explicite pour les tests MySQL temporaires, les vrais jobs isolés et les e-mails simulés. Les modifications des contrôleurs déjà présentes sont conservées.

Les 35 fichiers PHP de app/Services ont été examinés :

| Groupe | Fichiers examinés |
|---|---|
| Central/Auth | AccountMailDelivery, RegistrationJourney, RegistrationVerificationNotification |
| Central/Authorization | AuthorizationMutation, CentralPermissionAssignmentService, CentralPermissionResolver, CentralRoleAssignmentService, CentralUserAccess, PermissionCatalogService |
| Central/Countries | CountryCatalog, CountryService |
| Central/Dashboard et Diagnostics | Dashboard, DatabaseDiagnostics |
| Central/Plans, Roles et Subscriptions | FreePlanCatalog, CentralRoleCatalog, RoleManager, DefaultSubscription |
| Central/Tenants | TenantCreator, TenantDatabaseName, TenantManagementService, TenantProfileProjection, TenantProvisioner, TenantProvisioningRetry, TenantStatusService |
| Central/Users | ContactMigrationAudit, LegalVerificationService, PhoneNumberNormalizer, ProfilePhotoService, StaffInvitationService, StaffManagementService, UserApprovalService, UserManagementService |
| Tenant/Dashboard et Diagnostics | Dashboard, DatabaseDiagnostics |
| Infrastructure commune | SchemaBlueprint |

Corrections appliquées :

- AuthorizationMutation revient à DB::transaction(..., 3), conformément à la décision existante. Le nettoyage du cache Spatie couvre aussi un refus d'autorisation qui annule la transaction. Pas de connexion centrale imposée, de contrôle tenancy, de verrou global root ou de nouvel argument de cache ; RoleManager conserve son invalidation native après commit.
- DefaultSubscription utilise le dernier abonnement historique comme prédécesseur lorsqu'aucun actif n'est sélectionné. Une souscription gratuite déjà marquée EXPIRED ne provoque plus la réutilisation de la clé initiale. Le verrou propriétaire, les abonnements payants valables et l'idempotence sont conservés.
- TenantProfileProjection et TenantStatusService exigent une version de schéma non vide et une date de provisionnement pour les opérations qui nécessitent une boutique préparée. TenantCreator signale explicitement une absence de migrations avant de chercher le dernier fichier.
- ProfilePhotoService transforme une image JPEG tronquée en erreur de validation avant stockage, au lieu de laisser l'avertissement de getimagesize produire une erreur HTTP 500.
- Les rôles passés à HasTimedPermissions doivent être des instances du modèle configuré pour leur contexte ; un modèle brut du package sans les méthodes de métadonnées est refusé proprement. Les profils centraux et les rôles Tenant gardent leurs règles distinctes.
- CentralPermissionResolver utilise pluck('id') sur la collection retournée par Spatie. ContactMigrationAudit conserve les pays archivés avec le scope natif explicite. Les contrats de collections, relations, pivots, casts, tableaux et factories sont précisés dans les services, modèles et traits utilisés. Les types des données validées transmises par les contrôleurs concernés sont documentés ; aucune nouvelle couche n'est créée.

Contrôles déjà terminés : syntaxe des 35 services valide ; analyse PHPStan/Larastan niveau 7 des services, modèles Central/Tenant et traits utilisés réussie, zéro erreur. L'analyse élargie aux chemins configurés du projet conserve 11 avertissements hors services : conditions redondantes dans deux actions Fortify, trait ProfileValidationRules inutilisé, garde de plateforme du worker, types de la commande SyncTenantProfiles, relation héritée Domain.tenant et annotations de quatre Form Requests. Aucun contrôle ni assertion n'est désactivé pour masquer ces résultats.

Relances ciblées terminées : 5 tests et 21 assertions réussis pour le cache après refus d'autorisation, le rôle incompatible, l'abonnement déjà expiré et les deux préparations incomplètes ; 6 tests et 19 assertions réussis pour le catalogue des pays, son cache et l'audit téléphonique. Bases MySQL temporaires distinctes ; ces sélections ne manipulent pas le stockage partagé des photos.

Bilan de la suite complète initiale : **592 scénarios, 11 507 assertions ; 539 réussis, 46 échecs, 4 erreurs et 3 ignorés**. Les trois scénarios ignorés correspondent aux modes optionnels de comparaison complète des schémas MySQL, d'audit des bases existantes et de seeding complet avec diagnostic. Ils n'ont pas été activés. Cette exécution a subi une longue interruption d'horloge ; ses durées ne constituent pas un benchmark des services.

Les 50 échecs/erreurs ont été examinés. Plusieurs tests conservaient des attentes remplacées par les décisions actuelles : consultation dérivée des droits d'édition, nouvelle validation après édition du profil, activation initiale manuelle d'une boutique, page d'accueil en HTTP 200 et vérification de domaine assignable en masse. Les fixtures ont été corrigées sans modifier ces règles : permission de consultation explicite quand nécessaire, dates cohérentes dans les pivots directs, auteur de validation présent, numéros valides et forceFill pour les preuves de domaine simulées. Les assertions de protection restent présentes. Les notifications administratives sont vérifiées sur le modèle central réellement hydraté par les services ; NotificationFake classe les destinataires par classe PHP, contrairement à l'identité métier commune de l'alias. Les attentes de journal SMTP incluent désormais le champ operation existant.

Deux corrections d'intégration applicative ont également été confirmées : TenancyServiceProvider résout désormais le contrat HttpKernel pour appliquer les priorités à l'instance effectivement utilisée, au lieu d'instancier une autre classe concrète ; les domaines centraux secondaires redirigent vers le login canonique construit depuis APP_URL. La découverte publique des passkeys conserve son JSON et son URL, avec le contrôleur Central/Auth/PasskeyEndpointsController à la place d'une closure. Les exceptions publiques des tests de routes sont explicites et les destinations sensibles restent protégées.

Déplacement conservant le scénario et ses assertions : le test de panne de provisionnement de tests/Central/Feature/CentralManagementTest.php est placé dans **tests/Central/Integration/ProvisioningFailureTest.php**. Un job de provisionnement exige un contexte après commit ; il ne doit pas être simulé sous la transaction englobante de RefreshDatabase. Le nouveau test utilise TestCase et les migrations MySQL isolées, sans modifier tests/Pest.php ni le garde du job. Le conteneur fournit les deux dépendances réelles de handle ; l'échec du provisionneur, la réservation conservée, l'erreur assainie et la demande de reprise restent vérifiés.

Vérifications après corrections :

| Sélection | Résultat réellement obtenu |
|---|---|
| Nouvelles régressions transaction/rôle/abonnement/préparation | 5 tests réussis, 21 assertions |
| Pays, cache et audit des téléphones | 6 tests réussis, 19 assertions |
| JPEG tronqué, depuis la sélection diagnostique | Scénario réussi, 3 assertions |
| Invitations, erreurs SMTP, priorités et routes publiques/protégées | 9 tests réussis, 45 assertions |
| Relance des 40 scénarios centraux concernés | 34 réussis au premier passage ; les 6 restants ont ensuite tous réussi, avec 49 assertions sur cette dernière sélection |
| Provisionnement, administration et contexte Spatie des boutiques | 7 tests réussis, 265 assertions |

Les 50 scénarios défaillants du passage complet ont ainsi été revérifiés avec succès après correction. La suite complète n'a pas été rejouée une deuxième fois après ce lot ; ne pas annoncer un second passage intégral vert. Les tests se sont exécutés sur des bases temporaires distinctes, avec envois simulés. Les bases du premier passage ont été supprimées par le mécanisme d'isolation ; aucun reliquat de son préfixe ne subsiste. Les bases préexistantes sont conservées.

Syntaxe des services valide, Pint final exécuté avec succès et diff relu. PHPStan/Larastan reste sans erreur sur les services et leurs contrats de modèles/traits ; la vérification globale garde les 11 avertissements hors services précédemment listés. Le cache isolé de 274 routes a été généré, chargé et supprimé avec succès, puis le cache de routes actif du site a été renouvelé. Aucune migration, donnée existante, permission réelle ou configuration globale modifiée ; aucun SMTP réel ni parcours navigateur testé, aucun worker réel redémarré.

Limite conservée : le cas d'ordre des clés de couleurs dans TenantData.fingerprint signalé lors de l'audit des DTO n'est pas modifié par cet audit. Sa correction doit traiter la compatibilité avec les empreintes des réservations existantes ; aucune empreinte stockée n'a été réécrite.

## 26 octobre 2026 — Module 06 : domaines des boutiques

Administration → Domaines est raccordé au layout central, aux routes et aux six permissions Spatie demandées. Controller dans Central/Admin/Domains, Requests et Services dans Central/Domains, DomainPolicy enregistrée explicitement. DomainDnsInspector isole les contrôles externes DNS/TLS ; le modèle Domain applique désormais SoftDeletes à la colonne existante. Ajout secondaire, édition, vérification, bascule atomique du principal et archivage sont audités ; les mutations verrouillent la boutique et le domaine, vérifient la Policy et refusent les formulaires obsolètes. Les alias centraux sont également couverts. Détails et limites au §43 de l'architecture, sans nouvelle migration ni modification des BDD boutiques existantes.

Vérifications réellement exécutées : premier passage de 27 tests du module réussi (189 assertions), puis sélection élargie de 51 tests réussis (296 assertions : module, sécurité des routes, provisionnement et frontières tenant), complétée par le scénario des alias centraux réussi (1 test, 9 assertions). La sélection finale représente donc 52 scénarios distincts et 305 assertions. DNS et TLS simulés pour le module ; les tests de provisionnement utilisent les bases MySQL temporaires du dispositif existant, autorisées pour ce lot. Aucun e-mail réel ni test DNS/HTTPS sur un domaine client réel. La suite complète et le parcours navigateur réel n'ont pas été exécutés.

Syntaxe des fichiers concernés valide ; Pint --dirty --format agent réussi. L'analyse statique initiale a signalé les contrats du modèle Domain et du paginator : ajout des métadonnées/casts réels et du retour de relation typé, emploi du paginator concret qui expose getCollection, puis analyse ciblée réussie sans erreur. Après adaptation des alias, le Request concerné est à nouveau vérifié sans erreur. Cache de routes isolé compilé et chargé avec succès après correction du chemin temporaire Windows, puis cache actif renouvelé ; huit routes canoniques du module présentes sous auth:central, vérification d'e-mail et compte actif, avec UUID et throttles.

Après accord explicite et réussite des tests, DomainPermissionSeeder a ajouté uniquement les six permissions centrales absentes. Contrôle MySQL avant/après : permissions saas.domains.* de 0 à 6 ; rôles 5, attributions de rôles 5, permissions directes 11 et lignes de composition 9, valeurs inchangées. Aucun rôle enrichi automatiquement et aucune attribution existante modifiée. Le Super Admin conserve son accès implicite ; les autres membres nécessitent les attributions appropriées.

Pour un domaine personnalisé, configurer le TXT et le CNAME affichés ainsi que le routage web et le certificat HTTPS sur l'infrastructure. Le module contrôle ces éléments sans émettre de certificat ; les domaines racine avec ALIAS/ANAME et les pointages A/AAAA personnalisés restent hors de ce premier parcours CNAME. Les changements de slug et les dashboards Tenant restent hors périmètre.

### Correction après revue du Module 06 — 26 octobre 2026

Cette correction remplace les limites du premier parcours décrites ci-dessus ; le §43 de l'architecture et le schéma principal décrivent désormais le comportement courant.

- Une erreur technique DNS ou une réponse vide/inconclusive conserve les preuves acquises et ne transforme plus, à elle seule, une adresse disponible en 404. Une preuve non vide contradictoire ou un pointage explicitement incorrect reste refusé. Toutes les tentatives sont auditées et contrôlées après le réseau sous transaction.
- Les pointages A/AAAA vers les IP publiques de la base SaaS complètent le CNAME ; les domaines racine et les ALIAS/ANAME exposant ces mêmes adresses sont couverts. Comparaison IPv6 normalisée, refus des IP privées et connexion TLS vers une IP publique figée conservés.
- Le contrôle DNS et l'état HTTPS sont explicitement distingués. Une connexion impossible ou un timeout ne devient pas un nouveau succès ni une révocation automatique du dernier état HTTPS connu ; un échec TLS explicite est signalé. Aucun certificat n'est émis ni installé par le module.
- Une adresse archivée peut être restaurée avec sa même ligne et sa même boutique sous le droit saas.domains.create ; elle redevient secondaire/PENDING et perd ses anciennes preuves. Aucun septième droit ni transfert de domaine n'est ajouté.
- Une adresse secondaire SaaS peut être renommée, sous réserve des noms réservés et des slugs appartenant aux autres boutiques. Le slug et le nom de base restent inchangés. L'édition du principal guide vers une adresse de remplacement et présélectionne la boutique.
- La fiche de boutique utilise l'adresse principale prête. L'accueil public GET/HEAD d'une adresse secondaire redirige vers le principal prêt du même tenant, en 302 sans cache. Le principal, les formulaires et les diagnostics ne sont pas redirigés. Le contexte tenant est terminé après la réponse.

Vérifications réellement exécutées : sélection principale de 54 tests, avec 53 réussites et une attente de statut erronée dans le nouveau test de restauration d'une boutique archivée. La Policy refusait correctement l'action avec 403 ; l'attente 404 a été corrigée sans modifier cette protection, puis ce scénario a réussi (1 test, 5 assertions). Le parcours HTTP de redirection a ensuite été enrichi avec une autre boutique possédant un principal créé avant celui de la boutique testée : scénario réussi (1 test, 12 assertions), sans mélange des données et avec retour à la connexion centrale. Enfin, la sélection DNS/TLS élargie, incluant deux nouveaux cas de réponses vides, a réussi : 22 tests, 84 assertions. Au total, 56 scénarios distincts sont vérifiés à travers ces passages ; ne pas annoncer un unique passage intégral de la suite après toutes les corrections.

Pint final exécuté ; analyse statique ciblée sans erreur. Les conversions inet_pton/inet_ntop traitent explicitement leurs retours false, sans suppression d'analyse ni cast fictif. Cache actif de routes renouvelé et neuf routes canoniques du module confirmées. Aucune migration, configuration d'environnement, attribution de permission ou donnée des boutiques existantes modifiée pendant cette correction. Les tests restent sur MySQL temporaire, avec DNS/TLS simulés ; aucun test de domaine client réel ni parcours navigateur réel. La suite complète de l'application n'a pas été relancée.

### Revue des modifications utilisateur du Module 06 — 26 octobre 2026

Les huit fichiers demandés ont été relus avec leurs appelants : DomainVerificationService, DomainService, DomainDnsInspector, DomainPolicy, StoreDomainRequest, DomainActionRequest, StorefrontController et DomainController. Les extractions de méthodes, contrats, autorisations et transactions corrects sont conservés.

Trois régressions ont été reproduites avant correction par trois tests en échec : le Form Request rejetait les alias valides sous la base SaaS ; une valeur de domaine non textuelle provoquait une erreur 500 ; un ancien indicateur is_primary pouvait entraîner une redirection du domaine vers lui-même. StoreDomainRequest réutilise désormais la validation des hôtes réservés de DomainService, avec bail ; StorefrontController compare aussi l'identité du principal. Aucun changement de règle métier n'est ajouté.

L'essai HTTPS sur une seconde IP publique est conservé et couvert par quatre cas, avec au maximum deux essais. Seule la visibilité de handshake devient protected pour simuler cette frontière réseau dans les tests, sans modifier son traitement réel. Les protections contre les IP privées et la vérification du certificat et du nom restent en place.

Vérifications réellement exécutées après correction : DomainManagementTest et TenantRequestBoundaryTest passent ensemble, 63 tests et 366 assertions. Pint --dirty --format agent et PHPStan ciblé passent sans erreur ; git diff --check ne signale aucune erreur d'espacement. Bases MySQL temporaires isolées uniquement, DNS/TLS simulés : aucune donnée existante, migration, permission ou configuration d'environnement modifiée par cette revue. La suite complète de l'application et un domaine public réel n'ont pas été testés. Les règles d'intégration concernées sont précisées au §43 de l'architecture.

Capitalisation demandée après cette revue : les améliorations utilisateur conservées et les corrections vérifiées donnent lieu à des conventions réutilisables au §38 de l'architecture, avec déclencheurs et limites. AGENTS.md rappelle leur application aux prochains ajouts et modifications pertinents ; les spécificités DNS/HTTPS et Storefront restent au §43. Cette mise à jour concerne uniquement les trois documents existants, sans modification de code ou de BDD ni nouveau lancement de tests.

### Module 07 — Administration des déploiements boutiques — 26 octobre 2026

Administration → Déploiements est raccordé aux routes centrales, au menu, à TenantSchemaDeploymentPolicy et aux cinq permissions saas.deployments.*. Liste paginée et filtres par boutique, opération et état ; fiche par UUID, historique des tentatives et actualisation manuelle. Les actions passent par DeploymentActionRequest puis TenantDeploymentService, avec Policy, état attendu, verrou propriétaire/boutique/déploiement et audit. Les erreurs consultables sont des messages maîtrisés sous leur droit propre ; aucun message brut de sanitized_error ou de failed_jobs n'est exposé.

TenantProvisioner, ProvisionTenantDatabase et la queue centrale existants sont réutilisés. TenantSchemaVersions constitue la source des migrations prévues ; MigrateTenantJob partage la réservation, le mutex MySQL et les erreurs du job historique. La cible est fixée côté serveur, sans commande ou chemin provenant du formulaire. Le worker vérifie l'historique appliqué, termine tenancy et recalcule l'accès avec les contrôles existants. Une boutique en migration reste indisponible ; une suspension n'est pas annulée. Les reprises manuelles et automatiques créent de nouvelles lignes, gardent les échecs et leur corrélation ; les anciennes opérations ne reprennent pas après une demande plus récente. Le rattrapage CLI ne renvoie que la dernière réservation. Le parcours propriétaire conserve la reprise du provisionnement initial et ne peut pas relancer une migration administrative. Architecture et limites au §44, rappel dans AGENTS.md.

Corrections issues des tests : l'empreinte de formulaire utilise des champs et dates normalisés pour rester identique après relecture MySQL. Un test complémentaire de propriétaire nécessitait un auteur d'approbation conforme à la contrainte SQL et l'import d'OperationEnum ; ces données/imports de test ont été corrigés sans retirer de protection.

Contrôles réellement exécutés : interface/services, 17 tests réussis et 82 assertions après correction de l'empreinte ; compléments ciblés, 4 tests réussis et 11 assertions. Sélection finale des 22 scénarios du module : 21 réussites, 131 assertions, et un défaut de fixture ; après correction de la fixture et de son import, le scénario concerné réussit séparément avec 2 assertions. Les 22 scénarios sont ainsi validés à travers ces passages, sans annoncer un unique passage intégral réussi des 22. Une sélection précédente de 17 tests d'intégration/non-régression a réussi avec 132 assertions : provisionnement de deux boutiques isolées, échec/reprise, frontières tenant et première version du nouveau scénario de migration. Le scénario final de migration enrichi valide aussi une reprise automatique puis manuelle, la conservation des anciennes tentatives et la contention du mutex avec une seconde connexion MySQL. Une migration de contrôle réelle est exécutée uniquement dans la BDD temporaire du test ; elle ne figure pas dans le catalogue de production.

Pint final réussi ; PHPStan ciblé sans erreur ; compilation isolée du cache de routes puis renouvellement du cache actif réussis. Les cinq routes canoniques et leurs alias sont chargés avec auth:central, e-mail vérifié, compte actif, binding UUID et limitation des écritures. La suite complète de l'application et un parcours navigateur réel n'ont pas été exécutés. Le nettoyage des répertoires vides laissés par le générateur a été refusé par le contrôle automatique : ces répertoires sont conservés, sans effet sur les fichiers suivis ni le fonctionnement.

Après accord explicite et validation, DeploymentPermissionSeeder ajoute uniquement les cinq permissions absentes : contrôle avant/après de 0 à 5 ; rôles 5, attributions de rôles 5, permissions directes 11 et composition des rôles 9, valeurs inchangées. Les sept boutiques et sept déploiements réels sont conservés ; aucune BDD boutique existante n'est migrée ou réinitialisée. La file réelle ne contenait aucun job. Le worker Windows était activé mais arrêté ; sa tâche existante a été relancée, puis queue:listen et son processus queue:work ont été observés avec PHP 8.5. Aucun paramètre de sécurité Windows ou dépendance n'a été modifié.

### Revue des modifications utilisateur du Module 07 — 27 octobre 2026

Relecture des fichiers modifiés du module et de leurs dépendances : job partagé et job de migration, services de déploiement/provisionnement/reprise/versions/statuts, modèle, enum d'erreur, Controller et Request, Policies, middleware de disponibilité, catalogue/seeder, routes HTTP et console, vues et tests. Les extractions lisibles, constantes métier, ErrorCodeEnum avec compatibilité historique, diagnostics locaux centralisés et limitation HTTP avec préfixe distinct sont conservés. Aucun gain de performance non mesuré n'est annoncé.

Quatre erreurs PHPStan corrigées : types manquants de attempts/errorFor dans TenantDeploymentController, générique de candidates et type des modèles parcourus dans RetryPendingTenantProvisioning. La commande conserve la lecture par lots via lazyById avec son vrai modèle typé. Deux défauts de comportement ont été reproduits par deux tests en échec avant correction : le délai de rattrapage empêchait une reprise manuelle immédiate ; un ancien RUNNING abandonné écrasait ses informations d'origine. Le premier est corrigé par un mode --scheduled utilisé par la tâche périodique, avec conservation des seuils proposés par l'utilisateur. Le second était déjà présent dans le traitement initial : le job clôture désormais l'ancienne tentative sous mutex avant de réserver la suivante, en conservant son début et sa corrélation. Les autres règles et protections sont conservées.

Vérifications : sélection de 40 tests sur MySQL temporaire, 38 réussites et 224 assertions, puis correction de deux nouvelles fixtures de test. La date created_at devait être fixée par forceFill sans élargir Fillable ; le test des compteurs devait utiliser la vérification de domaine limitée à six appels, plutôt que le changement de principal limité à trente. Les deux scénarios corrigés ont ensuite réussi avec 26 assertions, sans suppression d'assertion ni affaiblissement des protections. Les 40 scénarios distincts sont ainsi validés à travers ces passages, et non par un unique passage final intégral. Ils couvrent les permissions, l'historique, les reprises, le mode planifié, les anciens codes d'erreur, l'isolation des compteurs, une vraie migration de test et les frontières tenant. PHPStan ciblé final sans erreur ; Pint exécuté. Le scheduler est déclaré toutes les cinq minutes avec --scheduled ; aucun nouveau service Windows/cron n'a été installé ou lancé pendant cette revue. Les limites et prérequis sont précisés au §44 et dans AGENTS.md. Aucune migration ou écriture métier sur les BDD réelles, aucune dépendance ajoutée ; pas de seeder réel exécuté ni de suite complète de l'application.

### Décision sur throttle — 27 octobre 2026

À la demande explicite de l'utilisateur, ne plus limiter les actions administratives authentifiées et autorisées par throttle ; conserver les protections de fréquence adaptées aux parcours publics. Règle ajoutée dans AGENTS.md et précisée au §35 de l'architecture, avec remplacement de l'ancienne recommandation du compteur administratif du §44. Les protections publiques avant connexion restent applicables, sans exemption basée sur une identité non authentifiée. Permissions, validations et protections de cohérence restent obligatoires.

Cette intervention est documentaire : aucun fichier de routes, test ou code applicatif modifié, aucun test MySQL ni commande Artisan exécuté. Les deux fichiers de routes déjà modifiés par l'utilisateur ont été préservés. Certaines limites administratives existent encore dans le code, notamment le groupe Déploiements ; leur retrait et l'adaptation des anciennes attentes de tests relèvent d'une intervention sur ces routes. Les résultats de tests précédents restent l'historique du comportement vérifié à cette date et ne constituent pas une validation de ce futur changement.

### Module 08 — Fonctionnalités commerciales — 27 octobre 2026

Module implémenté dans Administration → Fonctionnalités, en réutilisant features sans migration. Catalogue paginé/recherche, fiche, création inactive, modification, activation dédiée et archivage logique. Controller Central/Admin/Features, Requests Central/Plans, service FeatureCatalogService, FeaturePolicy, vues central/pages/admin/features et routes central/admin/features.php ; les références et décisions sont au §45 de l'architecture et dans AGENTS.md.

Décision confirmée : une liaison à une offre interdit la désactivation globale pour préserver les abonnements. shops est protégée contre la création administrative, la désactivation, l'archivage et la modification technique ; son nom reste éditable. Toute référence à une offre, un compteur d'utilisation ou une permission protège les paramètres techniques et interdit l'archivage. Le code est normalisé, permanent et réservé même après archivage. Les autorisations indépendantes sont appliquées dans les Requests, Policies et services ; verrous, état attendu et audits atomiques protègent les mutations. Aucun throttle administratif. Les droits commerciaux ne sont pas des permissions SaaS ; aucun CRUD de plans ni cache de décision ajouté.

Feature refuse is_active en assignation de masse et démarre avec false. FreePlanCatalog active explicitement shops seulement lorsqu'il vient de la créer, dans sa transaction existante ; le quota gratuit de deux boutiques et les offres payantes restent préservés. Les lecteurs de quota utilisent désormais la constante commune Feature.SHOPS_CODE, avec la même valeur shops.

Contrôles réellement exécutés sur MySQL temporaire isolé : fichier FeatureCatalogTest, 27 tests réussis et 184 assertions ; trois scénarios existants de non-régression, 3 réussites et 29 assertions (offre gratuite idempotente conservant les abonnements payants, création déléguée idempotente sous quota, doublons de slug/domaine sans réservation supplémentaire). Après remplacement des valeurs numériques par les enums dans le formulaire, le scénario création/normalisation a été relancé : 1 réussite et 12 assertions. Cela représente 30 scénarios distincts validés, avec une relance supplémentaire, pas une suite complète de l'application. Pint exécuté ; PHPStan ciblé sans erreur ; git diff --check sans erreur. Cache actif des routes renouvelé avec succès ; huit routes canoniques et leurs alias chargés, avec auth:central, vérification d'e-mail et contrôle du compte actif, sans throttle du module. Les tests HTTP rendent les pages Blade ; aucun parcours dans un navigateur réel ni test de concurrence à deux connexions n'a été exécuté pour ce module.

Après validation et accord explicite, seul FeaturePermissionSeeder a été exécuté dans la BDD centrale réelle : permissions saas.features.* de 0 à 5, guard central. Comptages avant/après inchangés : rôles 5, attributions de rôles 5, permissions directes 11, composition des rôles 9, fonctionnalités 4, boutiques 7. Aucun rôle enrichi automatiquement, aucune fonctionnalité réelle modifiée, aucune migration ni opération sur les BDD boutiques existantes. Les nouvelles permissions peuvent être déléguées par l'interface existante ; le Super Admin conserve son accès implicite.

### Rangement des modèles par module — 27 octobre 2026

Demande explicite de ranger app/Models comme les autres couches. La décision remplace l'ancienne exception de classement à plat dans Central/Tenant. Correspondance des 21 déplacements présentée avant exécution et conservée au §46 de l'architecture ; arborescence de modèles du §25 actualisée. Central contient Users, Countries, Roles, Permissions, Tenants, Domains, Deployments, Plans, Features, Subscriptions, Media et Audit. Tenant contient Users, Roles, Permissions, Shop, Media et Audit. PermissionAssignment rejoint Shared/Authorization ; Test rejoint Examples sans ajout de table. Les alias vides App/Models/User et Tenant restent à la racine et étendent les nouvelles implémentations.

Références mises à jour dans les classes, relations implicites, vues, routes, configurations, Providers, bootstrappers, factories, seeders et tests. Policies et factories restent dans leurs dossiers de contexte. UseFactory lie les modèles déplacés à leurs factories existantes ; les newFactory des deux alias sont conservés et les factories sans modèle explicite désignent désormais leur classe réelle. La morph map résout les anciens noms persistés vers les nouvelles classes et conserve ces mêmes valeurs pour les futures écritures. Les types central_user/shop_user et les connexions restent inchangés. Les jobs de provisionnement existants transportent des identifiants, sans modèle déplacé sérialisé. La lecture réelle des types d'historique et de la file a précédé les changements ; aucun job n'était en attente.

Contrôles exécutés sans écriture SQL : nouveau ModelOrganizationTest et MediaCastsTest existant, 47 tests réussis et 132 assertions. Le test d'organisation utilise le TestCase natif Laravel, sans le TestCase MySQL du projet ; des callbacks beforeExecuting font échouer toute tentative SQL sur mysql/tenant. Couverture : tables, types historiques, factories, alias, Policies, relations, pivot partagé, sélection explicite des connexions, bascule/restauration des modèles Spatie et rendu HTTP du login avec redirection d'un visiteur du dashboard vers la connexion. Cela ne constitue pas une exécution de la suite MySQL ni une preuve d'isolation par requêtes entre deux boutiques réelles.

Pint exécuté ; analyse PHPStan ciblée app/Models, AppServiceProvider, factories et bootstrappers Tenancy réussie sans erreur. Autoload Composer régénéré sans scripts et avec --strict-psr, sans avertissement PSR-4 final. Cache actif de routes renouvelé, vues compilées effacées et artisan about exécuté (Laravel 13.34.0, PHP 8.5.12). Les empreintes agrégées des migrations, manifestes/verrouillages et fichiers .env/.env.example sont identiques avant/après. Aucune migration, aucun seeder réel, aucun changement de données ni dépendance ; AGENTS.md, instructionn.txt, architecture et référence de modèle du schéma synchronisés. Les anciennes entrées datées du suivi et les artefacts de tests hors cache actif restent historiques.

L'analyse PHPStan globale signale aussi 10 écarts hors du rangement des modèles : CreateNewUser.php:30 et UpdateUserProfileInformation.php:26 (condition toujours vraie), ProfileValidationRules.php:9 (trait non utilisé), LocalProvisioningWorker.php:18 (comparaison constante), SyncTenantProfiles.php:26 (deux propriétés non inférées dans le callback), StoreCountryRequest.php:34 (type du paramètre de route), SearchPermissionsRequest.php:24, RenewStaffAssignmentRequest.php:15 et UpdateStaffPermissionsRequest.php:16 (types des tableaux de règles). Les corps de ces traitements ne sont pas modifiés par le rangement ; seuls les imports nécessaires ont changé dans les fichiers concernés. Ces signalements sont conservés pour une intervention distincte, sans les masquer ni annoncer une analyse globale réussie. Aucun passage global avant refactorisation n'a été enregistré pour établir une comparaison chiffrée antérieure.

### Supervision du worker Windows — 29 octobre 2026

Une réservation attendait avec attempts=0, sans worker PHP actif. Tâche Windows activée mais Ready, dernier résultat 1 ; ancien timeout de 300 secondes dans le journal. Historique Task Scheduler désactivé : cause du dernier arrêt non démontrable. La supervision reposait sur 999 redémarrages maximum et ne mettait à jour que la commande des tâches existantes.

LocalProvisioningWorker conserve queue:listen et timeout=300 ; enveloppe encodée, reprise après 60 secondes avec traces horodatées, déclencheur Windows toutes les cinq minutes avec IgnoreNew. Réinstallation : mise à jour des réglages et déclencheurs en conservant activation/désactivation. Aucun changement de sécurité Windows. Worker réel redémarré : base créée, provisionnement RUNNING observé.

Test sans SQL LocalProvisioningWorkerTest : deux sorties en erreur simulées, deux reprises et traces vérifiées ; 1 réussite, 7 assertions. Pint et syntaxe PHP réussis. Tâche Running, déclencheurs connexion et répétition PT5M vérifiés. Aucun test MySQL ni reset. Le résultat final de la boutique reste à observer séparément.
Résultat réel confirmé ensuite : déploiement SUCCEEDED, boutique ACTIVE, schema_version cible renseignée et provisioned_at le 29 octobre 2026 à 04:11:31 (horodatage BDD). Durée du job 1 min 17 s ; projection du profil terminée, superviseur toujours Running. La boutique a été renommée pendant le diagnostic ; suivi effectué par son identité interne stable.

### Optimisation de la lecture centrale et TLS MySQL — 29 octobre 2026

Intervention demandée dans verification.txt, application directe autorisée ensuite. Aucune migration ni modification de donnée métier réelle. Sessions/cache du site conservés ; recommandations séparées dans le complément de l’architecture.

TLS : CA officiel fourni par le propriétaire vérifié puis copié dans le stockage privé exclu de Git. .env du site utilise MYSQL_SSL_REQUIRED=true et ce CA ; config/database.php contrôle sa présence/lisibilité et vérifie le certificat serveur pour MySQL/MariaDB. Connexions centrales et boutique héritée vérifiées en lecture seule : TLS_AES_256_GCM_SHA384 ; un autre CA est refusé. Aucun secret reproduit. Worker local redémarré hors traitement (file vide) pour recharger la configuration ; état Running confirmé.

Lecture : le middleware recharge le compte une fois et marque son instance dans la requête GET/HEAD centrale ; le guard réutilise cette même instance. Les relations sont réutilisées seulement hors transaction. Contrôles frais et requêtes existantes conservés pour mutations, autres instances, jobs et Tenant. Aucun booléen de permission en cache global ; dates, droits directs, profils et root inchangés. Le middleware compare JourneyState ; le contrôleur ne répète plus le parcours. Dashboard réutilise CentralUserAccess et ne lit pas les boutiques du personnel.

Mesure bornée réelle sur Aiven : quatre isStaff passent de quatre requêtes observées avant à une requête après (229,82 ms pour cet échantillon après). Une fois les rôles chargés, Dashboard.forUser du personnel et quatre reconnaissances root supplémentaires produisent zéro requête. Les temps sont ponctuels, aucune amélioration HTTP globale chiffrée annoncée. Tests HTTP d’intégration root/lecteur : une lecture compte, une lecture des appartenances, aucune lecture boutiques ; tests exécutés hors transaction RefreshDatabase pour exercer le vrai périmètre de lecture.

Tests locaux uniquement, bases aléatoires isolées : série authentification/dashboard/parcours/middlewares, 69 scénarios dont 68 réussites initiales ; le seul échec a aussi été reproduit avec le trait et le service d’accès de HEAD chargés en mémoire dans un processus distinct. Cause préexistante : fixture de boutique en échec sans déploiement, alors que TenantPolicy exige une opération de provisionnement. Fixture complétée, refus sans déploiement conservé et scénario corrigé réussi. Aucun assouplissement de Policy. Neuf scénarios supplémentaires droits directs/expiration/root/rollback et Spatie Tenant réussis (76 assertions). Trois scénarios d’intégration de lecture/révocation réussis : deux dashboards (10 assertions) et révocation transactionnelle malgré relations chargées (5 assertions). Dernière série sans SQL : 66 tests, 168 assertions, incluant configuration TLS, droits de lecture et organisation des modèles. Total de 147 scénarios distincts validés sur ces exécutions ciblées ; aucune suite complète lancée.

Deux premières tentatives fonctionnelles ont échoué avant assertions car le fichier de test reprenait le CA distant pour MySQL local. .env.testing isolé (identifiants locaux fournis), ignoré par Git ; TestCase refuse désormais hôtes distants et DB_URL avant création de bases. Aucun essai de test sur Aiven. PHPStan ciblé aux six fichiers applicatifs/configuration modifiés : zéro erreur ; Pint et git diff --check réussis. Aucun parcours navigateur réel de performance authentifié sur le site distant ni métriques de console Aiven collectés.
Revue des types du 29 octobre 2026 : relations dynamiques roles/permissions documentées dans HasTimedPermissions avec @property-read EloquentCollection et les génériques réels. Aucune propriété PHP ajoutée et aucune logique modifiée. Pour les prochaines relations dynamiques mal comprises par un éditeur, préciser leur contrat PHPDoc avant de contourner le diagnostic ; ne pas masquer les erreurs ni créer une propriété qui ferait écran à Eloquent. Vérification : 25 tests sans SQL réussis, 46 assertions ; PHPStan ciblé six fichiers applicatifs/configuration sans erreur, Pint et diff conformes. La disparition visuelle du diagnostic dans VS Code n’a pas été observée directement.
## 31 octobre 2026 — Module 13 géographie centrale

Module réalisé sans package géographique : modèle/enum centraux, import JSON partagé seeder/administration, six permissions indépendantes, CRUD avec état attendu, cache versionné 12 h et endpoints publics UUID. Source locale : 69 wilayas et 1541 communes. Mapping détaillé, inventaire exact des fichiers et commandes au §49 de l'architecture.

Décision utilisateur : provenance et date facultatives ; origine technique et version SHA256 factuelles, date inconnue NULL. Migration additive de nullabilité, schéma/diagramme synchronisés. Lors de la livraison initiale, les données réelles étaient inchangées et deux anciennes zones local-fixture bloquaient l'import ; la résolution approuvée figure ci-dessous. Aucune permission ajoutée dans la base réelle.

Résultats sur séries ciblées : 16 tests validation/seeder (1583 assertions), 13 HTTP (79 assertions), 2 intégration (19 assertions), soit 31 scénarios distincts réussis. Import complet puis réexécution, états/identités, conflits/rollback, permissions, aperçu/confirmation/expiration/rejeu et isolation dans une vraie base boutique temporaire vérifiés. Cache après amorçage : zéro SQL sur find/list répétés, invalidation après commit et pays vérifiée. PHPStan ciblé 0, complet 21 erreurs préexistantes inchangées ; Pint, compilation Blade et route:cache réussis. Pas de suite complète ni de test navigateur interactif. Les corrections de fixtures de tokens non relus n'ont modifié aucune protection métier.

Suite autorisée : remplacement des deux zones historiques en conservant leurs ID/UUID/liens, et tests MySQL locaux temporaires des seeders. LocalDevelopmentSeeder central et tenant réutilisent désormais le référentiel JSON, avec les codes partagés de LocalFixtureSeeder. LocalGeographyFixturesTest réussi : 1 test, 15 assertions, 228,4 s, création complète de deux boutiques temporaires et seconde exécution sans doublons.

Application réelle dans la base centrale locale aydra : sauvegarde ignorée par Git dans storage/app/private/geography-replacement-20261031-163342-before.json ; seule migration 2026_10_31_101808_allow_unknown_geographic_effective_date appliquée. Remplacement transactionnel des deux anciennes valeurs, puis import partagé : 1608 créations et 2 conservées. Résultat SQL : 69 wilayas, 1541 communes, aucune zone local-fixture ; identités historiques, mappings transporteurs et points de retrait inchangés. Nouvel aperçu : 0 création, 1610 ignorées, aucun conflit. Aucun changement aux comptes ou boutiques ; aucune permission ajoutée par cette opération. Le garde permanent d'immuabilité des codes du modèle reste conservé.

Après accord distinct, GeographyPermissionSeeder a ajouté les six permissions saas.geography.* manquantes dans la base centrale. Aucun rôle ou pivot d'attribution modifié ; vérification réelle de Gate::forUser(root)->allows(viewAny,GeographicArea) positive. L'absence initiale des permissions masquait le lien de sidebar, même au root, car la résolution native Spatie vérifie d'abord l'existence du code.

### Remplacement de la source et codes postaux — 31 octobre 2026

Le propriétaire a retiré les anciens JSON et fourni Wilaya_Of_Algeria.json et Commune_Of_Algeria.json. Import adapté à code numérique/string, ar_name et post_code ; les codes de communes deviennent dz-{post_code}, sans reprise des ID JSON. Aucun code postal manquant dans les 1541 nouvelles lignes. Les homonymes de codes distincts sont conservés ; l'unicité est contrôlée par pays/type/parent/code, comme en SQL. Les codes et rattachements suivent les fichiers fournis sans certification de leur exactitude officielle.

Remplacement réel explicitement demandé, après sauvegarde privée storage/app/private/geography-new-source-20261031-172405-before.json et lecture des références des neuf bases préparées : seules Alger et Alger Centre étaient utilisées. Les 69 ID/UUID de wilayas sont conservés, leurs valeurs corrigées selon le nouveau fichier ; Alger Centre conserve ID/UUID et parent, avec code dz-16001. Les 1540 anciennes communes non référencées ont été retirées et remplacées dans une seule transaction, sans désactiver les FK ni modifier les protections permanentes du modèle. Import : 1540 créations, 70 lignes conservées ; aperçu suivant : 0 création, 1610 ignorées, aucun conflit. Empreintes des références des neuf boutiques, mappings centraux et points de retrait inchangées. Ancienne version et codes dz-source absents de la table courante. Audit de remplacement conservé, cache géographique invalidé ; aucun compte ou autre référentiel modifié.

Contrôles de cette adaptation : 15 tests HTTP/seeder réussis (1640 assertions), puis 21 tests validation/seeder réussis (1596 assertions) après la correction de syntaxe signalée par PHPStan ; ces séries se recouvrent et ne doivent pas être additionnées comme des scénarios distincts. La première exécution avait détecté une fixture d'import sans post_code : elle a été adaptée, sans assouplir la validation. PHPStan ciblé service d'import/constantes de fixtures : 0 erreur ; Pint, compilation Blade et diff check réussis. Vérification SQL : aucun ancien code, aucun code de commune hors format dz-{4 ou 5 chiffres}, aucune commune sans wilaya. Endpoint HTTP de recherche Alger Centre contrôlé via curl --resolve vers loopback, sans modifier DNS/hosts : réponse correcte avec dz-16001 et UUID conservés ; résolution DNS directe indisponible dans le terminal PowerShell.

Dernière série validation et intégration : 22 tests réussis, 69 assertions, 297 s ; seeding complet de deux boutiques locales temporaires, réexécution sans doublon, UUID géographiques des adresses, isolation tenant et invalidation du cache vérifiés. Avec les 15 scénarios HTTP/seeder, 37 scénarios distincts réussis pour cette adaptation, sans suite complète ni modification des boutiques réelles par les tests.

### Retrait de l'import administratif — 31 octobre 2026

Décision explicite : retirer entièrement « Importer un référentiel » de l'administration ; conserver les JSON et leur chargement par GeographicAreaSeeder. Bouton, vue import.blade.php, ImportGeographyRequest, routes GET import/POST preview/POST confirm, actions du contrôleur, jetons de cache et méthode de Policy supprimés. Catalogue et groupe Spatie réduits à cinq permissions. GeographyImportService ne reçoit plus d'acteur administratif, n'a plus d'aperçu et sert uniquement au seeding ; transactions, verrou du pays, validation, protection du contexte et audit système conservés.

Migration additive 2026_10_31_174124_remove_obsolete_geography_import_permission : retire seulement le droit obsolète non attribué ; refuse les attributions éventuelles au lieu de les supprimer silencieusement. Appliquée à la base centrale locale après tests et sauvegarde privée storage/app/private/geography-import-removal-20261031-174419-before.json. Contrôle après application : cinq droits géographiques, aucun import ; empreinte exacte des 1610 zones inchangée et autres permissions identiques. Routes chargées sans les trois anciens noms ; route:cache et view:cache reconstruits. Aucun changement aux boutiques.

Vérifications isolées : 35 tests validation/seeder/HTTP réussis (1662 assertions), puis deux tests supplémentaires des protections contre la suppression d'une permission attribuée réussis (4 assertions). Les trois tests de l'ancien téléversement ont été remplacés par le refus HTTP 404 des trois anciennes URL, y compris pour le Super Admin. Seeder normal, conservation des données, absence du bouton et non-réapparition de la permission vérifiés. PHPStan ciblé contrôleur/Policy/service : 0 erreur ; Pint conforme. instructionn.txt, architecture et plan de développement actualisés ; les entrées historiques précédentes décrivent leurs étapes datées et ne réintroduisent pas l'import.

### Revue des modifications géographiques — 31 octobre 2026

Relecture des 14 fichiers demandés et des raccordements : enum, modèle, Policy, deux contrôleurs, trois Requests, trois services, configuration et deux fichiers de routes. Enregistrement de la Policy, catalogue/groupe des cinq permissions, sidebar, routes parentes, invalidation depuis Country et seeders central/tenant cohérents ; les fichiers de raccordement déjà corrects sont conservés.

Corrections limitées à quatre fichiers applicatifs : GeographyCatalog retrouve un chemin central stable via base_path, car storage_path change pendant tenancy ; GeographicAreaService garantit end() dans finally après les lectures de références d'une boutique, y compris en cas d'erreur ; GeographyImportService garantit le retour list avec array_values et retire l'opérateur nullsafe redondant devant ?? ; GeographyController.edit ne charge plus les listes de pays/wilayas que son formulaire n'utilise pas. Aucune règle métier ou donnée réelle modifiée. Les extractions, readonly, constantes, scopes de recherche littérale et tri des paramètres de cache compatibles sont conservés.

Les défauts fonctionnels ont été reproduits avant correction : requête SQL supplémentaire dans un contexte tenant malgré un cache central amorcé ; contexte restant initialisé après échec de lecture tenant. La fixture initiale de table absente utilisait DROP, refusé par une FK ; remplacé par RENAME sur la seule base temporaire, sans désactiver les contraintes. Après correction : 37 tests validation/seeder/HTTP réussis (1666 assertions), deux tests ciblés de rendu/recherche littérale réussis (13 assertions), trois tests d'intégration réussis (27 assertions), soit 41 scénarios distincts en tenant compte du scénario de rendu réexécuté. Vérification des caches partagés, invalidation depuis Tenant, équivalence d'ordre des filtres, rollback, retour central, protections de permission et UUID.

PHPStan sur les classes du module : 0 erreur après correction ; la première analyse avait atteint la limite CLI de 128 Mo, relance avec --memory-limit=512M sans changement de php.ini. Syntaxe des 14 fichiers, Pint, route:cache et diff check réussis. Namespace du cache géographique renouvelé sans écriture SQL. Aucun seeding, migration ou modification des bases réelles ; aucune suite globale ni inspection navigateur authentifiée annoncée.

Diagnostic PHP Tools PHP0406 dans GeographicAreaService : les deux arguments de lockCountry utilisent explicitement Country::query()->where/whereKey pour exposer leur type Builder à l'éditeur. Le comportement SQL, le verrou et la transaction sont inchangés. Syntaxe PHP, Pint et PHPStan ciblé réussis ; aucune écriture en BDD ni nouveau test métier nécessaire. Lorsqu'un helper attend un Builder, rendre la construction de requête explicite plutôt que supprimer son type pour contourner un diagnostic.

Complément du 1er novembre 2026, PHP0416 : propriétés Eloquent de Country et GeographicArea documentées d'après les migrations (id, attributs, nullabilité, dates CarbonInterface et relations). Aucune propriété PHP concrète ni colonne ajoutée. Dans GeographicAreaService, constructions de Builders explicites et callback Query Builder SQL typé pour éviter les inférences ambiguës de l'éditeur. Relecture complète du service ; PHPStan ciblé sur les trois classes : 0 erreur, Pint conforme. Changements de typage/documentation seulement, sans écriture en BDD ni modification des règles métier.

PHP0406 sur initialize : le type de l'élément du curseur est précisé par @var Tenant dans la boucle. Ce type correspond au contrat LazyCollection<int,TModel> du curseur Eloquent installé et au modèle réellement hydraté ; Tenant implémente TenantWithDatabase, qui étend le contrat Stancl Tenant. Aucune conversion, requête ou vérification d'exécution ajoutée. Syntaxe, Pint et PHPStan ciblé conformes.

### Capitalisation des améliorations utilisateur — 1er novembre 2026

À la demande explicite du propriétaire, le rappel existant d'AGENTS.md est précisé et le §38 de l'architecture regroupe les pratiques validées des revues successives : POO lisible, dépendances readonly adaptées, constantes/enums utiles, Builders explicites, PHPDoc fidèle aux attributs Eloquent et curseurs, diagnostic d'éditeur vérifié, lectures utiles aux vues, clés de cache normalisées, portée centrale stable et retour de tenancy garanti. Les régressions observées servent de limites ; aucune adoption automatique d'une modification non vérifiée. Appliquer ces conventions directement lors des prochaines tâches pertinentes, sans généralisation hors périmètre ni accord implicite d'écriture/test. Aucun nouveau fichier ou changement applicatif pour cette demande ; consignes et références existantes réutilisées.

### Actualisation du statut, durée et configuration — 1er novembre 2026

Le lien d'actualisation rechargeait toute la page, en contournant le verrou JavaScript du polling. Il devient un bouton utilisant la même lecture JSON : une requête simultanée, délai de cinq secondes, respect de Retry-After et message d'attente. Le polling visible reste à quinze secondes ; limite serveur de vingt lectures par minute par commerçant, personnel SaaS exempt. La lecture ne crée aucun job et ne modifie pas le parcours. Redirection décidée par état/version plutôt que comparaison d'URL ; message de session affiché une seule fois.

La fiche Déploiement affiche la durée d'exécution de la tentative, depuis ses dates existantes, sans inclure l'attente dans la queue. Configuration propre au projet déplacée de config/geography.php vers config/central/geography.php avec adaptation de GeographyImportService ; configurations standards Laravel/packages conservées à la racine pour préserver leurs clés. Conventions et inventaire mis à jour dans l'architecture et rappel dans AGENTS.md.

Contrôles autorisés sur MySQL local temporaire : première série de 70 scénarios, 69 réussis et un ancien test attendant une limitation administrative devenue obsolète. Ce test est corrigé pour vérifier l'absence de limitation administrative conformément à la décision existante, puis réussit ; 18 tests supplémentaires du référentiel réussissent. Soit 88 scénarios distincts validés après correction. Parmi eux, le JavaScript réellement rendu est exécuté sous Node : 30 clics simultanés ne produisent qu'une requête ; temporisation et réponse 429 vérifiées. Aucun test navigateur authentifié annoncé. PHPStan ciblé : zéro erreur ; Pint, compilation Blade, cache des routes et diff check réussis. Aucun changement des bases réelles, aucune migration ni nouveau seeding.

### Module 14 — Transporteurs communs — 1er novembre 2026

Administration Transporteurs raccordée au menu, aux routes centrales, à la Policy et aux cinq droits Spatie saas.carriers.view/create/update/activate/archive. Fichiers classés Central/Shipping selon le §47 de l'architecture. Liste paginée avec recherche et filtres, création limitée à Yalidine, fiche, modification du nom, activation distincte et archivage logique confirmé. Aucun secret de boutique, appel API ni endpoint inventé. Le code et le connecteur prévu sont stables ; une liaison à une correspondance, un bureau ou un compte boutique interdit l'archivage. Verrou de cible, empreinte d'état, révision monotone et audit transactionnel préservés. Les vérifications inter-BDD terminent toujours la tenancy, même après erreur.

ShippingCarrierCatalog prépare la lecture publique uuid/code/name avec cache fichier central isolé par BDD, douze heures et invalidation versionnée après commit ; administration et décisions restent fraîches. ShippingCarrierSeeder est intégré au seeder normal, sans écraser une fiche existante ni réutiliser les liens fictifs des jeux locaux.

Autorisation explicite reçue pour les tests MySQL locaux temporaires et l'ajout réel de Yalidine et des cinq permissions. Dix-sept scénarios distincts validés : treize scénarios HTTP/Policy/seeder/rollback, une protection de référence géographique et trois intégrations cache/tenant. La première série HTTP comportait une attente incorrecte dans le nouveau test d'archivage : Eloquent fresh() relit aussi la ligne archivée ; assertion corrigée pour vérifier trashed(), puis scénario réexécuté avec succès. PHPStan ciblé : zéro erreur ; Pint, cache des routes, compilation Blade et diff check réussis. Les intégrations créent réellement des bases boutiques temporaires et les suppriment ensuite ; aucune boutique réelle n'est provisionnée ou migrée. Pas de test navigateur authentifié ni de test concurrent multi-processus annoncé.

Seeders spécialisés appliqués avec succès à la base centrale aydra après validation : Yalidine ajouté inactif, default_api_url NULL ; cinq permissions centrales ajoutées. Transporteur local-fixture existant conservé. Contrôle avant/après : onze attributions directes, cinq attributions de rôles, neuf associations rôle-permission et soixante-huit autres permissions inchangées. Aucun seeder global, migration ou suppression réelle exécuté pour ce module.

Correction du formulaire Transporteurs : la demande « seulement Yalidine pour l'instant » concerne les données initiales, pas une interdiction d'ajouter d'autres réseaux. Le code n'est plus prérempli ni readonly à la création ; la validation et le service acceptent d'autres codes uniques normalisés. Après création, le code reste permanent. Un autre transporteur utilise un connecteur non configuré et une origine administrative, jamais les informations de Yalidine ; aucun endpoint ou compte API ajouté. Textes et règles courantes actualisés. Trois tests ciblés réussis (44 assertions), dont création personnalisée, doublon normalisé, champs protégés et immutabilité après création ; PHPStan zéro erreur, Pint conforme et vues recompilées. Tests sur les seules bases locales temporaires autorisées pour ce module ; aucune modification de la base réelle pour cette correction.

### Revue des refactorisations Transporteurs — 1er novembre 2026

Sept fichiers applicatifs relus avec leurs appelants : Controller, deux Requests, Model, Policy, Catalog et Service. Extractions de constantes/actions/champs, helper de route typé, redirection lisible, extraction mutate/revise, découpage des références et filtre de recherche conditionnel conservés. Policy dans la transaction, verrou cible, état attendu, révision, audits et fin de tenancy restent cohérents. Aucun nouveau cache commun ni alignement du module géographique imposé.

Deux points corrigés dans ShippingCarrierCatalog : storage_path suffixé sous tenancy remplacé par le chemin central stable base_path ; array_values rétabli pour garantir le retour list déclaré, après deux diagnostics PHPStan return.type. L'ordre des résultats n'était pas démontré incorrect à l'exécution : cette seconde correction préserve un contrat typé vérifiable. Le helper connection et la constante COLUMNS de la refactorisation sont conservés.

Tests complétés : message exact code.in, noms explicites pour les sept jours de validité du profil de test, sujets de Policy et routes listés, exception de rollback obligatoire et type géographique enum. Ajout d'une vérification du cache lorsque le chemin storage de l'application change, avec restauration en finally ; les intégrations avec de vraies bases boutiques temporaires restent présentes. Accord explicite reçu pour ces tests locaux isolés. Résultat : 18 tests réussis, 141 assertions, 221 secondes ; PHPStan ciblé zéro erreur après correction, Pint et diff check conformes. Aucun seeding, migration ou changement des bases réelles. Conventions réutilisables ajoutées au §38, avec limites de généralisation.

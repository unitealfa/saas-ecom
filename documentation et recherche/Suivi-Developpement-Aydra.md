# Suivi de développement et audit Aydra

Ouvert le 6 octobre 2026 pour exécuter `instruction.txt`. Ce fichier distingue les spécifications, le code et les vérifications réelles. Une case non cochée signifie que le travail reste à faire ; une migration écrite ne prouve pas son exécution.

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

## Prochaine étape et limites explicites

Le lot demandé de préparation, seeding, connexion par domaine et vérification réelle est exécuté. **L'ensemble du SaaS n'est pas terminé.** Le §25 de l'instruction et le chapitre 15 de l'architecture demandent un développement progressif, module par module.

Avant toute activation commerciale : implémenter Spatie Permission et ses règles datées/signatures/absence de recoupement, le rôle propriétaire protégé, Activity Log transactionnel, les intentions/historiques de déploiement, les plans/features/abonnements et quotas concurrents, le singleton shop, les contrôles complets d'état de compte et d'accès, puis l'activation locale par jeton et le profil professionnel vérifié. Ajouter les services, Requests/DTOs/Controllers dans leurs dossiers de contexte au fur et à mesure des véritables fonctionnalités ; ne pas créer de faux endpoints métier ni de tables d'exemples hors des demandes explicites.

Le provisionneur actuel est un amorçage technique synchronisé après commit pour le développement. Une création commerciale devra réserver domaine/quota/intention durable dans la même transaction centrale et posséder un rattrapage après crash. Les seeders de démonstration ne remplacent pas ce workflow de production. Les formulaires nom/prénom distincts et les règles professionnelles complètes sont aussi à terminer ; `name` est seulement la compatibilité du kit.

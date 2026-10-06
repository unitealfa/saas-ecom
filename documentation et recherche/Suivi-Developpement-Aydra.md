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

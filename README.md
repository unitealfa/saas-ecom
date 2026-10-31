# Aydra

## Démarrage local — Windows / PowerShell

### 1. Installer les outils

- [Git pour Windows](https://git-scm.com/downloads/win).
- [Laravel Herd pour Windows](https://herd.laravel.com/windows) : installer et sélectionner PHP **8.5**.
- [Composer 2](https://getcomposer.org/download/) : sélectionner le PHP 8.5 de Herd pendant l'installation ; ne pas sélectionner herd-lite.
- [Node.js 24 LTS](https://nodejs.org/en/download) : version **24.11 minimum**, avec npm.
- [MySQL Community Server](https://dev.mysql.com/downloads/mysql/) : MySQL **8.4 LTS**, service démarré, port `3306` ; conserver le mot de passe administrateur choisi.
- Facultatif : [MySQL Workbench](https://dev.mysql.com/downloads/workbench/) pour exécuter les instructions SQL.

### 2. Cloner le dépôt et vérifier les outils

Dans PowerShell, remplacer `URL_DU_DEPOT` par l'adresse de clonage du dépôt :

```powershell
Set-Location "$env:USERPROFILE\Herd"
git clone URL_DU_DEPOT aydra
Set-Location aydra
$aydraPhp = "$env:USERPROFILE\.config\herd\bin\php85\php.exe"
$env:Path = "$(Split-Path $aydraPhp);$env:Path"
& $aydraPhp --version
& $aydraPhp -m
Get-Command composer | Select-Object Source
composer --version
node --version
npm.cmd --version
```

- Vérifier PHP `8.5`, Node `24.11+` et l'absence de `herd-lite` dans le chemin Composer.
- Activer les extensions PHP requises : `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `dom`, `xml`, `ctype`, `tokenizer`, `session`, `filter`, `hash` et `zip`.

### 3. Installer les dépendances

```powershell
composer install --prefer-dist
composer check-platform-reqs
npm.cmd ci
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

- Ne pas exécuter `laravel new`, `fortify:install`, `tenancy:install` ou une publication des migrations Spatie sur ce dépôt existant.

### 4. Préparer MySQL — installation locale neuve seulement

Dans MySQL Workbench ou le client MySQL, remplacer `MOT_DE_PASSE_LOCAL_UNIQUE` avant d'exécuter :

```sql
CREATE DATABASE aydra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aydra_local'@'127.0.0.1' IDENTIFIED BY 'MOT_DE_PASSE_LOCAL_UNIQUE';
GRANT ALL PRIVILEGES ON aydra.* TO 'aydra_local'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `boutique\_%`.* TO 'aydra_local'@'127.0.0.1';
```

- Utiliser un serveur MySQL local dédié ; ne pas appliquer ces droits de provisionnement comme configuration de production.
- Ne pas supprimer une base existante ; adapter les noms et identifiants si cette installation existe déjà.

### 5. Renseigner `.env`

Remplacer les valeurs correspondantes, conserver les autres lignes :

```dotenv
APP_NAME=Aydra
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://aydra.localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aydra
DB_USERNAME=aydra_local
DB_PASSWORD="MOT_DE_PASSE_LOCAL_UNIQUE"

SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=votre-adresse@gmail.com
MAIL_PASSWORD="VOTRE_MOT_DE_PASSE_APPLICATION"
MAIL_FROM_ADDRESS=votre-adresse@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

- Gmail : activer la validation en deux étapes puis créer un [mot de passe d'application](https://support.google.com/accounts/answer/185833) ; utiliser ce mot de passe dans `MAIL_PASSWORD`.
- Autre fournisseur SMTP : remplacer `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD` et `MAIL_FROM_ADDRESS` par ses paramètres.
- Sans envoi réel : mettre `MAIL_MAILER=log` et consulter `storage/logs/laravel.log` pour les liens de vérification locaux ; ne pas partager ces liens.
- Ne pas ajouter de secret à `.env.example` ni à Git.
- Conserver `APP_KEY` sur une installation existante ; ne générer la clé qu'une seule fois sur une installation neuve.

### 6. Initialiser l'application — installation neuve

```powershell
& $aydraPhp artisan config:clear --no-interaction
& $aydraPhp artisan key:generate --no-interaction
& $aydraPhp artisan migrate --path=database/migrations/central --no-interaction
& $aydraPhp artisan db:seed --no-interaction
& $aydraPhp artisan db:seed --class='Database\Seeders\Central\AuthorizationSeeder' --no-interaction
npm.cmd run build
```

- Ne pas lancer `migrate:fresh` ni `db:wipe`.
- Ne pas lancer les migrations Tenant dans la base centrale ; créer une boutique depuis le parcours Aydra pour déclencher son provisionnement.
- Le seeder normal prépare les pays et l'offre gratuite ; `AuthorizationSeeder` prépare les rôles et permissions, sans créer de compte Super Admin.
- Pour l'accès administratif initial, faire préparer un Super Admin par le responsable du projet ; aucune commande dédiée de création de ce compte n'est actuellement fournie.

### 7. Installer et démarrer le worker Windows

```powershell
& $aydraPhp artisan aydra:local-queue install --no-interaction
& $aydraPhp artisan aydra:local-queue start --no-interaction
& $aydraPhp artisan aydra:local-queue status --no-interaction
```

### 8. Démarrer le serveur local

Dans un terminal PowerShell ouvert à la racine du projet :

```powershell
$aydraPhp = "$env:USERPROFILE\.config\herd\bin\php85\php.exe"
& $aydraPhp artisan serve --host=127.0.0.1 --port=8000 --no-interaction
```

- Ouvrir `http://aydra.localhost:8000`.
- Après création d'une boutique, ouvrir le domaine enregistré avec le même port : `http://SLUG_BOUTIQUE.aydra.localhost:8000`.
- Ne pas utiliser le lien automatique `aydra.test` de Herd avec cette configuration.
- Pour les domaines sans port, faire configurer le serveur web local pour `aydra.localhost` et ses sous-domaines, puis mettre `APP_URL=http://aydra.localhost` et recharger la configuration ; ne pas modifier uniquement le lien du navigateur.

### 9. Travailler sur l'interface

Dans un deuxième terminal ouvert à la racine :

```powershell
npm.cmd run dev
```

### 10. Démarrer les tâches planifiées locales

Dans un troisième terminal ouvert à la racine :

```powershell
$aydraPhp = "$env:USERPROFILE\.config\herd\bin\php85\php.exe"
& $aydraPhp artisan schedule:work --no-interaction
```

### 11. Vérifier le démarrage

```powershell
& $aydraPhp artisan --version
& $aydraPhp artisan migrate:status --path=database/migrations/central --no-interaction
& $aydraPhp artisan aydra:local-queue status --no-interaction
& $aydraPhp artisan queue:failed --no-interaction
```

- Ouvrir `/register`, créer un compte, vérifier l'e-mail, attendre l'approbation administrative, puis configurer la première boutique.
- Après modification de `.env`, exécuter `& $aydraPhp artisan config:clear --no-interaction`, puis arrêter et redémarrer le worker avec `aydra:local-queue stop` et `aydra:local-queue start`.

## Installer les dépendances après un clonage

Utiliser PHP 8.5 et Composer 2. Vérifier `php --version` et `composer --version` dans le terminal avant l'installation. Sous Windows avec Herd, utiliser PHP 8.5 de Herd et ne pas utiliser le wrapper Composer de herd-lite.

Depuis la racine du dépôt :

```shell
composer install --prefer-dist
```

Cette commande installe les versions de `composer.lock`, déjà suivi dans Git. Ne pas utiliser `composer update` pour une simple installation et ne pas supprimer le fichier de verrouillage. Le projet privilégie déjà les archives avec `preferred-install: dist`.

Cette étape installe les dépendances PHP ; elle ne configure pas les bases MySQL. Consulter [les conventions d'installation et de vérification, §22](<documentation et recherche/architecture a suivre pour faire le code.txt>) avant les étapes suivantes. Ne pas lancer `composer run setup` sans préparer et vérifier la connexion : ce script exécute notamment des migrations.

### Si Composer demande un jeton GitHub

Une demande de jeton pour une dépendance publique, comme `symfony/process`, ne signifie pas que cette dépendance est privée. Une limite de téléchargements anonymes GitHub, un jeton local invalide ou un problème réseau peuvent empêcher son téléchargement. Le dépôt ne peut pas garantir l'absence de ces limites sur chaque ordinateur ; `--prefer-dist` ne les supprime pas.

1. Annuler la demande avec `Ctrl+C`.
2. Si un ancien jeton GitHub invalide est enregistré dans Composer, retirer uniquement cette entrée :

   ```shell
   composer config --global --unset github-oauth.github.com
   ```

   Cette commande modifie la configuration personnelle de Composer et ne révoque pas le jeton sur GitHub. Si le jeton est valide et nécessaire à d'autres projets, le conserver.

3. Vérifier la connexion et lancer le diagnostic, puis relancer l'installation :

   ```shell
   composer diagnose
   composer install --prefer-dist
   ```

   Avant de partager le diagnostic, masquer toute information sensible.

4. Si GitHub confirme une limite de requêtes, attendre son renouvellement ou configurer son propre jeton avec les permissions minimales pour les dépôts publics, selon la documentation officielle. Ne pas accorder le droit étendu `repo` pour télécharger uniquement des dépendances publiques.

Ne jamais partager un jeton dans Git, `composer.json`, `.env.example`, une capture ou un message. Composer peut le conserver en clair dans son fichier personnel `auth.json` ; le fichier `/auth.json` du projet est déjà ignoré par Git. Pour localiser le dossier de configuration personnel :

```shell
composer config --global home
```

Références officielles : [limites GitHub et OAuth](https://getcomposer.org/doc/articles/troubleshooting.md#api-rate-limit-and-oauth-tokens), [authentification Composer](https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth).

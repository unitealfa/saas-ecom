gxshdcjkgblhnmj,mk;

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

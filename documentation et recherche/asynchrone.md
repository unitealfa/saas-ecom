# Mails de comptes : mode synchrone par défaut

## Portée de cette livraison

- Invitations centrales (création, renvoi central et personnel, renouvellement après changement d’adresse non vérifiée) : `AccountInvitationRequested`, `StaffInvitationResent` → `SendAccountInvitation`.
- Boutique : `TeamInvitationMailRequested`, `TeamInvitationMailResent` → `SendTeamInvitation` ; `WelcomeEmployeeMailRequested` → `SendWelcomeEmployee` ; `EmployeeEmailConfirmationRequested` → `SendEmployeeEmailConfirmation` ; `EmployeePasswordResetRequested` → `SendEmployeePasswordReset`.
- La vérification centrale et le mot de passe oublié central restent inchangés. Leur notification native et leurs réponses Fortify exigent une revue complémentaire pour garantir une URL préparée dans la requête, la compatibilité des tests existants et l’absence d’une classe supplémentaire non autorisée. Ne pas prétendre que tous les mails sont asynchrones.
- Correctifs séparés de `ProvisionTenantDatabase` : déploiement absent ignoré ; écritures d’échec réunies dans une transaction centrale.

## Configuration et déploiement

`QUEUE_ACCOUNT_MAIL_CONNECTION` vaut `sync` par défaut. L’exécution reste immédiate, sans worker requis. `database` est l’autre connexion prise en charge pour les nouveaux listeners. Ne pas activer `database` en production sans worker opérationnel ; ne pas modifier `QUEUE_CONNECTION` pour cette fonctionnalité. La file est `default`, distincte de `saas-documents` et `tenant-provisioning`. La connexion de queue database doit écrire dans la connexion physique centrale et ne doit pas avoir le marqueur `central: true` (Stancl doit ajouter `tenant_id`).

Lorsque l’infrastructure le permet : définir `QUEUE_ACCOUNT_MAIL_CONNECTION=database`, mettre à jour la configuration Laravel, puis utiliser un worker permanent :

```sh
php artisan queue:work database --queue=default --sleep=2 --tries=3 --timeout=60 --max-time=3600 --no-interaction -v
php artisan queue:restart
```

Exécuter `queue:restart` à chaque déploiement avec des workers actifs. Le modèle `deployment/supervisor/aydra-default.conf` est livré mais non installé. `aydra:local-queue` gère une tâche Windows supplémentaire uniquement en local ; aucun worker n’est lancé par l’installateur.

`MAIL_SMTP_TIMEOUT` est optionnel : valeur par défaut `null` comme avant. 20 secondes est une recommandation, pas une valeur imposée. Les listeners ont 3 tentatives au total, backoff 10/60/300 secondes, timeout 60 secondes. Vérifier `retry_after > timeout` et la capacité réelle du worker. Aucune modification du délai ou des tentatives des documents SaaS.

## Cohérence, sécurité et échecs

Les invitations centrales sont déclenchées après retour de `AuthorizationMutation` ou de la transaction de profil. Les mails tenant sont construits et déclenchés après retour du bloc transactionnel ou de la génération du token de récupération. Aucun modèle Eloquent ne figure dans les événements. Le helper refuse une transaction encore ouverte ou un contexte incorrect et intercepte les exceptions de préparation ou d’envoi. Le listener vérifie le contexte tenant et les UUID ; les URLs de boutique sont déjà préparées par `TenantInvitationUrl`. Les listeners de liens temporaires contrôlent leur expiration. Les listeners centraux, de bienvenue et de réinitialisation retrouvent le compte par UUID et adresse. Les invitations tenant et confirmations d’adresse envoient au destinataire du payload ; elles ne revérifient pas tous les changements métier avant l’envoi. La validité finale du lien est contrôlée par le parcours d’acceptation ou de confirmation existant. Un ancien e-mail peut donc encore être envoyé. Le message de réussite des invitations et de la confirmation d’adresse distingue sync et database ; les réponses génériques de récupération de mot de passe et d’enregistrement du compte restent génériques.

Laravel 13.34.0 : `Illuminate/Events/Dispatcher.php` 674–683 (connexion et file), 748–755 (options et chiffrement) ; `Illuminate/Queue/Queue.php` 196–200 (chiffrement du job). Stancl 3.10.1 : `QueueTenancyBootstrapper.php` 128–158 (payload, sauf `QueueFake`). Le job est chiffré ; la clé `APP_KEY` doit rester disponible aux workers. Ne pas journaliser les événements ni les URLs. Les exceptions transmises aux workers sont génériques ; les hooks `failed()` consignent une opération et une classe, pas les liens ni les jetons. Pour les invitations, priorité au compte central actif et vérifié de l’auteur, puis au propriétaire via `central_user_uuid` en tenant ; sinon journal seulement. La notification utilise le canal `database` et le centre central existant.

L'envoi n'est pas garanti exactement une fois : un timeout après acceptation SMTP peut entraîner un doublon. Une queue sans worker, un job expiré ou une configuration invalide ne garantissent pas la remise du mail. Les jobs échoués nécessitent une politique de rétention et de reprise ; ne jamais les purger sans décision explicite. Si un envoi échoue au moment du dépôt en queue, le helper journalise ; `failed()` ne peut pas s’exécuter si aucun job n’a été créé.

## Vérifications à exécuter localement

Tests Laravel non exécutés lors de la livraison. Vérifier les suites `ProvisioningFailureTest`, `AccountMailEventsTest`, `TenantAccountMailEventsTest`, `LocalAccountMailWorkerTest`, les parcours existants de comptes central et tenant et la suite complète. Les tests en base doivent utiliser une instance MySQL locale isolée. Le test de payload tenant doit utiliser la vraie connexion database : `Queue::fake()` ne produit pas le payload Stancl. Vérifier la génération des liens, les messages sync/database, les annulations de transactions, les échecs SMTP et le destinataire central. Aucun déploiement, aucune installation de Supervisor, aucune configuration de production effectués.


## État local au 25 novembre 2026

Le journal PowerShell fourni confirme 18 fichiers modifiés, 19 créés, 33 contrôles PHP 8.5.12 réussis et 37 cibles déjà appliquées au second passage. Les tests Laravel ont été bloqués à la découverte par `Pest\Exceptions\TestCaseAlreadyInUse` dans `TenantAccountMailEventsTest.php` : `tests/Pest.php` associe déjà le dossier Tenant à `Tests\TestCase`. Retirer uniquement la déclaration locale redondante `pest()->extend(TestCase::class);` dans ce nouveau test, puis relancer les tests sur une base locale isolée. Aucun résultat métier positif n’est établi par ce journal.

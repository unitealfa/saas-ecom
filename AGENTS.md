# Instructions du projet Aydra

## Avant chaque tâche

- Lire ce fichier et les éventuels AGENTS.override.md ou instructions de sous-dossier applicables. Vérifier racine Git, dossier courant et diff ; préserver le travail existant et signaler les conflits de priorité.
- Après la reconnaissance initiale, consulter seulement le code et les références utiles. Lire .ai/rules/index.md et les règles applicables si elles existent, rechercher les mots-clés avec rg et activer les skills pertinents disponibles.
- Respecter le périmètre : expliquer une demande d'explication, réaliser entièrement une demande d'implémentation. Décider les choix techniques courants et réversibles ; clarifier une information métier importante ou une contradiction non résolue.
- Une décision explicite récente remplace la règle obsolète dans son document. Distinguer décision validée, implémentation et proposition ; ne pas enregistrer une supposition comme accord.

## Références selon la tâche

| Référence | Quand la consulter |
|---|---|
| [Architecture](<documentation et recherche/architecture a suivre pour faire le code.txt>) | Avant un nouveau fichier, déplacement ou changement de structure/responsabilités. §18–20 : organisation validée ; §21–22 : cadre durable, conventions et commandes. |
| [Schéma principal](<documentation et recherche/Schema-BDD-SaaS-Ecommerce-UUID.md>) | Avant un changement fonctionnel, de données ou de migration : référence métier, identifiants et cohérence. |
| [Diagramme central](<documentation et recherche/Diagramme-BDD-Centrale-Complet.md>) | Avant de modifier les tables, champs ou relations centrales. |
| [Diagramme boutique](<documentation et recherche/Diagramme-BDD-Boutique-Complet.md>) | Même contrôle pour une BDD tenant ; préserver son isolation. |
| [Suivi](<documentation et recherche/Suivi-Developpement-Aydra.md>) | Décisions récentes, état du code, contrôles datés et point de reprise éventuel. |
| [Recherche authentification/permissions](<documentation et recherche/Documentation-Laravel-Spatie-Permissions-Passkeys.md>) | Fortify, rôles, permissions, passkeys ; vérifier les exemples contre les versions installées. |
| [Recherche audit](<documentation et recherche/Recherche_complete_Spatie_Laravel_Activity_Log.md>) | Audit Spatie, événements et données journalisées. |
| [API EcoTrack](<documentation et recherche/ECOTRACK API.postman_collection.json>) | Intégrations transporteur ; protéger les identifiants et limiter les appels réels au besoin autorisé. |

Consulter aussi les notes du même dossier lorsqu'elles concernent la tâche. [last one notes.md](<documentation et recherche/last one notes.md>) est une analyse historique de V3 : ne pas réintroduire ses décisions remplacées. Un écart du code ne justifie pas de réécrire la conception.

## Architecture et réalisation

- Respecter Central / Tenant / Storefront et MVSC : Controller coordonne, Form Request valide, DTO transporte si utile, Service porte le métier, Model décrit données/relations, View affiche. Préserver les contrats Fortify et le cycle Livewire.
- Avant création/génération/déplacement, vérifier le chemin documenté et chercher un équivalent. Utiliser les dossiers de contexte ; préserver les noms de routes et actualiser toutes les références après déplacement.
- Routes : routes/central.php et routes/central/settings.php ; routes/tenant.php inclut routes/storefront.php dans le contexte tenant. Storefront utilise la BDD boutique, sans troisième BDD. Migrations/seeders sont séparés central / tenant. Conserver les exceptions partagées du §22.
- pages:: et layouts:: sont centraux ; prochaines créations Livewire : alias central::, tenant:: ou storefront:: et layout du contexte. La préparation Tenant du §19 reste inactive jusqu'à demande explicite.
- id est numérique et interne ; uuid sert au public et aux références inter-BDD. Préserver les exceptions Laravel/Spatie et PK composites. Une FK conserve la vraie clé parent : foreignId() seul n'est pas une contrainte SQL. Vérifier UK composites, nullabilité, actions et absence d'AUTO_INCREMENT sur une FK ; aucune FK SQL inter-BDD fictive.
- Réutiliser frameworks, bibliothèques et composants existants ; choisir la solution la plus simple. Pas de fonctionnalité, dépendance, refonte ou règle métier hors nécessité autorisée. Raccorder validation, autorisations, données, métier, interface et erreurs ; corriger la cause sans masquer l'échec.
- Vérifier les versions dans manifestes, verrouillages et packages installés. Pour une API incertaine, consulter le code installé ou la documentation officielle correspondante ; signaler ce qui reste incertain. Préférer Boost lorsqu'il est réellement disponible.
- Optimiser un coût concret : requêtes, pagination, index, volumes, attentes ou appels redondants. Cache avec portée, invalidation et bénéfice identifiés. Mesurer tout gain annoncé ou préciser qu'il reste à mesurer.

## Sécurité et vérifications

- Autoriser côté serveur, valider les entrées et protéger sorties, CSRF et téléversements concernés. Un bouton masqué ou un UUID ne remplace pas un contrôle d'accès. Aucun secret ou donnée personnelle inutile dans réponses, logs, instructions ou commits.
- Vérifier tenant, connexion et portée des requêtes, jobs, fichiers et caches ; aucune fuite entre boutiques. Utiliser transactions, contraintes et idempotence selon le besoin. Préserver les migrations déployées et les contrats non concernés.
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

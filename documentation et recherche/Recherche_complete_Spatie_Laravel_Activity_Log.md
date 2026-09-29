# Recherche complète — Remplacement de l’« Audit SaaS » par Spatie Laravel Activity Log

> Document unique regroupant les recherches fournies sur **`spatie/laravel-activitylog` v5**, la documentation officielle, les recherches vidéo sur les événements de modèles, la personnalisation, les cas d’usage e-commerce et l’affichage des logs dans une interface d’administration.
>
> L’objectif de ce document n’est pas de résumer les recherches, mais de **rassembler les éléments utiles dans un seul fichier**, de conserver les exemples et les points techniques, puis de montrer comment utiliser Spatie Activity Log à la place d’un système d’« Audit SaaS » développé entièrement à la main.

---

# 1. Décision pour le projet : remplacer « Audit SaaS » par Spatie Laravel Activity Log

Au lieu de développer un système d’audit complet à la main avec une table et une logique personnalisées pour chaque action, le projet peut utiliser :

- package : **`spatie/laravel-activitylog`**
- documentation : https://spatie.be/docs/laravel-activitylog/v5/introduction
- repository : https://github.com/spatie/laravel-activitylog

Le package permet de :

- journaliser les actions explicitement avec `activity()->log(...)`;
- journaliser automatiquement les événements des modèles Eloquent;
- savoir **quel objet** a été modifié grâce au `subject`;
- savoir **qui a provoqué l’action** grâce au `causer`;
- enregistrer des données supplémentaires dans `properties`;
- conserver les valeurs avant/après d’un modèle dans `attribute_changes`;
- distinguer plusieurs familles de logs avec `log_name`;
- donner un nom métier à l’événement avec `event`;
- filtrer les activités;
- désactiver temporairement la journalisation;
- nettoyer les anciens logs;
- enrichir chaque log avec des informations supplémentaires;
- regrouper les logs d’une même requête;
- utiliser le buffering lorsque beaucoup d’activités sont créées;
- afficher ensuite ces données dans un back-office, par exemple avec Filament.

Toutes les activités sont enregistrées dans la table :

```text
activity_log
```

L’idée devient donc :

```text
Ancien concept
Audit SaaS développé à la main

                ↓ remplacé par

spatie/laravel-activitylog

                ↓

activity_log
```

Le package devient la base technique du système d’audit.

Cela ne veut pas dire qu’il faut journaliser absolument toutes les modifications de toutes les tables.

Il faut toujours décider :

- quelles actions sont réellement intéressantes;
- quels champs doivent apparaître dans l’audit;
- quels champs doivent être exclus;
- quelle description doit être affichée;
- qui est le `causer`;
- quel modèle est le `subject`;
- quelles informations supplémentaires doivent aller dans `properties`;
- quels logs doivent être conservés;
- quels logs doivent être nettoyés avec le temps.

---

# 2. Sources regroupées dans cette recherche

## 2.1 Documentation officielle Spatie

Documentation principale :

https://spatie.be/docs/laravel-activitylog/v5/introduction

Parties utilisées dans cette recherche :

- Introduction
- Logging activity
- Cleaning up the log
- Logging model events
- Define causer for runtime
- Using placeholders
- Using multiple logs
- Disabling logging
- Customizing actions
- Before logging hook
- Buffering activities
- Log Options
- Causer Resolver

---

## 2.2 Vidéo — Model Events

**2. Laravel Activity Log By Team Spatie - Model Events**

https://www.youtube.com/watch?v=j6FB5WelWZY

Cette recherche montre concrètement :

- l’ajout du trait de journalisation sur un modèle;
- la création d’un utilisateur;
- la modification d’un utilisateur;
- la suppression d’un utilisateur;
- la création automatique de lignes dans `activity_log`;
- l’enregistrement de l’ancien et du nouveau contenu;
- le choix des attributs à suivre;
- le choix des événements à journaliser.

---

## 2.3 Vidéo — Customisation

**3. Laravel Activity Log By Team Spatie - Customisation**

https://www.youtube.com/watch?v=B4vdEBLgVHY

Cette recherche montre notamment :

- la personnalisation de la description;
- la personnalisation du nom du log;
- l’exclusion de certains changements;
- le problème de `updated_at`;
- la journalisation uniquement des champs réellement modifiés;
- l’importance d’éviter de journaliser des champs sensibles.

---

## 2.4 Vidéo — Active Way ou Model Events

**Spatie Activity Log Example: "Active Way" or Model Events?**

https://www.youtube.com/watch?v=oudypcGlbGI

La vidéo compare deux grandes manières de créer un audit :

1. journalisation explicite au moment précis où l’action métier se produit;
2. journalisation automatique via les événements Eloquent du modèle.

L’exemple est particulièrement intéressant pour un projet e-commerce car il utilise :

- produit;
- panier;
- quantité;
- couleur;
- taille;
- modification de produit;
- administration du catalogue.

---

## 2.5 Vidéo — Viewer dans Filament

**Spatie Activity Log Viewer in Filament**

https://www.youtube.com/watch?v=CV9zYYrZKRA

La recherche montre comment afficher les événements dans une interface d’administration avec :

- une table globale des logs;
- événement;
- sujet;
- auteur de l’action;
- description;
- propriétés JSON;
- filtres;
- pagination;
- affichage personnalisé.

---

# 3. Introduction officielle à Laravel Activity Log

Le package **`spatie/laravel-activitylog`** fournit des fonctions simples pour journaliser les activités des utilisateurs et les événements de modèles de l’application.

Toutes les activités sont enregistrées dans la table :

```text
activity_log
```

Exemple minimal :

```php
activity()->log('Look mum, I logged something');
```

Récupération de toutes les activités :

```php
use Spatie\Activitylog\Models\Activity;

Activity::all();
```

Exemple plus complet :

```php
activity()
   ->performedOn($anEloquentModel)
   ->causedBy($user)
   ->withProperties(['customProperty' => 'customValue'])
   ->log('Look mum, I logged something');

$lastLoggedActivity = Activity::all()->last();

$lastLoggedActivity->subject;
$lastLoggedActivity->causer;
$lastLoggedActivity->getProperty('customProperty');
$lastLoggedActivity->description;
```

Signification :

- `performedOn(...)` : objet sur lequel l’action a été faite;
- `causedBy(...)` : utilisateur ou modèle ayant provoqué l’action;
- `withProperties(...)` : données complémentaires;
- `log(...)` : description enregistrée;
- `subject` : modèle concerné;
- `causer` : auteur de l’action;
- `description` : texte du log.

---

# 4. Journalisation manuelle d’une activité

La forme la plus simple est :

```php
activity()->log('Look mum, I logged something');
```

Pour récupérer la dernière activité :

```php
$lastActivity = Activity::all()->last();

$lastActivity->description;
```

Résultat :

```text
Look mum, I logged something
```

Cette méthode convient lorsqu’on veut journaliser une **action métier précise** et pas seulement le fait qu’un modèle Eloquent a été modifié.

Exemples possibles :

```php
activity()->log('Order confirmed by merchant');
```

```php
activity()->log('Customer added product to cart');
```

```php
activity()->log('Delivery price manually changed');
```

```php
activity()->log('Order sent to carrier');
```

---

# 5. Définir le subject avec `performedOn()`

Le `subject` représente l’objet sur lequel l’activité est effectuée.

```php
activity()
   ->performedOn($someContentModel)
   ->log('edited');
```

Récupération :

```php
$lastActivity = Activity::all()->last();

$lastActivity->subject;
```

Le résultat correspond au modèle passé dans :

```php
performedOn(...)
```

Alias court :

```php
on(...)
```

Exemple :

```php
activity()
    ->on($product)
    ->log('Product edited');
```

Le produit devient le `subject`.

---

# 6. Définir le causer avec `causedBy()`

Le `causer` représente la personne ou le modèle qui a provoqué l’action.

```php
activity()
   ->causedBy($userModel)
   ->performedOn($someContentModel)
   ->log('edited');
```

Puis :

```php
$lastActivity = Activity::all()->last();

$lastActivity->causer;
```

Alias court :

```php
by(...)
```

Exemple :

```php
activity()
    ->by($admin)
    ->on($product)
    ->log('Product edited');
```

Si `causedBy()` n’est pas utilisé, le package peut utiliser automatiquement l’utilisateur connecté.

Cela permet donc d’éviter de répéter :

```php
->causedBy(auth()->user())
```

dans tous les cas où l’utilisateur actuellement connecté est bien l’auteur réel de l’action.

---

# 7. Activité sans utilisateur : `causedByAnonymous()`

Certaines actions peuvent être générées par le système et ne pas avoir d’utilisateur humain comme auteur.

Le package permet :

```php
activity()
    ->causedByAnonymous()
    ->log('Automatic process executed');
```

Alias :

```php
byAnonymous()
```

C’est utile pour des opérations comme :

- tâche planifiée;
- cron;
- webhook;
- système automatique;
- traitement interne;
- synchronisation;
- import;
- modification exécutée sans utilisateur connecté.

---

# 8. Ajouter des propriétés avec `withProperties()`

On peut ajouter des données arbitraires au log :

```php
activity()
   ->causedBy($userModel)
   ->performedOn($someContentModel)
   ->withProperties(['key' => 'value'])
   ->log('edited');
```

Récupération :

```php
$lastActivity->getProperty('key');
```

Résultat :

```text
value
```

Recherche directement dans le JSON :

```php
Activity::where('properties->key', 'value')->get();
```

`properties` est séparé de `attribute_changes`.

Différence :

```text
properties
= informations métier ajoutées volontairement

attribute_changes
= ancien/nouveau contenu automatiquement enregistré lors des événements de modèle
```

Exemple e-commerce :

```php
activity()
    ->performedOn($product)
    ->withProperties([
        'product_name' => $product->name,
        'variant_size' => $variant->size,
        'variant_color' => $variant->color,
        'quantity' => $quantity,
    ])
    ->log('Product added to cart');
```

---

# 9. Enregistrer une date personnalisée avec `createdAt()`

Il est possible de choisir la date de l’activité :

```php
activity()
    ->causedBy($userModel)
    ->performedOn($someContentModel)
    ->createdAt(now()->subDays(10))
    ->log('created');
```

Cette fonction est utile lorsqu’on enregistre une activité correspondant à un événement qui s’est produit avant l’insertion du log.

---

# 10. Définir un événement métier avec `event()`

La description et le type d’événement peuvent être séparés.

```php
activity()
    ->causedBy($userModel)
    ->performedOn($someContentModel)
    ->event('verified')
    ->log('The user has verified the content model.');
```

On obtient donc :

```text
event       = verified
description = The user has verified the content model.
```

Pour un projet e-commerce, cela permet par exemple :

```php
->event('order_confirmed')
```

```php
->event('order_cancelled')
```

```php
->event('stock_adjusted')
```

```php
->event('shipment_created')
```

```php
->event('subscription_changed')
```

La description peut rester destinée à l’humain tandis que `event` sert au filtrage et à la logique.

---

# 11. Modifier l’activité juste avant son enregistrement avec `tap()`

Exemple :

```php
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

activity()
   ->causedBy($userModel)
   ->performedOn($someContentModel)
   ->tap(function(ActivityContract $activity) {
      $activity->my_custom_field = 'my special value';
   })
   ->log('edited');
```

Puis :

```php
$lastActivity = Activity::all()->last();

$lastActivity->my_custom_field;
```

Résultat :

```text
my special value
```

Cela permet d’enrichir une activité juste avant l’écriture.

---

# 12. Journalisation automatique des événements Eloquent

Le package peut automatiquement journaliser les événements :

```text
created
updated
deleted
```

et, pour les modèles utilisant `SoftDeletes`, également :

```text
restored
```

Pour activer ce comportement sur un modèle :

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class NewsItem extends Model
{
    use LogsActivity;
}
```

Avec ce simple trait, les événements sont enregistrés.

Cependant, sans configuration supplémentaire, le package ne journalise pas forcément les changements d’attributs détaillés.

---

# 13. Configurer les attributs avec `getActivitylogOptions()`

Exemple :

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class NewsItem extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'text'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'text']);
    }
}
```

Ici, le package suit :

```text
name
text
```

Il est également possible d’utiliser :

```php
->logOnly(['*'])
```

pour prendre tous les attributs.

---

# 14. `logFillable()`

Pour enregistrer automatiquement tous les attributs déclarés dans `$fillable` :

```php
return LogOptions::defaults()
    ->logFillable();
```

Exemple :

```php
protected $fillable = [
    'name',
    'description',
    'price',
    'status',
];
```

`logFillable()` permet de prendre ce groupe sans répéter la liste.

---

# 15. `logUnguarded()`

Si le modèle utilise plutôt `$guarded`, il est possible de journaliser tous les attributs non protégés :

```php
return LogOptions::defaults()
    ->logUnguarded();
```

`logFillable()`, `logUnguarded()` et `logOnly()` peuvent être combinés.

Le résultat correspond à l’union des attributs sélectionnés.

---

# 16. Exemple complet : création d’un modèle

```php
$newsItem = NewsItem::create([
   'name' => 'original name',
   'text' => 'Lorem'
]);

$activity = Activity::all()->last();

$activity->description;
$activity->subject;
$activity->attribute_changes;
```

Résultat logique :

```text
description = created
subject     = instance de NewsItem
```

Changements :

```php
[
    'attributes' => [
        'name' => 'original name',
        'text' => 'Lorem',
    ],
];
```

---

# 17. Exemple complet : modification d’un modèle

```php
$newsItem->name = 'updated name';
$newsItem->save();

$activity = Activity::all()->last();
```

Résultat :

```text
description = updated
```

Le `subject` est le modèle modifié.

Les changements contiennent l’ancien et le nouveau contenu :

```php
[
    'attributes' => [
        'name' => 'updated name',
        'text' => 'Lorem',
    ],
    'old' => [
        'name' => 'original name',
        'text' => 'Lorem',
    ],
];
```

C’est l’un des éléments centraux du remplacement d’un système d’audit manuel.

On peut savoir :

```text
avant = original name
après = updated name
```

---

# 18. Exemple complet : suppression d’un modèle

```php
$newsItem->delete();

$activity = Activity::all()->last();
```

Résultat :

```text
description = deleted
```

Les anciennes valeurs peuvent être conservées dans :

```php
$activity->attribute_changes
```

Exemple :

```php
[
    'old' => [
        'name' => 'updated name',
        'text' => 'Lorem',
    ],
];
```

---

# 19. Choisir les événements à enregistrer

Par défaut :

```text
created
updated
deleted
```

On peut limiter les événements :

```php
protected static $recordEvents = ['deleted'];
```

Exemple :

```php
class NewsItem extends Model
{
    use LogsActivity;

    protected static $recordEvents = ['deleted'];
}
```

Ici, seul `deleted` est automatiquement enregistré.

---

# 20. Exclure certains événements

Il est également possible de conserver le comportement général mais d’exclure un événement précis :

```php
protected static $doNotRecordEvents = ['created'];
```

Exemple :

```php
class NewsItem extends Model
{
    use LogsActivity;

    protected static $doNotRecordEvents = ['created'];
}
```

---

# 21. Personnaliser la description automatique

Par défaut, la description peut être simplement :

```text
created
updated
deleted
```

On peut la rendre plus lisible :

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->setDescriptionForEvent(
            fn(string $eventName) => "This model has been {$eventName}"
        );
}
```

Résultat :

```text
This model has been created
```

Pour une interface d’administration, une description lisible est préférable à un simple mot générique.

Exemples métier possibles :

```text
Product was updated
Order was cancelled
Customer address was changed
Delivery price was updated
```

---

# 22. Utiliser un nom de log avec `useLogName()`

Par défaut :

```text
log_name = default
```

On peut utiliser :

```php
return LogOptions::defaults()
    ->useLogName('system');
```

Ou :

```text
users
orders
stock
catalog
auth
subscriptions
```

Exemple :

```php
activity('orders')->log('Order confirmed');
```

Le champ `log_name` devient :

```text
orders
```

Cela facilite ensuite le filtrage.

---

# 23. Ne pas créer de log lorsque seuls certains champs changent

Méthode :

```php
->dontLogIfAttributesChangedOnly([...])
```

Exemple :

```php
return LogOptions::defaults()
    ->logOnly(['name', 'text'])
    ->dontLogIfAttributesChangedOnly(['text']);
```

Si seul `text` change, aucun log n’est créé.

Si `name` et `text` changent ensemble, l’activité est créée.

---

# 24. Attention à `updated_at`

Une modification Eloquent entraîne généralement aussi une modification de :

```text
updated_at
```

Donc lorsqu’on veut ignorer un événement si certains champs seulement ont changé, `updated_at` peut provoquer une activité inattendue.

La documentation actuelle permet notamment de l’inclure dans :

```php
->dontLogIfAttributesChangedOnly([
    'password',
    'updated_at',
])
```

Ce point est également montré dans la vidéo de customisation : ignorer seulement `password` peut ne pas suffire si `updated_at` change en même temps.

---

# 25. Journaliser uniquement les attributs réellement modifiés : `logOnlyDirty()`

Exemple :

```php
return LogOptions::defaults()
    ->logOnly(['name', 'text'])
    ->logOnlyDirty();
```

Si seulement `name` change :

```text
name
```

sera présent dans les changements.

`text` ne sera pas ajouté inutilement.

Pour un audit lisible, c’est particulièrement utile.

---

# 26. Journaliser un attribut d’un modèle lié

La notation pointée peut être utilisée :

```php
->logOnly([
    'name',
    'text',
    'user.name',
]);
```

Exemple :

```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

La relation directe peut donc être utilisée dans le log.

---

# 27. Journaliser seulement certaines sous-clés JSON

Exemple de colonne JSON :

```text
preferences
```

Configuration :

```php
return LogOptions::defaults()
    ->logOnly([
        'preferences->notifications->status',
        'preferences->hero_url',
    ]);
```

Cela évite de journaliser tout le JSON.

---

# 28. Exemple avec un JSON

Création :

```php
$newsItem = NewsItem::create([
    'name' => 'Title',
    'preferences' => [
        'notifications' => [
            'status' => 'off',
        ],
        'hero_url' => ''
    ],
]);
```

Modification :

```php
$newsItem->update([
    'preferences' => [
        'notifications' => [
            'status' => 'on',
        ],
        'hero_url' => 'http://example.com/hero.png'
    ],
]);
```

Récupération :

```php
$lastActivity = Activity::latest()->first();

$lastActivity->attribute_changes->toArray();
```

Exemple de résultat :

```php
[
    "attributes" => [
        "preferences" => [
            "notifications" => [
                "status" => "on",
            ],
            "hero_url" => "http://example.com/hero.png",
        ],
    ],
    "old" => [
        "preferences" => [
            "notifications" => [
                "status" => "off",
            ],
            "hero_url" => "",
        ],
    ],
]
```

---

# 29. Éviter les logs vides avec `dontLogEmptyChanges()`

Exemple :

```php
return LogOptions::defaults()
    ->logOnly(['text'])
    ->logOnlyDirty()
    ->dontLogEmptyChanges();
```

Si aucun attribut suivi n’a réellement changé, le package ne stocke pas un log vide.

---

# 30. Exclure des attributs du contenu enregistré avec `logExcept()`

Exemple :

```php
return LogOptions::defaults()
    ->logAll()
    ->logExcept([
        'password',
        'remember_token',
    ]);
```

Ces valeurs ne doivent pas apparaître dans les changements.

Il faut distinguer :

```text
logExcept(...)
```

et :

```text
dontLogIfAttributesChangedOnly(...)
```

`logExcept()` signifie :

```text
ne mets jamais ces attributs dans le contenu du log
```

`dontLogIfAttributesChangedOnly()` signifie :

```text
si seuls ces attributs ont changé, ne crée pas l’activité
```

Pour les champs sensibles comme les mots de passe, la meilleure logique est de **ne pas stocker leur valeur dans l’audit**.

---

# 31. `CausesActivity`

Le package fournit :

```php
Spatie\Activitylog\Models\Concerns\CausesActivity
```

Cela permet à un modèle de retrouver les activités qu’il a causées.

Exemple sur l’utilisateur :

```php
Auth::user()->activitiesAsCauser;
```

---

# 32. `HasActivity`

Lorsqu’un modèle peut :

- être lui-même journalisé;
- provoquer des activités;

on peut utiliser :

```php
use Spatie\Activitylog\Models\Concerns\HasActivity;
```

Exemple :

```php
class User extends Model
{
    use HasActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable();
    }
}
```

Relations obtenues :

```text
activities()
activitiesAsSubject()
activitiesAsCauser()
```

---

# 33. `ActivityEvent` enum

Le package fournit un enum pour les événements standards :

```php
use Spatie\Activitylog\Enums\ActivityEvent;

ActivityEvent::Created;
ActivityEvent::Updated;
ActivityEvent::Deleted;
ActivityEvent::Restored;
```

Utilisation :

```php
Activity::forEvent(ActivityEvent::Created)->get();
```

Ou :

```php
activity()
    ->event(ActivityEvent::Updated)
    ->log('...');
```

Les événements personnalisés sous forme de chaînes restent possibles.

---

# 34. `restored` avec SoftDeletes

Si le modèle utilise :

```php
SoftDeletes
```

le package peut automatiquement journaliser :

```text
restored
```

en plus de :

```text
created
updated
deleted
```

---

# 35. Requêter les activités

Exemples :

```php
use Spatie\Activitylog\Models\Activity;
```

Par sujet :

```php
Activity::forSubject($newsItem)->get();
```

Par auteur :

```php
Activity::causedBy($user)->get();
```

Par événement :

```php
Activity::forEvent('updated')->get();
```

Combinaison :

```php
Activity::forSubject($newsItem)
    ->causedBy($user)
    ->forEvent('updated')
    ->get();
```

---

# 36. Désactiver la journalisation pour une instance de modèle

```php
$newsItem->disableLogging();

$newsItem->update([
    'name' => 'The new name is not logged',
]);
```

Réactivation :

```php
$newsItem->enableLogging();

$newsItem->update([
    'name' => 'The new name is logged',
]);
```

Cette désactivation concerne l’instance du modèle.

---

# 37. Modifier l’activité automatique avant l’enregistrement

Sur un modèle utilisant `LogsActivity`, il est possible d’ajouter :

```php
public function beforeActivityLogged(
    Activity $activity,
    string $eventName
) {
    $activity->description = "activity.logs.message.{$eventName}";
}
```

Cela permet d’adapter le log automatique avant l’insertion.

---

# 38. Journaliser un pivot model

Les tables pivot n’ont souvent pas de clé primaire classique.

Pour pouvoir utiliser un pivot comme `subject`, la documentation indique qu’il faut ajouter une clé primaire.

Migration :

```php
$table->id();
```

Modèle pivot :

```php
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

final class PivotModel extends Pivot
{
    use LogsActivity;

    public $incrementing = true;
}
```

---

# 39. Nettoyage des logs

Après un certain temps, la table `activity_log` peut devenir volumineuse.

Commande :

```bash
php artisan activitylog:clean
```

La commande supprime les activités plus anciennes que la durée définie par :

```text
clean_after_days
```

dans la configuration.

---

# 40. Automatiser le nettoyage avec le scheduler

Dans :

```text
routes/console.php
```

Exemple :

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('activitylog:clean --force')->daily();
```

`--force` est nécessaire en production pour éviter la confirmation interactive.

---

# 41. Nettoyer seulement un log précis

Exemple :

```bash
php artisan activitylog:clean my_log_channel
```

La commande filtre le champ :

```text
log_name
```

---

# 42. Choisir la durée au moment de la commande

Exemple :

```bash
php artisan activitylog:clean --days=7
```

Cette valeur remplace la configuration pour cet appel.

---

# 43. MySQL : récupérer l’espace après nettoyage

Après beaucoup de suppressions, la table peut toujours utiliser de l’espace disque.

Commandes proposées :

```sql
OPTIMIZE TABLE activity_log;
```

ou :

```sql
ANALYZE TABLE activity_log;
```

Attention : ces opérations peuvent verrouiller les lectures/écritures.

Elles doivent donc être utilisées dans une fenêtre de maintenance adaptée.

---

# 44. Définir un causer pour une portion d’exécution

Dans un job ou une commande CLI, il n’y a pas forcément d’utilisateur connecté.

Spatie permet :

```php
use Spatie\Activitylog\Facades\Activity;

Activity::defaultCauser($admin, function () {
    $product->update([
        'name' => 'New name',
    ]);
});
```

Toutes les activités du bloc utiliseront :

```text
$admin
```

comme `causer`.

Après le bloc, le précédent causer est restauré.

---

# 45. Causer global pour le reste de la requête

```php
Activity::defaultCauser($admin);

$product->update([
    'name' => 'New name',
]);
```

La dernière activité aura `$admin` comme auteur.

C’est utile dans :

- jobs;
- commandes CLI;
- seeders;
- multi-guard;
- traitements administratifs.

---

# 46. Placeholders

Les descriptions peuvent contenir des placeholders.

Types disponibles montrés dans la recherche :

```text
:subject
:causer
:properties
```

Exemple :

```php
activity()
    ->performedOn($article)
    ->causedBy($user)
    ->withProperties([
        'laravel' => 'awesome',
    ])
    ->log(
        'The subject name is :subject.name, ' .
        'the causer name is :causer.name ' .
        'and Laravel is :properties.laravel'
    );
```

Le texte final remplace les placeholders par les vraies valeurs.

---

# 47. Plusieurs logs avec `log_name`

Sans précision :

```php
activity()->log('hi');
```

le log utilise :

```text
default
```

On peut choisir :

```php
activity('other-log')->log('hi');
```

Puis :

```php
Activity::all()->last()->log_name;
```

Résultat :

```text
other-log
```

---

# 48. Nom de log spécifique par modèle

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->useLogName('custom_log_name_for_this_model');
}
```

---

# 49. Utiliser un enum pour les noms de logs

```php
enum LogName: string
{
    case Orders = 'orders';
    case Auth = 'auth';
}
```

Puis :

```php
activity(LogName::Orders)->log('hi');
```

Résultat :

```text
orders
```

Même principe avec :

```php
->useLogName(LogName::Orders)
```

---

# 50. Cast du `log_name` dans un modèle Activity personnalisé

Exemple :

```php
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'log_name' => LogName::class,
        ];
    }
}
```

Il faut ensuite enregistrer ce modèle dans la configuration du package.

---

# 51. Filtrer par `log_name`

Exemple SQL/Eloquent :

```php
Activity::where('log_name', 'other-log')->get();
```

Scope fourni :

```php
Activity::inLog('other-log')->get();
```

Plusieurs logs :

```php
Activity::inLog('default', 'other-log')->get();
```

ou :

```php
Activity::inLog([
    'default',
    'other-log',
])->get();
```

Avec enums :

```php
Activity::inLog(LogName::Orders)->get();
```

---

# 52. Désactiver tous les logs temporairement

Globalement pour la requête :

```php
activity()->disableLogging();
```

Réactivation :

```php
activity()->enableLogging();
```

---

# 53. Exécuter un bloc sans log

```php
activity()->withoutLogging(function () {
    // ...
});
```

Pendant le bloc :

- appels manuels;
- événements automatiques de modèles;

ne créent pas d’activité.

---

# 54. Personnaliser les actions internes du package

Le package utilise des classes d’action.

Les principales sont :

```text
LogActivityAction
CleanActivityLogAction
```

`LogActivityAction` gère notamment :

- remplacement des placeholders;
- transformation des changements;
- appel du hook avant enregistrement;
- sauvegarde de l’activité.

`CleanActivityLogAction` gère le nettoyage.

---

# 55. Surcharger `LogActivityAction`

Exemple :

```php
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Actions\LogActivityAction;

class CustomLogActivityAction extends LogActivityAction
{
    protected function save(Model $activity): void
    {
        dispatch(fn () => $activity->save());
    }
}
```

Configuration :

```php
// config/activitylog.php

'actions' => [
    'log_activity' => \App\Actions\CustomLogActivityAction::class,
    'clean_log' => \Spatie\Activitylog\Actions\CleanActivityLogAction::class,
],
```

---

# 56. Transformer les changements avant enregistrement

Exemple très important pour la sécurité :

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\Activitylog\Actions\LogActivityAction;

class RedactPasswordAction extends LogActivityAction
{
    protected function transformChanges(Model $activity): void
    {
        $changes = $activity->attribute_changes?->toArray() ?? [];

        Arr::forget($changes, [
            'attributes.password',
            'old.password',
        ]);

        $activity->attribute_changes = collect($changes);
    }
}
```

Cela peut servir de deuxième niveau de protection pour retirer des valeurs sensibles.

---

# 57. Méthodes personnalisables de `LogActivityAction`

| Méthode | Utilité |
|---|---|
| `resolveDescription($activity, $description)` | Résout la description |
| `transformChanges($activity)` | Modifie les changements avant sauvegarde |
| `beforeActivityLogged($activity)` | Exécute le hook avant le log |
| `save($activity)` | Sauvegarde l’activité |
| `replacePlaceholders($description, $activity)` | Remplace les placeholders |

---

# 58. Méthodes personnalisables de `CleanActivityLogAction`

| Méthode | Utilité |
|---|---|
| `getCutOffDate($maxAgeInDays)` | Calcule la date limite |
| `deleteOldActivities($cutOffDate, $logName)` | Supprime les anciennes activités |

---

# 59. Hook global `beforeLogging`

On peut enrichir toutes les activités juste avant leur sauvegarde.

Exemple avec l’adresse IP :

```php
use Spatie\Activitylog\Facades\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Activity::beforeLogging(
            function (\Spatie\Activitylog\Contracts\Activity $activity) {
                $activity->properties = $activity->properties
                    ->put('ip', request()->ip());
            }
        );
    }
}
```

Les activités manuelles et automatiques récupèrent ainsi :

```text
ip
```

---

# 60. Plusieurs callbacks `beforeLogging`

Exemple :

```php
Activity::beforeLogging(function ($activity) {
    $activity->properties = $activity->properties
        ->put('ip', request()->ip());
});

Activity::beforeLogging(function ($activity) {
    $activity->properties = $activity->properties
        ->put('user_agent', request()->userAgent());
});
```

Les callbacks sont exécutés dans l’ordre d’enregistrement.

---

# 61. Regrouper les activités d’une même requête avec un `batch_uuid`

Ajouter la colonne :

```php
Schema::table('activity_log', function (Blueprint $table) {
    $table->uuid('batch_uuid')
        ->nullable()
        ->index();
});
```

Puis :

```php
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\Activity;

$batchUuid = (string) Str::uuid();

Activity::beforeLogging(
    function ($activity) use ($batchUuid) {
        $activity->batch_uuid = $batchUuid;
    }
);
```

Toutes les activités de la requête possèdent le même identifiant.

Cela permet de reconstruire une opération complexe ayant entraîné plusieurs modifications.

---

# 62. Buffering des activités

Par défaut, chaque activité produit son propre :

```sql
INSERT
```

Si une requête déclenche beaucoup de logs, cela augmente le nombre de requêtes SQL.

Le buffering permet d’accumuler les activités en mémoire, puis d’effectuer une insertion groupée.

---

# 63. Quand utiliser le buffering

Cas indiqués dans la documentation :

- beaucoup de modèles modifiés dans une même requête;
- opérations par lot;
- endpoints produisant de nombreux événements Eloquent;
- volonté de réduire la charge de la base liée au logging.

Pour quelques activités par requête, le comportement normal reste adapté.

---

# 64. Activer le buffering

Dans `.env` :

```env
ACTIVITYLOG_BUFFER_ENABLED=true
```

Ou dans :

```text
config/activitylog.php
```

```php
'buffer' => [
    'enabled' => true,
],
```

Aucun changement n’est nécessaire dans les appels `activity()->log()` existants.

---

# 65. Fonctionnement du buffering

Lorsque le buffering est actif :

1. les activités sont gardées en mémoire;
2. elles ne sont pas immédiatement insérées;
3. après l’envoi de la réponse, pendant la phase `terminating`, elles sont insérées en lot;
4. pour les workers de queue, le buffer est vidé à la fin de chaque job;
5. un mécanisme de sécurité est également prévu à l’arrêt de l’application.

---

# 66. Attention : pas d’ID immédiatement avec le buffering

```php
$activity = activity()->log('some activity');
```

Avec buffering :

```php
$activity->id
```

peut encore être :

```text
null
```

à ce moment précis.

L’ID est obtenu une fois le buffer écrit en base.

Donc si une fonctionnalité dépend immédiatement de l’ID du log, il ne faut pas activer le buffering pour ce besoin sans adapter la logique.

---

# 67. Buffering et Laravel Octane

La documentation indique que le buffer est un binding scoped.

Il est réinitialisé entre les requêtes Octane.

La callback de terminaison fonctionne par requête.

---

# 68. Buffering et queues

Les logs produits dans un job sont insérés en lot à la fin du job.

Cela vaut également lorsque le job se termine en erreur selon le mécanisme prévu par le package.

---

# 69. `LogOptions`

La configuration d’un modèle passe par :

```php
getActivitylogOptions()
```

Exemple :

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty();
}
```

---

# 70. Valeurs par défaut de `LogOptions`

La documentation présente notamment :

```php
public ?string $logName = null;

public bool $logEmptyChanges = true;

public bool $logFillable = false;

public bool $logOnlyDirty = false;

public bool $logUnguarded = false;

public array $logAttributes = [];

public array $logExceptAttributes = [];

public array $dontLogIfAttributesChangedOnly = [];

public array $attributeRawValues = [];

public ?Closure $descriptionForEvent = null;
```

---

# 71. Méthodes de `LogOptions`

## `defaults()`

```php
LogOptions::defaults();
```

Point de départ de la configuration.

## `logAll()`

Équivalent à :

```php
->logOnly(['*'])
```

## `logUnguarded()`

Journalise les attributs qui ne sont pas dans `$guarded`.

## `logFillable()`

Journalise les attributs `$fillable`.

## `dontLogFillable()`

Désactive la journalisation automatique des `$fillable`.

## `logOnlyDirty()`

Ne conserve que les champs réellement modifiés.

## `logOnly([...])`

Choisit les attributs à journaliser.

## `logExcept([...])`

Exclut des attributs.

## `dontLogIfAttributesChangedOnly([...])`

Empêche la création d’une activité lorsque seuls les attributs indiqués ont changé.

## `dontLogEmptyChanges()`

Évite les activités sans changement utile.

## `logEmptyChanges()`

Autorise les logs vides.

## `useLogName(...)`

Définit `log_name`.

## `useAttributeRawValues([...])`

Utilise la valeur brute de certains attributs au lieu de passer par leurs mutators.

## `setDescriptionForEvent(...)`

Personnalise la description selon le nom de l’événement.

---

# 72. `CauserResolver`

`CauserResolver` est responsable de la résolution de l’auteur de l’activité.

Dans la majorité des cas, la documentation conseille de passer par :

```php
Activity::defaultCauser(...)
```

et non de manipuler directement le resolver.

---

# 73. Utilisation avancée du `CauserResolver`

```php
use Spatie\Activitylog\Support\CauserResolver;

app(CauserResolver::class)->resolveUsing(function ($subject) {
    return User::find(1);
});
```

`setCauser()` a priorité sur `resolveUsing()`.

Méthodes exposées :

```php
resolve(...)
resolveUsing(...)
setCauser(...)
withCauser(...)
```

---

# 74. Recherche vidéo 1 — Model Events — notes détaillées

Source :

https://www.youtube.com/watch?v=j6FB5WelWZY

La vidéo part de l’objectif suivant : enregistrer l’activité d’un utilisateur ou d’un modèle.

Le package est ajouté au projet et le trait de journalisation est placé sur le modèle utilisateur.

Une création d’utilisateur est effectuée avec Tinker.

Après la création :

```text
une ligne apparaît dans activity_log
```

La vidéo effectue ensuite une modification du même utilisateur.

La mise à jour du nom entraîne une nouvelle entrée dans la table d’activités.

On récupère ensuite la dernière activité pour vérifier :

```text
updated
```

La vidéo teste aussi la suppression.

Un utilisateur est créé, puis supprimé.

Une nouvelle activité apparaît avec :

```text
deleted
```

À ce stade, on connaît donc :

```text
created
updated
deleted
```

mais la vidéo insiste ensuite sur une question plus importante :

```text
qu’est-ce qui a réellement changé ?
```

Le simple événement `updated` n’est pas suffisant pour un système d’audit détaillé.

La configuration des attributs suivis est alors ajoutée.

Dans l’exemple de la vidéo, l’objectif est d’obtenir les informations de champs comme :

```text
name
email
```

Après modification, la ligne d’activité contient les informations permettant de voir :

```text
ancienne valeur
nouvelle valeur
```

Le tutoriel montre donc la différence entre :

```text
savoir qu’un modèle a été mis à jour
```

et :

```text
savoir précisément quels champs ont changé et quelles étaient les valeurs
```

Le même principe est observé lors de la suppression : les attributs du modèle supprimé sont disponibles dans l’activité.

La vidéo montre aussi qu’on peut limiter les événements à journaliser.

Exemple logique :

```text
seulement created
```

Dans ce cas :

- création → activité;
- mise à jour → pas d’activité;
- suppression → pas d’activité.

Puis un événement supplémentaire peut être rajouté selon le besoin.

La conclusion technique de cette recherche est que le package permet de configurer séparément :

```text
les événements
les attributs
les anciennes valeurs
les nouvelles valeurs
```

Note de version importante :

Certaines vidéos utilisent une syntaxe correspondant à d’anciennes versions du package. Pour le projet actuel utilisant la documentation v5, la configuration doit suivre la syntaxe actuelle avec :

```php
getActivitylogOptions(): LogOptions
```

et les méthodes de `LogOptions`.

Les concepts montrés restent les mêmes.

---

# 75. Recherche vidéo 2 — Customisation — notes détaillées

Source :

https://www.youtube.com/watch?v=B4vdEBLgVHY

Cette vidéo reprend le système de log précédent mais approfondit sa personnalisation.

Premier point :

```text
la description
```

Au départ, le log peut seulement indiquer :

```text
updated
```

La vidéo montre qu’on peut produire une description plus claire pour l’utilisateur.

Par exemple :

```text
You have updated a user
```

ou :

```text
You have created a user
```

Le but est que la description soit compréhensible directement dans l’interface.

Deuxième point :

```text
log_name
```

La valeur par défaut est :

```text
default
```

La vidéo montre l’intérêt de définir par exemple :

```text
user
```

pour les activités du modèle utilisateur.

Il devient ensuite possible de récupérer uniquement ce groupe de logs.

Ce principe correspond à la fonctionnalité actuelle :

```php
->useLogName('user')
```

ou :

```php
activity('user')->log(...);
```

Troisième point :

```text
ignorer certains changements
```

La démonstration utilise le mot de passe.

Le tutoriel montre d’abord ce qui arrive si le mot de passe est suivi : sa valeur hachée peut apparaître dans les changements.

Même si la valeur est hachée, le tutoriel explique qu’il n’est pas souhaitable de l’enregistrer.

Le champ doit donc être exclu.

Le test montre toutefois qu’une activité peut encore être créée parce que :

```text
updated_at
```

change également.

La vidéo ajoute donc aussi la gestion de `updated_at`.

Ce point est particulièrement important lorsqu’on veut dire :

```text
ne crée pas un log si seul tel champ technique a changé
```

Quatrième point :

```text
log only dirty
```

Sans cette option, plusieurs champs déclarés comme suivis peuvent se retrouver dans le contenu du changement.

Avec la logique `dirty`, seuls les champs réellement modifiés sont affichés.

Exemple :

Si :

```text
name
email
password
```

sont suivis mais que seul :

```text
name
```

change, le log peut ne contenir que :

```text
name
```

Cela améliore fortement la lisibilité.

La vidéo teste également un changement d’email et confirme que seul l’email apparaît lorsque la journalisation des champs réellement modifiés est active.

La recherche présente donc quatre éléments principaux à conserver dans le projet :

```text
description lisible
log_name
exclusion des champs inutiles ou sensibles
logOnlyDirty
```

---

# 76. Recherche vidéo 3 — Active Way ou Model Events — notes détaillées

Source :

https://www.youtube.com/watch?v=oudypcGlbGI

La vidéo part d’un vrai problème rencontré sur les projets :

```text
que faut-il journaliser ?
où mettre l’appel de journalisation ?
qu’est-ce qui est obligatoire ?
qu’est-ce qui est optionnel ?
comment les données seront-elles affichées plus tard ?
```

L’exemple utilisé est un magasin de T-shirts.

Les cas montrés sont très proches d’un SaaS e-commerce :

- ajout d’un produit au panier;
- choix de couleur;
- choix de taille;
- modification d’une quantité;
- modification des informations d’un produit par un administrateur.

La vidéo montre deux grandes méthodes.

---

## 76.1 Méthode 1 — journalisation explicite

Lorsqu’un produit est ajouté au panier, le code appelle volontairement le logger.

L’activité peut contenir :

- description;
- subject;
- causer;
- properties.

La vidéo rappelle les alias :

```text
performedOn() → on()
causedBy()    → by()
```

Si `causedBy()` n’est pas indiqué, l’utilisateur connecté peut être utilisé automatiquement.

La description peut également contenir des placeholders.

Exemple conceptuel :

```text
:subject.name
:causer.name
```

Ils sont remplacés au moment de la création du log.

---

## 76.2 Exemple du panier

L’utilisateur :

1. choisit une couleur;
2. choisit une taille;
3. ajoute le produit au panier.

Une entrée apparaît dans `activity_log`.

La vidéo montre dans la ligne :

- description;
- `subject_type`;
- `subject_id`;
- `properties` JSON.

Lorsque le même produit est ajouté à nouveau, la quantité est augmentée.

Le log peut contenir dans les propriétés :

- nouvelle quantité;
- taille;
- couleur;
- nom du produit.

---

## 76.3 Conserver des valeurs lisibles dans `properties`

La vidéo insiste sur un point d’architecture important.

Au lieu de stocker uniquement :

```text
color_id = 4
size_id = 2
product_id = 183
```

il peut être utile de stocker également les valeurs humaines :

```text
color = Black
size = S
product_name = Classic Cotton T-Shirt
```

Pourquoi ?

Parce que lorsque l’administrateur consultera le log plus tard, l’interface n’aura pas besoin d’exécuter plusieurs requêtes supplémentaires simplement pour convertir tous les IDs en texte.

Cela rend également le log plus autonome dans le temps.

Le conseil n’interdit pas de conserver les IDs.

Il dit surtout que le log destiné à être lu devrait conserver les informations textuelles utiles à l’affichage.

---

## 76.4 Méthode 2 — événements automatiques du modèle

La vidéo montre ensuite un administrateur modifiant la description d’un produit.

Le modèle `Product` peut utiliser :

```php
LogsActivity
```

et :

```php
getActivitylogOptions()
```

On définit par exemple les attributs à suivre :

```text
name
description
price
is_active
```

Puis :

```text
logOnlyDirty
```

permet d’obtenir seulement les champs réellement modifiés.

---

## 76.5 Différence entre les deux approches

### Journalisation automatique sur le modèle

Avantage :

```text
chaque modification du modèle peut être capturée sans répéter l’appel partout
```

Inconvénient soulevé dans la vidéo :

le modèle ne connaît pas toujours le contexte métier exact.

Une baisse de stock peut provenir de :

- achat client;
- correction de stock par un administrateur;
- import;
- opération entrepôt;
- retour;
- synchronisation.

Techniquement, le modèle voit toujours :

```text
stock a changé
```

mais le sens métier n’est pas identique.

---

## 76.6 Journalisation explicite dans le flux métier

Cette approche permet une description plus précise.

Exemples :

```text
Stock decreased after customer purchase
```

ou :

```text
Stock manually corrected by administrator
```

ou :

```text
Stock updated by warehouse import
```

Le log peut aussi récupérer des informations présentes dans la requête ou le service métier qui ne sont pas directement dans le modèle.

---

## 76.7 Visibilité du comportement pour les développeurs

La vidéo mentionne également un point de maintenance.

Un trait placé sur un modèle peut journaliser automatiquement une action sans que cela soit visible dans le contrôleur ou le service.

Un nouveau développeur doit connaître ce comportement.

Avec un appel explicite :

```php
activity()->...
```

le comportement est directement visible dans le flux de code.

La vidéo précise cependant que c’est en partie une préférence d’architecture.

Les deux mécanismes restent valides.

---

## 76.8 Application correcte au projet

Le projet peut donc utiliser une approche mixte.

### Événements de modèle

À utiliser pour des changements génériques dont le sens reste identique.

Exemples :

```text
nom produit changé
description produit changée
prix produit changé
statut utilisateur changé
```

### Logs métier explicites

À utiliser lorsqu’il faut connaître le contexte.

Exemples :

```text
commande confirmée après appel
commande annulée par marchand
stock décrémenté après commande
retour reçu
colis remis en stock
livraison créée chez le transporteur
abonnement validé par super administrateur
prix de commande modifié manuellement
```

---

# 77. Recherche vidéo 4 — Activity Log Viewer dans Filament — notes détaillées

Source :

https://www.youtube.com/watch?v=CV9zYYrZKRA

La recherche part d’une question :

```text
comment afficher tous les logs dans une seule interface ?
```

Plusieurs plugins existent, mais certains affichent surtout les activités d’une fiche précise.

La vidéo cherche plutôt une page globale contenant toutes les activités.

---

## 77.1 Une simple page Filament peut suffire

La vidéo explique qu’il n’est pas forcément nécessaire de créer ou installer un package supplémentaire.

Une page Filament avec une table peut être construite.

Exemple de génération :

```bash
php artisan make:filament-page
```

La page utilise ensuite les fonctionnalités de table de Filament.

La requête principale utilise le modèle :

```php
Spatie\Activitylog\Models\Activity
```

---

## 77.2 Événements visibles

L’exemple montre des logs comme :

- utilisateur connecté;
- commande créée;
- statut de commande changé.

Les propriétés sont stockées en JSON.

---

## 77.3 Filtres

La table permet de filtrer notamment par :

```text
subject type
causer
```

Par exemple :

```text
Order
User
```

et par utilisateur ayant causé l’action.

---

## 77.4 Éviter le problème N+1

La vidéo charge :

```text
causer
subject
```

avec la requête.

L’objectif est d’éviter d’exécuter une requête SQL supplémentaire pour chaque ligne affichée.

C’est particulièrement important lorsque la table contient beaucoup d’événements.

---

## 77.5 Colonnes personnalisées

La table peut afficher :

- événement;
- subject;
- nom du subject;
- causer;
- description;
- propriétés;
- date.

L’événement peut être affiché avec un badge.

---

## 77.6 Nom du subject

Par défaut, la table d’activité contient des informations de type :

```text
subject_type
subject_id
```

Cela ne donne pas automatiquement un nom lisible.

La vidéo montre qu’on peut définir une convention au niveau des modèles pour savoir quel attribut afficher.

Pour une commande :

```text
order_number
```

Pour un produit :

```text
name
```

Pour un utilisateur :

```text
name
```

Le viewer peut donc présenter :

```text
Commande #12345
```

au lieu de seulement :

```text
App\Models\Order / 872
```

---

## 77.7 Affichage du causer

Le `causer` est souvent un utilisateur.

On peut afficher :

```text
causer.name
```

Si aucun utilisateur n’est disponible, l’interface peut afficher :

```text
System
```

Cela couvre les actions automatiques.

---

## 77.8 `properties` JSON

Le contenu de `properties` peut varier énormément selon l’activité.

Il peut contenir deux petites valeurs ou un contenu plus important.

La vidéo explique donc qu’un affichage unique rigide est difficile.

Possibilités :

- JSON brut;
- tableau;
- modal;
- action « View details »;
- HTML personnalisé;
- format spécifique selon le type de log.

Cette flexibilité explique pourquoi une page custom peut être plus adaptée qu’un package universel.

---

## 77.9 Filtres disponibles seulement sur les données existantes

Pour le filtre `subject_type`, on peut récupérer les types réellement présents.

Pour le filtre `causer`, on peut récupérer les utilisateurs ayant réellement au moins une activité.

La vidéo indique que ces requêtes peuvent être optimisées si nécessaire.

---

## 77.10 Description

La description peut être tronquée dans la table.

Exemple :

```text
50 premiers caractères
```

avec tooltip ou détail complet.

---

## 77.11 Pagination et volume

Le tutoriel prévoit une pagination car une table d’activité peut rapidement contenir beaucoup de lignes.

Le principe important est :

```text
ne pas charger toute la table activity_log en une seule fois
```

---

# 78. Synthèse technique appliquée au remplacement d’« Audit SaaS »

Cette section n’enlève aucun élément des recherches précédentes. Elle traduit les fonctionnalités observées en règles de mise en œuvre pour le projet.

Le nouveau système d’audit peut être basé sur :

```text
Spatie Activity Log
```

avec :

```text
activity_log
```

comme journal technique principal.

---

# 79. Informations à conserver dans une activité

Pour une activité utile, on doit réfléchir aux éléments suivants.

## `log_name`

Catégorie générale :

```text
auth
users
shops
catalog
orders
stock
shipping
subscriptions
settings
```

## `event`

Événement métier :

```text
created
updated
deleted
order_confirmed
order_cancelled
shipment_created
stock_adjusted
subscription_approved
```

## `description`

Texte lisible pour l’administrateur.

## `subject`

Objet concerné.

## `causer`

Auteur de l’action.

## `properties`

Contexte supplémentaire.

## `attribute_changes`

Anciennes et nouvelles valeurs lorsque la journalisation d’événement de modèle est utilisée.

---

# 80. Exemple — modification d’un produit

```php
class Product extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('catalog')
            ->logOnly([
                'name',
                'description',
                'price',
                'status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

Résultat attendu :

```text
log_name    = catalog
event       = updated
subject     = Product
causer      = utilisateur connecté
changes     = seulement les champs réellement modifiés
```

---

# 81. Exemple — confirmation d’une commande

Ici, un log métier explicite est plus intéressant.

```php
activity('orders')
    ->performedOn($order)
    ->causedBy(auth()->user())
    ->event('order_confirmed')
    ->withProperties([
        'order_number' => $order->number,
        'status_before' => 'pending_confirmation',
        'status_after' => 'confirmed',
    ])
    ->log(
        'Order :properties.order_number was confirmed'
    );
```

Cela explique clairement **pourquoi** l’état a changé.

---

# 82. Exemple — changement manuel de prix

```php
activity('orders')
    ->performedOn($order)
    ->causedBy(auth()->user())
    ->event('order_price_changed')
    ->withProperties([
        'old_total' => $oldTotal,
        'new_total' => $order->total,
        'reason' => $reason,
    ])
    ->log('Order total was manually changed');
```

---

# 83. Exemple — stock

Une modification automatique du champ :

```text
stock
```

n’explique pas toujours la raison.

Il est préférable de pouvoir distinguer :

```text
stock_decreased_after_order
stock_restored_after_return
stock_manual_adjustment
stock_imported
```

Exemple :

```php
activity('stock')
    ->performedOn($variant)
    ->event('stock_decreased_after_order')
    ->withProperties([
        'product_name' => $product->name,
        'variant_label' => $variantLabel,
        'quantity' => $quantity,
        'order_number' => $order->number,
    ])
    ->log('Stock decreased after order');
```

---

# 84. Exemple — authentification

Une connexion n’est pas nécessairement un changement sur un modèle.

Elle peut être journalisée explicitement :

```php
activity('auth')
    ->causedBy($user)
    ->event('login')
    ->withProperties([
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ])
    ->log('User logged in');
```

---

# 85. Champs sensibles à ne pas journaliser

Ne pas stocker volontairement dans les logs :

```text
password
password_confirmation
remember_token
tokens secrets
API keys
secrets
identifiants sensibles
```

Exemple :

```php
->logExcept([
    'password',
    'remember_token',
])
```

Un `transformChanges()` personnalisé peut également servir de sécurité supplémentaire.

---

# 86. Recommandation sur `properties`

Pour les données destinées à être relues plus tard, le cas e-commerce de la vidéo montre l’intérêt de stocker des valeurs lisibles.

Exemple préférable :

```json
{
  "product_id": 183,
  "product_name": "Classic Cotton T-Shirt",
  "variant_id": 92,
  "size": "S",
  "color": "Black",
  "quantity": 2
}
```

plutôt que seulement :

```json
{
  "product_id": 183,
  "variant_id": 92,
  "size_id": 2,
  "color_id": 4
}
```

Les IDs peuvent rester, mais les valeurs humaines rendent le log plus facilement consultable.

---

# 87. Organisation possible des `log_name`

Exemple :

```php
enum LogName: string
{
    case Auth = 'auth';
    case Users = 'users';
    case Shops = 'shops';
    case Catalog = 'catalog';
    case Orders = 'orders';
    case Stock = 'stock';
    case Shipping = 'shipping';
    case Subscriptions = 'subscriptions';
    case Settings = 'settings';
}
```

Utilisation :

```php
activity(LogName::Orders)
    ->event('order_confirmed')
    ->log('Order confirmed');
```

---

# 88. Viewer d’administration proposé

Une page d’administration peut afficher au minimum :

| Colonne | Contenu |
|---|---|
| Date | `created_at` |
| Log | `log_name` |
| Event | `event` |
| Description | `description` |
| Sujet | type + nom lisible |
| Auteur | causer |
| Détails | properties / changes |

Filtres possibles :

```text
date
log_name
event
subject_type
causer
```

Action de détail :

```text
Voir les propriétés
Voir l’ancien contenu
Voir le nouveau contenu
Voir le batch
```

---

# 89. Exemple de requête pour le viewer

```php
use Spatie\Activitylog\Models\Activity;

$query = Activity::query()
    ->with([
        'causer',
        'subject',
    ])
    ->latest();
```

Le chargement de `causer` et `subject` évite le N+1 montré dans la recherche Filament.

---

# 90. Nettoyage et rétention

La table ne doit pas grossir indéfiniment sans décision.

Exemple de scheduler :

```php
Schedule::command(
    'activitylog:clean --force'
)->daily();
```

La durée doit être choisie selon les besoins du projet.

Pour certains logs importants, l’usage de plusieurs `log_name` permet aussi d’appliquer des politiques différentes.

---

# 91. Cas où le buffering est pertinent

Le buffering peut devenir utile pour :

- import massif de produits;
- modification de beaucoup de variantes;
- correction de stocks en lot;
- migration;
- synchronisation transporteur;
- traitement qui met à jour plusieurs modèles en une requête.

Il n’est pas nécessaire de l’activer simplement parce que le package le propose.

---

# 92. Règle de conception finale

Le système ne doit pas être pensé comme :

```text
journaliser chaque changement de chaque colonne
```

mais comme :

```text
conserver les actions importantes permettant de comprendre :
qui a fait quoi,
sur quel objet,
quand,
avec quel contexte,
et éventuellement quelle était la valeur avant et après.
```

Spatie fournit l’infrastructure.

Le projet doit définir le sens métier des logs.

---

# 93. Sources

## Documentation officielle

- https://spatie.be/docs/laravel-activitylog/v5/introduction
- https://spatie.be/docs/laravel-activitylog/v5/basic-usage/logging-activity
- https://spatie.be/docs/laravel-activitylog/v5/basic-usage/cleaning-up-the-log
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/logging-model-events
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/define-causer-for-runtime
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/using-placeholders
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/using-multiple-logs
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/disabling-logging
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/customizing-actions
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/before-logging-hook
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/buffering
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/log-options
- https://spatie.be/docs/laravel-activitylog/v5/advanced-usage/causer-resolver

## Vidéos

- Model Events  
  https://www.youtube.com/watch?v=j6FB5WelWZY

- Customisation  
  https://www.youtube.com/watch?v=B4vdEBLgVHY

- Active Way or Model Events  
  https://www.youtube.com/watch?v=oudypcGlbGI

- Activity Log Viewer in Filament  
  https://www.youtube.com/watch?v=CV9zYYrZKRA

---

# 94. Conclusion de la recherche

Le remplacement d’un module d’« Audit SaaS » entièrement personnalisé par **Spatie Laravel Activity Log** permet de s’appuyer sur un composant déjà prévu pour :

```text
journalisation manuelle
journalisation automatique
subject
causer
properties
old/new values
events
log_name
filtres
nettoyage
hooks
buffering
personnalisation
affichage
```

Les recherches vidéo complètent la documentation en montrant surtout :

```text
comment l’utiliser dans un vrai projet,
comment éviter des logs inutiles,
comment rendre les descriptions lisibles,
comment distinguer les actions métier des simples modifications Eloquent,
comment conserver des données lisibles dans properties,
et comment construire un viewer d’administration.
```

Le point essentiel retenu pour la conception est donc :

```text
Spatie Activity Log remplace l’infrastructure d’audit faite maison,
mais le projet conserve la responsabilité de décider quelles actions métier sont importantes et quelles informations doivent être enregistrées.
```

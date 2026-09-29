**A. Verdict argumenté**

**Développement
possible après corrections ciblées. L’architecture générale est exploitable,
mais je ne recommande pas de figer toutes les migrations métier dans l’état
actuel.**

L’audit
porte sur **Schema-BDD-SaaS-Ecommerce-Corrige-V3(1).md**, lu intégralement : diagrammes,
règles hors diagrammes, parcours, contraintes, exemples, décisions restantes et
inventaire des **49 tables centrales et 66 tables boutique**.

Trois
conclusions principales :

- **Le cœur      commande–révision–réservation–expédition est cohérent sur le papier.** Les protections contre la      survente, les modifications concurrentes et les doubles traitements sont      réellement décrites. 
- **Certaines jonctions entre      modules restent insuffisamment définies**, notamment les corrections de frais déjà payés,      l’identité durable des variantes et la restauration des documents déjà      émis. 
- **Les règles fiscales et le      contrat réel DHD/Ecotrack constituent encore des dépendances de      conception.** Le      document reconnaît plusieurs de ces limites : je ne les présente pas comme      des oublis. 

|      |
| ---- |

**Périmètre**

|      |
| ---- |

**Décision**

|     |
| --- |

Identités centrales, séparation
&#x20; des BDD, catalogue descriptif, contenu

|     |
| --- |

Développement possible, avec les
&#x20; protections transversales décrites

|     |
| --- |

Variantes et stock

|     |
| --- |

Corriger l’identité durable des
&#x20; variantes avant de figer les migrations

|     |
| --- |

Commandes, révisions, confirmation
&#x20; et réservation

|     |
| --- |

Développement possible avec tests
&#x20; concurrents obligatoires

|     |
| --- |

Reversements et corrections
&#x20; financières

|     |
| --- |

Clarifier les corrections après
&#x20; paiement avant les migrations concernées

|     |
| --- |

Facturation, avoirs et échanges

|     |
| --- |

Fixer les règles documentaires et
&#x20; renforcer les associations avant activation

|     |
| --- |

Connecteur DHD/Ecotrack

|     |
| --- |

Construire l’adaptateur et les
&#x20; opérations durables ; ne pas considérer les capacités distantes comme
&#x20; validées

|     |
| --- |

Sauvegarde/restauration

|     |
| --- |

Compléter la protection contre le
&#x20; retour en arrière des documents et des opérations déjà exécutées

**Limite du
verdict :** il s’agit
d’un audit de conception, pas d’une certification d’implémentation. Aucune
migration MySQL, transaction Laravel, restauration réelle ou opération
transporteur n’a été exécutée pendant cet audit.

**B. Compréhension du modèle**

**Architecture et sources de vérité**

|      |
| ---- |

**Domaine**

|      |
| ---- |

**Source de
&#x20;  vérité retenue**

|     |
| --- |

Utilisateurs, propriété, membres,
&#x20; permissions, abonnements

|     |
| --- |

BDD centrale

|     |
| --- |

Identité légale et règles
&#x20; centrales versionnées

|     |
| --- |

Central ; snapshots historiques
&#x20; dans les opérations

|     |
| --- |

Catalogue et disponibilité d’une
&#x20; boutique

|     |
| --- |

BDD de cette boutique

|     |
| --- |

Quantités de stock

|     |
| --- |

Journal mouvements_stock ; compteurs matérialisés
&#x20; vérifiables

|     |
| --- |

Contenu commercial d’une commande

|     |
| --- |

Révision immuable et ses lignes

|     |
| --- |

Accord client

|     |
| --- |

Contrat rattaché à une révision
&#x20; exacte

|     |
| --- |

Contenu expédié

|     |
| --- |

Révision liée à l’unique livraison
&#x20; de la commande

|     |
| --- |

Encaissement client

|     |
| --- |

Écritures d’encaissement vérifiées

|     |
| --- |

Argent reçu du transporteur

|     |
| --- |

Bordereaux rapprochés et leurs
&#x20; ventilations

|     |
| --- |

Frais transporteur

|     |
| --- |

Frais constatés ; leurs
&#x20; allocations ne constituent pas de nouvelles charges

|     |
| --- |

Documents fiscaux

|     |
| --- |

Snapshots des factures/avoirs émis

|     |
| --- |

État transporteur

|     |
| --- |

Événements importés et interprétés
&#x20; par un adaptateur ; pas simple copie aveugle du dernier statut

Le parcours
retenu est bien : checkout sans réservation → commande à confirmer → accord
téléphonique sur une révision précise → réservation atomique → validation
transporteur distincte de la remise physique → sortie de stock lors de la
remise → livraison ou retour → rapprochement financier.

Un échange
après expédition devient une **nouvelle commande liée**, ce qui préserve la
règle d’un colis maximum par commande.

**Décisions que je conserve**

- **BDD distincte par boutique :** cohérente avec la restauration      individuelle et l’isolation recherchée. Elle ne dispense pas d’isoler les      jobs, fichiers, caches et autorisations. 
- **Variante standard pour un      produit simple :** évite      deux systèmes de prix et de stock. 
- **Snapshots historiques :** duplication justifiée ; une      commande ne doit pas changer avec le catalogue. 
- **Compteurs de stock matérialisés      :**      acceptable puisqu’un journal permet leur reconstruction et qu’ils sont      écrits atomiquement. 
- **Factures et avoirs dans une      même table typée :** valide ; une seconde table boutique avoirs n’est pas nécessaire. 
- **Réservation à la confirmation :** choix métier cohérent      techniquement, sous réserve de la qualification contractuelle du checkout.      
- **Propriétaire immuable :** restriction explicite du MVP,      pas défaut relationnel. Une transmission future de boutique nécessiterait      une procédure nouvelle. 
- **Absence de transaction      distribuée central–tenant :** choix raisonnable avec intentions durables,      déduplication et rapprochement. 
- **UUID en CHAR(36) :** préférence physique défendable      au démarrage ; passer immédiatement en BINARY(16) n’est pas une correction fonctionnelle      indispensable. 

**C. Registre des problèmes**

Les
problèmes ci-dessous distinguent les contradictions démontrables des risques
dépendant d’une future implémentation. Une relation insuffisamment contrainte
n’est pas une preuve qu’un code inexistant contient déjà une faille.

**AUD-01 —
L’identité physique d’une variante doit devenir immuable après sa première
utilisation**

**Gravité :** majeure — **Nature :** risque
conditionnel d’intégrité stock

**Emplacement
:** T2 variantes_produits, T3 variantes_valeurs, valeurs_options, catalogue, stock, commandes, retours et imports.

**Problème**

variantes_produits.produit_id est immuable, mais cela ne suffit
pas à garantir que la signification physique de la variante reste identique
dans le temps.

La
composition d’une variante est portée notamment par :

variantes_valeurs

valeurs_options

Une variante
peut par exemple représenter :

VAR-123

→ chaussure

→ couleur noire

→ taille 40

Si les
relations définissant cette composition peuvent être modifiées après
utilisation, le même UUID pourrait ensuite représenter :

VAR-123

→ chaussure

→ couleur noire

→ taille 41

L’identité
technique resterait identique alors que l’article physique aurait changé.

---

**Scénario**

État initial
:

VAR-123 = Nike noire taille 40

Une commande
utilise cette variante :

commande CMD-100

→ VAR-123

→ taille 40

La paire est
ensuite expédiée.

Après
l’expédition, la composition de VAR-123 est
modifiée :

ancienne composition :

taille 40

nouvelle composition :

taille 41

Le client
retourne ensuite la paire initialement expédiée.

Le retour
référence toujours :

VAR-123

Le stock
reçoit donc :

+1 VAR-123

Mais VAR-123 signifie maintenant taille 41.

Le système
indique alors :

+1 taille 41

alors que le
stock physique reçu contient :

+1 taille 40

Les
quantités sont mathématiquement correctes, mais le stock devient matériellement
faux.

Le snapshot
de commande permet encore de savoir que le client avait reçu une taille 40,
mais il ne protège pas la destination du mouvement de retour.

---

**Correction retenue**

Dès qu’une
variante connaît sa **première utilisation métier**, les dimensions
définissant son identité physique deviennent immuables.

La première
utilisation comprend au minimum :

premier mouvement_stock

OU

première reservation_stock

OU

première présence dans articles_commande

À partir de
cet instant, il devient interdit de modifier la composition physique de la
variante.

Exemple :

VAR-123 = taille 40

déjà
utilisée.

Cette
opération doit être interdite :

VAR-123

taille 40

→ taille 41

La taille 41
doit devenir une nouvelle variante :

VAR-123 = taille 40

VAR-456 = taille 41

avec un UUID
distinct.

---

**État d’utilisation de la variante**

Prévoir
explicitement dans variantes_produits un marqueur permettant de savoir si
l’identité physique a déjà été engagée.

Par exemple
:

utilisee_at DATETIME(6) NULL

Signification
:

utilisee_at IS NULL

→ composition physique encore modifiable

utilisee_at IS NOT NULL

→ composition physique immuable

Le champ
doit être renseigné lors du premier événement métier qui engage la variante.

Exemple :

première réservation :

utilisee_at = NOW()

ou :

premier mouvement de stock :

utilisee_at = NOW()

ou :

première ligne historique de commande :

utilisee_at = NOW()

Le premier
événement rencontré fixe définitivement la variante.

---

**Éléments devenant immuables**

Après utilisee_at, interdire toute modification pouvant changer
l’article physique réellement stocké.

Cela inclut
notamment :

produit_id

composition variantes_valeurs

option_id associée

valeur_option_id associée

suppression d'une dimension physique

ajout d'une nouvelle dimension physique

remplacement d'une valeur d'option par une autre
valeur

Exemple
interdit :

taille = 40

→ taille = 41

Exemple
interdit :

couleur = noir

→ couleur = blanc

si la
couleur distingue réellement des articles physiques différents en stock.

Exemple
interdit :

taille 40 + noir

→ taille 40 + noir + matière cuir

si matière devient une dimension permettant de distinguer les
unités physiques.

---

**Corrections éditoriales autorisées**

Il faut
distinguer :

changement de signification physique

et :

simple correction de présentation

Une
correction purement éditoriale peut rester autorisée si elle ne change pas la
signification réelle de l’option.

Exemples :

"Noire"

→ "Noir"

ou :

"Taille 40 "

→ "Taille 40"

ou :

"Bleue"

→ "Bleu"

si la valeur
représente toujours exactement le même article physique.

En revanche
:

"40"

→ "41"

n’est jamais
une correction éditoriale.

C’est une
nouvelle variante physique.

---

**Valeurs d’options**

Une
attention particulière doit être portée à valeurs_options.

Si une
valeur est déjà utilisée par des variantes historiques, son changement ne doit
pas transformer rétroactivement leur sens.

Exemple :

valeur_option :

id = VAL-40

nom = "40"

Il est
interdit de faire :

VAL-40.nom = "41"

si cette
valeur représente réellement la taille.

Cela ferait
indirectement apparaître toutes les variantes historiques utilisant VAL-40 comme des tailles 41.

Pour une
nouvelle taille :

VAL-40 = taille 40

VAL-41 = taille 41

La nouvelle
valeur reçoit donc son propre identifiant.

---

**Signature de variante**

La signature
de variante doit continuer à être recalculée et validée depuis sa composition.

Cependant,
après première utilisation :

signature physique

→ immuable

Une
tentative de changement de composition produisant une signature différente doit
être refusée.

Exemple :

signature actuelle :

taille=40|couleur=noir

Tentative :

taille=41|couleur=noir

Résultat :

REFUS

La signature
historique ne doit jamais être remplacée.

---

**Archivage**

Une variante
déjà utilisée peut être :

désactivée

archivée

masquée du catalogue

rendue non vendable

mais elle ne
doit pas être réutilisée pour représenter un autre article physique.

Exemple :

VAR-123 = taille 40

La taille 40
n’est plus vendue.

Correct :

VAR-123

→ archivee =
true

Incorrect :

VAR-123

ancienne taille 40

→ devient taille 41

L’archivage
conserve l’identité historique.

---

**Retour**

Tout retour
doit continuer à pointer vers la variante historique réellement expédiée.

Exemple :

livraison

→ VAR-123

→ taille 40

Le retour
doit produire :

retour

→ VAR-123

et le
mouvement de stock correspondant doit alimenter uniquement :

VAR-123

Cette règle
garantit que la paire taille 40 revenue physiquement ne peut jamais augmenter
le stock d’une taille 41.

---

**Imports**

Les imports
catalogue ne doivent pas pouvoir contourner cette règle.

Si un
fichier d’import tente :

VAR-123 actuellement taille 40

vers :

VAR-123 taille 41

et que VAR-123.utilisee_at IS NOT NULL :

import refusé

Le système
doit demander :

création d'une nouvelle variante

et non la
mutation de la variante existante.

---

**Concurrence**

Le
verrouillage doit empêcher la course suivante :

Transaction A

→ transforme taille 40 en taille 41

pendant que
:

Transaction B

→ utilise pour la première fois la taille 40 dans une
commande

La
modification de composition et la première utilisation doivent donc verrouiller
la même ligne :

variantes_produits

avec :

SELECT id,
utilisee_at

FROM
variantes_produits

WHERE id =
\:variante_id

FOR UPDATE;

**Modification de variante**

Sous verrou
:

1\. lire
utilisee_at

2\. si
utilisee_at IS NOT NULL

   et changement physique demandé

   → REFUS

3\. sinon modifier composition

4\. recalculer signature

5\. COMMIT

**Première utilisation**

Sous le même
verrou :

1\. verrouiller variante

2\. vérifier sa composition/signature

3\. si utilisee_at IS NULL

   → renseigner
utilisee_at

4\. créer réservation / mouvement / ligne historique

5\. COMMIT

Ainsi, une
mutation et un premier usage concurrents ne peuvent pas tous les deux réussir
sur des significations différentes.

---

**Protection applicative et BDD**

La règle
doit être protégée à plusieurs niveaux.

**Service Laravel**

Toute
modification de composition doit passer par un service vérifiant :

utilisee_at

avant
autorisation.

**BDD**

Les
modifications directes sensibles doivent également être protégées autant que
possible.

En
particulier, les modifications de :

variantes_valeurs

doivent être
refusées lorsque la variante parente est déjà utilisée.

Cette
protection peut nécessiter :

trigger BDD

ou une autre
protection transactionnelle centralisée garantissant qu’un script ou une
importation directe ne puisse contourner la règle.

La
protection applicative seule n’est pas suffisante pour cet invariant de stock.

---

**Règle finale**

variante jamais utilisée

→ composition physique modifiable

première réservation

OU premier mouvement

OU première ligne de commande historique

→ variante devient utilisée

variante utilisée

→ identité physique immuable

nouvelle taille / couleur / dimension physique

→ nouvelle variante

→ nouvel UUID

correction orthographique sans changement de sens

→ autorisée

variante devenue obsolète

→ archivage

→ jamais recyclage de son UUID

---

**Tests**

**Test 1 — Modification avant utilisation**

Créer :

VAR-123 = taille 40

utilisee_at = NULL

Modifier en
taille 41 avant toute utilisation.

Résultat
attendu :

accepté

---

**Test 2 — Modification après commande**

Créer :

VAR-123 = taille 40

L’utiliser
dans une commande.

Puis tenter
:

taille 40

→ taille 41

Résultat
attendu :

REFUS

---

**Test 3 — Modification après réservation**

Créer une
réservation sur VAR-123.

Puis tenter
de changer sa composition.

Résultat
attendu :

REFUS

---

**Test 4 — Modification après mouvement de stock**

Créer un
mouvement de stock pour VAR-123.

Puis tenter
de transformer sa composition.

Résultat
attendu :

REFUS

---

**Test 5 — Retour historique**

Expédier :

VAR-123 = taille 40

Puis
archiver la variante.

Le client
retourne ensuite le produit.

Résultat
attendu :

retour → VAR-123

stock/quarantaine → VAR-123

La variante
historique reste utilisable pour les opérations de retour même si elle n’est
plus vendable.

---

**Test 6 — Nouvelle taille**

Après
utilisation de :

VAR-123 = taille 40

créer :

VAR-456 = taille 41

Résultat
attendu :

deux UUID distincts

deux stocks distincts

---

**Test 7 — Concurrence**

Transaction
A tente de modifier :

VAR-123

40 → 41

au même
moment où la transaction B réalise la première réservation de VAR-123.

Les deux
transactions doivent verrouiller la même variante.

Résultat
attendu :

un seul ordre sérialisé

Si la
première utilisation gagne :

modification refusée

Si la
modification gagne avant toute utilisation :

nouvelle composition enregistrée

puis première utilisation sur cette composition

Il ne doit
jamais être possible que l’historique commercial considère VAR-123 comme taille 40 pendant que le stock courant la
considère comme taille 41.

---

**AUD-02 — Une
correction de frais déjà payés doit distinguer charge corrigée, créance et flux
bancaire réel**

**Gravité :** majeure — **Nature :** ambiguïté
financière avec scénario incomplet

**Emplacement
:** T13 bordereaux_reversement, T16 reglements_frais_transporteur, T17 indemnisations_transporteur, §10.2.

**Problème**

Le modèle
distingue déjà les frais transporteur, les règlements et les bordereaux
rapprochés avec des flux réels.

Cependant,
il ne représente pas encore suffisamment clairement le cas suivant :

un frais a réellement été payé

↓

le transporteur reconnaît ensuite que le montant dû
était inférieur

↓

le remboursement n'est pas encore reçu

Il faut
distinguer explicitement :

correction de la charge

réaffectation d'un paiement déjà réalisé

créance sur le transporteur

remboursement ou compensation réellement réalisé

Ces
événements ne doivent jamais être confondus.

---

**Scénario**

Le
transporteur facture initialement :

650 DZD

Le
commerçant paie réellement :

650 DZD

La
trésorerie cumulée est donc :

-650 DZD

Plus tard,
le transporteur reconnaît que le vrai frais devait être :

600 DZD

Mais il ne
rembourse pas immédiatement les :

50 DZD

À cet
instant, la situation correcte doit être :

charge transporteur = 600 DZD

trésorerie cumulée = -650 DZD

créance sur transporteur = 50 DZD

Il ne faut
surtout pas enregistrer immédiatement :

+50 DZD de trésorerie

car cet
argent n'a pas encore été reçu.

---

**1. Correction de la charge**

La charge
initiale était :

+650 DZD

Après
reconnaissance de l'erreur, la charge économique correcte devient :

600 DZD

L'historique
ne doit pas être réécrit silencieusement.

La
correction doit être représentée par une écriture compensatoire.

Exemple :

frais initial :

+650

puis :

correction :

-50

Résultat :

charge nette = 600 DZD

---

**2. Paiement historique**

Le paiement
réellement effectué reste :

650 DZD

Il ne doit
pas être transformé rétroactivement en :

600 DZD

Le
commerçant a réellement sorti :

650 DZD

La
trésorerie historique reste donc :

-650 DZD

jusqu'à ce
qu'un remboursement ou une compensation réelle intervienne.

---

**3. Créance sur le transporteur**

La
différence entre :

montant payé = 650

et :

charge corrigée = 600

devient
immédiatement une créance :

créance transporteur = 50 DZD

Prévoir un
support dédié, par exemple :

creances_transporteur

avec au
minimum :

id

prestataire_id

frais_transporteur_id nullable

reglement_frais_id nullable

montant_initial

montant_restant

motif

statut

cree_at

soldee_at
nullable

Statuts
possibles :

ouverte

partiellement_compensee

remboursee

compensee

annulee

Exemple :

prestataire = Ecotrack

montant_initial = 50

montant_restant = 50

motif = correction_frais

statut = ouverte

---

**4. Réaffectation du paiement**

Après
correction, le paiement initial de 650 DZD peut être
réaffecté comptablement.

Exemple :

paiement P1 = 650

Initialement
:

allocation P1 → frais F1 = 650

Après
correction :

allocation P1 → frais F1 = 600

Les :

50 DZD

restants ne
représentent pas un remboursement bancaire.

Ils
représentent :

une créance sur le transporteur

La
réaffectation est donc uniquement comptable.

---

**5. Réaffectation sans cash**

Une
réaffectation ne doit jamais modifier :

trésorerie

net bancaire

encaissements réels

décaissements réels

Règle :

réaffectation comptable

≠ mouvement bancaire

Donc :

contrepassation d'allocation

ne signifie
pas automatiquement :

remboursement reçu

---

**6. Remboursement bancaire réel**

Supposons
que le mois suivant le transporteur rembourse réellement :

50 DZD

À cet
instant seulement :

entrée de trésorerie = +50 DZD

La créance
est alors apurée :

créance initiale = 50

remboursement = 50

reste = 0

Résultat
final :

charge transporteur = 600

cash sorti initialement = -650

remboursement reçu = +50

trésorerie nette = -600

créance restante = 0

---

**7. Compensation sur un futur montant dû**

Le
transporteur peut également ne pas faire de remboursement bancaire.

Il peut
compenser la créance sur un futur frais.

Exemple :

créance existante = 50

Nouveau
frais :

500 DZD

Le
transporteur accepte la compensation :

500 - 50 = 450 DZD à payer réellement

Résultat :

créance consommée = 50

cash réellement payé = 450

créance restante = 0

Il n'y a
toujours aucun faux mouvement bancaire de :

+50 DZD

---

**8. Compensation partielle**

La
compensation peut être partielle.

Exemple :

créance = 50

Compensation
:

20

Résultat :

créance restante = 30

Le système
doit donc permettre plusieurs apurements pour une même créance.

---

**9. Allocations de créance**

Prévoir par
exemple :

allocations_creances_transporteur

avec :

id

creance_transporteur_id

type_apurement

reference_apurement_id

montant

created_at

type_apurement peut être :

remboursement_bancaire

compensation_frais

compensation_bordereau

autre_reglement_valide

Invariant :

SUM(allocations)

<= montant_initial

et :

montant_restant

\=

montant_initial

\-

SUM(allocations validees)

---

**10. Relation avec les indemnisations**

Une créance
provenant d'une correction de frais ne doit pas être confondue avec une
indemnisation transporteur.

Exemple
d'indemnisation :

colis perdu

→ transporteur doit 8 000 DZD

Exemple de
créance de correction :

frais payé = 650

frais corrigé = 600

→ trop-payé = 50

Les deux
représentent une somme due par le transporteur, mais leur origine est
différente.

Il faut donc
conserver explicitement :

indemnisation_transporteur

pour les
sinistres,

et :

creance_transporteur

pour les
trop-payés, corrections ou autres montants dus.

Une table
générique unique reste possible si elle possède un :

type_origine

suffisamment
explicite.

---

**11. Bordereaux et flux réels**

Un bordereau
rapproché avec un flux bancaire réel doit représenter uniquement :

argent réellement reçu

ou

argent réellement payé

Il ne faut
pas créer artificiellement :

bordereau +50 DZD

au moment de
la correction du frais si aucun remboursement n'a encore eu lieu.

La créance
doit exister indépendamment du futur mouvement bancaire.

---

**12. Situation comptable à chaque étape**

**Après paiement initial**

charge = 650

trésorerie = -650

créance = 0

**Après correction du frais**

charge = 600

trésorerie = -650

créance = 50

**Après remboursement réel**

charge = 600

trésorerie = -600

créance = 0

Aucun
encaissement fictif ne doit apparaître entre la correction et le remboursement.

---

**Invariants**

Pour chaque
créance :

montant_initial >= 0

montant_restant >= 0

montant_restant <= montant_initial

et :

montant_restant

\=

montant_initial

\-

remboursements_valides

\-

compensations_valides

Une créance
ne doit jamais être soldée économiquement deux fois.

---

**Concurrence**

Lors d'un
remboursement ou d'une compensation :

SELECT \*

FROM
creances_transporteur

WHERE id =
\:id

FOR UPDATE;

Puis
recalculer :

montant_restant

avant de
créer une nouvelle allocation.

Cela empêche
deux workers de consommer simultanément la même créance.

---

**Corrections**

Une erreur
sur une créance existante ne doit pas être corrigée par réécriture silencieuse.

Utiliser :

contrepassation

\+

nouvelle écriture correcte

selon le
même principe que les autres journaux financiers.

---

**Règle finale**

frais payé = 650

puis :

frais corrigé = 600

doit
produire immédiatement :

charge = 600

trésorerie = -650

créance transporteur = 50

et jamais :

trésorerie = -600

tant que le
transporteur n'a pas réellement remboursé ou compensé les 50 DZD.

Ensuite :

remboursement réel

OU

compensation réelle

apure la
créance.

---

**Vérifications**

**Test 1 — Correction sans remboursement**

frais initial = 650

paiement réel = 650

frais corrigé = 600

Résultat
attendu :

charge = 600

trésorerie = -650

créance = 50

---

**Test 2 — Remboursement réel**

Créance :

50

Remboursement
reçu :

50

Résultat
attendu :

entrée trésorerie = +50

créance restante = 0

---

**Test 3 — Compensation complète**

Créance :

50

Nouveau
frais :

500

Compensation
:

50

Cash
réellement payé :

450

Résultat :

créance = 0

---

**Test 4 — Compensation partielle**

Créance :

50

Compensation
:

20

Résultat :

créance restante = 30

---

**Test 5 — Double consommation concurrente**

Deux workers
tentent d'utiliser simultanément la même créance de :

50

Résultat
attendu :

une seule allocation possible

La seconde
transaction doit constater :

montant_restant = 0

ou le
montant réellement restant.

---

**Test 6 — Réaffectation sans mouvement bancaire**

Modifier
l'allocation d'un paiement déjà effectué après correction du frais.

Résultat
attendu :

trésorerie inchangée

net bancaire inchangé

créance créée ou ajustée

Aucun
remboursement fictif ne doit être généré.

---

**AUD-03 — Une obligation de facturation peut être
associée à un document de la mauvaise révision ou du mauvais type**

**Gravité :
majeur — Nature : risque conditionnel ; insuffisance des contraintes proposées
certaine.**

**Emplacement
:** T22 obligations_facturation, T17 factures.

**Preuve :** les FK proposées garantissent
séparément :

- que la révision appartient à la      commande ; 
- que la facture appartient à la      commande. 

Elles ne
garantissent pas que la facture associée correspond à **la révision, au type
de pièce et à la facture d’origine de l’obligation**.

**Scénario :** une obligation relative à R2 est
marquée émise avec une facture de R1, toutes deux appartenant à la même
commande. Les FK décrites acceptent cette association. Même possibilité entre
une obligation d’avoir et une facture ordinaire.

**Conséquence
:** le
rapprochement « obligation avec document » peut annoncer une émission terminée
alors que la pièce attendue manque.

**Correction
minimale :**

- définir précisément la révision      documentaire attendue ; 
- renforcer la FK vers la facture      avec commande, révision et type ; 
- vérifier l’égalité de la      facture d’origine pour les avoirs ; 
- interdire statut=emise sans document effectivement      émis. 

**Impacts :** worker de facturation, reprises,
émission d’avoirs et contrôle des obligations.

**Vérification
:** injections
négatives « bonne commande/mauvaise révision », « mauvais type », « mauvais
document d’origine ». Toutes doivent échouer, y compris hors interface.

---

**AUD-04 — Une
restauration ne doit jamais permettre de réutiliser un numéro de facture déjà
émis ou transmis**

**Gravité :** majeure — **Nature :** risque
conditionnel d’intégrité documentaire et fiscale.

**Emplacement
:** C12
restauration, T20 sequences_documents, T17/T19 documents et
transmissions.

**Problème**

Les numéros
de documents sont alloués dans la BDD tenant au moyen de :

sequences_documents

Une
restauration d’un backup ancien peut donc faire revenir cette séquence à une
valeur déjà utilisée après la date du backup.

L’unicité
SQL locale ne suffit pas, car une facture émise après le backup peut
disparaître de la BDD restaurée tout en ayant déjà été :

émise

générée

transmise au client

téléchargée

archivée

communiquée à un système externe

Son numéro
doit donc rester considéré comme définitivement utilisé.

---

**Scénario**

Au moment du
backup :

dernier numéro utilisé = 100

prochain numéro = 101

Après le
backup, le système émet :

FACT-101

et la
transmet au client.

La BDD
tenant contient alors :

FACT-101

prochain numéro = 102

Une
restauration remet ensuite la BDD à l’état du backup :

FACT-101 absente

prochain numéro = 101

Sans
protection supplémentaire, une nouvelle vente pourrait recevoir :

FACT-101

alors qu’une
autre facture portant ce numéro a déjà été communiquée.

Le système
aurait alors :

même tenant

même série

même type de document

même numéro

pour deux
documents différents.

---

**Conséquence**

Cette
situation peut provoquer :

collision de numérotation

documents contradictoires

perte de traçabilité

rapprochement impossible

perte de preuve

risque fiscal

La
contrainte UNIQUE de la BDD tenant restaurée ne
détecte pas nécessairement le problème, puisque la première facture peut avoir
disparu de cette BDD.

---

**Correction retenue**

**1. Registre durable hors de la BDD tenant**

Prévoir dans
la BDD centrale un registre dédié, par exemple :

registre_documents_emis

Ce registre
ne doit pas être restauré lorsqu’une seule BDD tenant est restaurée.

Il conserve
au minimum :

id

tenant_id

type_document

serie

numero_document

document_id_tenant

commande_id nullable

revision_id nullable

facture_origine_id nullable

date_emission

empreinte_document

statut_emission

date_transmission
nullable

created_at

Selon les
besoins, il peut également contenir :

stockage_document_key

version_format

cle_emission_document

---

**2. Unicité durable des numéros**

Le registre
central doit empêcher la réutilisation d’un numéro déjà émis.

Par exemple
:

UNIQUE (

    tenant_id,

   
type_document,

    serie,

   
numero_document

)

Ainsi, même
si la BDD tenant restaurée tente de réémettre :

FACT-101

le registre
central sait déjà que ce numéro a été utilisé.

La nouvelle
émission doit être refusée.

---

**3. Stockage durable du document**

Le document
final ou son snapshot immuable doit être conservé hors du périmètre de
restauration de la BDD tenant.

Par exemple
dans :

S3 / MinIO

avec une clé
telle que :

tenants/{tenant_uuid}/private/factures/{document_uuid}.pdf

ou :

tenants/{tenant_uuid}/private/avoirs/{document_uuid}.pdf

Le stockage
documentaire doit rester indépendant d’une restauration MySQL du tenant.

Lorsque
l’infrastructure le permet, utiliser :

versioning

rétention

protection contre suppression accidentelle

Object Lock / stockage immuable

Le registre
central conserve au minimum :

empreinte_document = SHA-256(...)

afin de
vérifier qu’un document retrouvé correspond bien au document émis.

---

**4. Architecture retenue**

BDD tenant

│

├── factures

├── avoirs

├── sequences_documents

└── transmissions

BDD centrale

│

└── registre_documents_emis

    └── mémoire
durable des numéros et identités déjà émis

S3 / MinIO

│

└── PDF / snapshots immuables des documents

La BDD
tenant gère donc la facturation opérationnelle courante.

Mais elle ne
doit jamais être la seule source permettant de déterminer si un numéro a déjà
été émis.

---

**5. Ordre d’émission**

Une facture
ne doit pas être considérée comme correctement émise simplement parce qu’un
numéro local a été attribué.

Le processus
doit suivre un ordre contrôlé :

1\. verrouiller la séquence tenant

2\. déterminer le numéro

3\. produire le snapshot définitif

4\. créer le document

5\. enregistrer durablement l’identité d’émission

6\. stocker le document dans S3 / MinIO

7\. autoriser ensuite sa transmission

Une facture
déjà transmise doit toujours avoir une trace durable extérieure à la seule BDD
tenant.

---

**6. Pas de fausse transaction distribuée**

La BDD
tenant, la BDD centrale et S3/MinIO ne forment pas une transaction SQL atomique
unique.

Il ne faut
donc pas supposer :

COMMIT tenant

\+

COMMIT central

\+

upload document

comme une
opération indivisible.

Le mécanisme
doit utiliser :

identifiants stables

clés idempotentes

statuts intermédiaires

reprises après crash

réconciliation

Prévoir par
exemple :

cle_emission_document

Une reprise
du worker avec la même clé doit retrouver l’émission existante au lieu de
produire une nouvelle facture.

---

**Restauration du tenant**

Lorsqu’une
BDD tenant est restaurée :

tenant.statut = suspendu_restauration

Pendant
cette phase :

nouvelle facture = interdite

nouvel avoir = interdit

nouvelle allocation de numéro = interdite

jusqu’au
rapprochement.

---

**7. Rapprochement après restauration**

Le processus
doit :

1\. identifier le backup restauré

2\. récupérer son point_reconciliation_central

3\. lire registre_documents_emis

4\. identifier les documents émis après le backup

5\. comparer avec les factures/avoirs présents dans la
BDD restaurée

6\. retrouver les documents dans le stockage S3/MinIO

7\. rapprocher les transmissions

8\. reconstruire les références locales nécessaires

9\. recalculer une position sûre de sequences_documents

10\. vérifier les invariants

11\. réactiver le tenant seulement après validation

---

**8. Reconstruction de la séquence**

Exemple :

La BDD
restaurée indique :

prochain numéro = 101

Mais le
registre central contient :

FACT-101

FACT-102

FACT-103

Le système
ne doit surtout pas reprendre à :

101

Il doit
reconstruire une position sûre.

Par exemple
:

prochain numéro autorisé = 104

sous réserve
des règles fiscales de séquence validées.

Le registre
durable devient donc une borne de sécurité minimale.

---

**9. Numéro déjà émis**

Règle
obligatoire :

numéro connu dans registre_documents_emis

→ numéro définitivement indisponible pour une nouvelle
pièce

Même si :

la facture locale a disparu

la transmission locale a disparu

la séquence restaurée propose ce numéro

il ne doit
jamais être réutilisé.

---

**10. Transmission**

transmissions doit rester liée au document
réellement émis.

Une nouvelle
tentative d’envoi de :

FACT-101

doit
retransmettre exactement :

FACT-101

et non créer
une nouvelle pièce.

Un échec
d’email ou d’API ne doit jamais provoquer automatiquement une nouvelle
allocation de numéro.

---

**11. Factures et avoirs**

La
protection doit couvrir tous les documents utilisant une séquence contrôlée,
notamment :

factures

avoirs

et tout
autre document fiscal ajouté ultérieurement.

Si plusieurs
séries existent, chaque série doit être protégée séparément.

Exemple :

FACT-2026-001

AVOIR-2026-001

peuvent
appartenir à deux séries distinctes.

Mais un
numéro déjà utilisé dans sa propre série reste définitivement consommé.

---

**12. Nouvelle série après incident**

Une
restauration ne doit pas automatiquement provoquer la création d’une nouvelle
série.

Si une
nouvelle série devient nécessaire pour des raisons fiscales ou comptables, elle
doit suivre une procédure explicitement validée.

Exemple :

ancienne série :

FACT-2026-...

et
éventuellement :

nouvelle série :

FACT-R2026-...

uniquement
si cette pratique est comptablement et fiscalement autorisée.

La création
d’une nouvelle série ne doit pas servir à contourner une impossibilité de
reconstruire correctement l’historique.

---

**13. Rapprochement impossible**

Si le
système ne peut pas déterminer avec suffisamment de certitude :

quels numéros ont été émis

quels documents ont été transmis

quelles identités sont déjà utilisées

alors :

emission_documents = bloquee

Le système
ne doit jamais :

deviner le prochain numéro

ou reprendre
automatiquement depuis la séquence restaurée.

Une
intervention contrôlée est nécessaire.

---

**Restauration centrale**

Le registre
central protège correctement une restauration isolée d’une BDD tenant
uniquement si la BDD centrale reste plus récente.

Il faut donc
également prévoir le cas où :

BDD centrale

est
elle-même restaurée.

---

**14. Protection du registre central**

Prévoir pour
la BDD centrale :

backups indépendants

PITR

historique de restauration

réconciliation

et conserver
les documents physiques dans un stockage durable indépendant.

Ainsi, même
si le central est restauré, les documents présents dans S3/MinIO et leurs
empreintes peuvent participer à la reconstruction de l’historique.

---

**15. Facturation du SaaS**

Le même
principe s’applique aux factures centrales :

SaaS → commerçant

Si la BDD
centrale est restaurée à un instant antérieur, une facture SaaS déjà transmise
ne doit jamais pouvoir recevoir une seconde pièce portant le même numéro.

La
facturation SaaS doit donc bénéficier du même principe de registre durable et
de rapprochement après restauration.

---

**PITR**

Le Point-In-Time Recovery reste utile.

Il réduit la
quantité de données perdues lors d’un incident.

Mais :

PITR

≠ protection absolue contre la réutilisation d'un
numéro

car une
restauration peut volontairement cibler un instant antérieur à l’émission d’un
document.

Le
rapprochement documentaire reste donc obligatoire après toute restauration vers
le passé.

---

**Autres opérations
irréversibles**

Le même
raisonnement doit être appliqué aux identifiants d’opérations ayant déjà
produit un effet externe irréversible.

Par exemple
:

identifiant d’émission documentaire

référence de transmission externe

référence de paiement réellement exécuté

référence de remboursement réellement exécuté

référence d’opération transporteur déjà créée

Une
restauration locale ne doit pas permettre de réutiliser ces identités pour une
nouvelle opération différente.

---

**Règles finales**

document jamais émis

→ traitement selon le workflow d’émission

document émis

→ numéro définitivement considéré utilisé

document transmis

→ même numéro et même snapshot doivent rester
retrouvables

restauration tenant

→ tenant suspendu

→ facturation bloquée

→ rapprochement obligatoire

numéro présent dans registre_documents_emis

→ jamais réutilisé

séquence restaurée < historique durable

→ séquence reconstruite

état impossible à reconstruire avec certitude

→ émission bloquée

---

**Vérifications**

**Test 1 — Facture émise après backup**

Créer un
backup avec :

prochain numéro = 101

Puis émettre
et transmettre :

FACT-101

Restaurer le
backup.

Résultat
attendu :

FACT-101 ne peut jamais être attribuée à une nouvelle
facture

---

**Test 2 — Plusieurs documents après backup**

Après backup
:

FACT-101

FACT-102

FACT-103

sont émises.

Puis
restauration.

La BDD
restaurée indique :

prochain numéro = 101

Le registre
central indique :

101

102

103

Résultat
attendu :

aucun numéro réutilisé

séquence repositionnée sur une valeur sûre

---

**Test 3 — Facture transmise mais disparue localement**

Émettre et
transmettre :

FACT-105

Restaurer un
backup antérieur.

Résultat :

registre central

→ FACT-105 connue

S3 / MinIO

→ FACT-105 retrouvable

BDD tenant

→ références reconstruites ou rapprochées

Une nouvelle
FACT-105 doit être impossible.

---

**Test 4 — Transmission échouée**

Créer :

FACT-110

Le premier
email échoue.

Nouvelle
tentative :

retransmission de FACT-110

Résultat
interdit :

création de FACT-111 juste parce que l’email précédent
a échoué

sauf si un
nouveau document distinct est réellement nécessaire pour une autre raison
métier.

---

**Test 5 — Rapprochement impossible**

Simuler :

séquence locale ancienne

registre central indisponible

stockage documentaire incomplet

Résultat
attendu :

émission bloquée

et non :

reprise automatique de la facturation

---

**Test 6 — Concurrence après réactivation**

Deux workers
tentent d’émettre simultanément les premières factures après restauration.

Résultat
attendu :

verrou de séquence

\+

contrôle registre durable

\+

unicité

garantissent
:

deux numéros distincts

aucune collision

aucune réutilisation

---

**Principe final**

BDD tenant

→ état opérationnel courant

BDD centrale

→ registre durable des identités documentaires

S3 / MinIO

→ preuve documentaire durable

La
restauration d’une BDD tenant ne doit donc jamais suffire à faire oublier qu’un
numéro a déjà été émis ou communiqué.

---

**AUD-05 — La
portée des exceptions de permission doit être protégée aussi strictement que
celle des rôles**

**Gravité :** majeure — **Nature :** ambiguïté
de sécurité

**Emplacement
:** C2 roles_permissions, C3 exceptions_permissions, §7.

**Problème**

Le modèle
protège déjà correctement l’association entre rôles et permissions.

La règle
existante est :

rôle tenant

→ permission tenant uniquement

rôle plateforme

→ permission plateforme uniquement

Cette règle
empêche par exemple qu’un rôle de boutique reçoive une permission réservée à
l’administration globale du SaaS.

Cependant,
les exceptions individuelles utilisent notamment :

permission_id

tenant_id NULLABLE

sans définir
suffisamment explicitement la relation obligatoire entre :

permissions.portee

et :

exceptions_permissions.tenant_id

Les
exceptions doivent respecter exactement la même séparation tenant / plateforme que les rôles.

---

**Risque**

Sans règle
explicite, il serait techniquement possible de créer une exception comme :

permission :

utilisateurs.usurper_identite

portee = plateforme

tenant_id = Boutique-A

ou l’inverse
:

permission :

produits.modifier

portee = tenant

tenant_id = NULL

La
signification de ces exceptions deviendrait alors dépendante de
l’interprétation du moteur d’autorisation.

Cela peut
produire :

droits incohérents

ou, dans le
pire cas :

élévation de privilèges

si une
exception est utilisée sans vérifier correctement sa portée.

---

**Correction retenue**

**1. Exception sur permission tenant**

Si :

permission.portee = tenant

alors :

tenant_id IS NOT NULL

est
obligatoire.

Exemple
correct :

permission = produits.modifier

portee = tenant

tenant_id = Boutique-A

Résultat :

AUTORISÉ

L’exception
signifie :

cet utilisateur possède produits.modifier

uniquement dans Boutique-A

Exemple
interdit :

permission = produits.modifier

portee = tenant

tenant_id = NULL

Résultat :

REFUS

Une
permission tenant ne doit jamais exister sous forme d’exception globale sans
boutique.

---

**2. Exception sur permission plateforme**

Si :

permission.portee = plateforme

alors :

tenant_id IS NULL

est
obligatoire.

Exemple
correct :

permission = utilisateurs.usurper_identite

portee = plateforme

tenant_id = NULL

Résultat
possible :

AUTORISÉ

uniquement
si les autres règles d’administration et de délégation l’autorisent.

Exemple
interdit :

permission = utilisateurs.usurper_identite

portee = plateforme

tenant_id = Boutique-A

Résultat :

REFUS

Une
permission plateforme ne doit jamais être transformée en permission locale
d’une boutique via exceptions_permissions.

---

**3. Règle structurelle**

La règle
obligatoire devient :

permission.portee
\= tenant

↔ tenant_id
IS NOT NULL

et :

permission.portee = plateforme

↔ tenant_id IS NULL

Ces deux
combinaisons sont les seules autorisées.

Les
combinaisons suivantes sont interdites :

permission tenant

\+ tenant_id NULL

et :

permission plateforme

\+ tenant_id renseigné

---

**4. Protection applicative**

Le service
Laravel responsable de la création ou modification d’une exception doit charger
la permission puis vérifier :

if permission.portee == tenant

    tenant_id
obligatoire

et :

if permission.portee == plateforme

    tenant_id
obligatoirement NULL

Toute
exception incohérente doit être refusée avant insertion.

---

**5. Protection BDD**

La règle ne
doit pas dépendre uniquement du service Laravel.

Une
insertion directe, une migration, un import ou un script d’administration ne
doit pas pouvoir contourner la séparation des portées.

Prévoir une
protection BDD, par exemple avec un trigger sur :

exceptions_permissions

qui vérifie
la portée de :

permission_id

avant INSERT ou UPDATE.

Pseudo-règle
:

SI permission.portee = tenant

ET tenant_id IS NULL

→ REFUS

SI permission.portee = plateforme

ET tenant_id IS NOT NULL

→ REFUS

---

**6. Appartenance au tenant**

Une
exception tenant ne peut être utilisée que dans une boutique à laquelle
l’utilisateur appartient selon les règles d’appartenance existantes.

Exemple :

user = U1

permission = produits.modifier

tenant_id = Boutique-A

L’exception
n’est valide que si :

U1 possède une appartenance valide à Boutique-A

selon les
règles métier déjà définies.

Une
exception ne doit donc jamais permettre de contourner :

l’appartenance au tenant

ou :

l’état de cette appartenance

---

**7. L’exception ne contourne
pas le plan**

Les règles
déjà présentes restent applicables.

Une
exception ne doit pas permettre d’activer une fonctionnalité interdite par le
plan lorsque le modèle prévoit que le plan reste prioritaire.

Exemple :

permission = analytics.premium

mais :

plan actuel

→ fonctionnalité non disponible

Une
exception individuelle ne doit pas automatiquement contourner cette
restriction.

Les
priorités d’autorisation existantes restent donc applicables.

---

**8. Délégation des permissions**

Le fait
qu’un administrateur possède une permission ne signifie pas nécessairement
qu’il peut l’accorder à quelqu’un d’autre.

Il faut
distinguer :

posséder une permission

et :

pouvoir déléguer cette permission

Lorsqu’un
utilisateur crée une exception pour un autre utilisateur, le système doit donc
vérifier :

l’auteur peut-il déléguer cette permission ?

---

**Exemple**

Un
administrateur possède :

commandes.rembourser

mais son
périmètre de délégation ne lui permet pas d’accorder ce droit.

Il tente :

exceptions_permissions :

user_cible = Employé-B

permission = commandes.rembourser

tenant_id = Boutique-A

Même si
l’administrateur possède lui-même cette permission :

création exception

→ REFUS

s’il n’a pas
le droit de la déléguer.

---

**Règle**

permission détenue

≠ permission délégable

Toute
création, modification ou réactivation d’une exception doit vérifier le droit
de délégation de l’auteur.

Cette règle
s’applique également aux exceptions temporaires.

---

**9. Auto-attribution**

Un
administrateur ne doit pas pouvoir contourner son propre périmètre en créant
une exception pour lui-même.

Exemple :

Administrateur A

ne peut pas
déléguer :

tenants.suspendre

Il tente :

exception :

user_id = A

permission = tenants.suspendre

Résultat :

REFUS

Le contrôle
de délégation doit donc s’appliquer même lorsque :

auteur_id = user_id cible

---

**10. Permissions d’assistance**

Les
permissions particulières utilisées par l’équipe plateforme pour l’assistance
ne doivent pas être représentées comme des exceptions tenant artificielles.

Par exemple
:

utilisateurs.usurper_identite

support.acceder_tenant

restent :

portee = plateforme

tenant_id = NULL

Elles
peuvent ensuite comporter leurs propres contrôles :

motif d’assistance

durée

journalisation

autorisation

session temporaire

mais elles
restent des permissions plateforme.

---

**11. Restrictions
administratives**

Les
restrictions administratives restent distinctes des exceptions positives de
permission.

Exemples :

compte suspendu

tenant suspendu

restriction de sécurité

permission explicitement interdite

Ces règles
ne doivent pas être transformées en simples exceptions_permissions.

L’ordre de
priorité défini pour les interdictions reste applicable.

Une
permission accordée par exception ne doit jamais contourner une restriction
prioritaire.

---

**12. Cache des permissions**

La
modification d’une exception doit invalider les caches d’autorisation
concernés.

Lorsqu’une
exception est :

créée

modifiée

révoquée

expirée

le cache de
l’utilisateur concerné doit être invalidé pour le contexte correspondant.

Exemple :

exception tenant Boutique-A révoquée

doit
invalider au minimum :

cache permissions

user U1

tenant Boutique-A

Une ancienne
autorisation ne doit pas survivre dans le cache après révocation.

---

**13. Jobs et sessions longues**

Les jobs
sensibles ne doivent pas considérer qu’une exception reste valide simplement
parce qu’elle existait au moment où le job a été créé.

Avant une
action sensible, le droit doit être recalculé selon les règles déjà retenues.

Exemple :

exception accordée

→ job créé

→ exception révoquée

→ job démarre

Résultat :

job doit vérifier à nouveau le droit

et refuser
l’action si l’autorisation n’existe plus.

La même
logique s’applique aux sessions d’assistance.

---

**Règle finale**

permission tenant

→ exception avec tenant_id obligatoire

permission plateforme

→ exception avec tenant_id NULL obligatoire

exception tenant

→ appartenance tenant obligatoire

exception

→ ne contourne pas les restrictions prioritaires

auteur

→ doit avoir le droit de déléguer la permission

auto-attribution

→ soumise au même contrôle de délégation

permissions assistance

→ restent des permissions plateforme distinctes

création / révocation exception

→ invalidation du cache concerné

---

**Vérifications**

**Test 1 — Permission tenant sans tenant**

Tenter :

permission = produits.modifier

portee = tenant

tenant_id = NULL

Résultat
attendu :

INSERT REFUSÉ

y compris en
insertion SQL directe.

---

**Test 2 — Permission plateforme avec tenant**

Tenter :

permission = utilisateurs.usurper_identite

portee = plateforme

tenant_id = Boutique-A

Résultat
attendu :

INSERT REFUSÉ

---

**Test 3 — Permission tenant correcte**

Tenter :

permission = produits.modifier

portee = tenant

tenant_id = Boutique-A

avec
utilisateur membre valide de Boutique-A.

Résultat :

ACCEPTÉ

si les
autres règles sont respectées.

---

**Test 4 — Utilisateur non membre**

Créer :

permission tenant

tenant_id = Boutique-A

user = U1

alors que U1
n’appartient pas à Boutique-A.

Résultat
attendu :

REFUS

---

**Test 5 — Permission non délégable**

Un
administrateur possède :

commandes.rembourser

mais ne peut
pas la déléguer.

Il tente de
l’attribuer à un employé via exceptions_permissions.

Résultat
attendu :

REFUS

---

**Test 6 — Auto-attribution interdite**

L’administrateur
tente de s’accorder lui-même une permission qu’il ne peut pas déléguer.

Résultat :

REFUS

---

**Test 7 — Révocation et cache**

Créer une
exception valide puis la révoquer.

Après
révocation :

cache invalidé

et :

prochaine vérification

→ permission absente

---

**Test 8 — Job après révocation**

Créer un job
pendant qu’une exception est active.

Révoquer
l’exception avant exécution du job.

Résultat
attendu :

job recalcule le droit

→ action refusée

si
l’exception n’est plus valide.

---

**Principe final**

La règle
appliquée aux rôles et aux exceptions doit être identique sur la portée :

tenant

→ uniquement contexte tenant

plateforme

→ uniquement contexte plateforme

exceptions_permissions ne doit jamais devenir un moyen
indirect de contourner les protections déjà imposées à roles_permissions.



---

**AUD-06 — La
reconnaissance économique d’un retour doit avoir un contrat de données
explicite**

**Gravité :** modérée — **Nature :** ambiguïté
métier.

**Emplacement
:** §10.3
résultat, §12 statistiques, T18 incidents, T22 documents.

**Problème**

Le modèle
distingue déjà correctement plusieurs événements :

vente

retour physique

incident

décision commerciale

avoir

remboursement

Cependant,
il indique que le revenu doit être corrigé :

à la date du retour reconnu

sans définir
précisément quel enregistrement représente ce **retour économiquement reconnu**.

Il manque
donc une source de vérité explicite contenant :

la date économique utilisée

le montant corrigé

les lignes concernées

les quantités concernées

le motif de la correction

la date à laquelle la décision a été enregistrée

Sans cette
définition, plusieurs calculs différents peuvent être considérés comme
corrects.

---

**Scénario**

Une commande
est vendue en janvier :

vente = 10 000 DZD

En février,
le colis revient physiquement.

retour reçu = février

Après
contrôle, une partie de la marchandise est manquante.

En mars, le
commerçant prend une décision commerciale :

8 000 DZD de la vente sont annulés

2 000 DZD restent économiquement conservés

En avril, le
client reçoit réellement :

remboursement = 8 000 DZD

On possède
donc quatre dates différentes :

janvier

→ vente

février

→ réception physique du retour

mars

→ reconnaissance économique de la correction

avril

→ remboursement réel

Ces quatre
événements ne doivent pas être fusionnés.

---

**Conséquence du problème actuel**

Un
développeur pourrait décider :

revenu corrigé en février

parce que le
colis a été reçu en février.

Un autre
pourrait utiliser :

revenu corrigé en mars

parce que la
décision commerciale a été prise en mars.

Un troisième
pourrait utiliser :

revenu corrigé en avril

parce que le
remboursement a été payé en avril.

Les trois
implémentations pourraient sembler compatibles avec :

retour reconnu

si ce terme
n’est pas formellement défini.

Les
statistiques mensuelles deviendraient donc incohérentes.

---

**Correction retenue**

**1. Définir un événement de reconnaissance économique**

Le système
doit disposer d’un événement métier explicite représentant :

la décision économique définitive prise sur tout ou
partie d’une vente

Cet
événement indique qu’un montant précédemment reconnu comme revenu commercial
doit désormais être :

annulé

réduit

maintenu

ou corrigé

Cet
événement devient la source de vérité des statistiques commerciales liées aux
retours.

---

**2. Une nouvelle table n’est pas obligatoire**

Il n’est pas
nécessaire de créer une nouvelle table si une structure existante peut porter
correctement cet événement.

Par exemple,
un historique correctement structuré dans :

incidents_commande

incidents_commande_details

ou un
historique métier dédié peut être utilisé.

La condition
est que l’événement possède explicitement toutes les informations nécessaires.

Si aucune
table existante ne peut garantir ces propriétés proprement, une table dédiée
peut être créée, par exemple :

reconnaissances_economiques

ou :

corrections_commerciales

Le choix
physique de table est secondaire.

Le contrat
métier, lui, est obligatoire.

---

**3. Données minimales de
l’événement**

L’événement
de reconnaissance économique doit permettre de connaître au minimum :

id

tenant_id

commande_id

revision_id

incident_id nullable

date_effet

date_enregistrement

motif

statut

created_at

Les lignes
associées doivent permettre de connaître :

article_commande_id

quantite_concernee

montant_initial_concerne

montant_annule

montant_conserve

motif_detaille nullable

Selon le
modèle retenu, ces informations peuvent être dans :

une table principale

\+

une table de lignes

---

**4. Date d’effet et date
d’enregistrement**

Il faut
distinguer :

date_effet

et :

date_enregistrement

**date_effet**

Correspond à
la période économique dans laquelle la correction commerciale doit être
comptabilisée pour les indicateurs.

Exemple :

date_effet = 2026-03-05

**date_enregistrement**

Correspond
au moment réel où le système a enregistré cette décision.

Exemple :

date_enregistrement = 2026-03-06 10:42:18

Ces dates
peuvent être identiques, mais elles n’ont pas la même signification.

---

**5. Retour physique ≠
reconnaissance économique**

La réception
physique d’un retour ne doit pas automatiquement modifier le revenu.

Exemple :

10 février

→ colis reçu

À cette date
:

stock / quarantaine

→ peuvent être affectés

mais :

revenu commercial

→ pas nécessairement corrigé

Le
commerçant doit encore pouvoir contrôler :

articles réellement reçus

quantités

état des produits

articles manquants

responsabilité éventuelle

La décision
économique peut venir plus tard.

---

**6. Reconnaissance économique ≠
remboursement**

La décision
commerciale peut être prise avant le paiement effectif.

Exemple :

5 mars

→ décision :

   rembourser 8
000 DZD

Le
remboursement bancaire peut arriver :

10 avril

→ paiement réel de 8 000 DZD

Les
statistiques commerciales utilisent :

5 mars

pour la
correction économique.

La
trésorerie utilise :

10 avril

pour le
mouvement financier réel.

---

**7. Reconnaissance économique ≠
avoir**

Un avoir est
un document fiscal ou comptable.

Il peut
découler de la décision économique, mais il ne constitue pas forcément
l’événement métier initial.

Donc :

décision économique

et :

émission de l’avoir

doivent
rester distinguées.

Exemple :

5 mars

→ décision commerciale définitive

puis :

6 mars

→ émission de l’avoir correspondant

Le système
doit pouvoir rapprocher les deux.

---

**8. Reconnaissance économique ≠
retour complet automatique**

Un retour
physique ne signifie pas nécessairement :

annulation de 100 % de la vente

Exemple :

Commande :

2 produits

total = 10 000 DZD

Au retour :

produit A reçu correctement

produit B manquant

Décision
commerciale :

montant annulé = 6 000 DZD

montant conservé = 4 000 DZD

Le système
doit pouvoir représenter cette situation sans forcer :

0 DZD

ou :

10 000 DZD

comme seuls
résultats possibles.

---

**9. Granularité par ligne**

La
correction économique doit être reconstructible au niveau des lignes
concernées.

Exemple :

Article A

quantité = 1

montant initial = 6 000

montant annulé = 6 000

Article B

quantité = 1

montant initial = 4 000

montant annulé = 0

montant conservé = 4 000

Total :

montant initial concerné = 10 000

montant annulé = 6 000

montant conservé = 4 000

Cela permet
de produire correctement :

retours par produit

marge corrigée

meilleures ventes nettes

revenu net

statistiques SAV

---

**10. Invariants**

Pour chaque
ligne reconnue économiquement :

quantite_concernee >= 0

et :

montant_initial_concerne >= 0

montant_annule >= 0

montant_conserve >= 0

Avec
l’invariant :

montant_annule + montant_conserve

\=

montant_initial_concerne

sauf si le
modèle métier prévoit explicitement d’autres composantes.

La quantité
reconnue économiquement ne doit pas dépasser la quantité admissible pour la
ligne concernée.

---

**11. Événement immuable**

Une
reconnaissance économique finalisée ne doit pas être modifiée silencieusement.

Exemple :

mars :

montant annulé = 8 000

Il ne faut
pas faire plus tard :

UPDATE montant_annule = 6 000

sans
historique.

Si une
décision économique déjà finalisée doit être corrigée :

ancienne décision

→ reste conservée

puis :

nouvel événement correctif

→ contrepassation / correction

Cela permet
de reconstruire les statistiques telles qu’elles ont évolué.

---

**12. Statut de la décision**

Le système
peut distinguer au minimum :

brouillon

finalisee

annulee_par_correction

Les
statistiques officielles ne doivent utiliser que les événements :

finalises

Un brouillon
SAV ne doit pas modifier le revenu.

---

**13. Exemple complet**

Commande
vendue :

15 janvier

revenu = +10 000 DZD

Retour
physique :

20 février

colis reçu

Conséquence
:

mouvement physique / contrôle retour

mais pas
encore nécessairement :

correction revenu

Décision
commerciale :

5 mars

montant initial concerné = 10 000

montant annulé = 8 000

montant conservé = 2 000

Statistiques
commerciales :

mars

→ correction = -8 000 DZD

Avoir :

6 mars

→ avoir de 8 000 DZD

Remboursement
:

10 avril

→ client reçoit réellement 8 000 DZD

Trésorerie :

avril

→ -8 000 DZD

---

**14. Impact sur les
statistiques**

Les
indicateurs commerciaux doivent utiliser explicitement :

date_effet de la reconnaissance économique

pour les
corrections de revenu.

Exemple :

revenu brut janvier = 10 000

Puis :

correction économique mars = -8 000

Le système
ne doit pas réécrire silencieusement :

janvier = 2 000

si la
convention métier retenue consiste à enregistrer la correction dans la période
où elle est reconnue.

Les rapports
doivent pouvoir distinguer :

ventes brutes

corrections commerciales

ventes nettes

---

**15. Meilleures ventes**

Les
statistiques de meilleures ventes doivent préciser leur convention.

Par exemple
:

ventes brutes

→ quantités vendues à la date de vente

et :

ventes nettes

→ ventes moins corrections économiques reconnues

Ainsi, une
vente de janvier retournée économiquement en mars ne crée pas des résultats
différents selon le développeur qui écrit la requête.

---

**16. Marge estimée**

La marge
doit suivre la même convention temporelle.

Exemple :

vente janvier

→ marge initialement reconnue

Puis :

correction économique mars

→ correction correspondante de marge

Il faut
éviter qu’un module utilise :

date_retour_physique

alors qu’un
autre utilise :

date_remboursement

pour
calculer la marge nette.

---

**17. Clôtures mensuelles**

Une période
déjà clôturée ne doit pas être silencieusement réécrite lorsqu’un retour est
reconnu plus tard.

Exemple :

janvier clôturé

Puis :

retour reconnu économiquement en mars

La
correction doit apparaître selon la convention retenue :

mars

et non
modifier silencieusement les chiffres déjà publiés pour janvier.

Si des états
retraités historiques sont proposés, ils doivent être explicitement distingués
des états tels qu’ils étaient clôturés.

---

**18. Exports**

Les exports
doivent conserver les dates séparément.

Exemple :

date_vente

date_retour_physique

date_reconnaissance_economique

date_avoir

date_remboursement

Un export ne
doit pas présenter une seule colonne ambiguë :

date_retour

si plusieurs
événements différents existent.

---

**19. Relation avec les
incidents**

Un incident
peut servir de support à la décision économique.

Mais :

incident ouvert

ne signifie
pas automatiquement :

revenu corrigé

Exemple :

incident créé en février

Puis :

analyse pendant plusieurs jours

Puis :

décision économique finalisée en mars

C’est
l’événement finalisé qui doit porter la correction économique.

---

**20. Relation avec les
documents de facturation**

Une
obligation de facturation ou un avoir peut découler de la reconnaissance
économique.

Par exemple
:

reconnaissance économique

montant annulé = 8 000

peut créer :

obligation_facturation

type = avoir

montant attendu = 8 000

Mais le
document fiscal garde sa propre identité, son propre statut et sa propre date
d’émission.

La
reconnaissance économique ne doit donc pas être déduite uniquement de :

facture

avoir

remboursement

Elle doit
être représentée explicitement.

---

**Règles finales**

vente

≠ retour physique

retour physique

≠ décision économique

décision économique

≠ avoir

avoir

≠ remboursement bancaire

réception du retour

→ impact physique éventuel

reconnaissance économique finalisée

→ correction des indicateurs commerciaux

émission de l’avoir

→ événement documentaire/fiscal

remboursement réel

→ impact de trésorerie

La source de
vérité pour les statistiques commerciales liées aux retours devient :

événement de reconnaissance économique finalisé

\+

date_effet

\+

lignes

\+

quantités

\+

montants

\+

motif

---

**Vérifications**

**Test 1 — Retour reçu sans décision**

vente janvier

retour physique février

aucune décision commerciale

Résultat :

février

→ aucun ajustement automatique du revenu

---

**Test 2 — Décision le mois suivant**

vente janvier

retour février

décision économique mars

Résultat :

correction commerciale

→ mars

selon la
convention retenue.

---

**Test 3 — Remboursement ultérieur**

décision mars = -8 000 DZD

remboursement avril = 8 000 DZD

Résultat :

revenu commercial

→ corrigé en mars

trésorerie

→ impactée en avril

---

**Test 4 — Retour partiellement reconnu**

vente = 10 000

montant annulé = 8 000

montant conservé = 2 000

Résultat :

correction revenu = -8 000

et non :

-10 000

---

**Test 5 — Produit manquant**

Retour
physique :

2 produits attendus

1 réellement reçu

1 manquant

Décision :

remboursement accepté seulement pour l’article reçu

Le système
doit représenter exactement les lignes, quantités et montants concernés.

---

**Test 6 — Modification après finalisation**

Créer une
reconnaissance :

montant annulé = 8 000

statut = finalisee

Tenter de
remplacer directement :

8 000 → 6 000

Résultat
attendu :

modification directe refusée

Une
correction explicite doit être créée.

---

**Test 7 — Reconstruction multi-mois**

Rejouer :

janvier → vente

février → retour physique

mars → décision économique

avril → remboursement

Le système
doit reproduire exactement les mêmes résultats à chaque reconstruction.

Deux
développeurs utilisant la même convention doivent obtenir les mêmes chiffres.

---

**Principe final**

Le système
doit toujours pouvoir répondre séparément à quatre questions :

Quand la marchandise est-elle revenue ?

Quand a-t-on décidé économiquement quelle part de la
vente devait être annulée ?

Quand le document fiscal correspondant a-t-il été émis
?

Quand l’argent a-t-il réellement été remboursé ?

Ces quatre
réponses peuvent porter quatre dates différentes et ne doivent jamais être
déduites les unes des autres.

---

**AUD-07 — Le
remplacement gratuit et l’hypothèse « avoir + nouvelle facture » doivent être
représentés par deux mécanismes compatibles avec leur valeur économique**

**Gravité :** bloquante pour le cas concerné — **Nature
:** risque conditionnel explicitement reconnu mais non résolu.

**Emplacement
:** T8
remplacement gratuit, T22 obligations/documentation de facturation, §10.4
facturation et révisions.

**Problème**

Le modèle
prévoit actuellement un mécanisme de :

remplacement_gratuit

dans lequel
le client reçoit un nouvel article sans nouvelle valeur commerciale à payer.

Exemple :

produit initial = 8 000 DZD

produit de remplacement = 0 DZD

reste demandé au client = 0 DZD

Cette
représentation est cohérente si le remplacement constitue uniquement une
opération SAV gratuite.

Cependant,
T22 envisage également, selon le traitement documentaire retenu, la possibilité
de produire :

avoir de l'ancienne vente

\+

nouvelle facture pour le nouvel article

Or §10.4
impose l’invariant :

facture complète

\=

revision.total_commande

Si une
révision de remplacement vaut :

0 DZD

elle ne peut
pas produire correctement une nouvelle facture de :

8 000 DZD

sans casser
cet invariant.

---

**Scénario conditionnel**

Commande
initiale :

article = 8 000 DZD

Le produit
présente un défaut.

Le
commerçant décide d’envoyer gratuitement un produit de remplacement.

Le client ne
doit rien payer de plus.

Le modèle remplacement_gratuit produit :

revision.total_commande = 0 DZD

reste_client = 0 DZD

Supposons
maintenant qu’une règle documentaire validée impose :

avoir ancienne vente = 8 000 DZD

nouvelle facture = 8 000 DZD

La nouvelle
facture aurait alors :

montant facture = 8 000 DZD

alors que :

revision.total_commande = 0 DZD

On obtient :

facture ≠ révision

ce qui viole
l’invariant documentaire retenu.

---

**Conséquence**

Sans
distinction explicite entre les deux modèles, le développement pourrait
produire :

facture de 8 000 DZD

sur une révision à 0 DZD

ou
contourner artificiellement :

facture complète = revision.total_commande

ou encore
mélanger :

valeur commerciale

reste à payer

compensation

remboursement

Les
statistiques commerciales et financières deviendraient également ambiguës.

---

**Correction retenue**

Deux
mécanismes doivent être distingués.

---

**1. Remplacement gratuit**

Le mécanisme
:

remplacement_gratuit

est utilisé
lorsque le traitement métier/documentaire retenu considère le nouvel envoi
comme une opération SAV sans nouvelle vente valorisée.

Exemple :

vente initiale = 8 000 DZD

remplacement SAV :

valeur commerciale nouvelle = 0 DZD

reste client = 0 DZD

La nouvelle
opération peut donc avoir :

type_operation = remplacement_gratuit

avec :

revision.total_commande = 0

Elle doit
rester reliée à :

commande_origine_id

revision_origine_id

article_commande_origine_id

incident_id

motif_remplacement

afin de
conserver la traçabilité.

---

**Règle**

remplacement_gratuit

→ aucune nouvelle vente valorisée

→ aucun nouveau montant demandé au client

Il ne faut
donc pas générer artificiellement une facture valorisée incompatible avec cette
révision.

---

**2. Échange valorisé**

Si le
traitement documentaire finalement validé exige qu’un nouvel article soit
représenté par une nouvelle vente valorisée, il faut utiliser le mécanisme :

echange_valorise

et non :

remplacement_gratuit

Exemple :

ancienne vente = 8 000 DZD

Le
traitement produit :

avoir ancienne vente = 8 000 DZD

et :

nouvelle revision = 8 000 DZD

nouvelle facture = 8 000 DZD

L’invariant
reste alors :

facture = revision.total_commande

---

**3. Compensation**

Le fait que
la nouvelle opération soit valorisée à :

8 000 DZD

ne signifie
pas nécessairement que le client doit payer :

8 000 DZD

Une
compensation peut réduire le reste à payer.

Exemple :

nouvelle vente = 8 000 DZD

compensation = 8 000 DZD

reste client = 0 DZD

Ainsi :

valeur commerciale = 8 000

mais :

argent supplémentaire demandé = 0

Ces deux
notions doivent rester distinctes.

---

**4. Valeur commerciale ≠ reste
à payer**

Le modèle
doit explicitement distinguer :

valeur commerciale de l'opération

de :

montant restant à payer par le client

Exemple :

revision.total_commande = 8 000

compensation = 8 000

reste_a_payer = 0

Cette
situation est parfaitement différente d’un remplacement dont :

revision.total_commande = 0

---

**5. Même prix**

Exemple :

ancien article = 8 000

nouvel article = 8 000

**Si remplacement gratuit**

nouvelle valeur = 0

reste client = 0

**Si échange valorisé**

nouvelle valeur = 8 000

compensation = 8 000

reste client = 0

Le résultat
financier demandé au client est identique :

0 DZD supplémentaire

mais la
représentation commerciale et documentaire est différente.

---

**6. Nouveau produit plus cher**

Exemple :

ancien article = 8 000

nouvel article = 10 000

Dans un
échange valorisé :

nouvelle valeur = 10 000

compensation = 8 000

reste client = 2 000

Le client
doit alors payer :

2 000 DZD

si la
politique commerciale retenue le prévoit.

---

**7. Nouveau produit moins cher**

Exemple :

ancien article = 8 000

nouvel article = 6 000

Le mécanisme
doit représenter séparément :

nouvelle vente = 6 000

et la
manière dont la différence :

2 000 DZD

est traitée
:

remboursement

créance client

avoir

autre compensation autorisée

selon la
politique commerciale et documentaire retenue.

Cette
différence ne doit pas être perdue dans le simple changement de variante.

---

**8. COD**

Pour une
nouvelle opération valorisée :

total_commande = 8 000

avec :

compensation = 8 000

le montant
COD réellement demandé peut être :

0 DZD

Il faut donc
distinguer :

montant_facture

de :

montant_a_encaisser

La
génération du montant COD ne doit pas utiliser directement :

revision.total_commande

sans tenir
compte des compensations applicables.

---

**9. Relation avec la commande
d’origine**

Qu’il
s’agisse d’un remplacement gratuit ou d’un échange valorisé, la nouvelle
opération doit rester reliée à l’opération d’origine.

Conserver
par exemple :

commande_origine_id

revision_origine_id

article_commande_origine_id

incident_id

Cela permet
de reconstruire :

vente initiale

→ incident

→ retour éventuel

→ décision

→ remplacement / échange

→ compensation éventuelle

→ documents associés

---

**10. Interdiction de
transformer silencieusement un remplacement gratuit en vente valorisée**

Une
opération créée comme :

remplacement_gratuit

avec :

revision.total_commande = 0

ne doit pas
ensuite recevoir silencieusement :

facture complète = 8 000

Si le
traitement documentaire nécessite finalement une nouvelle vente valorisée, il
faut utiliser le workflow approprié :

echange_valorise

ou une
représentation explicitement prévue pour ce cas.

---

**11. Obligations de facturation**

obligations_facturation doit connaître le type de
traitement attendu.

Exemple :

type_operation = remplacement_gratuit

ne doit pas
générer automatiquement une obligation :

nouvelle_facture_valorisee

si cette
facture est incompatible avec la valeur commerciale de la révision.

Inversement
:

type_operation = echange_valorise

peut
produire les obligations documentaires correspondantes :

avoir

nouvelle facture

si ce
traitement a été validé.

---

**12. Statistiques**

Les
indicateurs doivent distinguer les deux mécanismes.

**Remplacement gratuit**

Peut être
comptabilisé comme :

coût SAV

sans créer
automatiquement :

nouvelle vente commerciale

**Échange valorisé**

Peut
produire :

nouvelle valeur commerciale

\+

correction de l'ancienne opération

\+

compensation

Les
statistiques ne doivent pas compter indistinctement les deux scénarios comme
deux nouvelles ventes.

---

**13. Règle documentaire à
valider**

Le schéma ne
doit pas décider seul si un remplacement doit juridiquement ou fiscalement
produire :

un avoir

une nouvelle facture

les deux

ou une autre pièce

Ce point
doit être validé selon les règles comptables et fiscales applicables.

AUD-07 ne
conclut donc pas :

tout remplacement doit produire avoir + facture

Il conclut
uniquement :

SI avoir + nouvelle facture valorisée sont exigés

ALORS une révision de remplacement à 0 DZD n'est pas
compatible

avec cette représentation.

---

**14. Règles finales**

remplacement_gratuit

→ valeur commerciale nouvelle = 0

remplacement_gratuit

→ reste client = 0

remplacement_gratuit

→ pas de facture valorisée incompatible avec une
révision à 0

---

echange_valorise

→ nouvelle valeur commerciale > 0

facture complète

→ doit correspondre à revision.total_commande

compensation

→ peut réduire le montant réellement demandé

valeur commerciale

≠ montant réellement encaissé

valeur commerciale

≠ reste client

---

**Vérifications**

**Test 1 — Remplacement gratuit**

Produit
initial :

8 000 DZD

Remplacement
:

type = remplacement_gratuit

revision.total_commande = 0

reste client = 0

Résultat
attendu :

aucune facture complète de 8 000

ne doit être rattachée à cette révision à 0

---

**Test 2 — Échange valorisé même prix**

ancien article = 8 000

nouvel article = 8 000

Résultat :

nouvelle revision = 8 000

nouvelle facture = 8 000

compensation = 8 000

reste client = 0

---

**Test 3 — Échange plus cher**

ancien article = 8 000

nouvel article = 10 000

Résultat
attendu :

nouvelle revision = 10 000

compensation = 8 000

reste client = 2 000

si cette
politique commerciale est retenue.

---

**Test 4 — Échange moins cher**

ancien article = 8 000

nouvel article = 6 000

Le système
doit représenter explicitement le traitement des :

2 000 DZD

restants.

Ils ne
doivent pas disparaître du modèle.

---

**Test 5 — Incompatibilité volontaire**

Créer :

revision.total_commande = 0

type = remplacement_gratuit

Puis tenter
de rattacher :

facture complète = 8 000

Résultat
attendu :

REFUS

---

**Test 6 — COD avec compensation**

nouvelle revision = 8 000

compensation = 8 000

Résultat :

facture = 8 000

montant COD à encaisser = 0

Le
transporteur ne doit pas recevoir une demande d’encaissement de 8 000 DZD.

---

**Principe final**

Le système
doit toujours pouvoir répondre séparément à trois questions :

Quelle est la valeur commerciale de la nouvelle
opération ?

Quel montant est documenté/facturé ?

Quel montant supplémentaire doit réellement payer le
client ?

Ces trois
montants peuvent être différents.

La règle
fondamentale est donc :

remplacement gratuit à 0

\+

facture valorisée correspondant à une nouvelle vente

ne doivent
jamais être combinés sans passer par un mécanisme d’échange valorisé ou une
autre représentation explicitement validée.

---

**AUD-08 — Le
retour physique d’une commande est obligatoirement complet**

**Gravité :** majeure avant lancement — **Nature
:** règle métier à formaliser et à valider juridiquement et
opérationnellement.

**Emplacement
:** §1, T9
retours, T18 incidents/SAV, T22 corrections financières.

**Règle métier retenue**

Dans le MVP,
un retour physique ne peut jamais porter sur une partie seulement des articles
expédiés dans une livraison.

Si une
livraison contient :

Article A

Article B

Article C

alors un
retour accepté porte obligatoirement sur :

A + B + C

Le système
ne permet pas :

retour physique de B uniquement

ou :

retour physique de A + C uniquement

Le retour
concerne donc l’intégralité du contenu de la livraison concernée.

---

**Conséquence**

La table de
retour ne doit pas permettre de choisir librement certaines lignes comme
articles retournés.

Le système
doit reconstruire automatiquement les articles attendus à partir de la révision
effectivement expédiée.

Ainsi :

livraison.revision_expediee

→ définit les articles attendus au retour

Tous les
articles et quantités expédiés doivent être présents dans le périmètre du
retour.

---

**Articles manquants lors du retour**

Un retour
complet signifie que tous les articles sont **attendus**.

Cela ne
signifie pas qu’ils sont nécessairement tous effectivement reçus.

Exemple :

expédié :

A x1

B x1

C x1

Le client
retourne le colis.

À la
réception :

A reçu = 1

B reçu = 1

C reçu = 0

Le système
ne doit pas considérer qu’il s’agit d’un retour partiel autorisé.

Il s’agit
d’un :

retour complet attendu

\+

article C manquant constaté

Le système
doit donc enregistrer :

C attendu = 1

C reçu = 0

C manquant_documente = 1

selon les
règles de retour et de stock déjà prévues.

---

**SAV et incident sur une seule ligne**

Un incident
peut concerner une seule ligne de commande.

Exemple :

Article B défectueux

L’incident
peut donc être enregistré au niveau :

article_commande_id = B

Mais si cet
incident nécessite un retour physique dans le MVP :

le colis complet doit revenir

Il n’existe
pas de flux physique séparé permettant de recevoir uniquement B.

Donc :

incident partiel

≠ retour physique partiel

Le SAV peut
être analysé par ligne, alors que le mouvement physique de retour reste au
niveau du colis complet.

---

**Correction financière partielle**

Le fait que
tout le colis revienne ne signifie pas que toute la commande doit
automatiquement être remboursée.

Exemple :

A = conforme

B = défectueux

C = conforme

Le colis
complet revient :

A + B + C

mais la
décision commerciale peut ensuite être :

B remboursé

A non remboursé

C non remboursé

ou toute
autre décision autorisée par les règles métier validées.

Il faut donc
conserver la séparation :

retour physique complet

≠ correction financière complète

Une
correction financière ou un avoir peut rester partiel même si le retour
physique porte sur tout le colis.

---

**Règle de validation**

Lors de la
création d’un retour :

articles_retour attendus

\=

articles de la revision_expediee

avec les
quantités expédiées correspondantes.

Le système
doit refuser toute création de retour dans laquelle une ligne expédiée est
volontairement absente du périmètre.

---

**Vérification**

Commande
expédiée :

A x1

B x2

C x1

Tentative de
retour :

B x2 uniquement

Résultat
attendu :

REFUS

Retour créé
correctement :

A x1 attendu

B x2 attendu

C x1 attendu

Puis
réception réelle :

A x1 reçu

B x2 reçu

C x0 reçu

Résultat :

retour complet attendu

C x1 manquant documenté

et non :

retour partiel

---

**Principe final**

incident

→ peut concerner une ligne précise

décision financière

→ peut être partielle

mais :

retour physique

→ toujours colis complet

Tous les
articles de la livraison sont donc attendus au retour, même si certains peuvent
ensuite être constatés manquants, perdus, endommagés ou placés en quarantaine.

---

**AUD-09 — La
reprise après perte ou restauration de la BDD centrale doit disposer d’un
protocole explicite**

**Gravité :** majeure pour l’exploitation — **Nature
:** risque conditionnel.

**Emplacement
:** C12
sauvegardes/restaurations et §13 exploitation, actuellement principalement
centrés sur les BDD tenant.

**Problème**

La BDD
centrale contient des informations indispensables au fonctionnement global du
SaaS, notamment :

comptes utilisateurs

permissions plateforme

appartenances / routages vers les tenants

abonnements et quotas

intentions et coordinations inter-BDD

états financiers centraux

reversements

références aux comptes transporteurs

références vers les secrets

journaux de coordination

registre documentaire central

Les BDD
boutiques peuvent continuer à exister même si la BDD centrale est perdue ou
restaurée à un instant antérieur.

Le système
doit donc définir explicitement comment reconstruire un état central fiable
avant de reprendre les opérations sensibles.

Une
restauration centrale ne doit jamais être considérée comme terminée uniquement
parce que :

le serveur MySQL redémarre

ou :

le backup a été restauré avec succès

---

**Scénario**

À :

10:00

un backup
central est créé.

Après ce
backup :

11:00

→ permission administrateur de U1 révoquée

12:00

→ règlement transporteur R-500 réellement exécuté

13:00

→ nouveau compte transporteur configuré

14:00

→ incident majeur sur la BDD centrale

La
restauration replace le central à :

10:00

Les BDD
tenant et les systèmes externes sont cependant plus récents.

La BDD
restaurée peut donc indiquer :

U1 = administrateur

R-500 = non réglé

compte transporteur récent = absent

alors que la
réalité est :

U1 n'est plus administrateur

R-500 a déjà été payé

un compte transporteur plus récent existe

---

**Conséquences**

Une reprise
non contrôlée peut provoquer :

réactivation de permissions révoquées

double paiement

double reversement

répétition d'une opération transporteur

utilisation d'anciens secrets

perte d'intentions inter-BDD

routage incorrect vers les tenants

état financier central incohérent

impossibilité de rapprocher central et tenants

---

**Correction retenue**

**1. Sauvegarde indépendante de la BDD centrale**

La BDD
centrale doit disposer de son propre mécanisme de sauvegarde, indépendant des
sauvegardes tenant.

Prévoir :

backup complet central

\+

sauvegardes incrémentales selon infrastructure

\+

PITR

\+

rétention

\+

tests réguliers de restauration

La politique
centrale doit définir :

RPO central

RTO central

fréquence

rétention

emplacement des sauvegardes

chiffrement

contrôle d'intégrité

Les
sauvegardes tenant et centrale ne doivent pas être considérées comme un seul
mécanisme de restauration.

---

**2. PITR central**

La BDD
centrale doit disposer, lorsque l’infrastructure le permet, d’un :

Point-In-Time Recovery

permettant
de restaurer la base aussi près que possible de l’incident.

Cependant :

PITR

≠ garantie de cohérence globale

car certains
effets externes peuvent avoir été exécutés après le point restauré.

Exemple :

paiement bancaire

opération transporteur

document émis

secret renouvelé

Un
rapprochement reste donc obligatoire.

---

**3. Mode incident central**

Lorsqu’une
indisponibilité ou restauration centrale est détectée :

plateforme_mode = reprise_centrale

Les
opérations sensibles doivent être bloquées.

Au minimum :

modification des rôles

attribution/révocation de permissions

création de tenant

suppression de tenant

modification d'abonnement

changement de propriétaire

reversement

règlement financier

création d'opération externe irréversible

émission documentaire dépendant du central

modification de configuration transporteur

doivent être
suspendues jusqu’à validation.

---

**4. Fonctionnement des
boutiques pendant l’indisponibilité**

Le
comportement des tenants doit être explicitement défini.

Deux
catégories doivent être distinguées.

**Actions éventuellement autorisées**

Des
opérations purement locales peuvent éventuellement continuer si elles ne
nécessitent aucune vérité centrale fraîche.

Exemples
possibles :

consultation catalogue

consultation locale des commandes

préparation locale de commandes déjà existantes

consultation du stock local

uniquement
si la politique de sécurité le permet.

**Actions bloquées**

Toute
opération dépendant d’un état central récent doit être bloquée.

Par exemple
:

authentification nécessitant validation centrale

permissions sensibles

abonnements

quotas

opérations financières

secrets

création de tenant

coordination externe

Principe :

central indisponible

→ aucune opération sensible ne doit utiliser
aveuglément un cache ancien

---

**5. État de reprise**

Pendant la
restauration :

central.statut_exploitation = reprise

ou un
mécanisme opérationnel équivalent doit empêcher la réouverture automatique.

Les tenants
concernés peuvent également être considérés temporairement comme :

mode_degrade

jusqu’à
validation.

Il ne s’agit
pas obligatoirement d’ajouter une table.

Le mécanisme
peut être porté par :

configuration d'exploitation

feature flag

orchestrateur

état système

mais le
comportement doit être explicite.

---

**6. Ordre de reprise**

L’ordre
recommandé est :

1\. isoler la plateforme

2\. restaurer la BDD centrale

3\. restaurer l'accès aux secrets et dépendances
nécessaires

4\. identifier précisément le point temporel restauré

5\. empêcher les opérations sensibles

6\. comparer le central restauré aux BDD tenant

7\. rapprocher les systèmes externes

8\. reconstruire ou rejouer les opérations manquantes

9\. vérifier les invariants de sécurité et financiers

10\. invalider les caches

11\. réactiver progressivement la plateforme

---

**7. Identification du point
restauré**

La
restauration doit connaître son point de reprise.

Prévoir un
identifiant ou watermark tel que :

point_reconciliation_central

ou un
équivalent monotone.

Cela permet
d’identifier :

tout ce qui est connu avant le backup

et :

tout ce qui doit être recherché ou reconstruit après
ce point

Ce mécanisme
doit être compatible avec celui déjà utilisé pour les restaurations tenant.

---

**8. Rapprochement avec les
tenants**

Après
restauration du central, chaque tenant doit être considéré comme une source
potentiellement plus récente pour certaines informations locales.

Le système
doit comparer notamment :

existence du tenant

identifiants

état de certaines opérations inter-BDD

commandes liées aux opérations centrales

références de transport

références financières

watermarks

Il ne faut
cependant pas recopier aveuglément toutes les données tenant vers le central.

Le
rapprochement doit respecter quelle base constitue la source de vérité pour
chaque information.

---

**9. Permissions et sécurité**

Les
permissions restaurées doivent être traitées avec une prudence particulière.

Exemple :

Backup :

U1 = administrateur

Après le
backup :

U1 → rôle révoqué

Puis
restauration.

Le système
ne doit pas conclure :

backup dit administrateur

→ donc U1 redevient administrateur

Toute
révocation connue après le point restauré doit être retrouvée depuis une source
durable disponible :

PITR

journal de sécurité durable

audit externe

autre mécanisme prévu

Si l’état
réel ne peut pas être déterminé :

accès sensible = bloqué

plutôt que
réactivé automatiquement.

---

**10. Invalidation des caches**

Après
restauration centrale :

caches_permissions

caches_roles

caches_tenants

caches_abonnements

caches_routage

doivent être
invalidés.

Un cache
créé avant l’incident ne doit pas devenir une source de vérité pendant la
reprise.

---

**11. Finance et paiements**

Toute
opération financière doit être rapprochée avant reprise.

Exemple :

BDD
restaurée :

R-500 = à payer

mais banque
:

R-500 = déjà exécuté

Résultat
obligatoire :

NE PAS REPAYER

Le système
doit rechercher :

cle_operation

reference_bancaire

reference_bordereau

reference_transporteur

identifiant externe

historique des paiements

avant toute
réémission.

---

**12. Idempotence**

Toutes les
opérations centrales ayant des effets externes doivent utiliser des
identifiants stables.

Exemple :

cle_operation = REGLEMENT-R500

Après
restauration, une reprise avec la même clé doit :

retrouver l'opération existante

et non :

créer un second règlement

La
restauration ne doit jamais générer une nouvelle clé uniquement parce que
l’ancienne ligne centrale a disparu.

---

**13. Comptes transporteurs**

Si un compte
transporteur a été créé ou modifié après le backup central, le rapprochement
doit vérifier :

identifiant local

identifiant externe

version de configuration

secret associé

statut du compte

Une
configuration restaurée plus ancienne ne doit pas écraser silencieusement une
configuration plus récente.

---

**14. Secrets**

La BDD
centrale ne doit idéalement conserver que :

références vers les secrets

et non les
secrets en clair.

Le système
de secrets doit disposer de son propre mécanisme de sauvegarde/versionnement.

Après
restauration centrale :

reference_secret centrale

doit être
rapprochée avec :

version réellement active dans le gestionnaire de
secrets

Si la
correspondance ne peut pas être garantie :

intégration concernée = désactivée

jusqu’à
résolution.

---

**15. Registre documentaire**

Le registre
durable des documents émis défini dans AUD-04 doit également être vérifié.

Si le
central restauré a perdu :

FACT-101

FACT-102

alors que
les PDF existent toujours dans S3/MinIO et ont été transmis :

les numéros ne doivent pas être réutilisés

La reprise
centrale doit donc inclure :

registre_documents_emis

\+

stockage documentaire

\+

transmissions

dans son
rapprochement.

---

**16. Intentions inter-BDD**

Toute
intention créée avant une opération distribuée doit être analysée.

Exemple :

central :

intention création tenant

puis :

tenant réellement créé

mais
l’intention disparaît après restauration.

Le système
doit pouvoir déterminer :

tenant existe réellement

et ne pas
créer une seconde base.

Inversement
:

intention présente

tenant absent

doit être
traité comme une opération incomplète à reprendre ou annuler selon le workflow
défini.

---

**17. Reconstruction**

La
reconstruction ne doit jamais consister à :

copier tout ce qui semble plus récent

Chaque
domaine doit avoir une source de vérité explicite.

Exemple :

permissions plateforme

→ central / journal sécurité

stock boutique

→ BDD tenant

paiement réel

→ système bancaire / référence rapprochée

opération transporteur

→ central + fournisseur externe

document émis

→ registre durable + stockage documentaire

---

**18. Conflits**

Si deux
sources contiennent des états incompatibles :

central restauré

≠ tenant

≠ système externe

le système
ne doit pas choisir arbitrairement.

L’opération
concernée doit passer dans un état tel que :

a_rapprocher

ou :

bloquee_reconciliation

et ne doit
pas produire de nouvel effet externe tant que le conflit n’est pas résolu.

---

**19. Réouverture**

La
plateforme ne peut sortir du mode de reprise que lorsque les invariants
critiques sont validés.

Au minimum :

permissions cohérentes

routages tenant cohérents

abonnements cohérents

secrets accessibles

opérations financières rapprochées

aucun paiement ambigu

aucune opération externe ambiguë

registre documentaire cohérent

caches invalidés

jobs sensibles contrôlés

Ensuite
seulement :

plateforme_mode = normal

---

**20. Jobs**

Les jobs
présents dans les files d’attente doivent être examinés après restauration.

Un job créé
avant l’incident peut correspondre à une opération :

déjà exécutée

révoquée

annulée

ou devenue invalide

Chaque job
sensible doit donc vérifier :

état actuel

\+

clé idempotente

\+

autorisation actuelle

avant
exécution.

---

**21. Ordre central–tenants**

Le principe
de reprise retenu est :

central restauré en premier

mais :

central restauré

≠ central immédiatement considéré comme vérité
complète

Il entre
d’abord en phase de :

réconciliation

avec les
tenants et les systèmes externes.

Les tenants
ne sont pleinement réactivés qu’après validation des dépendances centrales
critiques.

---

**22. Si reconstruction
impossible**

Si certaines
informations critiques ne peuvent pas être reconstruites avec suffisamment de
certitude :

opération concernée = bloquée

Exemples :

paiement ambigu

→ pas de nouveau paiement

permission ambiguë

→ accès sensible refusé

secret ambigu

→ intégration désactivée

document ambigu

→ nouvelle émission bloquée

La reprise
doit privilégier :

absence temporaire d'opération

plutôt que :

répétition d'une opération irréversible

---

**Plan de sauvegarde central**

Prévoir au
minimum :

sauvegardes automatiques

PITR

rétention

chiffrement

copie hors serveur principal

contrôle d'intégrité

tests de restauration

journal des restaurations

Les backups
centraux doivent être stockés séparément de la machine hébergeant la BDD
centrale.

---

**Tests de reprise**

**Test 1 — Permission révoquée après backup**

Backup :

U1 = admin

Après backup :

U1 = révoqué

Restaurer le
backup.

Résultat
attendu :

U1 ne récupère pas automatiquement son ancien accès

---

**Test 2 — Paiement exécuté après backup**

Backup :

R-500 = à payer

Après backup
:

R-500 payé réellement

Restaurer.

Résultat
attendu :

aucun second règlement

---

**Test 3 — Tenant plus récent**

Créer/modifier
une opération tenant après le backup central.

Restaurer le
central.

Résultat
attendu :

l'opération est rapprochée

sans écraser
arbitrairement l’état tenant.

---

**Test 4 — Compte transporteur récent**

Créer un
compte externe après backup.

Restaurer.

Résultat
attendu :

aucune recréation aveugle

Le système
retrouve ou rapproche l’identifiant externe existant.

---

**Test 5 — Secret renouvelé**

Backup avec
:

secret version 4

Puis
rotation :

version 5

Restaurer.

Résultat :

le système ne réactive pas automatiquement la version
4

---

**Test 6 — Job ancien**

Créer un job
de règlement.

Exécuter
réellement le règlement.

Restaurer le
central à une date antérieure.

Réinjecter
le job.

Résultat :

clé idempotente détectée

→ aucun nouveau paiement

---

**Test 7 — Central indisponible**

Couper
volontairement le central.

Vérifier que
:

opérations locales éventuellement autorisées

→ continuent selon le mode dégradé défini

mais :

permissions sensibles

finance

création tenant

abonnements

opérations externes irréversibles

sont
bloqués.

---

**Principe final**

restauration tenant

→ rapprochement avec central

mais
également :

restauration centrale

→ rapprochement avec tenants

\+ systèmes externes

\+ stockage documentaire

\+ gestionnaire de secrets

La règle
fondamentale est :

un backup restauré représente un état ancien connu,

pas automatiquement l'état réel actuel du SaaS.

Aucune
permission sensible, opération financière ou opération externe irréversible ne
doit être réactivée ou répétée tant que le rapprochement n’a pas démontré que
son état est cohérent.

**Conservation du stock**

Avec les
définitions du document :

image





P désigne le physique **vendable**
; le stock physiquement détenu inclut aussi la quarantaine Q.

image

image





Pour une
ligne de retour :

image





À clôture :

image





**Exemple :** cinq unités expédiées ; quatre
reviennent ; trois revendables, une détruite, une manquante.

|      |
| ---- |

**Étape**

|      |
| ---- |

**P**

|      |
| ---- |

**R**

|      |
| ---- |

**Q**

|     |
| --- |

Stock initial

|     |
| --- |

5

|     |
| --- |

0

|     |
| --- |

0

|     |
| --- |

Confirmation

|     |
| --- |

5

|     |
| --- |

5

|     |
| --- |

0

|     |
| --- |

Expédition

|     |
| --- |

0

|     |
| --- |

0

|     |
| --- |

0

|     |
| --- |

Réception de 4

|     |
| --- |

0

|     |
| --- |

0

|     |
| --- |

4

|     |
| --- |

Inspection : 3 revendables, 1
&#x20; perdue

|     |
| --- |

3

|     |
| --- |

0

|     |
| --- |

0

Conservation
: image. Le manquant n’a jamais augmenté P ou Q.

**Garantie :** CHECK pour les
soldes locaux ; transaction verrouillée pour journal/réservations/compteurs ;
rapprochement périodique pour la reconstruction complète. Un CHECK ne garantit pas seul une somme entre plusieurs tables.

**Conservation financière**

Pour une
livraison :

image

image

image





Pour un
bordereau :

image





où B est le reversement produits, I
l’indemnisation effectivement réglée et N le flux
bancaire signé.

**Exemple COD
:** produits 5
000, livraison client 650 retenue :

- encaissé : 5 650 ; 
- frais client : 650 ; 
- reversable produits : 5 000 ; 
- premier reversement : 3 000 ; 
- reste dû : 2 000. 

Si 300 de
frais commerçant sont compensés sur le second règlement : banque +1 700,
reversement produits +2 000, frais réglés 300. La charge n’est pas inscrite une
deuxième fois dans depenses.

**Frais seuls
:** retour
impayé, frais commerçant 300 : B=0, I=0, N=−300 une fois réellement payé.

Pour l’avoir
et l’échange :

image





Le plafond
global de l’encaissement initial s’applique également.

**Exemple :** vente payée 10 000, avoir 10 000,
échange à 8 000 :

- compensation : 8 000 ; 
- différence remboursable : 2 000      ; 
- disponible après engagement des      deux : zéro ; 
- une deuxième demande de      remboursement doit échouer. 

Le document
réserve déjà les budgets des brouillons pertinents : **je ne relève pas une
absence générale de protection contre la double consommation des avoirs.**

**Où garantir les invariants**

|      |
| ---- |

**Invariant**

|      |
| ---- |

**Garantie
&#x20;  principale**

|     |
| --- |

Appartenance produit/variante,
&#x20; commande/révision, livraison/retour

|     |
| --- |

FK composites et colonnes
&#x20; obligatoires

|     |
| --- |

Un colis par commande

|     |
| --- |

UNIQUE(livraisons.commande_id)

|     |
| --- |

Soldes locaux non négatifs

|     |
| --- |

CHECK + NOT NULL

|     |
| --- |

Sommes de réservations, plafonds
&#x20; SAV et financiers

|     |
| --- |

Transactions avec verrouillage des
&#x20; parents communs

|     |
| --- |

Immutabilité des faits validés

|     |
| --- |

Triggers/privilèges et services
&#x20; dédiés

|     |
| --- |

Référence centrale encore
&#x20; autorisée

|     |
| --- |

Contrôle central au moment de
&#x20; l’action ; aucune FK locale ne suffit

|     |
| --- |

Absence de double effet distant

|     |
| --- |

Seulement si garantie distante
&#x20; prouvée ; sinon blocage et rapprochement

|     |
| --- |

Concordance du journal et des
&#x20; projections

|     |
| --- |

Contrôle périodique reconstructif,
&#x20; sans correction silencieuse

**F. Corrections ciblées**

**1. Figer la composition des variantes utilisées**

Ajouter une
règle normative à T2/T3 :

Toute
modification de composition, de rattachement d’option ou de signification d’une
valeur utilisée par une variante engagée est interdite. L’archivage et la
création d’une nouvelle identité remplacent la mutation.

Le service
doit verrouiller la variante ; les écritures SQL directes de composition
doivent être protégées. La vérification « existe-t-il déjà un mouvement ? »
faite avant transaction serait insuffisante.

**2. Renforcer les obligations de facturation**

Exemple
conceptuel, à adapter aux migrations et aux règles de révision documentaire :

ALTER TABLE factures

  ADD UNIQUE KEY
uq_facture_contexte

    (id,
commande_id, revision_id, type_document);

ALTER TABLE obligations_facturation

  ADD CONSTRAINT
fk_obligation_document_exact

  FOREIGN KEY

    (facture_id, commande_id, revision_id,
type_document)

  REFERENCES factures

    (id,
commande_id, revision_id, type_document);

Compléter
par :

- commande_id, revision_id, type_document obligatoires ; 
- condition « émise ⇒      facture renseignée » ; 
- contrôle du statut émis du      document ; 
- égalité des références      d’origine pour un avoir ; 
- interdiction de remplacer après      émission le document associé. 

La FK
nullable autorise l’obligation non encore satisfaite ; elle ne remplace pas les
contrôles de transition.

**3. Représenter le trop-payé transporteur**

Avant le
DDL, choisir explicitement :

- comment est constatée une      créance reconnue ; 
- comment un paiement ancien est      réaffecté sans modifier la banque ; 
- comment la créance est apurée      par remboursement ou compensation future. 

Une
éventuelle creances_prestataire doit porter au minimum prestataire,
origine précise, montant, date de reconnaissance, clé métier et preuves. Ses
apurements doivent référencer des règlements réels. **Ne pas créer un
bordereau rapproché fictif pour équilibrer une correction.**

**4. Fermer les portées des exceptions**

Règle
attendue :

permission.portee = plateforme  ⇒ tenant_id IS NULL

permission.portee = tenant      ⇒ tenant_id IS NOT NULL

Un trigger
peut vérifier le parent permissions. Le contrôle applicatif
d’attribution doit également vérifier le délégant, le bénéficiaire, le contexte
et les interdictions prioritaires.

**5. Compléter le contrat de restauration**

Ajouter aux
contrôles de réouverture :

- inventaire des documents déjà      émis hors backup ; 
- borne de numérotation non      réutilisable ; 
- rapprochement des clés      d’émission et de transmission ; 
- génération de contexte      invalidant les anciens workers ; 
- rapport des références      centrales obsolètes ; 
- application des politiques de      rétention courantes. 

Conserver la
suspension tant qu’une divergence financière ou documentaire demeure
inexpliquée.

**6. Fixer les faits utilisés dans les indicateurs**

Définir pour
chaque événement économique :

- identifiant stable ; 
- commande et lignes concernées ;      
- quantité et montant signés ; 
- date d’effet et date      d’enregistrement ; 
- motif et référence de      correction ; 
- lien éventuel avec      facture/avoir, sans confondre les deux faits. 

Cela suffit
pour rendre les statistiques reproductibles ; un système complet d’event
sourcing n’est pas nécessaire.

**Points physiques à conserver et à vérifier**

- Même type, longueur et      collation de part et d’autre des FK. 
- DECIMAL, jamais flottant pour les      montants. 
- UTC pour les instants ; bornes      calendaires en Africa/Algiers. 
- RESTRICT sur les dépendances métier      historiques ; pas de cascade d’un visiteur vers commandes/finance. 
- Création des tables cycliques      puis ajout des FK ; revision_courante_id renseignée dans la phase      transactionnelle prévue. 
- Archivage distinct de      suppression ; restauration d’un catalogue ne doit pas réutiliser une autre      identité. 
- Index du §13 globalement      justifiés par les requêtes annoncées ; leur efficacité reste à mesurer. 

Les
historiques ordonnés doivent utiliser la séquence métier prévue, pas l’ordre
lexical d’un UUID.

**G. Décisions métier restantes**

| Décision | Pourquoi elle change la conception | Hypothèse provisoire |
| --- | --- | --- |
| Quel événement rend la commande contractuellement acceptée ? | Détermine contrat, information de disponibilité et preuve | Conserver le parcours téléphonique technique, sans conclure à sa qualification juridique |
| Quels documents pour chaque remplacement/échange ? | Peut changer type de commande, valeur, avoir et compensation | Activer uniquement les cas dont la règle a été validée |
| Série par boutique ou par entité légale ? | Détermine l’emplacement de l’allocation des numéros | Ne pas figer T20 avant décision |
| Quel RPO/RTO acceptable pour central et boutiques ? | Détermine PITR, archives externes et conditions de reprise | Aucune promesse de perte nulle sans dispositif testé |

Les points suivants ne sont plus des décisions ouvertes dans ce registre :

- la correction économique d’un retour utilise l’événement explicite défini dans **AUD-06** ;
- le trop-payé transporteur devient une créance jusqu’à son apurement réel selon **AUD-02** ;
- le retour physique du MVP porte obligatoirement sur le colis complet selon **AUD-08** ;
- la reprise après perte/restauration de la BDD centrale suit le protocole défini dans **AUD-09**.

Je ne rouvre pas les décisions déjà fixées : DZD, invités, stock par variante, absence de multi-entrepôts, propriétaire immuable et un colis par commande.

**Scalabilité : hypothèse de travail**

À titre de dimensionnement, **1 000 boutiques × 50 commandes/jour** donnent 50 000 commandes/jour. Avec trois lignes par commande et vingt événements logistiques, cela représente environ :

- 150 000 lignes de commande/jour ;
- 1 million d’événements logistiques/jour, avant analytics et audits.

Ces nombres ne démontrent ni saturation ni capacité : ils montrent où mesurer.

Les premiers sujets sont la taille des historiques, la fréquence du polling, les connexions, la durée des migrations sur de nombreuses BDD et les sauvegardes. Le journal central sous verrou tenant peut aussi limiter une boutique très active.

**Aucun besoin démontré ici de sharding, microservices ou event sourcing généralisé.** Des workers bornés, des index mesurés, une rétention appliquée et des migrations progressives doivent précéder ces choix.

La personnalisation avancée du thème peut attendre. En revanche, supprimer les journaux de stock ou les intentions transporteur pour réduire le nombre de tables retirerait des garanties nécessaires.

**H. Plan avant développement**

**1. À résoudre avant les migrations concernées**

1. Figer l’identité physique des variantes utilisées — **AUD-01**.
2. Formaliser les corrections de frais déjà payés — **AUD-02**.
3. Renforcer les liens obligation/document — **AUD-03**.
4. Définir la portée des exceptions — **AUD-05**.
5. Fixer les règles documentaires des remplacements/échanges — **AUD-07**.
6. Définir le fait économique servant aux statistiques — **AUD-06**.
7. Enregistrer les versions exactes MySQL, PHP, Laravel et Tenancy ; produire le DDL réel.

**2. À implémenter et tester pendant le développement**

1. Tests d’intégrité négatifs sur les FK composites, types, NULL et transitions.
2. Tests à deux connexions réelles pour stock, révisions, quotas, avoirs et remboursements.
3. Reconstruction du stock et des soldes financiers depuis les journaux.
4. Arrêts forcés avant/après commit, envoi HTTP et réception de réponse.
5. Tests croisés de boutiques sur requêtes, jobs, caches, fichiers, exports et sessions.
6. Tests de révocation avant exécution des jobs.
7. Migrations progressives avec reprise après échec d’une boutique.

**3. À valider avant lancement**

1. Contrat, facturation, séries, SAV et protection des données applicables aux vendeurs concernés.
2. Capacités réelles DHD/Ecotrack : création, recherche, incertitude, COD zéro, retours, desks, frais, preuves et reversements.
3. Rapprochement complet de cas réels : livré, refusé, perdu, retourné après paiement, reversement partiel, frais corrigés.
4. Restauration tenant après émission de factures et création de colis — **AUD-04**.
5. Reprise de la BDD centrale et récupération des clés — **AUD-09**.
6. Mesure du RPO/RTO et essai de charge selon le volume de lancement.
7. Validation juridique et opérationnelle de la règle MVP « retour physique = colis complet » — **AUD-08**.

**4. Améliorations pouvant attendre**

- Passage des UUID en stockage binaire si les mesures le justifient.
- Agrégats analytiques spécialisés lorsque les requêtes deviennent coûteuses.
- Personnalisation avancée du thème.
- Automatisation des cas SAV complexes actuellement exclus.
- Optimisations d’infrastructure après identification d’un goulot réel.

**La V3 fournit une base de développement sérieuse sur le plan structurel, mais elle ne constitue pas encore une spécification entièrement fermée pour la finance, la facturation et la reprise après incident. Les corrections ci-dessus permettent de poursuivre sans réécrire arbitrairement l’ensemble du modèle.**

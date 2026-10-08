# Diagramme complet de la BDD centrale

**Mise à jour technique du 8 octobre 2026 :** MySQL, Spatie Permission 8.3.0 et Activity Log 5.0.0. `created_at`/`updated_at` proviennent de `timestamps()` et `deleted_at` de `softDeletes()` pour les tables archivables ; ces TIMESTAMP sont nullable. Les dates métier restent des DATETIME. Les migrations de création Permission et Activity Log sont publiées par les commandes officielles Spatie ; deux migrations d'extension centrales conservent les UUID, durées et informations d'audit décrits ici. Les FK conservent RESTRICT ; les index natifs ne sont pas recréés en doublon. La suppression de `protect_central_identifiers_and_ownership` est conservée ; voir la mise à jour technique du schéma principal pour ses conséquences sur les triggers. Les 62 relations FK simples centrales et 192 locales utilisent `foreignId()` avec leur vraie contrainte SQL vers la PK du parent ; cette colonne conserve un ID existant, sans AUTO_INCREMENT. Les index ordinaires et UNIQUE sont définis dans les migrations de leurs tables. Les FK simples et composites dont les parents sont disponibles sont définies au même endroit ; les références vers un parent créé ensuite, notamment le cycle commande/révision, sont ajoutées dans `add_documented_foreign_keys`, avec mention dans la migration de création. Les contrats REF/POLY restent distincts.

Source : [Schema-BDD-SaaS-Ecommerce-UUID.md](Schema-BDD-SaaS-Ecommerce-UUID.md), version V4.10 du 6 octobre 2026. Ce document présente **les 28 tables centrales et leurs 465 champs dans un seul diagramme Mermaid**, puis explique chaque table et chaque champ simplement. Les permissions SaaS portent leurs durées dans les tables existantes ; les quotas viennent des offres. Les migrations correspondantes sont vérifiées sur des bases MySQL de test isolées.

**Identifiants :** `id` est un numéro interne auto-incrémenté (1, 2, 3…), jamais un UUID. `uuid` est un champ distinct et unique pour les liens publics et les références entre BDD. Les FK locales utilisent les ID numériques ; les trois pivots Spatie gardent leurs PK composites. Le contexte technique Tenancy utilise `tenants.id`, avec `id_generator=null`, tandis que les routes publiques utilisent `tenants.uuid`.

**Tailles nécessaires seulement :** les montants se déclarent avec `decimal('champ')` ; `SchemaBlueprint` conserve une fois DECIMAL(14,2), pour les centimes et la capacité documentée. Les formats fixes UUID, SHA-256, ISO et couleur sont conservés. Les booléens s'écrivent `true`/`false` en PHP et `TRUE`/`FALSE` en SQL ; les statuts et compteurs restent numériques. `id()` est déjà unique par sa PK. `features.code` possède son index UNIQUE avec une chaîne sans longueur explicite ; les unicités composites de relations restent nécessaires.

**Déclarations et casts :** `$table->uuid('uuid');` applique les règles communes de `App\Services\SchemaBlueprint` (CHAR(36), ASCII, collation `ascii_bin`, UNIQUE), sur toutes les connexions. Les VARCHAR utilisent la longueur Laravel de 255 sans argument de longueur dans les migrations ; `rememberToken()` garde son format natif. Les dates sont sans microsecondes, sans argument `6`. `tenants.slug` conserve son index UNIQUE et sa validation technique ; `tenants.shop_name` possède un index non unique. Les modèles existants castent les codes documentés vers leurs enums PHP, les indicateurs en booléens, les versions en entiers, les dates métier en dates immuables et le mot de passe via `hashed` ; voir §3.3 du schéma pour les classes.

**Bases et domaines :** une nouvelle base suit `boutique_{slug_initial}`, par exemple `boutique_nour`, sans suffixe d’ID. Son nom est réservé dans `tenants.data.tenancy_db_name` avant le provisionnement et reste stable après renommage. Avec le préfixe `boutique_`, le slug initial est limité à 55 caractères ; les noms trop longs, déjà réservés ou déjà présents sont refusés, sans troncature ni adoption d’une autre base. Les bases existantes ne changent que sur autorisation explicite ; l’utilisateur confirme aussi le retrait du suffixe des deux bases d’essai actuelles. `APP_URL` vaut ici `http://aydra.localhost` et `central_domains` contient explicitement `127.0.0.1`, `localhost` et `aydra.localhost`. `{tenants.slug}.{SAAS_BASE_DOMAIN}` définit le sous-domaine boutique, avec une base dérivée d’APP_URL sauf configuration explicite. Aucune donnée d’essai n’est créée par le seeder normal.

## 1. Les 28 tables expliquées très simplement

**Initialisation des boutiques :** un propriétaire central peut posséder plusieurs lignes `tenants`. Chaque ligne déclenche `TenantProvisioner` après commit : migrations tenant, création de son unique profil `shop` et du compte propriétaire local. Les quatre réglages initiaux peuvent être fournis avant la sauvegarde via `Tenant::configureShopForProvisioning()` ; leurs défauts sont `OTHER`, `default`, `{"primary":"#2563EB","secondary":"#FFFFFF"}` et `7` jours. `tenants.data.tenancy_shop_settings` porte seulement l'intention technique de création jusqu'au succès, puis est libéré ; les réglages courants appartiennent à la BDD tenant. Aucune table centrale supplémentaire ni nouvelle contrainte singleton n'est créée. Le statut de préparation reste distinct de l'activation commerciale.

**Essais locaux :** le seeder explicite `Central\LocalDevelopmentSeeder` peut créer deux propriétaires, un administrateur et deux boutiques, selon le §3.4 du schéma. Le seeder normal reste limité aux référentiels. `/_dev/database` permet de vérifier localement les ID/UUID, liens de propriété, domaines et noms de bases ; accès seulement en `local`/`testing`, avec debug actif et requête depuis la boucle locale. Aucune table ou relation du diagramme n’est modifiée.

| Table | Explication très simple |
|---|---|
| **`countries`** | La liste des pays : Algérie, France, Arabie saoudite, Soudan et Égypte. |
| **`users`** | Les comptes des propriétaires et administrateurs du SaaS : prénom (first_name), nom de famille (last_name), e-mail, mot de passe protégé, téléphone, pays et informations professionnelles prévues. Le nom de chaque boutique est dans tenants.shop_name. |
| **`tenants`** | La liste des boutiques : leur nom, leur propriétaire, leur état et les informations pour retrouver leur propre base de données. |
| **`domains`** | Les adresses Internet des boutiques. Elle indique à quelle boutique appartient chaque adresse. |
| **`contact_verifications`** | Les demandes de vérification d’un e-mail ou d’un téléphone : code protégé, expiration, essais et résultat. |
| **`features`** | Le catalogue des possibilités des abonnements. Exemple : pouvoir créer plusieurs boutiques ou utiliser une fonctionnalité particulière. |
| **`permissions`** | La liste des actions qu’une personne peut être autorisée à faire. Exemple : gérer les offres ou valider un paiement. |
| **`roles`** | Les groupes d’actions autorisées. Chaque rôle a un nom unique ; deux rôles de mêmes actions et mêmes durées sont refusés, même avec des noms différents. |
| **`role_has_permissions`** | Les actions de chaque rôle avec la durée de chacune : 9999 jours par défaut et au maximum, ou une durée plus courte. |
| **`model_has_roles`** | Les rôles attribués à chaque compte et leur date de départ. Plusieurs rôles sont possibles, sans action commune entre eux. |
| **`model_has_permissions`** | Une autorisation directement attribuée à un compte, si ce chemin natif est utilisé, avec ses dates. Elle ne peut pas doubler une action déjà donnée par un rôle. |
| **`admin_restrictions`** | Limite les comptes, boutiques ou rôles sur lesquels un administrateur peut agir dans l’administration centrale. |
| **`plans`** | Les offres d’abonnement proposées aux commerçants, avec leurs versions. Exemple : Gratuit et Pro. Une ancienne version reste conservée pour comprendre les anciens abonnements. |
| **`plan_features`** | Indique ce que chaque offre permet et ses limites. Exemple : l’offre Gratuit autorise 1 boutique et l’offre Pro en autorise 3. |
| **`subscriptions`** | Une seule table contient les abonnements et leurs échéances. Une ligne de type 1 décrit « Karim a le plan Pro ». Ses lignes de type 2 décrivent « Karim doit payer 3 000 DA pour septembre », puis octobre, etc. Les échéances gardent leurs propres UUID, montants, dates et états ; elles ne remplacent pas la ligne d’abonnement. tenant_id reste facultatif sur l’abonnement et désigne une boutique du même propriétaire. |
| **`feature_usage`** | Les quantités déjà utilisées pour une fonctionnalité pendant une période. Elle aide à suivre les limites de l’abonnement. |
| **`geographic_areas`** | Les wilayas et communes dans une seule table. Chaque commune est reliée à sa wilaya, et les zones à leur pays. |
| **`activity_log`** | Le journal des actions de la partie centrale : qui a fait quoi et quand. Exemple : un administrateur a validé un paiement. |
| **`tenant_schema_deployments`** | L’historique de création et de mise à jour technique des bases des boutiques. Exemple : la mise à jour de la boutique de Karim a réussi, tandis qu’une autre doit être réessayée. |
| **`saas_invoices`** | Les factures et les avoirs du SaaS. Chaque fiche indique qui doit payer, pour quel abonnement, la période, les montants et le PDF. Un avoir réduit une facture déjà émise. |
| **`saas_invoice_lines`** | Le détail des factures et des avoirs : description, quantité, prix, remise, taxes et total. Une correction retrouve la vraie ligne de facture d’origine. |
| **`saas_billing_settings`** | Les réglages de la facturation : les règles qui disent quand facturer et les compteurs qui donnent des numéros uniques aux factures et aux avoirs. |
| **`saas_document_deliveries`** | Les envois des factures et des avoirs : à qui, par quel moyen, quand, combien d’essais et si le document a bien été remis. |
| **`saas_transfers`** | Les paiements reçus et remboursements effectués à distance par banque, CCP ou BaridiMob : montant, preuve PDF, référence, auteur et vérification. |
| **`media`** | Les informations pour retrouver les fichiers du SaaS : factures PDF, reçus, preuves, images… Le fichier lui-même est stocké séparément. |
| **`shipping_carriers`** | Le catalogue commun des sociétés et réseaux de livraison : leur nom, leur code et le connecteur serveur prévu. Il ne contient aucun compte privé de boutique. |
| **`carrier_geo_mappings`** | Le dictionnaire commun qui traduit une wilaya ou une commune en code compris par un réseau de livraison. Les exceptions privées d’un compte restent dans sa boutique. |
| **`pickup_points`** | Le catalogue commun des bureaux officiels des transporteurs, avec leur adresse, leur code et leur emplacement. Chaque commerçant choisit séparément les bureaux qu’il autorise. |

## 2. Un seul diagramme pour toute la BDD centrale

**Lecture :** PK = clé primaire ; FK = lien SQL dans cette même BDD ; UK = unicité ; REF = référence UUID externe, sans FK entre bases. u64 = BIGINT UNSIGNED ; u16 = SMALLINT UNSIGNED ; u8 = TINYINT UNSIGNED ; « ? » = champ pouvant être NULL selon sa phase/type. Identifiants d’abord, FK/références ensuite, autres champs après. Les pivots Spatie gardent leurs clés composites natives, sans id/uuid inventés.

**Un « ? » ne signifie pas « toujours facultatif » :** une date de validation peut être vide avant validation, mais devient obligatoire après ; les champs d'abonnement/échéance dépendent du type de ligne, et un avoir exige sa facture d'origine. Les règles du schéma imposent ces obligations. Les colonnes calculées sont remplies par la BDD, sans saisie utilisateur.

Les 62 liens FK couvrent toutes les colonnes marquées FK. Les 29 liens POLY sont conditionnels : subject_type, causer_type ou model_type choisissent un modèle explicitement autorisé ; aucune FK SQL universelle n’est créée. Les traits pleins participent à la PK ; les autres sont pointillés. Les FK composites, types, phases, plafonds et transactions restent obligatoires selon le schéma principal, même si le dessin montre chaque colonne séparément.

Les comptes et pivots utilisent central_user ; les comptes employés restent locaux. Les sujets du nouveau catalogue sont audités au central ; les décisions privées de boutique sont auditées localement. Les PDF fiscaux et preuves de virements restent dans media, avec leurs parents typés et contrôles de §7.6. Aucun bureau central ne contient les clés API, clients ou tarifs privés d’une boutique.

```mermaid
erDiagram
    direction LR

countries {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  char(2) code UK
  varchar name_fr
  varchar name_en
  varchar name_ar "?"
  boolean is_active
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

users {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 country_id FK
  u64 legal_verified_by_id FK "?"
  varchar email UK
  varchar last_name
  varchar first_name "?"
  varchar password
  varchar phone "?"
  datetime email_verified_at "?"
  datetime phone_verified_at "?"
  datetime whatsapp_verified_at "?"
  varchar legal_form "?"
  varchar activity_nature "?"
  varchar nif "?"
  varchar nis "?"
  varchar registration_number "?"
  varchar artisan_card_number "?"
  text legal_address "?"
  decimal share_capital "?"
  u64 legal_profile_version "?"
  u8 legal_verification_status "?"
  datetime legal_verified_at "?"
  varchar locale
  u8 status
  datetime last_login_at "?"
  varchar(100) remember_token "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

tenants {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  varchar slug UK
  varchar document_prefix UK
  varchar internal_label
  varchar shop_name
  u64 profile_version
  varchar creation_key
  char(64) creation_hash
  u8 status
  boolean is_primary
  int activation_priority "?"
  datetime over_quota_since_at "?"
  json data
  varchar schema_version "?"
  datetime provisioned_at "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

domains {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 tenant_id FK
  varchar domain UK
  u8 type
  boolean is_primary
  u8 verification_status
  datetime verified_at "?"
  u8 certificate_status "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

contact_verifications {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  u8 channel
  varchar normalized_destination
  varchar code_hash
  datetime expires_at
  int attempts_count
  datetime consumed_at "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

features {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar code UK
  varchar name
  u8 value_type
  varchar unit "?"
  u8 quota_scope
  u8 period
  boolean is_active
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

permissions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar name
  varchar guard_name
  varchar label
  varchar feature_code "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

roles {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u8 super_admin_slot UK "?"
  char(64) permission_signature UK
  varchar name
  varchar guard_name
  varchar label
  boolean is_system
  boolean is_protected
  boolean is_super_admin
  u64 permission_version
  timestamp created_at "?"
  timestamp updated_at "?"
}

role_has_permissions {
  u64 permission_id PK,FK
  u64 role_id PK,FK
  u16 duration_days
}

model_has_roles {
  u64 role_id PK,FK
  varchar model_type PK
  u64 model_id PK
  datetime assigned_at
}

model_has_permissions {
  u64 permission_id PK,FK
  varchar model_type PK
  u64 model_id PK
  datetime assigned_at
  datetime expires_at
}

admin_restrictions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 admin_id FK
  u64 permission_id FK
  u64 target_tenant_id FK "?"
  u64 target_user_id FK "?"
  u64 target_role_id FK "?"
  u64 created_by_id FK
  u8 effect
  u8 status
  datetime started_at
  datetime ended_at "?"
  varchar normalized_target_type
  u64 normalized_target_id
  u8 active_slot "?"
  datetime expires_at "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

plans {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar code
  int version
  varchar name
  text description "?"
  decimal monthly_price
  decimal annual_price
  boolean is_active
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

plan_features {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 plan_id FK
  u64 feature_id FK
  boolean is_active
  bigint limit "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

subscriptions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  u64 parent_subscription_id FK "?"
  u64 tenant_id FK "?"
  u64 plan_id FK "?"
  u64 assigned_by_id FK "?"
  varchar installment_number UK "?"
  varchar operation_key UK
  u8 record_type
  u8 parent_record_type "?"
  u8 status "?"
  u8 period "?"
  decimal agreed_amount "?"
  datetime started_at "?"
  datetime period_starts_at
  datetime period_ends_at "?"
  datetime trial_ends_at "?"
  datetime ended_at "?"
  boolean auto_renew "?"
  decimal installment_amount "?"
  datetime due_at "?"
  u8 installment_status "?"
  u8 active_owner_slot "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

feature_usage {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  u64 tenant_id FK "?"
  u64 feature_id FK
  datetime period_starts_at
  datetime period_ends_at "?"
  bigint quantity
  timestamp created_at "?"
  timestamp updated_at "?"
}

geographic_areas {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 country_id FK
  u64 parent_id FK "?"
  u8 type
  u8 parent_type "?"
  u64 parent_key
  varchar code
  varchar name_fr
  varchar name_ar "?"
  boolean is_active
  varchar reference_source
  date effective_at
  varchar reference_version
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

activity_log {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 tenant_id FK "?"
  varchar operation_key UK "?"
  u64 subject_id "?"
  u64 causer_id "?"
  varchar log_name "?"
  text description
  varchar subject_type "?"
  varchar event "?"
  varchar causer_type "?"
  json attribute_changes "?"
  json properties "?"
  uuid correlation_id
  u8 origin
  timestamp created_at "?"
  timestamp updated_at "?"
}

tenant_schema_deployments {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 tenant_id FK
  varchar source_version "?"
  varchar target_version
  u8 operation
  u8 status
  int attempt_number
  varchar operation_key
  datetime started_at "?"
  datetime ended_at "?"
  varchar error_code "?"
  text sanitized_error "?"
  uuid correlation_id
  json runtime_versions
  timestamp created_at "?"
  timestamp updated_at "?"
}

saas_invoices {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  u64 subscription_id FK
  u64 installment_id FK
  u64 billing_rule_id FK
  u64 original_invoice_id FK "?"
  u64 sequence_id FK "?"
  u64 document_media_id FK "?"
  varchar number UK "?"
  varchar operation_key UK
  u8 document_type
  u8 subscription_record_type
  u8 installment_record_type
  u8 billing_rule_record_type
  u8 original_invoice_document_type "?"
  u8 sequence_record_type "?"
  json billing_rule_snapshot
  int fiscal_year "?"
  bigint sequence_number "?"
  decimal net_amount
  json taxes
  decimal tax_amount
  decimal total_amount
  char(3) currency
  u8 status
  text reason "?"
  datetime period_starts_at
  datetime period_ends_at
  datetime due_at "?"
  json saas_identity_snapshot
  json customer_identity_snapshot
  datetime issued_at "?"
  datetime cancelled_at "?"
  text cancellation_reason "?"
  uuid correlation_id "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

saas_invoice_lines {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 document_id FK
  u64 user_id FK
  u64 original_invoice_id FK "?"
  u64 original_invoice_line_id FK "?"
  varchar operation_key UK
  u8 document_type
  u8 original_line_document_type "?"
  int line_number
  varchar description
  decimal quantity
  decimal net_unit_price "?"
  decimal net_discount "?"
  decimal net_amount
  json taxes
  decimal tax_amount
  decimal total_amount
  text reason "?"
  uuid correlation_id "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

saas_billing_settings {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 created_by_id FK "?"
  u64 validated_by_id FK "?"
  varchar operation_key UK
  u8 record_type
  u8 document_type "?"
  int fiscal_year "?"
  varchar prefix "?"
  bigint next_number "?"
  u8 sequence_slot "?"
  varchar code "?"
  int version "?"
  varchar trigger_event "?"
  varchar numbering_scope "?"
  json parameters "?"
  u8 policy_status "?"
  text validation_reference "?"
  datetime effective_at "?"
  datetime ends_at "?"
  datetime validated_at "?"
  uuid correlation_id "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

saas_document_deliveries {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  u64 document_id FK
  u64 created_by_id FK "?"
  u64 proof_media_id FK "?"
  varchar operation_key UK
  u8 document_type
  u8 channel
  text encrypted_recipient
  u8 delivery_status
  int attempts_count
  json delivery_attempts "?"
  datetime next_attempt_at "?"
  datetime sent_at "?"
  datetime delivered_at "?"
  varchar provider_reference "?"
  varchar error_code "?"
  datetime sending_started_at "?"
  uuid correlation_id
  timestamp created_at "?"
  timestamp updated_at "?"
}

saas_transfers {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK
  u64 document_id FK
  u64 original_payment_id FK "?"
  u64 credit_note_id FK "?"
  u64 proof_media_id FK "?"
  u64 source_proof_media_id FK "?"
  u64 created_by_id FK "?"
  u64 validated_by_id FK "?"
  u64 performed_by_id FK "?"
  u64 reversal_of_id FK "?"
  char(64) active_transaction_fingerprint UK "?"
  varchar operation_key UK
  u8 record_type
  u8 document_type
  u8 original_payment_record_type "?"
  u8 credit_note_document_type "?"
  u8 transfer_method
  u8 transfer_status
  u8 refund_reason "?"
  decimal amount
  char(3) currency
  text reason "?"
  varchar transfer_reference "?"
  varchar financial_account_key "?"
  char(64) transaction_fingerprint "?"
  text encrypted_transfer_details "?"
  datetime occurred_at "?"
  datetime sending_started_at "?"
  datetime validated_at "?"
  varchar error_code "?"
  uuid correlation_id
  timestamp created_at "?"
  timestamp updated_at "?"
}

media {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 created_by_id FK "?"
  varchar storage_key UK
  u64 model_id
  varchar model_type
  varchar collection_name
  varchar disk
  varchar mime_type
  varchar original_name
  u64 size_bytes
  int width "?"
  int height "?"
  int duration_seconds "?"
  text alt_text "?"
  u8 visibility
  int position
  boolean is_primary
  u8 primary_slot "?"
  char(64) file_hash "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

shipping_carriers {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar code UK
  varchar name
  varchar adapter
  varchar default_api_url "?"
  boolean is_active
  varchar reference_source
  int reference_version
  datetime synced_at "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

carrier_geo_mappings {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 carrier_id FK
  u64 geographic_area_id FK
  u8 zone_type
  varchar external_code
  varchar external_name
  varchar external_province_code
  varchar verification_source
  datetime verified_at "?"
  int mapping_version
  boolean is_active
  datetime synced_at "?"
  timestamp created_at "?"
  timestamp updated_at "?"
}

pickup_points {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 carrier_id FK
  u64 province_id FK
  u64 municipality_id FK "?"
  u8 province_type
  u8 municipality_type "?"
  varchar external_code
  varchar name
  text address
  varchar phone "?"
  varchar map_url "?"
  boolean is_carrier_active
  varchar reference_source
  int reference_version
  datetime synced_at "?"
  timestamp created_at "?"
  timestamp updated_at "?"
  timestamp deleted_at "?"
}

countries ||..o{ users : "FK country_id"
users |o..o{ users : "FK legal_verified_by_id"
users ||..o{ tenants : "FK user_id"
tenants ||..o{ domains : "FK tenant_id"
users ||..o{ contact_verifications : "FK user_id"
permissions ||--o{ role_has_permissions : "FK permission_id"
roles ||--o{ role_has_permissions : "FK role_id"
roles ||--o{ model_has_roles : "FK role_id"
permissions ||--o{ model_has_permissions : "FK permission_id"
users ||..o{ admin_restrictions : "FK admin_id"
permissions ||..o{ admin_restrictions : "FK permission_id"
tenants |o..o{ admin_restrictions : "FK target_tenant_id"
users |o..o{ admin_restrictions : "FK target_user_id"
roles |o..o{ admin_restrictions : "FK target_role_id"
users ||..o{ admin_restrictions : "FK created_by_id"
plans ||..o{ plan_features : "FK plan_id"
features ||..o{ plan_features : "FK feature_id"
users ||..o{ subscriptions : "FK user_id"
subscriptions |o..o{ subscriptions : "FK parent_subscription_id"
tenants |o..o{ subscriptions : "FK tenant_id"
plans |o..o{ subscriptions : "FK plan_id"
users |o..o{ subscriptions : "FK assigned_by_id"
users ||..o{ feature_usage : "FK user_id"
tenants |o..o{ feature_usage : "FK tenant_id"
features ||..o{ feature_usage : "FK feature_id"
countries ||..o{ geographic_areas : "FK country_id"
geographic_areas |o..o{ geographic_areas : "FK parent_id"
tenants |o..o{ activity_log : "FK tenant_id"
tenants ||..o{ tenant_schema_deployments : "FK tenant_id"
users ||..o{ saas_invoices : "FK user_id"
subscriptions ||..o{ saas_invoices : "FK subscription_id"
subscriptions ||..o{ saas_invoices : "FK installment_id"
saas_billing_settings ||..o{ saas_invoices : "FK billing_rule_id"
saas_invoices |o..o{ saas_invoices : "FK original_invoice_id"
saas_billing_settings |o..o{ saas_invoices : "FK sequence_id"
media |o..o{ saas_invoices : "FK document_media_id"
saas_invoices ||..o{ saas_invoice_lines : "FK document_id"
users ||..o{ saas_invoice_lines : "FK user_id"
saas_invoices |o..o{ saas_invoice_lines : "FK original_invoice_id"
saas_invoice_lines |o..o{ saas_invoice_lines : "FK original_invoice_line_id"
users |o..o{ saas_billing_settings : "FK created_by_id"
users |o..o{ saas_billing_settings : "FK validated_by_id"
users ||..o{ saas_document_deliveries : "FK user_id"
saas_invoices ||..o{ saas_document_deliveries : "FK document_id"
users |o..o{ saas_document_deliveries : "FK created_by_id"
media |o..o{ saas_document_deliveries : "FK proof_media_id"
users ||..o{ saas_transfers : "FK user_id"
saas_invoices ||..o{ saas_transfers : "FK document_id"
saas_transfers |o..o{ saas_transfers : "FK original_payment_id"
saas_invoices |o..o{ saas_transfers : "FK credit_note_id"
media |o..o{ saas_transfers : "FK proof_media_id"
media |o..o{ saas_transfers : "FK source_proof_media_id"
users |o..o{ saas_transfers : "FK created_by_id"
users |o..o{ saas_transfers : "FK validated_by_id"
users |o..o{ saas_transfers : "FK performed_by_id"
saas_transfers |o..o{ saas_transfers : "FK reversal_of_id"
users |o..o{ media : "FK created_by_id"
shipping_carriers ||..o{ carrier_geo_mappings : "FK carrier_id"
geographic_areas ||..o{ carrier_geo_mappings : "FK geographic_area_id"
shipping_carriers ||..o{ pickup_points : "FK carrier_id"
geographic_areas ||..o{ pickup_points : "FK province_id"
geographic_areas |o..o{ pickup_points : "FK municipality_id"
users ||--o{ model_has_roles : "POLY model_id ; central_user"
users ||--o{ model_has_permissions : "POLY model_id ; central_user"
users |o..o{ activity_log : "POLY causer_id ; central_user ou NULL"
saas_invoices ||..o{ media : "POLY model_id ; saas_invoice ou saas_credit_note ; document_type 1/2"
saas_transfers ||..o{ media : "POLY model_id ; saas_payment ou saas_refund ; record_type 1/2"
countries |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
users |o..o{ activity_log : "POLY subject_id ; central_user"
tenants |o..o{ activity_log : "POLY subject_id ; tenant"
domains |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
contact_verifications |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
features |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
permissions |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
roles |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
admin_restrictions |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
plans |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
plan_features |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
subscriptions |o..o{ activity_log : "POLY subject_id ; subscription ou subscription_installment ; record_type 1/2"
feature_usage |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
geographic_areas |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
tenant_schema_deployments |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
saas_invoices |o..o{ activity_log : "POLY subject_id ; saas_invoice ou saas_credit_note ; document_type 1/2"
saas_invoice_lines |o..o{ activity_log : "POLY subject_id ; saas_invoice_line ou saas_credit_note_line ; document_type 1/2"
saas_billing_settings |o..o{ activity_log : "POLY subject_id ; saas_sequence ou saas_billing_rule ; record_type 1/2"
saas_document_deliveries |o..o{ activity_log : "POLY subject_id ; saas_document_delivery"
saas_transfers |o..o{ activity_log : "POLY subject_id ; saas_payment ou saas_refund ; record_type 1/2"
media |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
shipping_carriers |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_geo_mappings |o..o{ activity_log : "POLY subject_id si modèle autorisé"
pickup_points |o..o{ activity_log : "POLY subject_id si modèle autorisé"
```

## 3. Les liens entre central et boutique

countries/geographic_areas fournissent pays et zones ; shipping_carriers fournit les réseaux ; carrier_geo_mappings et pickup_points fournissent les codes et bureaux communs. Les boutiques conservent des UUID vérifiés et des snapshots historiques, sans FK SQL vers ce central. Une mise à jour du catalogue ne réécrit pas les colis déjà préparés.

## 4. Chaque champ expliqué simplement

Une ligne est une fiche ; un champ est une case de cette fiche. Un champ calculé est rempli par la BDD, et un lien permet de retrouver une autre fiche. « Vide » signifie NULL dans les cas prévus. Les valeurs d’enums et contraintes exactes restent dans le schéma principal.

### 1. `countries` — 10 champs

La liste des pays : Algérie, France, Arabie saoudite, Soudan et Égypte.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `code` | Le code court du pays. Exemple : DZ pour l’Algérie, FR pour la France. |
| `name_fr` | Le nom affiché en français. |
| `name_en` | Le nom affiché en anglais. |
| `name_ar` | Le nom affiché en arabe. |
| `is_active` | Indique si le pays peut être sélectionné au lancement. L’Algérie est active ; les autres pays restent prévus pour plus tard. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date du retrait du pays, sans effacer sa fiche ; vide tant qu’il n’est pas retiré. |

### 2. `users` — 30 champs

Les comptes des propriétaires de boutiques et des administrateurs du SaaS : nom, e-mail, mot de passe protégé, téléphone, pays… Elle contient aussi les informations professionnelles du propriétaire.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `country_id` | Le pays du compte, à retrouver dans countries. |
| `legal_verified_by_id` | L’administrateur central qui a vérifié les informations professionnelles du propriétaire. |
| `email` | Son adresse e-mail, unique parmi les comptes de cette base. |
| `last_name` | Le nom de famille du titulaire du compte, par exemple Amrani. |
| `first_name` | Le prénom du titulaire du compte, par exemple Karim. |
| `password` | La version protégée, dite hachée, du mot de passe. Elle sert à vérifier la connexion. |
| `phone` | Le numéro de téléphone du compte. |
| `email_verified_at` | La date où l’adresse e-mail a été confirmée. |
| `phone_verified_at` | La date où le numéro de téléphone a été confirmé. |
| `whatsapp_verified_at` | La date où le contact WhatsApp a été confirmé. |
| `legal_form` | La forme juridique déclarée de l’activité ou de l’entreprise. |
| `activity_nature` | Le genre d’activité professionnelle exercée par le propriétaire. |
| `nif` | Le numéro d’identification fiscale du professionnel. |
| `nis` | Le numéro d’identification statistique du professionnel. |
| `registration_number` | Le numéro d’immatriculation professionnelle, par exemple le registre du commerce. |
| `artisan_card_number` | Le numéro de carte d’artisan si ce document concerne cette activité. |
| `legal_address` | L’adresse professionnelle officielle du propriétaire ou de son entreprise. |
| `share_capital` | Le montant du capital de l’entreprise, lorsque cette information s’applique. |
| `legal_profile_version` | Le numéro de version du dossier professionnel. Il augmente quand les informations importantes changent. |
| `legal_verification_status` | L’état de vérification du dossier professionnel. Choix : 1 = en attente ; 2 = vérifié ; 3 = échec ; 4 = expiré ; 5 = incomplet. |
| `legal_verified_at` | La date où le dossier professionnel a été validé. |
| `locale` | La langue choisie pour utiliser le SaaS. Exemple : français ou arabe. |
| `status` | L’état du compte : utilisable, inactif, suspendu ou supprimé. Choix : 1 = actif ; 2 = inactif ; 3 = suspendu ; 4 = supprimé. |
| `last_login_at` | La date de la dernière connexion réussie. |
| `remember_token` | Une clé secrète utilisée pour garder la connexion lorsque la personne choisit de rester connectée. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 3. `tenants` — 20 champs

La liste des boutiques : leur nom, leur propriétaire, leur état et les informations pour retrouver leur propre base de données.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le propriétaire de cette boutique, à retrouver dans users. |
| `internal_label` | Le nom utilisé par les administrateurs pour reconnaître la boutique. |
| `shop_name` | Le nom affiché de la boutique. |
| `slug` | Le morceau lisible de son adresse. Exemple : karim-shoes. |
| `profile_version` | La version des informations de la boutique, pour suivre leur mise à jour dans sa base. |
| `document_prefix` | Le préfixe stable qui distingue les documents de cette boutique. |
| `creation_key` | Le code de la demande de création, pour éviter de créer deux boutiques quand elle est répétée. |
| `creation_hash` | Un résumé calculé du contenu de la demande de création, pour reconnaître une demande identique. |
| `status` | L’état de la boutique : en création, active, inactive, suspendue, hors limite, en échec ou supprimée. Choix : 1 = en préparation ; 2 = actif ; 3 = inactif ; 4 = suspendu ; 5 = hors limite de l’abonnement ; 6 = préparation échouée ; 9 = supprimé. |
| `is_primary` | Indique la boutique principale du propriétaire, notamment lorsqu’une seule boutique peut rester active. |
| `activation_priority` | L’ordre de préférence pour décider quelles boutiques peuvent rester actives dans les limites de l’offre. |
| `over_quota_since_at` | La date depuis laquelle la boutique dépasse les possibilités de l’abonnement. |
| `data` | Les réglages techniques de cette boutique. `tenancy_db_name` garde le nom réel et stable de sa base, par exemple boutique_nour ; renommer la boutique ne change pas ce nom. |
| `schema_version` | La version de la structure de sa base de données. |
| `provisioned_at` | La date où la préparation initiale de la base de la boutique a été terminée. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 4. `domains` — 12 champs

Les adresses Internet des boutiques. Elle indique à quelle boutique appartient chaque adresse.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `tenant_id` | La boutique concernée, à retrouver dans tenants. |
| `domain` | L’adresse Internet complète de la boutique. Exemple : karim-shoes.exemple.com. |
| `type` | Indique si l’adresse est un sous-domaine du SaaS ou un domaine personnalisé. Choix : 1 = sous-domaine ; 2 = domaine personnalisé. |
| `is_primary` | Indique l’adresse principale utilisée pour cette boutique. |
| `verification_status` | L’état de vérification permettant de confirmer que cette adresse peut être utilisée. Choix : 1 = en attente ; 2 = vérifié ; 3 = échec ; 4 = expiré ; 5 = incomplet. |
| `verified_at` | La date où le contrôle de cette adresse a été validé. |
| `certificate_status` | L’état du certificat de sécurité HTTPS de cette adresse. Choix : 1 = en attente ; 2 = actif ; 3 = erreur ; 4 = expiré. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 5. `contact_verifications` — 11 champs

Les demandes de vérification d’un e-mail ou d’un téléphone : code protégé, expiration, essais et résultat.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le compte dont on vérifie l’e-mail ou le téléphone. |
| `channel` | Le moyen utilisé pour envoyer le code de vérification. Choix : 1 = SMS ; 2 = WhatsApp ; 3 = e-mail. |
| `normalized_destination` | L’e-mail ou le téléphone écrit dans un format uniforme, pour toujours reconnaître le même contact. |
| `code_hash` | La version protégée du code envoyé, pour pouvoir vérifier la réponse. |
| `expires_at` | La date et l’heure à partir desquelles cet élément n’est plus valable. |
| `attempts_count` | Le nombre de fois où une réponse au code de vérification a été essayée. |
| `consumed_at` | La date où le bon code a été utilisé. Il ne doit servir qu’une fois. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 6. `features` — 12 champs

Le catalogue des possibilités des abonnements. Exemple : pouvoir créer plusieurs boutiques ou utiliser une fonctionnalité particulière.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `code` | Le code stable qui reconnaît la fonctionnalité dans le SaaS. |
| `name` | Le nom lisible de cette fonctionnalité. |
| `value_type` | Indique si la possibilité se règle par oui/non ou par une quantité maximale. Choix : 1 = oui/non ; 2 = quantité. |
| `unit` | Ce que l’on compte. Exemple : nombre de boutiques. |
| `quota_scope` | Indique si la limite concerne tout le propriétaire ou chaque boutique séparément. Choix : 1 = propriétaire entier ; 2 = boutique. |
| `period` | La période pendant laquelle la quantité est comptée. Choix : 1 = sur toute la durée ; 2 = par jour ; 3 = par mois ; 4 = par année. |
| `is_active` | Indique si cette fonctionnalité fait encore partie du catalogue disponible. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 7. `permissions` — 8 champs

La liste des actions qu’une personne peut être autorisée à faire. Exemple : gérer les offres ou valider un paiement.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `name` | Le nom technique de l’action autorisée. Exemple : saas.plans.manage pour gérer les offres. |
| `guard_name` | L’espace d’authentification auquel ce rôle ou cette permission appartient. Ici : central, pour le SaaS. |
| `label` | Le nom lisible affiché à l’utilisateur, à la place du code technique. |
| `feature_code` | Le code d’une fonctionnalité liée à cette action lorsque cette liaison est prévue. Il reste vide pour les permissions d’administration centrale. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 8. `roles` — 13 champs

Les groupes d’autorisations. Exemple : le rôle « gestionnaire des abonnements » rassemble les actions utiles à ce travail.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `super_admin_slot` | Vaut 1 pour le rôle racine et reste vide pour les autres. Cette valeur calculée empêche d’avoir deux rôles racines dans cette base. |
| `permission_signature` | Une empreinte des actions et de leurs durées, utilisée pour refuser deux rôles identiques même s’ils ont des noms différents. |
| `name` | Le nom technique qui reconnaît ce rôle. |
| `guard_name` | L’espace d’authentification auquel ce rôle ou cette permission appartient. Ici : central, pour le SaaS. |
| `label` | Le nom lisible affiché à l’utilisateur, à la place du code technique. |
| `is_system` | Indique un rôle créé et géré par le système. |
| `is_protected` | Indique un rôle dont les modifications et la suppression sont spécialement limitées. |
| `is_super_admin` | Indique le rôle racine qui donne les droits complets dans l’administration centrale. |
| `permission_version` | Un numéro qui augmente quand les autorisations du rôle changent, pour actualiser les droits gardés en mémoire. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 9. `role_has_permissions` — 3 champs

Indique quelles actions sont autorisées pour chaque rôle. Exemple : un gestionnaire peut attribuer un abonnement.

| Champ | Explication très simple |
|---|---|
| `permission_id` | L’action que ce rôle permet, à retrouver dans permissions. |
| `role_id` | Le rôle qui reçoit cette autorisation, à retrouver dans roles. |
| `duration_days` | Le nombre de jours pendant lesquels cette action est autorisée à partir de l’attribution du rôle. Par défaut et au maximum : 9999 ; minimum : 1. |

### 10. `model_has_roles` — 4 champs

Indique quel compte possède quel rôle. Exemple : Ahmed possède le rôle de gestionnaire.

| Champ | Explication très simple |
|---|---|
| `role_id` | Le rôle attribué à ce compte, à retrouver dans roles. |
| `model_type` | Le type du compte recevant le rôle. Ici, central_user désigne un compte de users. |
| `model_id` | Le numéro du compte qui reçoit ce rôle. Son sens est donné par model_type. |
| `assigned_at` | La date de départ des durées pour ce compte. La même personne ne reçoit pas un nouveau délai lors d’un simple retry. |

### 11. `model_has_permissions` — 5 champs

Donne une autorisation directement à un compte. Exemple : Ahmed peut valider un paiement grâce à une autorisation personnelle.

| Champ | Explication très simple |
|---|---|
| `permission_id` | L’autorisation donnée directement à ce compte, à retrouver dans permissions. |
| `model_type` | Le type du compte recevant l’autorisation. Ici, central_user désigne un compte de users. |
| `model_id` | Le numéro du compte qui reçoit directement l’autorisation. Son sens est donné par model_type. |
| `assigned_at` | La date de début de cette autorisation directe, enregistrée par le serveur. |
| `expires_at` | La date à partir de laquelle cette autorisation ne donne plus accès. Obligatoire, après le début et au maximum 9999 jours plus tard. |

### 12. `admin_restrictions` — 19 champs

Limite les comptes, boutiques ou rôles sur lesquels un administrateur peut agir dans l’administration centrale.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `admin_id` | L’administrateur dont on limite les possibilités. |
| `permission_id` | L’action à laquelle cette restriction s’applique. |
| `target_tenant_id` | La boutique centrale visée par la restriction. |
| `target_user_id` | Le compte central visé par la restriction. |
| `target_role_id` | Le rôle central visé par la restriction. |
| `created_by_id` | Le compte de la personne qui a créé cet élément. |
| `effect` | Indique si l’action est interdite sur cette cible ou autorisée seulement sur les cibles désignées. Choix : 1 = interdire ; 2 = autoriser seulement les cibles désignées. |
| `status` | L’état de cette restriction. Choix : 1 = actif ; 2 = révoqué ; 3 = expiré ; 4 = clôturé. |
| `started_at` | La date et l’heure à partir desquelles cette période ou cette opération commence. |
| `ended_at` | La date et l’heure où cette période ou cette opération se termine. |
| `normalized_target_type` | Une valeur calculée qui précise le genre de cible : boutique, compte, rôle ou ensemble global. |
| `normalized_target_id` | Le numéro calculé de la cible choisie. La valeur 0 représente une restriction globale. |
| `active_slot` | Une valeur calculée pour éviter de répéter la même restriction active pour cet administrateur, cette action et cette cible. |
| `expires_at` | La date et l’heure à partir desquelles cet élément n’est plus valable. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 13. `plans` — 12 champs

Les offres d’abonnement proposées aux commerçants, avec leurs versions. Exemple : Gratuit et Pro. Une ancienne version reste conservée pour comprendre les anciens abonnements.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `code` | Le code stable de l’offre. Il permet de regrouper ses différentes versions. |
| `version` | Le numéro de version de cet élément. Une nouvelle version garde les anciennes informations dans l’historique. |
| `name` | Le nom affiché de l’offre. Exemple : Gratuit ou Pro. |
| `description` | Un texte qui présente cette offre d’abonnement. |
| `monthly_price` | Le prix prévu pour un mois, en dinars algériens au lancement. |
| `annual_price` | Le prix prévu pour une année, en dinars algériens au lancement. |
| `is_active` | Indique si cette version de l’offre peut encore être proposée. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 14. `plan_features` — 8 champs

Indique ce que chaque offre permet et ses limites. Exemple : l’offre Gratuit autorise 1 boutique et l’offre Pro en autorise 3.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `plan_id` | L’offre d’abonnement concernée, à retrouver dans plans. |
| `feature_id` | La fonctionnalité concernée, à retrouver dans features. |
| `is_active` | Indique si cette fonctionnalité est autorisée dans cette offre : oui ou non. |
| `limit` | Le nombre maximum autorisé. Exemple : 3 boutiques. Vide signifie illimité pour un quota autorisé ; pour une fonction oui/non, ce champ reste vide. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 15. `subscriptions` — 26 champs

Une seule table contient les abonnements et leurs échéances. Une ligne de type 1 décrit « Karim a le plan Pro ». Ses lignes de type 2 décrivent « Karim doit payer 3 000 DA pour septembre », puis octobre, etc. Les échéances gardent leurs propres UUID, montants, dates et états ; elles ne remplacent pas la ligne d’abonnement. tenant_id reste facultatif sur l’abonnement et désigne une boutique du même propriétaire.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le propriétaire de l’abonnement et de ses échéances, à retrouver dans users. |
| `parent_subscription_id` | Pour une échéance, l’abonnement auquel elle appartient. Ce champ reste vide sur la ligne d’abonnement. |
| `tenant_id` | La boutique spécialement visée par l’abonnement, si l’on en choisit une. Vide signifie un abonnement au niveau du propriétaire. |
| `plan_id` | L’offre choisie sur la ligne d’abonnement. Une échéance retrouve l’offre depuis son abonnement parent. |
| `assigned_by_id` | L’administrateur qui a attribué l’abonnement. |
| `record_type` | Indique ce que représente la ligne : un abonnement ou une échéance à payer. Choix : 1 = abonnement ; 2 = échéance. |
| `parent_record_type` | Vaut automatiquement 1 pour une échéance, pour garantir que son parent est bien un abonnement. |
| `status` | L’état de l’abonnement. Il reste vide sur une échéance. Choix : 1 = prévu pour plus tard ; 2 = en essai ; 3 = actif ; 4 = expiré ; 5 = annulé. |
| `period` | Indique si l’abonnement suit une période mensuelle ou annuelle. Choix : 1 = par mois ; 2 = par année. |
| `agreed_amount` | Le prix convenu pour l’abonnement lors de son attribution. |
| `started_at` | La date de début de l’abonnement. |
| `period_starts_at` | Le début de la période de droits pour un abonnement, ou de la période facturée pour une échéance. |
| `period_ends_at` | La fin de cette période. Elle peut rester vide pour l’abonnement gratuit ; une échéance garde sa date de fin. |
| `trial_ends_at` | La date de fin de l’essai, lorsque l’abonnement en possède un. |
| `ended_at` | La date où cette attribution d’abonnement a été clôturée. |
| `auto_renew` | Indique si le prochain renouvellement doit être préparé. Les virements restent effectués manuellement. |
| `installment_number` | Le numéro unique et lisible de l’échéance à payer. |
| `installment_amount` | La somme initialement due pour cette échéance, en dinars algériens. |
| `due_at` | La date limite pour régler cette échéance. |
| `installment_status` | Indique si l’échéance attend son règlement, est partiellement payée, soldée ou annulée. Choix : 1 = en attente ; 2 = partiellement payé ; 3 = soldé ; 4 = annulé. |
| `active_owner_slot` | Vaut automatiquement 1 sur l’abonnement actif du propriétaire et reste vide sur les autres lignes. Il empêche deux abonnements actifs en même temps. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 16. `feature_usage` — 10 champs

Les quantités déjà utilisées pour une fonctionnalité pendant une période. Elle aide à suivre les limites de l’abonnement.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le propriétaire dont on compte l’utilisation. |
| `tenant_id` | La boutique dont on compte l’utilisation, si la limite fonctionne par boutique. Vide correspond à un suivi au niveau du propriétaire. |
| `feature_id` | La fonctionnalité concernée, à retrouver dans features. |
| `period_starts_at` | Le début de la période concernée. |
| `period_ends_at` | La fin de la période concernée. |
| `quantity` | La quantité déjà utilisée pendant cette période. Exemple : le nombre d’utilisations d’une fonctionnalité. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 17. `geographic_areas` — 17 champs

Les wilayas et communes dans une seule table. Chaque commune est reliée à sa wilaya, et les zones à leur pays.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `country_id` | Le pays auquel cette wilaya ou cette commune appartient. |
| `parent_id` | Pour une commune, le numéro de sa wilaya. Une wilaya laisse ce champ vide. |
| `type` | Indique si la ligne représente une wilaya ou une commune. Choix : 1 = wilaya ; 2 = commune. |
| `parent_type` | Vaut automatiquement 1 pour une commune, pour imposer un parent de type wilaya. |
| `parent_key` | Une valeur calculée pour classer les codes : le numéro de la wilaya pour une commune, ou 0 pour une wilaya. Elle aide à éviter les doublons. |
| `code` | Le code officiel de cette wilaya ou commune. |
| `name_fr` | Le nom affiché en français. |
| `name_ar` | Le nom affiché en arabe. |
| `is_active` | Indique si cette zone peut être choisie pour une nouvelle adresse. |
| `reference_source` | Le document ou la source officielle d’où viennent les informations géographiques. |
| `effective_at` | La date à partir de laquelle cette version du référentiel géographique s’applique. |
| `reference_version` | La version du référentiel officiel utilisée pour cette zone. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 18. `activity_log` — 17 champs

Le journal des actions de la partie centrale : qui a fait quoi et quand. Exemple : un administrateur a validé un paiement.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `subject_id` | Le numéro de cet objet, à retrouver selon subject_type. |
| `causer_id` | Le numéro du compte qui a réalisé l’action, à retrouver selon causer_type. |
| `tenant_id` | La boutique centrale concernée par l’action, lorsqu’une boutique précise est concernée. |
| `log_name` | La famille de l’action. Exemple : connexions, abonnements ou facturation. |
| `description` | Une phrase lisible qui raconte ce qui s’est passé. |
| `subject_type` | Le type de l’objet sur lequel l’action a porté. Exemple : un abonnement ou une facture. |
| `event` | Le code stable de l’action réalisée. Exemple : subscription_assigned pour un abonnement attribué. |
| `causer_type` | Le type du compte qui a réalisé l’action. Au central : central_user ; un événement système peut laisser ce champ vide. |
| `attribute_changes` | Les champs autorisés qui ont changé, avec leur ancienne et leur nouvelle valeur. |
| `properties` | Les informations utiles pour comprendre l’action : résultat, raison, références ou contexte limité. |
| `operation_key` | Le code unique d’une action précise et de son étape, pour éviter de journaliser deux fois le même résultat. |
| `correlation_id` | Un code partagé entre les différentes étapes d’une même opération, pour pouvoir les retrouver ensemble. |
| `origin` | Indique si l’action vient d’une personne, du système, d’un transporteur ou d’une tâche automatique. Choix : 1 = personne ; 2 = système ; 3 = transporteur ; 4 = tâche automatique. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | Un champ conservé pour la compatibilité du journal. Les activités restent protégées contre les modifications ordinaires. |

### 19. `tenant_schema_deployments` — 17 champs

L’historique de création et de mise à jour technique des bases des boutiques. Exemple : la mise à jour de la boutique de Karim a réussi, tandis qu’une autre doit être réessayée.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `tenant_id` | La boutique concernée, à retrouver dans tenants. |
| `source_version` | La version de la base de la boutique avant cette opération. Elle peut être vide lors de sa première création. |
| `target_version` | La version que la base doit avoir après l’opération. |
| `operation` | Indique s’il s’agit de créer la base ou de mettre à jour sa structure. Choix : 1 = création initiale de la base ; 2 = mise à jour de sa structure. |
| `status` | L’état de cette exécution technique. Choix : 1 = en attente ; 2 = en cours ; 3 = réussi ; 4 = échoué ; 5 = annulé. |
| `attempt_number` | Le numéro de l’essai. Exemple : 2 signifie que l’opération a été tentée une deuxième fois. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `started_at` | La date et l’heure à partir desquelles cette période ou cette opération commence. |
| `ended_at` | La date et l’heure où cette période ou cette opération se termine. |
| `error_code` | Un code court qui indique le genre d’erreur rencontré. |
| `sanitized_error` | Le message d’erreur avec les informations privées retirées. |
| `correlation_id` | Un code partagé entre les différentes étapes d’une même opération, pour pouvoir les retrouver ensemble. |
| `runtime_versions` | Les versions techniques réellement utilisées pendant l’opération, par exemple PHP, Laravel et MySQL. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 20. `saas_invoices` — 38 champs

Les factures et les avoirs du SaaS. Chaque fiche indique qui doit payer, pour quel abonnement, la période, les montants et le PDF. Un avoir réduit une facture déjà émise.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le propriétaire qui reçoit cette facture ou cet avoir, à retrouver dans users. |
| `subscription_id` | L’abonnement lié à la facture ou à l’avoir. Le lien vise une ligne abonnement de subscriptions. |
| `installment_id` | L’échéance précise facturée, à retrouver parmi les lignes échéance de subscriptions. |
| `billing_rule_id` | La version de règle qui a servi au document. Elle vise une ligne RULE, de type 2, dans saas_billing_settings. |
| `original_invoice_id` | La facture d’origine que cet avoir ou sa ligne vient corriger, à retrouver dans saas_invoices. |
| `sequence_id` | Le compteur qui a donné le numéro au document. Il vise une ligne SEQUENCE, de type 1, dans saas_billing_settings. |
| `document_media_id` | Le fichier PDF privé de la facture ou de l’avoir, à retrouver dans media. Sa clé et son empreinte y restent protégées. |
| `document_type` | Le genre de document : 1 = facture ; 2 = avoir. Un avoir réduit le montant d’une facture. |
| `subscription_record_type` | Vaut automatiquement 1 si subscription_id est rempli, pour imposer un vrai abonnement. |
| `installment_record_type` | Vaut automatiquement 2 si installment_id est rempli, pour imposer une vraie échéance. |
| `billing_rule_record_type` | Vaut automatiquement 2 pour imposer une vraie règle de facturation. |
| `original_invoice_document_type` | Vaut automatiquement 1 quand une facture d’origine est indiquée, pour imposer une vraie facture ; reste vide sans cette origine. |
| `sequence_record_type` | Vaut automatiquement 1 quand un compteur de numérotation est indiqué ; reste vide avant réservation du numéro. |
| `billing_rule_snapshot` | La copie exacte de la règle appliquée, gardée pour comprendre une ancienne facture même si les règles changent. |
| `fiscal_year` | L’année de la série qui a donné son numéro au document. Elle reste vide avant la réservation de ce numéro. |
| `sequence_number` | Le nombre réservé dans le compteur pour cette facture ou cet avoir. |
| `number` | Le numéro complet et lisible du document. Exemple : FAC-2026-000123. |
| `net_amount` | Le total du document avant les taxes, égal à la somme de ses lignes. |
| `taxes` | La ventilation historique des taxes du document, avec leurs bases, taux et montants. |
| `tax_amount` | Le total des taxes du document, égal à la somme des taxes de ses lignes. |
| `total_amount` | Le total du document avec les taxes, égal à la somme de ses lignes. |
| `currency` | La monnaie du document. Au lancement : DZD, le dinar algérien. |
| `status` | L’état du document : 1 = brouillon ; 2 = émis ; 3 = annulé ; 4 = en préparation du PDF. |
| `reason` | La raison de l’avoir. Une facture ordinaire laisse ce champ vide. |
| `period_starts_at` | Le début de la période couverte par la facture ou l’avoir. |
| `period_ends_at` | La fin de la période couverte par la facture ou l’avoir. |
| `due_at` | La date limite de paiement de la facture. Un avoir laisse ce champ vide. |
| `saas_identity_snapshot` | La copie de l’identité professionnelle du SaaS au moment de l’émission du document. |
| `customer_identity_snapshot` | L’identité figée du propriétaire qui paie : first_name, last_name et informations professionnelles applicables. Le nom de boutique éventuel est celui du tenant concerné ; les nouveaux formats ne contiennent plus legal_name ni tax_regime. |
| `issued_at` | La date officielle d’émission de la facture ou de l’avoir. |
| `cancelled_at` | La date où le brouillon de document a été annulé. |
| `cancellation_reason` | Le texte qui explique pourquoi ce brouillon a été annulé. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `correlation_id` | Un code partagé avec les autres étapes de la même facturation, pour les retrouver ensemble. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 21. `saas_invoice_lines` — 22 champs

Le détail des factures et des avoirs : description, quantité, prix, remise, taxes et total. Une correction retrouve la vraie ligne de facture d’origine.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `document_id` | La facture ou l’avoir auquel cette ligne appartient, à retrouver dans saas_invoices. |
| `user_id` | Le même propriétaire que celui du document. Cette référence empêche de mélanger les clients. |
| `original_invoice_id` | La facture d’origine que cet avoir ou sa ligne vient corriger, à retrouver dans saas_invoices. |
| `original_invoice_line_id` | La vraie ligne de facture corrigée par cette ligne d’avoir, à retrouver dans saas_invoice_lines. Une ligne de facture laisse ce champ vide. |
| `document_type` | La même nature que le document parent : 1 = ligne de facture ; 2 = ligne d’avoir. Le serveur fixe cette valeur. |
| `original_line_document_type` | Vaut automatiquement 1 quand une ligne d’origine est indiquée, pour imposer une ligne de facture ; reste vide sans cette origine. |
| `line_number` | La position de cette ligne dans la facture ou l’avoir. Exemple : 1 pour la première ligne. |
| `description` | Le texte qui explique ce qui est facturé ou corrigé sur cette ligne. |
| `quantity` | La quantité facturée ou corrigée sur cette ligne. |
| `net_unit_price` | Le prix d’une unité avant les taxes, sur une ligne de facture. |
| `net_discount` | La réduction appliquée avant les taxes sur cette ligne de facture. |
| `net_amount` | Le montant de cette ligne avant les taxes. |
| `taxes` | Le détail historique des taxes appliquées sur cette ligne. |
| `tax_amount` | Le total des taxes de cette ligne. |
| `total_amount` | Le montant de cette ligne avec les taxes. |
| `reason` | La raison de cette ligne d’avoir. Une ligne de facture laisse ce champ vide. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `correlation_id` | Un code pour retrouver cette ligne avec les autres étapes de la même facturation ou correction. Il peut rester vide. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 22. `saas_billing_settings` — 24 champs

Les réglages de la facturation : les règles qui disent quand facturer et les compteurs qui donnent des numéros uniques aux factures et aux avoirs.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `created_by_id` | Le compte de la personne qui a créé cet élément. Il peut rester vide pour une création du système. |
| `validated_by_id` | L’administrateur qui a validé cette version de règle. Un compteur laisse ce champ vide. |
| `record_type` | Le rôle de ce réglage : 1 = compteur de numérotation ; 2 = règle de facturation. |
| `document_type` | Pour un compteur, le document qu’il numérote : 1 = facture ; 2 = avoir. Une règle laisse ce champ vide. |
| `fiscal_year` | L’année à laquelle ce compteur de numéros s’applique. Une règle laisse ce champ vide. |
| `prefix` | Le début ajouté devant les numéros de cette série. Exemple : FAC. |
| `next_number` | Le prochain nombre à réserver dans ce compteur. |
| `sequence_slot` | Vaut automatiquement 1 pour un compteur et reste vide pour les autres lignes. Il permet un seul compteur par année et genre de document. |
| `code` | Le code stable de la règle de facturation, pour regrouper ses différentes versions. |
| `version` | Le numéro de version de cette règle. Les anciens documents gardent leur ancienne version. |
| `trigger_event` | L’événement qui déclenche la règle. Exemple : une nouvelle échéance à facturer. |
| `numbering_scope` | Indique que les numéros sont ceux des documents émis par le SaaS. La valeur prévue est saas_issuer. |
| `parameters` | Les réglages de cette version de règle : conditions, document à produire et paramètres autorisés. |
| `policy_status` | L’état de la règle de facturation. Choix : 1 = brouillon ; 2 = validé ; 3 = actif ; 4 = retiré. |
| `validation_reference` | Le texte ou la référence qui justifie la validation de cette règle. |
| `effective_at` | La date à partir de laquelle cette version de règle peut s’appliquer. |
| `ends_at` | La date où cette version de règle cesse de s’appliquer. |
| `validated_at` | La date de validation de cette règle. Un compteur laisse ce champ vide. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `correlation_id` | Le code qui relie la création ou la validation de cette règle à ses autres étapes. Il peut rester vide sur un compteur. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 23. `saas_document_deliveries` — 22 champs

Les envois des factures et des avoirs : à qui, par quel moyen, quand, combien d’essais et si le document a bien été remis.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le propriétaire auquel le document envoyé appartient. |
| `document_id` | La facture ou l’avoir déjà émis que l’on transmet, à retrouver dans saas_invoices. |
| `created_by_id` | Le compte de la personne qui a créé cet élément. Il peut rester vide pour une création du système. |
| `proof_media_id` | Le fichier privé qui prouve une remise documentée, s’il existe. Sa clé et son empreinte sont protégées dans media. |
| `document_type` | La même nature que le document envoyé : 1 = facture ; 2 = avoir. Le serveur fixe cette valeur. |
| `channel` | Le moyen utilisé pour transmettre le document. Choix : 1 = e-mail ; 2 = lien par SMS ; 3 = lien par WhatsApp ; 4 = remise avec preuve. |
| `encrypted_recipient` | Les coordonnées du destinataire conservées de manière protégée. Exemple : l’e-mail recevant la facture. |
| `delivery_status` | L’état de l’envoi du document, pour suivre son départ et sa réception. Choix : 1 = en attente ; 2 = en cours ; 3 = envoyé ; 4 = remis ou reçu ; 5 = échec pouvant être retenté ; 6 = échec définitif ; 7 = résultat incertain ; 8 = annulé. |
| `attempts_count` | Le nombre d’essais d’envoi de ce document. |
| `delivery_attempts` | L’historique technique limité des essais d’envoi et de leurs résultats. |
| `next_attempt_at` | La date prévue pour un nouvel essai d’envoi autorisé. |
| `sent_at` | La date où le document a été envoyé. |
| `delivered_at` | La date où sa remise ou sa réception a été confirmée. |
| `provider_reference` | La référence de l’envoi donnée par le service de communication. |
| `error_code` | Le code d’une erreur rencontrée pendant l’envoi. |
| `sending_started_at` | La date enregistrée juste avant de tenter l’envoi du document. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `correlation_id` | Le code qui relie cet envoi aux autres étapes de la même facturation. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 24. `saas_transfers` — 35 champs

Les paiements reçus et remboursements effectués à distance par banque, CCP ou BaridiMob : montant, preuve PDF, référence, auteur et vérification.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `user_id` | Le propriétaire concerné par ce paiement ou ce remboursement. |
| `document_id` | La facture concernée par ce paiement ou remboursement, à retrouver dans saas_invoices. |
| `original_payment_id` | Le paiement vérifié qui finance ce remboursement. Il vise un PAYMENT de type 1 dans saas_transfers ; un paiement laisse ce champ vide. |
| `credit_note_id` | L’avoir émis qui justifie un remboursement lorsque le prix facturé a diminué. Il vise document_type=2 dans saas_invoices ; un simple trop-payé peut laisser ce champ vide. |
| `proof_media_id` | Le fichier privé de preuve, à retrouver dans media. Sa clé et son empreinte sont conservées et protégées dans media. |
| `source_proof_media_id` | La photo originale du reçu dans media lorsqu’un PDF a été fabriqué à partir de cette photo. |
| `created_by_id` | Le compte qui a déclaré le paiement ou préparé le remboursement. Le préparateur d’un remboursement est un administrateur. |
| `validated_by_id` | L’administrateur qui a vérifié les fonds réellement reçus ou reversés. |
| `performed_by_id` | L’administrateur qui a réellement effectué le virement de remboursement. |
| `reversal_of_id` | L’écriture financière d’origine que l’on corrige en ajoutant un montant opposé. L’original reste conservé. |
| `record_type` | Le sens de l’opération : 1 = paiement reçu du client ; 2 = remboursement versé par le SaaS. |
| `document_type` | Vaut automatiquement 1, pour garantir que le virement est relié à une facture. |
| `original_payment_record_type` | Vaut automatiquement 1 quand original_payment_id est rempli, pour imposer un vrai paiement source ; reste vide sans paiement d'origine. |
| `credit_note_document_type` | Vaut automatiquement 2 quand credit_note_id est rempli, pour imposer un vrai avoir ; reste vide sans avoir lié. |
| `transfer_method` | Le moyen utilisé pour effectuer le paiement ou le remboursement à distance. Choix : 1 = virement bancaire ; 2 = virement CCP ; 3 = BaridiMob ; 4 = autre transfert manuel. |
| `transfer_status` | L’état du virement. Une correction par écriture inverse conserve l’original et son historique. Choix : 1 = déclaré ; 2 = approuvé ; 3 = vérifié ; 4 = refusé ; 5 = annulé ; 6 = résultat incertain ; 7 = corrigé par une écriture inverse. |
| `refund_reason` | Le genre de raison qui justifie le remboursement. Choix : 1 = avoir ; 2 = trop-payé ; 3 = transfert reçu en double ; 4 = exception approuvée. |
| `amount` | Le montant du paiement ou du remboursement. Il est positif pour un virement ordinaire ; un montant négatif sert uniquement à corriger une écriture. |
| `currency` | La monnaie du virement, identique à celle de la facture. Au lancement : DZD, le dinar algérien. |
| `reason` | Le motif du remboursement, du refus, du résultat incertain ou de la correction. Il peut rester vide sur une simple déclaration de paiement. |
| `transfer_reference` | La référence réelle donnée pour le virement bancaire, CCP ou BaridiMob. |
| `financial_account_key` | Le code interne qui reconnaît le compte bancaire ou CCP utilisé par le SaaS. |
| `transaction_fingerprint` | Un résumé calculé du réseau, du compte, du sens et de la référence du virement, pour reconnaître la même transaction. |
| `active_transaction_fingerprint` | Cette empreinte est activée automatiquement pour un virement vérifié et non corrigé par un inverse. Elle empêche de compter deux fois la même transaction ; reste vide dans les autres cas. |
| `encrypted_transfer_details` | Les informations bancaires nécessaires au transfert, conservées de manière protégée. |
| `occurred_at` | La date réelle du virement, contrôlée lors de sa vérification. |
| `sending_started_at` | La date enregistrée avant de faire le virement manuel de remboursement. Un paiement reçu laisse ce champ vide. |
| `validated_at` | La date où l’administrateur a confirmé la vérification réelle de ce virement. |
| `error_code` | Le code du problème rencontré lorsqu’un virement a un résultat incertain. |
| `operation_key` | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| `correlation_id` | Le code qui relie les étapes de ce paiement, remboursement ou correction. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |

### 25. `media` — 23 champs

Les informations pour retrouver les fichiers du SaaS : factures PDF, reçus, preuves, images… Le fichier lui-même est stocké séparément.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, attribué par auto-incrémentation : 1, 2, 3… Il n’est jamais envoyé au client. |
| `uuid` | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| `model_id` | Le numéro de cet objet dans sa table, choisi selon model_type. |
| `created_by_id` | Le compte de la personne qui a créé cet élément. |
| `model_type` | Le type de l’objet auquel le fichier appartient. Exemple : facture SaaS ou paiement. |
| `collection_name` | Le groupe d’utilisation du fichier. Exemple : facture, reçu, preuve ou logo. |
| `disk` | L’espace de stockage dans lequel le fichier se trouve, local ou distant. |
| `storage_key` | La clé ou le chemin qui permet de retrouver ce fichier dans cet espace. |
| `mime_type` | Le format du fichier. Exemple : application/pdf pour un PDF. |
| `original_name` | Le nom du fichier lorsqu’il a été envoyé. |
| `size_bytes` | La taille du fichier, mesurée en octets. |
| `width` | La largeur de l’image ou de la vidéo, en pixels. |
| `height` | La hauteur de l’image ou de la vidéo, en pixels. |
| `duration_seconds` | La durée de la vidéo, en secondes. |
| `alt_text` | Un texte qui décrit l’image, utile notamment aux personnes qui ne peuvent pas la voir. |
| `visibility` | Indique si le fichier est public ou accessible seulement après autorisation. Choix : 1 = public ; 2 = privé. |
| `position` | L’ordre d’affichage du fichier dans son groupe. |
| `is_primary` | Indique si ce fichier est le principal pour cet objet et ce groupe. |
| `primary_slot` | Vaut automatiquement 1 pour un fichier principal non supprimé. Il empêche deux fichiers principaux dans le même groupe du même objet. |
| `file_hash` | L’empreinte calculée à partir du fichier, pour vérifier que son contenu n’a pas changé. |
| `created_at` | La date et l’heure où cette ligne a été créée. |
| `updated_at` | La date et l’heure de la dernière modification de cette ligne. |
| `deleted_at` | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 26. `shipping_carriers` — 13 champs

Le catalogue commun des sociétés et réseaux de livraison : leur nom, leur code et le connecteur serveur prévu. Il ne contient aucun compte privé de boutique.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de ce réseau de livraison. |
| `uuid` | Son identifiant public commun à toutes les boutiques. |
| `code` | Son code stable, par exemple ecotrack ou dhd. |
| `name` | Le nom de la société ou du réseau. |
| `adapter` | Le code d’un connecteur autorisé installé sur le serveur ; aucun script libre. |
| `default_api_url` | L’adresse API publique proposée par défaut, si elle est connue et contrôlée. |
| `is_active` | Indique si de nouvelles livraisons peuvent utiliser ce réseau. |
| `reference_source` | La source vérifiée de ces informations communes. |
| `reference_version` | La version du référentiel publiée après validation. |
| `synced_at` | La date de dernière actualisation vérifiée. |
| `created_at` | La date d’ajout de ce réseau. |
| `updated_at` | La date de dernière actualisation de sa fiche. |
| `deleted_at` | Sa date d’archivage, sans supprimer les anciennes références. |

### 27. `carrier_geo_mappings` — 15 champs

Le dictionnaire commun qui traduit une wilaya ou une commune en code compris par un réseau de livraison. Les exceptions privées d’un compte restent dans sa boutique.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette correspondance. |
| `uuid` | Son identifiant public stable. |
| `carrier_id` | Le réseau qui utilise ce code géographique. |
| `geographic_area_id` | La wilaya ou la commune centrale concernée. |
| `zone_type` | Indique si cette zone est une wilaya ou une commune ; doit correspondre à la zone centrale. |
| `external_code` | Le code réellement attendu par ce réseau pour cette zone. |
| `external_name` | Le nom utilisé par le réseau pour cette zone. |
| `external_province_code` | Le code de la wilaya chez ce réseau, même pour une commune. |
| `verification_source` | La source utilisée pour vérifier cette correspondance commune. |
| `verified_at` | La date de vérification ; vide signifie qu’elle ne suffit pas pour un envoi. |
| `mapping_version` | La version de cette traduction ; une requête préparée garde la version choisie. |
| `is_active` | Indique si cette traduction est disponible pour de nouvelles requêtes. |
| `synced_at` | La date de dernière synchronisation validée. |
| `created_at` | La date d’ajout de cette correspondance. |
| `updated_at` | La date de dernière actualisation autorisée. |

### 28. `pickup_points` — 19 champs

Le catalogue commun des bureaux officiels des transporteurs, avec leur adresse, leur code et leur emplacement. Chaque commerçant choisit séparément les bureaux qu’il autorise.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de ce bureau central. |
| `uuid` | L’identifiant public du bureau conservé dans les commandes des boutiques. |
| `carrier_id` | Le réseau de livraison auquel appartient ce bureau. |
| `province_id` | La wilaya centrale du bureau. |
| `municipality_id` | Sa commune centrale, si la source permet de l’identifier précisément. |
| `province_type` | La valeur calculée qui impose une wilaya pour province_id. |
| `municipality_type` | La valeur calculée qui impose une commune pour municipality_id. |
| `external_code` | Le code du bureau attendu par ce réseau ; unique dans ce réseau. |
| `name` | Le nom du bureau affiché aux clients. |
| `address` | L’adresse publique de ce bureau. |
| `phone` | Son numéro de contact public, si disponible. |
| `map_url` | Le lien public pour le trouver sur une carte. |
| `is_carrier_active` | Indique si le transporteur propose encore ce bureau ; cela ne remplace pas le choix du commerçant. |
| `reference_source` | La source contrôlée de cette fiche publique. |
| `reference_version` | La version de la fiche du bureau utilisée pour les nouveaux choix. |
| `synced_at` | La date de dernière synchronisation de cette fiche. |
| `created_at` | La date d’ajout de ce bureau. |
| `updated_at` | La date de dernière actualisation de sa fiche. |
| `deleted_at` | La date d’archivage ; les commandes antérieures gardent leur snapshot. |

## 5. Décisions et contrôles de cette version

| Données | Emplacement |
|---|---|
| Pays et zones | countries/geographic_areas centraux, conservés |
| Réseaux publics | shipping_carriers central |
| Codes de zones et bureaux officiels communs | carrier_geo_mappings/pickup_points centraux |
| Comptes API, clés, tarifs négociés, exceptions de compte et bureaux masqués | BDD de chaque boutique |
| Colis, acheteurs et argent de boutique | BDD de chaque boutique |

Un bureau est utilisable seulement si le réseau le propose encore, le commerçant l’autorise et le compte transporteur réel le valide. Les codes privés ne deviennent jamais un référentiel public. Les UUID sont stables ; les versions/sources sont enregistrées et les anciens snapshots restent intacts. La facturation SaaS conserve ses cinq tables, ses documents immuables et ses paiements/remboursements manuels à distance.

## 6. Contraintes à respecter avec le dessin

Les règles suivantes complètent obligatoirement le diagramme ; elles sont identiques aux contrats centraux C2.1/C2.2 du schéma principal.

### C2.1 — Durées et règles communes des attributions

Ces règles sont appliquées séparément dans la BDD centrale et dans chaque BDD boutique. Elles ont la même structure, mais aucune attribution, date, signature ou décision d’autorisation n’est synchronisée entre ces BDD. Le guard central ne donne jamais une permission tenant ; les noms/compositions de rôles sont uniques dans leur propre BDD seulement.

| Table/champ dans chaque BDD | Rôle |
|---|---|
| roles.permission_signature | Empêche deux rôles de même ensemble de permissions et de mêmes durées, indépendamment de leur nom et de l’ordre de saisie. |
| role_has_permissions.duration_days | Durée de chaque action du rôle : SMALLINT UNSIGNED NOT NULL DEFAULT 9999, CHECK entre 1 et 9999 inclus. |
| model_has_roles.assigned_at | Début du compteur propre au compte qui reçoit ce rôle ; DATETIME NOT NULL, UTC. |
| model_has_permissions.assigned_at / expires_at | Dates obligatoires d’une attribution directe native, si ce chemin est utilisé ; il ne crée ni exception ni priorité sur un rôle. |

**Rôle et personne :** les durées font partie du rôle. Pour donner une action supplémentaire ou une durée différente à une seule personne, créer/utiliser un autre rôle de composition différente, puis remplacer l’attribution concernée atomiquement. Deux utilisateurs recevant le même rôle à des dates différentes ont des échéances différentes. Modifier un rôle existant change sa règle pour tous ses bénéficiaires, avec contrôle et audit de cet impact ; cela ne déplace pas leurs dates d’attribution. Un nom identique est toujours refusé, même si les permissions diffèrent ; deux compositions identiques sont aussi refusées. Deux compositions qui ne diffèrent que par une durée sont différentes.

**Période effective :** pour une permission du rôle, début=model_has_roles.assigned_at et fin=DATE_ADD(assigned_at, INTERVAL duration_days DAY). L’autorisation vaut sur [début,fin) : à l’instant exact de fin, elle ne vaut plus. Chaque permission expire indépendamment des autres. 9999 est un nombre réel de jours, environ 27 ans et 4 mois, pas un code « illimité ». La BDD et le service exigent 1<=duration_days<=9999. Une attribution directe est bornée par CHECK(expires_at>assigned_at AND expires_at<=DATE_ADD(assigned_at,INTERVAL 9999 DAY)) ; son service utilise 9999 jours par défaut. Les dates de début sont produites par le serveur au moment de l’attribution, jamais par un formulaire libre. Aucun calcul d’expiration utilisant NOW() n’est une colonne générée.

**Plusieurs rôles, aucun recoupement :** un compte peut recevoir plusieurs rôles dans sa BDD, mais les ensembles de permissions de tous ses rôles encore attribués sont deux à deux disjoints. Le contrôle porte sur la composition, même si une permission a déjà expiré ; enlever/remplacer explicitement l’attribution qui bloque avant une nouvelle attribution. Un droit direct, si utilisé, ne peut pas être aussi présent dans un de ses rôles. Un même rôle n’est attribué qu’une fois par la PK native. Ces règles s’appliquent également quand on ajoute une permission à un rôle déjà attribué : contrôler chacun de ses bénéficiaires et refuser toute la mutation si elle créerait un recoupement. Il n’existe ni priorité entre sources, ni table secondaire ALLOW/DENY de permissions. Root au central et shop-owner dans sa boutique, dont les capacités sont implicites et protégées, ne se cumulent avec aucun autre rôle ou droit direct dans leur BDD. Leur accès privilégié existant n’est pas une délégation ordinaire à durée ; leurs contrôles de compte, contexte, propriété, quotas et état métier restent obligatoires.

**Prolongation et reprise :** changer duration_days conserve assigned_at ; la nouvelle fin reste calculée depuis le début existant, avec le même maximum. Un retry d’attribution retrouve la ligne et ne remet jamais le compteur à zéro. Une réattribution/renouvellement volontaire utilise une intention distincte, motivée et auditée ; elle change assigned_at explicitement et relance toutes les durées de ce rôle. Retirer une attribution révoque immédiatement ses droits, sans toucher aux autres rôles disjoints. Aucun cron n’est nécessaire pour que l’expiration soit effective.

### C2.2 — Contrôles centraux, concurrence et intégration Spatie

**Écriture atomique :** création/modification de rôle, changement de durée, attribution/retrait/remplacement et droit direct passent par le même service sur la connexion centrale. Le rôle root protégé et non supprimable sert de ligne stable de sérialisation des mutations d’autorisation, sans table supplémentaire : verrouiller sa ligne FOR UPDATE, puis les autres rôles et comptes concernés dans l’ordre croissant de leurs id. Après les verrous, relire compositions et attributions en lecture courante, vérifier guard, acteur habilité, nom/signature, durées et absence de recoupement, puis écrire pivots/signature/permission_version et activité dans une transaction. Les UNIQUE SQL ferment les courses de noms/signatures ; les PK ferment les doubles attributions. Un conflit annule toute la mutation. Le rôle root est amorcé une seule fois par le bootstrap protégé avant ce service ; il ne peut être supprimé ou remplacé comme ligne de catalogue. Toutes les voies (UI, API, imports, jobs, seeders ordinaires) respectent ce protocole. Les contrôles inter-lignes exigent le service et une protection dédiée pour tout écrivain SQL de maintenance ; ils ne sont pas prétendus couverts par un simple CHECK. Les imports ne fabriquent pas un début d’attribution depuis la date d’import si la date réelle est inconnue.

**Lecture effective :** le résolveur central vérifie compte actif, bon guard, attribution unique de chaque capacité, date actuelle, restrictions de cible C3 et protections système. Les relations/méthodes d’autorisation Spatie de chaque contexte sont adaptées pour lire dates de pivots et durées ; l’union native non datée ne suffit pas. Configurer register_permission_check_method=false et enregistrer le contrôle personnalisé pour éviter que le Gate::before natif autorise avant le contrôle temporel ; ne jamais combiner un chemin natif non daté et un chemin daté pour la même capacité. Pour une capacité connue saas.*, le hook central rend false en cas d’expiration/absence/blocage et true seulement pour un droit effectif ou root protégé ; ne pas retourner null après un refus temporel. Les actions d’objet des Policies restent évaluées séparément : le hook retourne null pour leurs noms génériques. Les vérifications brutes de présence de rôle/permission ne sécurisent pas une opération datée. Utiliser un catalogue de capacités concrètes, sans permissions wildcard attribuables qui contourneraient les ensembles disjoints. Les invariants métier et l’isolation des boutiques restent obligatoires même pour root. Le contrôle local est décrit en T24.1.

**Caches et audit :** à chaque décision sensible, relire les attributions et leurs dates actuelles ainsi que les versions des rôles, recalculer la validité à l’heure serveur et revalider avant l’effet dans les services concernés. Une version de rôle seule ne détecte pas un retrait de rôle ou un changement de assigned_at ; une relation chargée auparavant n’est pas l’autorité de cette lecture. Les caches ne dépassent jamais la prochaine échéance ; une mutation invalide les relations chargées et les caches concernés après commit, y compris dans les workers. Un cache encore présent ou un cron arrêté ne conserve aucun droit expiré. Les activités d’attribution/révocation/renouvellement/changement de composition ou durée, doublons refusés et délégations refusées ont leurs phases distinctes et propriétés filtrées : utilisateur/rôle/permissions par UUID, anciennes/nouvelles durées et dates, acteur et motif. Les pivots sans id/uuid ne deviennent pas des sujets polymorphes fictifs ; le sujet est le compte ou le rôle et les clés de pivot sont dans les propriétés. Le temps qui passe est contrôlé lors de la décision ; ne pas promettre un événement de révocation ponctuel produit par la seule horloge.

Cette adaptation est un choix du projet, pas une option native de tenancy/teams. Les cinq tables Spatie restent, leurs clés composites restent, et les contrôles temporels et d’unicité sont à implémenter lors du développement. Source technique : [Spatie, contrôle personnalisé](https://github.com/spatie/laravel-permission/blob/main/docs/advanced-usage/custom-permission-check.md), [Spatie, contrôles de permissions](https://github.com/spatie/laravel-permission/blob/main/src/Traits/HasPermissions.php) et [extension des modèles](https://github.com/spatie/laravel-permission/blob/main/docs/advanced-usage/extending.md).


### 6.8 Classifications, références communes et renvoi impayé — V4.8

**Dictionnaire de classement local :** UNIQUE(categories.id,record_type), UNIQUE(categories.record_type,slug) ; FK(categories.parent_id,parent_record_type) → categories(id,record_type), FK(products.category_id,category_record_type) → categories(id,record_type), FK(product_tags.tag_id,tag_record_type) → categories(id,record_type). parent_record_type=CASE WHEN parent_id IS NOT NULL THEN 1 ELSE NULL END ; category_record_type=CASE WHEN category_id IS NOT NULL THEN 1 ELSE NULL END ; tag_record_type=2, tous GENERATED ALWAYS AS (...) STORED. record_type IN (1,2), TAG impose parent_id NULL. Références RESTRICT, types/identité immuables, cycles/auto-parent contrôlés sous verrou par service/trigger avec les colonnes de base. Les helpers calculés ne remplacent pas les CHECK/triggers de forme ; UNIQUE(product_id,tag_id) conserve la relation multiple.

**Référentiel central C10 :** UNIQUE(geographic_areas.id,type), UNIQUE(geographic_areas.id,parent_id,type). FK(carrier_geo_mappings.geographic_area_id,zone_type) → geographic_areas(id,type). province_type=1 et municipality_type=CASE WHEN municipality_id IS NOT NULL THEN 2 ELSE NULL END STORED pour pickup_points. FK(pickup_points.province_id,province_type) → geographic_areas(id,type) ; FK(pickup_points.municipality_id,province_id,municipality_type) → geographic_areas(id,parent_id,type). province_id non NULL ; municipality_id facultatif, mais s’il existe son parent correspond exactement à la wilaya. Les FK simples restent présentes. RESTRICT pour ces liens et ceux au réseau ; pas de cascade/codes réaffectés ni de FK vers une BDD tenant. Index réseau/état/zone et UUID selon lectures. En V4.8, les 27 tables centrales antérieures conservaient tous leurs champs ; V4.9 adapte C1–C4 ; les clés composites ajoutées sur geographic_areas ne changent ni ses valeurs ni son modèle pays/wilaya/commune.

**Bureau central dans les colis locaux :** order_revisions.pickup_point_uuid et shipments.pickup_point_uuid ont exactement le même stockage UUID/collation ; FK locale(shipped_revision_id,order_id,pickup_point_uuid) → order_revisions(id,order_id,pickup_point_uuid), avec UNIQUE parent. Aucun FK local ne cible central.pickup_points ou un pickup_points local retiré. À domicile UUID et snapshot NULL ; stop desk UUID/snapshot requis. CHECK de mode et FK(shipped_revision_id,order_id,delivery_mode) empêchent qu’un NULL fasse disparaître la protection. Le service résout l’UUID central, contrôle réseau du compte, géographie et autorisation boutique avant la révision, puis conserve le choix exact pour l’envoi. Un changement de disponibilité/code du catalogue ne change pas le snapshot historique.

**Renvoi type 4 :** unpaid_resend_slot=CASE WHEN order_type=4 THEN 1 ELSE NULL END STORED ; UNIQUE(original_return_id,unpaid_resend_slot). order_type IN (1,2,4) ; type 4 exige original_order_id et original_return_id non NULL. FK locale(original_return_id,original_order_id) → order_returns(id,order_id), avec UNIQUE parent ; chaîne sans cycle, source différente et origine effectivement expédiée. Type 1 n’a pas d’origine ; type 2 garde ses contrôles incident/SAV. Le retour source ne s’efface pas et ne donne pas deux enfants impayés. La forme de la nouvelle commande, son encaissement source réellement nul, sa réception/inspection, ses quantités et son allocation aux remèdes sont contrôlés sous verrou. original_incident_id/quantity sont tous deux NULL en renvoi sans incident ; s’ils sont renseignés, leur appartenance/valeur reste contrôlée sans limiter le renvoi du colis à cette seule ligne d’incident.

**Prix :** DECIMAL(14,2), return_cost_recovery_amount NOT NULL DEFAULT 0, >=0 ; motif non vide si >0. Montant 0 hors type 4 vérifié par service/trigger contre le parent. customer_shipping_fee >=0 ; 0<=shipping_discount<=customer_shipping_fee ; order_total=applied_subtotal+customer_shipping_fee−shipping_discount+return_cost_recovery_amount ; amount_to_collect=order_total. Les révisions étant immuables, une modification de prix crée une autre révision entière. Aucun ancien crédit, prix différentiel, champ ou table d’affectation d’avoir n’entre dans ce calcul. La dette du retour ancien et le revenu récupéré sur le nouveau dossier restent des faits distincts.

Les restrictions MySQL 8.4 sur les FK de colonnes générées STORED, les actions référentielles et les CHECK restent celles du §6.7 : aucun CASCADE/SET NULL et aucun contrôle NEW/OLD d’une colonne générée. Contrôler la forme à partir des colonnes de base, avec vérifications transactionnelles quand un parent est nécessaire. [Documentation MySQL : colonnes générées](https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html), [clés étrangères](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html), [CHECK](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html).

Les protections centrales C1–C9/§6.7 restent applicables : propriété et souscriptions/échéances typées, correspondance facture/avoir/lignes, compteurs et règles séparés par type, virements/préuves, budgets et contrepassations. En V4.8, les 27 définitions initiales étaient conservées ; C10 ajoutait uniquement les références publiques communes et leurs clés composites.

## 7. Traçabilité et préparation

### Traçabilité V4.8 — décisions confirmées, catalogue partagé et renvois

| Données | Emplacement retenu et motif |
|---|---|
| Pays, wilayas et communes | Central countries/geographic_areas inchangés ; références UUID depuis les boutiques |
| Réseaux et connecteurs de livraison | Central shipping_carriers ; information publique commune, pas de clé API |
| Bureaux officiels et codes géographiques communs | Central pickup_points/carrier_geo_mappings ; une seule copie par réseau/zone |
| Compte transporteur et secrets | Boutique carrier_accounts, réseau référencé par UUID |
| Livreurs employés/propriétaire, autorisations de bureaux et exceptions privées | Boutique shipping_providers et reference_configuration contrôlée |
| Prix client, tarifs négociés et versions de tarif retour | Boutique shipping_rates ; gratuit réel distinct de tarif inconnu |
| Catégories et étiquettes | Boutique categories type 1/2 ; produits séparés et product_tags conservé |
| Produits, variantes, options et composition | Boutique ; identités, prix et stocks gardent leurs contraintes |
| Commandes, anciens colis/retours et renvois | Boutique ; nouvelle commande type 4 et un colis par commande |
| Coût du retour et ajout manuel au renvoi | Ancien carrier_fees pour charge réelle ; nouvelle order_revisions pour prix commercial et motif |
| Factures/avoirs, remboursements payés et corrections économiques | Boutique, faits/pièces/encaissements réels distincts |
| Audit | Journal de la BDD qui exécute l’action ; secrets filtrés, références publiques seulement entre bases |

**Retraits et correspondances :** tags est fusionnée dans categories ; attributes/product_attributes, visitor_preferences, processing_activity_register et exchange_offsets sont retirées du périmètre. Aucun remplacement caché pour préférences ou registre. La description et les champs directs conservés suffisent aux informations produit prévues ; les options vendables restent inchangées. Les anciennes carrier_geo_mappings/pickup_points locales deviennent des références au catalogue central et seules les exceptions réellement privées restent locales. Le code OrderTypeEnum 3 EXCHANGE et AmountKindEnum 4 EXCHANGE_DIFFERENCE sont retirés sans réattribution ; RESEND_UNPAID reçoit 4. V4.9 adapte l’identité et les autorisations centrales ; les cinq tables de facturation et les mécanismes de paiements/remboursements SaaS restent inchangés.

**Préparation de migration, aucune exécution ici :** analyser les données réellement présentes avant bascule. Pour tags → categories, conserver UUID/name/slug/dates/deleted_at avec record_type=2 ; les catégories antérieures reçoivent 1. Réallouer les PK numériques si elles entrent en collision, réécrire product_tags et les morphs tag selon une correspondance vérifiée, conserver les signatures/options/produits et imposer les FK typées. Aucun tag ne devient un produit. Pour références de livraison, rapprocher par réseau officiel/code externe/zone, pas par nom approximatif ; les divergences non vérifiées restent bloquées. Conserver les UUID centraux stables, traduire les anciens pickup_point_id locaux en pickup_point_uuid, et figer les anciens snapshots avant tout retrait. Les désactivations commerçant deviennent disabled_pickup_point_uuids ou ALLOWLIST ; ne pas fusionner les contrats/secrets/tarifs privés. Les comptes transporteur gardent leurs UUID et clés locales ; carrier_uuid provient du réseau identifié, aucune identité ne se devine.

Si des écritures de l’ancien échange payé existent, archiver/réconcilier leurs avoirs, remboursements, affectations et COD avant retrait ; ne jamais les convertir en impayé ni effacer leurs pièces/audits. Aucun crédit payé n’est simplement réduit à 0 et aucun ancien code d’enum n’est réutilisé. Les caractéristiques/preferences/registres déjà présents exigent une décision explicite de conservation documentaire/export si nécessaire avant une suppression physique future ; ce document ne lance aucune suppression. Les anciennes activités/morphs disposent d’une correspondance historique sans rendre les modèles retirés créables. Vérifier commandes/revisions/colis, unicités, budgets, médias/PDF, réservations et projections de stock après toute migration. Conserver les règles de contrepassation exactes, reçus privés, remboursements réels, manquants et plafonds issus des notes professionnelles.

**Sources de décision :** demandes confirmées dans la conversation, truc.txt, notes du dépôt, recherches Laravel/Spatie et historique complet disponible (37 commits jusqu’à 374bbac). Les notes historiques ne remplacent pas les derniers choix métier explicites. Les diagrammes et glossaires décrivent seulement la version active ; les migrations et leurs vérifications MySQL sont détaillées dans la mise à jour technique en tête du document.

Les 37 commits jusqu’à 374bbac et les notes servent à préserver les contraintes déjà identifiées. Ce document décrit la version active ; les anciennes décisions incompatibles restent uniquement dans la traçabilité historique du schéma principal. Aucune donnée ni migration n’est exécutée.

### Traçabilité V4.9 — identités et permissions dans les deux contextes

Décisions confirmées : first_name/last_name pour les personnes centrales et locales, tenants.shop_name pour chaque boutique et shop.shop_name pour sa projection ; retrait de legal_name/tax_regime du profil central courant et des nouveaux formats de snapshot ; retrait de feature_overrides au central et de permission_overrides au central et dans chaque boutique. Les quotas viennent de l’offre et plan_features. Les cinq tables Spatie sont conservées dans chaque BDD ; admin_restrictions reste uniquement centrale. Durées par permission de rôle, 9999 jours par défaut et au maximum ; noms uniques ; compositions identiques en permissions/durées refusées ; plusieurs rôles par compte sans aucune permission commune. La fin est calculée depuis la date d’attribution propre à cette personne. Aucun registre d’exceptions caché ni nouvelle table n’est ajouté.

**Portée et compatibilité :** la dernière instruction étend les règles aux boutiques. La BDD centrale passe de 30 à 28 tables, 465 champs ; chaque boutique passe de 60 à 59 tables, 1005 champs. users, roles et les trois pivots sont adaptés dans chaque contexte ; shop.name devient shop.shop_name sans créer une seconde source publique. Les autres définitions sont conservées. Toutes les boutiques gardent le même schéma, mais leurs rôles/permissions/dates sont indépendants ; les noms/compositions identiques entre BDD sont permis. Les invitations commencent les durées à leur acceptation, conservent un début existant et refusent les recoupements. Identifiants/UUID, guards, audit, propriété, quotas, facturation, stock et référentiels publics gardent leurs protections. PermissionEffectEnum est retiré du catalogue actif ; OverrideStatusEnum reste utilisé par admin_restrictions.

**Préparation future, aucune migration exécutée :** si des données existent dans chaque BDD, établir le sens réel de l’ancien users.name avant sa correspondance vers last_name ; ne pas découper arbitrairement un nom complet. Conserver first_name et les noms publics des boutiques ; shop.name est renommé en shop_name sans changer sa valeur. Ne pas réécrire anciens snapshots/PDF/audits. Pour les attributions, récupérer les dates réelles prouvées ; une date inconnue bloque la bascule jusqu’à une décision explicite, sans prolonger un droit par la date d’import. Calculer signatures, contrôler noms/compositions/recoupements et résoudre les conflits avant UNIQUE/CHECK. Ne pas convertir une ancienne permission temporaire en 9999 jours : reconstruire une composition/durée/datation équivalente quand cela est possible, sinon signaler le cas sans élargir le droit. Une ancienne interdiction exige une décision explicite de retrait/remplacement du droit/rôle ; au central seulement, une restriction de cible compatible peut conserver son sens ; elle n’est pas convertie en autorisation. Les exceptions de quota requièrent une offre/version adaptée et une souscription cohérente. Archiver les décisions remplacées et leurs audits ; aucune suppression physique de donnée ne découle de ces documents.

**Scénarios de conception à vérifier au développement :**

| Cas | Résultat attendu |
|---|---|
| Durée non renseignée, centrale ou locale | 9999 jours ; début serveur enregistré lors de l’attribution |
| Durée 0, négative, 10000 ou NULL | Refus du validateur et du CHECK central |
| Permission à son instant exact d’expiration, cron arrêté/cache présent | Refus immédiat ; les autres permissions du rôle encore valides restent disponibles |
| Même rôle attribué à A puis à B à des dates différentes | Deux débuts propres et fins calculées séparément |
| Deux rôles de même nom, permissions différentes | Refus UNIQUE(name,guard_name) |
| Noms différents, mêmes permissions/durées dans un autre ordre | Même signature canonique ; seconde création refusée, y compris en concurrence |
| Même ensemble de permissions, une durée différente | Composition différente possible sous un nom distinct |
| Deux rôles sans permission commune pour un compte | Attribution permise, durées propres à chaque rôle |
| Deux rôles avec une permission commune, même si elle a expiré dans l’un | Refus ; retrait/remplacement explicite avant attribution |
| Droit direct et rôle accordant la même permission | Refus, sans règle prioritaire |
| Ajout de permission à un rôle utilisé créant un doublon chez un bénéficiaire | Modification entière refusée ; version/signature/pivots/audit de succès inchangés |
| Attribution identique rejouée | Aucun nouveau début ni prolongation implicite |
| Durée du rôle modifiée | Date initiale conservée ; tous les bénéficiaires concernés revalidés et impact audité |
| Renouvellement explicitement demandé et retry | Nouveau début une seule fois ; toutes les durées de ce rôle relancées, motif et audit conservés |
| Root ou shop-owner reçoit un rôle/droit direct supplémentaire dans sa BDD | Refus ; le rôle privilégié demeure unique et protégé |
| Offre particulière demandée pour un commerçant | Nouveau plan/version et plan_features ; aucune feature_overrides centrale |
| Modification du prénom/nom ou nom public de boutique | Identité personnelle dans sa BDD ; nom public central projeté vers shop.shop_name ; pièces déjà émises immuables |


Les scénarios d’invitation, réactivation, concurrence et isolation propres aux boutiques sont détaillés en T24.1 ; les contrôles temporels et la protection des callbacks Spatie sont requis dans les deux modèles utilisateurs.

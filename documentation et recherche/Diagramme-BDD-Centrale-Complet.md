# Diagramme complet de la BDD centrale

Source : [Schema-BDD-SaaS-Ecommerce-UUID.md](Schema-BDD-SaaS-Ecommerce-UUID.md), version V4.5 du 30 septembre 2026. Ce fichier présente **les 27 tables centrales et leurs 442 champs dans un seul diagramme Mermaid**, avec chaque FK déclarée et les liens polymorphes. La facturation comprend cinq tables : saas_invoices est réduite de 97 à 38 champs.

## 1. Les 27 tables expliquées simplement

| Table | Explication très simple |
|---|---|
| **`countries`** | La liste des pays : Algérie, France, Arabie saoudite, Soudan et Égypte. |
| **`users`** | Les comptes des propriétaires de boutiques et des administrateurs du SaaS : nom, e-mail, mot de passe protégé, téléphone, pays… Elle contient aussi les informations professionnelles du propriétaire. |
| **`tenants`** | La liste des boutiques : leur nom, leur propriétaire, leur état et les informations pour retrouver leur propre base de données. |
| **`domains`** | Les adresses Internet des boutiques. Elle indique à quelle boutique appartient chaque adresse. |
| **`contact_verifications`** | Les demandes de vérification d’un e-mail ou d’un téléphone : code protégé, expiration, essais et résultat. |
| **`features`** | Le catalogue des possibilités des abonnements. Exemple : pouvoir créer plusieurs boutiques ou utiliser une fonctionnalité particulière. |
| **`permissions`** | La liste des actions qu’une personne peut être autorisée à faire. Exemple : gérer les offres ou valider un paiement. |
| **`roles`** | Les groupes d’autorisations. Exemple : le rôle « gestionnaire des abonnements » rassemble les actions utiles à ce travail. |
| **`role_has_permissions`** | Indique quelles actions sont autorisées pour chaque rôle. Exemple : un gestionnaire peut attribuer un abonnement. |
| **`model_has_roles`** | Indique quel compte possède quel rôle. Exemple : Ahmed possède le rôle de gestionnaire. |
| **`model_has_permissions`** | Donne une autorisation directement à un compte. Exemple : Ahmed peut valider un paiement grâce à une autorisation personnelle. |
| **`permission_overrides`** | Les autorisations ou interdictions exceptionnelles, avec leurs dates. Exemple : interdire temporairement une action à un compte. |
| **`admin_restrictions`** | Limite les comptes, boutiques ou rôles sur lesquels un administrateur peut agir dans l’administration centrale. |
| **`plans`** | Les offres d’abonnement : Gratuit, Pro, etc., avec leurs prix et leurs versions. |
| **`plan_features`** | Indique les possibilités et limites de chaque offre. Exemple : Gratuit permet 1 boutique, Pro en permet 3. |
| **`subscriptions`** | Les abonnements et leurs échéances à payer. Exemple : Karim possède Pro ; une autre ligne indique qu’il doit payer 3 000 DA pour septembre. |
| **`feature_overrides`** | Les exceptions aux possibilités ou limites d’une offre. Exemple : accorder temporairement une boutique supplémentaire à Karim. |
| **`feature_usage`** | Les quantités déjà utilisées pour une fonctionnalité pendant une période. Elle aide à suivre les limites de l’abonnement. |
| **`geographic_areas`** | Les wilayas et communes dans une seule table. Chaque commune est reliée à sa wilaya, et les zones à leur pays. |
| **`activity_log`** | Le journal des actions de la partie centrale : qui a fait quoi et quand. Exemple : un administrateur a validé un paiement. |
| **`tenant_schema_deployments`** | L’historique des créations et mises à jour des bases de données des boutiques : ce qui a réussi, échoué ou doit être repris. |
| **`saas_invoices`** | Les factures et les avoirs du SaaS. Chaque fiche indique qui doit payer, pour quel abonnement, la période, les montants et le PDF. Un avoir réduit une facture déjà émise. |
| **`saas_invoice_lines`** | Le détail des factures et des avoirs : description, quantité, prix, remise, taxes et total. Une correction retrouve la vraie ligne de facture d’origine. |
| **`saas_billing_settings`** | Les réglages de la facturation : les règles qui disent quand facturer et les compteurs qui donnent des numéros uniques aux factures et aux avoirs. |
| **`saas_document_deliveries`** | Les envois des factures et des avoirs : à qui, par quel moyen, quand, combien d’essais et si le document a bien été remis. |
| **`saas_transfers`** | Les paiements reçus et remboursements effectués à distance par banque, CCP ou BaridiMob : montant, preuve PDF, référence, auteur et vérification. |
| **`media`** | Les informations pour retrouver les fichiers du SaaS : factures PDF, reçus, preuves, images… Le fichier lui-même est stocké séparément. |

## 2. Diagramme central complet

**Lecture :** PK = clé primaire ; FK = clé étrangère SQL ; UK = unicité. Les liens FK indiquent la colonne concernée ; les liens POLY utilisent un identifiant et son type de modèle et ne créent pas de FK SQL universelle. Un trait plein participe à la clé primaire de l’enfant ; les autres traits sont pointillés. Les extrémités distinguent un parent obligatoire ou facultatif et plusieurs lignes enfants possibles. Les FK composites, phases, plafonds et protections des preuves complètent le dessin : voir C8 et §6.7 du schéma principal.

subject_type choisit un modèle central autorisé pour subject_id ; les deux peuvent être NULL. Les liens « si modèle auditable autorisé » sont conditionnels, sans imposer un nouvel alias ni une nouvelle FK. Les pivots Spatie n’autorisent que central_user ; les comptes de boutique restent dans leurs BDD. Les PDF fiscaux sont attachés à saas_invoice/saas_credit_note ; les preuves de virement à saas_payment/saas_refund. Les autres collections ou parents de media suivent les règles explicites de §7.6.

```mermaid
erDiagram
    direction LR

    countries {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        char(2) code UK "ISO 3166-1 alpha-2"
        varchar name_fr
        varchar name_en
        varchar name_ar "nullable"
        boolean is_active
        datetime created_at
        datetime updated_at
    }

    users {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned country_id FK "countries.id"
        bigint_unsigned legal_verified_by_id FK "nullable ; users.id ; administrateur central habilite"
        varchar name
        varchar first_name "nullable"
        varchar email UK
        varchar password
        varchar phone "nullable"
        datetime email_verified_at "nullable"
        datetime phone_verified_at "nullable"
        datetime whatsapp_verified_at "nullable"
        varchar legal_name "nullable ; raison sociale seulement si distincte du nom de la personne"
        varchar legal_form "nullable avant dossier professionnel"
        varchar activity_nature "nullable avant dossier professionnel"
        varchar nif "nullable avant verification"
        varchar nis "nullable selon regime"
        varchar registration_number "nullable selon activite"
        varchar artisan_card_number "nullable selon activite"
        text legal_address "nullable avant verification"
        decimal share_capital "nullable si inapplicable"
        varchar tax_regime "nullable avant verification"
        bigint_unsigned legal_profile_version "nullable pour administrateur ; 1 au premier profil vendeur"
        tinyint_unsigned legal_verification_status "nullable pour administrateur ; VerificationStatusEnum"
        datetime legal_verified_at "nullable"
        varchar(10) locale
        tinyint_unsigned status "UserStatusEnum ; DEFAULT 1"
        datetime last_login_at "nullable"
        varchar(100) remember_token "nullable"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    tenants {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id ; propriétaire immuable"
        varchar internal_label
        varchar shop_name
        varchar(63) slug UK
        bigint_unsigned profile_version
        varchar(32) document_prefix UK
        varchar creation_key
        char(64) creation_hash
        tinyint_unsigned status "TenantStatusEnum"
        boolean is_primary
        int activation_priority "nullable"
        datetime over_quota_since_at "nullable"
        json data
        varchar schema_version "nullable"
        datetime provisioned_at "nullable"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    domains {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned tenant_id FK "tenants.id"
        varchar(253) domain UK
        tinyint_unsigned type "DomainTypeEnum"
        boolean is_primary
        tinyint_unsigned verification_status "VerificationStatusEnum"
        datetime verified_at "nullable"
        tinyint_unsigned certificate_status "nullable ; CertificateStatusEnum"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    contact_verifications {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id"
        tinyint_unsigned channel "ContactChannelEnum"
        varchar normalized_destination
        varchar code_hash
        datetime expires_at
        int attempts_count
        datetime consumed_at "nullable"
        datetime created_at
        datetime updated_at
    }

    features {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        varchar(100) code UK
        varchar name
        tinyint_unsigned value_type "FeatureValueTypeEnum"
        varchar unit "nullable"
        tinyint_unsigned quota_scope "QuotaScopeEnum"
        tinyint_unsigned period "QuotaPeriodEnum"
        boolean is_active
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    permissions {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        varchar(125) name "nom technique de capacité"
        varchar(32) guard_name "central"
        varchar label
        varchar(100) feature_code "nullable ; code features central"
        datetime created_at
        datetime updated_at
    }

    roles {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        varchar(125) name
        varchar(32) guard_name "central"
        varchar label
        boolean is_system
        boolean is_protected
        boolean is_super_admin
        tinyint_unsigned super_admin_slot UK "generated nullable ; 1 si is_super_admin"
        bigint_unsigned permission_version
        datetime created_at
        datetime updated_at
    }

    role_has_permissions {
        bigint_unsigned permission_id PK,FK "permissions.id"
        bigint_unsigned role_id PK,FK "roles.id"
    }

    model_has_roles {
        bigint_unsigned role_id PK,FK "roles.id"
        varchar(64) model_type PK "alias morph local"
        bigint_unsigned model_id PK "users.id pour un utilisateur"
    }

    model_has_permissions {
        bigint_unsigned permission_id PK,FK "permissions.id"
        varchar(64) model_type PK "alias morph local"
        bigint_unsigned model_id PK "users.id pour un utilisateur"
    }

    permission_overrides {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id"
        bigint_unsigned permission_id FK "permissions.id"
        bigint_unsigned assigned_by_id FK "users.id"
        tinyint_unsigned effect "PermissionEffectEnum"
        tinyint_unsigned status "OverrideStatusEnum"
        datetime started_at
        datetime ended_at "nullable"
        tinyint_unsigned active_slot "generated nullable"
        datetime expires_at "nullable"
        text reason "nullable"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    admin_restrictions {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned admin_id FK "users.id"
        bigint_unsigned permission_id FK "permissions.id"
        bigint_unsigned target_tenant_id FK "nullable ; tenants.id"
        bigint_unsigned target_user_id FK "nullable ; users.id"
        bigint_unsigned target_role_id FK "nullable ; roles.id"
        bigint_unsigned created_by_id FK "users.id"
        tinyint_unsigned effect "RestrictionEffectEnum"
        tinyint_unsigned status "OverrideStatusEnum"
        datetime started_at
        datetime ended_at "nullable"
        varchar(16) normalized_target_type "generated"
        bigint_unsigned normalized_target_id "generated ; 0 si global"
        tinyint_unsigned active_slot "generated nullable"
        datetime expires_at "nullable"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    plans {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        varchar code
        int version
        varchar name
        text description "nullable"
        decimal monthly_price
        decimal annual_price
        boolean is_active
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    plan_features {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned plan_id FK "plans.id"
        bigint_unsigned feature_id FK "features.id"
        boolean is_active
        bigint limit "nullable"
        datetime created_at
        datetime updated_at
    }

    subscriptions {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id ; proprietaire des deux types"
        bigint_unsigned parent_subscription_id FK "nullable ; subscriptions.id ; obligatoire pour echeance"
        bigint_unsigned tenant_id FK "nullable ; tenants.id ; abonnement seulement"
        bigint_unsigned plan_id FK "nullable ; plans.id ; abonnement seulement"
        bigint_unsigned assigned_by_id FK "nullable ; users.id ; attribution abonnement"
        tinyint_unsigned record_type "SubscriptionRecordTypeEnum ; NOT NULL"
        tinyint_unsigned parent_record_type "generated STORED ; 1 pour echeance, sinon NULL"
        tinyint_unsigned status "nullable ; SubscriptionStatusEnum ; abonnement seulement"
        tinyint_unsigned period "nullable ; BillingPeriodEnum ; abonnement seulement"
        decimal agreed_amount "nullable ; abonnement seulement"
        datetime started_at "nullable ; abonnement seulement"
        datetime period_starts_at "requis ; periode de droit ou periode facturee"
        datetime period_ends_at "nullable pour abonnement gratuit ; requis pour echeance"
        datetime trial_ends_at "nullable ; abonnement seulement"
        datetime ended_at "nullable ; abonnement seulement"
        boolean auto_renew "nullable ; abonnement seulement"
        varchar(191) installment_number UK "nullable ; echeance seulement"
        decimal installment_amount "nullable ; echeance seulement ; DZD"
        datetime due_at "nullable ; echeance seulement"
        tinyint_unsigned installment_status "nullable ; InstallmentStatusEnum ; echeance seulement"
        tinyint_unsigned active_owner_slot "generated STORED ; 1 si record_type=1 et status=3, sinon NULL"
        varchar(191) operation_key UK
        datetime created_at
        datetime updated_at
    }

    feature_overrides {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id"
        bigint_unsigned tenant_id FK "nullable ; tenants.id"
        bigint_unsigned feature_id FK "features.id"
        bigint_unsigned assigned_by_id FK "users.id"
        boolean is_active
        bigint limit "nullable"
        datetime started_at
        datetime expires_at "nullable"
        text reason "nullable"
        datetime created_at
        datetime updated_at
    }

    feature_usage {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id"
        bigint_unsigned tenant_id FK "nullable ; tenants.id"
        bigint_unsigned feature_id FK "features.id"
        datetime period_starts_at
        datetime period_ends_at "nullable"
        bigint quantity
        datetime created_at
        datetime updated_at
    }

    geographic_areas {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned country_id FK "countries.id"
        bigint_unsigned parent_id FK "nullable ; geographic_areas.id ; wilaya de la commune"
        tinyint_unsigned type "GeoZoneTypeEnum"
        tinyint_unsigned parent_type "generated STORED ; 1 pour commune, sinon NULL"
        bigint_unsigned parent_key "generated STORED ; COALESCE(parent_id,0)"
        varchar(32) code
        varchar name_fr
        varchar name_ar "nullable"
        boolean is_active
        varchar reference_source
        date effective_at
        varchar reference_version
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    activity_log {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned tenant_id FK "nullable ; tenants.id ; objet central concerné"
        varchar(64) log_name "nullable ; catégorie stable"
        text description
        varchar(64) subject_type "nullable ; alias morph local"
        bigint_unsigned subject_id "nullable ; PK du sujet local"
        varchar(100) event "nullable ; code événement extensible"
        varchar(64) causer_type "nullable ; alias morph local"
        bigint_unsigned causer_id "nullable ; PK acteur local"
        json attribute_changes "nullable ; attributes et old en v5"
        json properties "nullable ; contexte filtré"
        varchar(191) operation_key UK "nullable ; deduplication explicite par action et phase"
        uuid correlation_id "index ; identifiant technique partagé"
        tinyint_unsigned origin "ActivityOriginEnum"
        datetime created_at
        datetime updated_at
    }

    tenant_schema_deployments {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned tenant_id FK "tenants.id"
        varchar source_version "nullable"
        varchar target_version
        tinyint_unsigned operation "DeploymentOperationEnum"
        tinyint_unsigned status "ExecutionStatusEnum"
        int attempt_number
        varchar operation_key
        datetime started_at "nullable"
        datetime ended_at "nullable"
        varchar error_code "nullable"
        text sanitized_error "nullable"
        uuid correlation_id
        json runtime_versions
        datetime created_at
        datetime updated_at
    }

    saas_invoices {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id ; proprietaire"
        bigint_unsigned subscription_id FK "subscriptions.id ; abonnement type 1"
        bigint_unsigned installment_id FK "subscriptions.id ; echeance type 2"
        bigint_unsigned billing_rule_id FK "saas_billing_settings.id ; regle type 2"
        bigint_unsigned original_invoice_id FK "nullable facture ; requis avoir ; saas_invoices.id"
        bigint_unsigned sequence_id FK "nullable avant reservation ; saas_billing_settings.id ; compteur type 1"
        bigint_unsigned document_media_id FK "nullable avant generation ; media.id ; PDF prive"
        tinyint_unsigned document_type "DocumentTypeEnum ; 1 INVOICE / 2 CREDIT_NOTE seulement"
        tinyint_unsigned subscription_record_type "generated STORED ; 1"
        tinyint_unsigned installment_record_type "generated STORED ; 2"
        tinyint_unsigned billing_rule_record_type "generated STORED ; 2"
        tinyint_unsigned original_invoice_document_type "generated STORED ; 1 si original_invoice_id non NULL"
        tinyint_unsigned sequence_record_type "generated STORED ; 1 si sequence_id non NULL"
        json billing_rule_snapshot "regle appliquee figee"
        int fiscal_year "nullable avant reservation"
        bigint sequence_number "nullable avant reservation"
        varchar(191) number UK "nullable avant reservation ; numero fiscal"
        decimal net_amount "HT document"
        json taxes "ventilation fiscale historique"
        decimal tax_amount
        decimal total_amount
        char(3) currency "DZD au lancement"
        tinyint_unsigned status "DocumentStatusEnum"
        text reason "nullable facture ; motif requis avoir"
        datetime period_starts_at
        datetime period_ends_at
        datetime due_at "nullable avoir"
        json saas_identity_snapshot
        json customer_identity_snapshot
        datetime issued_at "nullable avant emission"
        datetime cancelled_at "nullable ; brouillon annule"
        text cancellation_reason "nullable"
        varchar(191) operation_key UK "cle stable avec espace de noms serveur"
        uuid correlation_id "nullable ; contexte stable"
        datetime created_at
        datetime updated_at
    }

    saas_invoice_lines {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned document_id FK "saas_invoices.id ; facture ou avoir parent"
        bigint_unsigned user_id FK "users.id ; meme proprietaire que le document"
        bigint_unsigned original_invoice_id FK "nullable facture ; requis ligne avoir ; saas_invoices.id"
        bigint_unsigned original_invoice_line_id FK "nullable facture ; requis ligne avoir ; saas_invoice_lines.id"
        tinyint_unsigned document_type "DocumentTypeEnum ; 1/2 ; identique au parent ; serveur"
        tinyint_unsigned original_line_document_type "generated STORED ; 1 si original_invoice_line_id non NULL"
        int line_number
        varchar description
        decimal quantity "strictement positive"
        decimal net_unit_price "nullable ligne avoir"
        decimal net_discount "nullable ligne avoir"
        decimal net_amount
        json taxes
        decimal tax_amount
        decimal total_amount
        text reason "nullable ligne facture ; requis ligne avoir"
        varchar(191) operation_key UK "cle stable avec espace de noms serveur"
        uuid correlation_id "nullable ; contexte stable de la ligne"
        datetime created_at
        datetime updated_at
    }

    saas_billing_settings {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned created_by_id FK "nullable systeme ; users.id"
        bigint_unsigned validated_by_id FK "nullable avant validation regle ; users.id"
        tinyint_unsigned record_type "SaasBillingSettingRecordTypeEnum ; 1 SEQUENCE / 2 RULE"
        tinyint_unsigned document_type "nullable regle ; DocumentTypeEnum ; 1/2 compteur"
        int fiscal_year "nullable regle"
        varchar(32) prefix "nullable regle"
        bigint next_number "nullable regle ; compteur strictement croissant"
        tinyint_unsigned sequence_slot "generated STORED ; 1 si record_type=1, sinon NULL"
        varchar(100) code "nullable compteur ; code stable regle"
        int version "nullable compteur ; version regle"
        varchar trigger_event "nullable compteur"
        varchar numbering_scope "nullable compteur ; saas_issuer"
        json parameters "nullable compteur ; parametres autorises"
        tinyint_unsigned policy_status "nullable compteur ; PolicyStatusEnum"
        text validation_reference "nullable avant validation regle"
        datetime effective_at "nullable compteur/brouillon"
        datetime ends_at "nullable"
        datetime validated_at "nullable avant validation regle"
        varchar(191) operation_key UK "cle stable avec espace de noms serveur"
        uuid correlation_id "nullable compteur ; requis regle"
        datetime created_at
        datetime updated_at
    }

    saas_document_deliveries {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id ; meme proprietaire que le document"
        bigint_unsigned document_id FK "saas_invoices.id ; en-tete emis"
        bigint_unsigned created_by_id FK "nullable systeme ; users.id"
        bigint_unsigned proof_media_id FK "nullable ; media.id ; preuve privee de remise"
        tinyint_unsigned document_type "DocumentTypeEnum ; 1/2 ; identique au document ; serveur"
        tinyint_unsigned channel "DocumentDeliveryChannelEnum"
        text encrypted_recipient "destination utilisable chiffree"
        tinyint_unsigned delivery_status "DocumentDeliveryStatusEnum"
        int attempts_count
        json delivery_attempts "nullable ; essais techniques filtres"
        datetime next_attempt_at "nullable"
        datetime sent_at "nullable"
        datetime delivered_at "nullable"
        varchar provider_reference "nullable"
        varchar error_code "nullable"
        datetime sending_started_at "nullable avant envoi"
        varchar(191) operation_key UK "cle stable avec espace de noms serveur"
        uuid correlation_id
        datetime created_at
        datetime updated_at
    }

    saas_transfers {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id ; proprietaire"
        bigint_unsigned document_id FK "saas_invoices.id ; facture type 1 seulement"
        bigint_unsigned original_payment_id FK "nullable paiement ; requis remboursement ; saas_transfers.id"
        bigint_unsigned credit_note_id FK "nullable ; saas_invoices.id ; avoir type 2"
        bigint_unsigned proof_media_id FK "nullable avant preuve ; media.id ; PDF prive"
        bigint_unsigned source_proof_media_id FK "nullable ; media.id ; image originale convertie"
        bigint_unsigned created_by_id FK "nullable systeme ; users.id ; requis remboursement"
        bigint_unsigned validated_by_id FK "nullable avant verification ; users.id ; admin"
        bigint_unsigned performed_by_id FK "nullable avant remboursement reel ; users.id ; admin"
        bigint_unsigned reversal_of_id FK "nullable ; saas_transfers.id ; inverse exact"
        tinyint_unsigned record_type "SaasTransferRecordTypeEnum ; 1 PAYMENT / 2 REFUND"
        tinyint_unsigned document_type "generated STORED ; 1"
        tinyint_unsigned original_payment_record_type "generated STORED ; 1 si original_payment_id non NULL"
        tinyint_unsigned credit_note_document_type "generated STORED ; 2 si credit_note_id non NULL"
        tinyint_unsigned transfer_method "SaasTransferMethodEnum"
        tinyint_unsigned transfer_status "SaasTransferStatusEnum"
        tinyint_unsigned refund_reason "nullable paiement ; SaasRefundReasonEnum"
        decimal amount "signe ; positif hors inverse comptable"
        char(3) currency "DZD au lancement"
        text reason "nullable declaration paiement ; requis remboursement/refus/correction"
        varchar(191) transfer_reference "nullable avant verification"
        varchar(64) financial_account_key "nullable avant verification ; alias compte SaaS"
        char(64) transaction_fingerprint "nullable avant verification ; transaction normalisee"
        char(64) active_transaction_fingerprint UK "generated STORED ; VERIFIED sans reversal_of_id"
        text encrypted_transfer_details "nullable ; donnees bancaires minimales chiffrees"
        datetime occurred_at "nullable avant preuve ; date du transfert reel"
        datetime sending_started_at "nullable ; remboursement reel seulement"
        datetime validated_at "nullable avant verification"
        varchar error_code "nullable ; resultat incertain"
        varchar(191) operation_key UK "cle stable avec espace de noms serveur"
        uuid correlation_id
        datetime created_at
        datetime updated_at
    }

    media {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned created_by_id FK "nullable ; users.id"
        varchar(64) model_type "alias morph ; parent local"
        bigint_unsigned model_id "clé du parent local"
        varchar(64) collection_name "logo, gallery, invoice, proof..."
        varchar(64) disk
        varchar storage_key UK
        varchar mime_type
        varchar original_name
        bigint_unsigned size_bytes
        int width "nullable"
        int height "nullable"
        int duration_seconds "nullable"
        text alt_text "nullable"
        tinyint_unsigned visibility "MediaVisibilityEnum"
        int position
        boolean is_primary
        tinyint_unsigned primary_slot "generated nullable ; 1 si principal actif"
        char(64) file_hash "nullable"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    %% Toutes les FK declarees, puis les liens polymorphes conditionnels
    countries ||..o{ users : "FK country_id"
    users |o..o{ users : "FK legal_verified_by_id"
    users ||..o{ tenants : "FK user_id"
    tenants ||..o{ domains : "FK tenant_id"
    users ||..o{ contact_verifications : "FK user_id"
    permissions ||--o{ role_has_permissions : "FK permission_id"
    roles ||--o{ role_has_permissions : "FK role_id"
    roles ||--o{ model_has_roles : "FK role_id"
    permissions ||--o{ model_has_permissions : "FK permission_id"
    users ||..o{ permission_overrides : "FK user_id"
    permissions ||..o{ permission_overrides : "FK permission_id"
    users ||..o{ permission_overrides : "FK assigned_by_id"
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
    users ||..o{ feature_overrides : "FK user_id"
    tenants |o..o{ feature_overrides : "FK tenant_id"
    features ||..o{ feature_overrides : "FK feature_id"
    users ||..o{ feature_overrides : "FK assigned_by_id"
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
    permission_overrides |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    admin_restrictions |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    plans |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    plan_features |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    subscriptions |o..o{ activity_log : "POLY subject_id ; subscription ou subscription_installment ; record_type 1/2"
    feature_overrides |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    feature_usage |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    geographic_areas |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    tenant_schema_deployments |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
    saas_invoices |o..o{ activity_log : "POLY subject_id ; saas_invoice ou saas_credit_note ; document_type 1/2"
    saas_invoice_lines |o..o{ activity_log : "POLY subject_id ; saas_invoice_line ou saas_credit_note_line ; document_type 1/2"
    saas_billing_settings |o..o{ activity_log : "POLY subject_id ; saas_sequence ou saas_billing_rule ; record_type 1/2"
    saas_document_deliveries |o..o{ activity_log : "POLY subject_id ; saas_document_delivery"
    saas_transfers |o..o{ activity_log : "POLY subject_id ; saas_payment ou saas_refund ; record_type 1/2"
    media |o..o{ activity_log : "POLY subject_id ; si modele auditable autorise"
```

**Facturation :** saas_invoices et saas_invoice_lines distinguent facture/avoir par document_type=1/2 ; saas_billing_settings distingue compteur/règle par record_type=1/2 ; saas_transfers distingue paiement/remboursement par record_type=1/2. Les envois sont dans saas_document_deliveries. Les PDF et leurs empreintes sont protégés dans media ; envoyer un document ou déposer un reçu ne confirme pas des fonds. Abonnements/échéances et wilayas/communes restent fusionnés.

## 3. Chaque champ expliqué un par un

Une ligne est une fiche ; chaque champ est une case. **id et uuid viennent d’abord, puis les FK, puis les autres champs.** Une FK permet de retrouver une ligne liée. Un champ calculé est rempli automatiquement. Les liens model_id/subject_id/causer_id sont choisis selon leur type. Les trois pivots Spatie gardent leurs clés composées sans id/uuid ajouté.

« Vide » veut dire NULL. Certains champs sont réservés à un type de ligne ou à une étape pas encore réalisée ; les règles du schéma décident quand ils deviennent obligatoires. Un montant de facture est différent d’un montant réellement reçu ou remboursé.

### 1. `countries`

La liste des pays : Algérie, France, Arabie saoudite, Soudan et Égypte.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`code`** | Donnée | Le code court du pays. Exemple : DZ pour l’Algérie, FR pour la France. |
| **`name_fr`** | Donnée | Le nom affiché en français. |
| **`name_en`** | Donnée | Le nom affiché en anglais. |
| **`name_ar`** | Donnée | Le nom affiché en arabe. |
| **`is_active`** | Donnée | Indique si le pays peut être sélectionné au lancement. L’Algérie est active ; les autres pays restent prévus pour plus tard. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 2. `users`

Les comptes des propriétaires de boutiques et des administrateurs du SaaS : nom, e-mail, mot de passe protégé, téléphone, pays… Elle contient aussi les informations professionnelles du propriétaire.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`country_id`** | Clé étrangère | Le pays du compte, à retrouver dans countries. |
| **`legal_verified_by_id`** | Clé étrangère | L’administrateur central qui a vérifié les informations professionnelles du propriétaire. |
| **`name`** | Donnée | Le nom du titulaire du compte. |
| **`first_name`** | Donnée | Le prénom du titulaire du compte. |
| **`email`** | Donnée | Son adresse e-mail, unique parmi les comptes de cette base. |
| **`password`** | Donnée | La version protégée, dite hachée, du mot de passe. Elle sert à vérifier la connexion. |
| **`phone`** | Donnée | Le numéro de téléphone du compte. |
| **`email_verified_at`** | Donnée | La date où l’adresse e-mail a été confirmée. |
| **`phone_verified_at`** | Donnée | La date où le numéro de téléphone a été confirmé. |
| **`whatsapp_verified_at`** | Donnée | La date où le contact WhatsApp a été confirmé. |
| **`legal_name`** | Donnée | Le nom officiel de l’entreprise lorsqu’il est différent du nom de la personne. |
| **`legal_form`** | Donnée | La forme juridique déclarée de l’activité ou de l’entreprise. |
| **`activity_nature`** | Donnée | Le genre d’activité professionnelle exercée par le propriétaire. |
| **`nif`** | Donnée | Le numéro d’identification fiscale du professionnel. |
| **`nis`** | Donnée | Le numéro d’identification statistique du professionnel. |
| **`registration_number`** | Donnée | Le numéro d’immatriculation professionnelle, par exemple le registre du commerce. |
| **`artisan_card_number`** | Donnée | Le numéro de carte d’artisan si ce document concerne cette activité. |
| **`legal_address`** | Donnée | L’adresse professionnelle officielle du propriétaire ou de son entreprise. |
| **`share_capital`** | Donnée | Le montant du capital de l’entreprise, lorsque cette information s’applique. |
| **`tax_regime`** | Donnée | Le régime fiscal déclaré pour cette activité. |
| **`legal_profile_version`** | Donnée | Le numéro de version du dossier professionnel. Il augmente quand les informations importantes changent. |
| **`legal_verification_status`** | Donnée | L’état de vérification du dossier professionnel. Choix : 1 = en attente ; 2 = vérifié ; 3 = échec ; 4 = expiré ; 5 = incomplet. |
| **`legal_verified_at`** | Donnée | La date où le dossier professionnel a été validé. |
| **`locale`** | Donnée | La langue choisie pour utiliser le SaaS. Exemple : français ou arabe. |
| **`status`** | Donnée | L’état du compte : utilisable, inactif, suspendu ou supprimé. Choix : 1 = actif ; 2 = inactif ; 3 = suspendu ; 4 = supprimé. |
| **`last_login_at`** | Donnée | La date de la dernière connexion réussie. |
| **`remember_token`** | Donnée | Une clé secrète utilisée pour garder la connexion lorsque la personne choisit de rester connectée. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 3. `tenants`

La liste des boutiques : leur nom, leur propriétaire, leur état et les informations pour retrouver leur propre base de données.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire de cette boutique, à retrouver dans users. |
| **`internal_label`** | Donnée | Le nom utilisé par les administrateurs pour reconnaître la boutique. |
| **`shop_name`** | Donnée | Le nom affiché de la boutique. |
| **`slug`** | Donnée | Le morceau lisible de son adresse. Exemple : karim-shoes. |
| **`profile_version`** | Donnée | La version des informations de la boutique, pour suivre leur mise à jour dans sa base. |
| **`document_prefix`** | Donnée | Le préfixe stable qui distingue les documents de cette boutique. |
| **`creation_key`** | Donnée | Le code de la demande de création, pour éviter de créer deux boutiques quand elle est répétée. |
| **`creation_hash`** | Donnée | Un résumé calculé du contenu de la demande de création, pour reconnaître une demande identique. |
| **`status`** | Donnée | L’état de la boutique : en création, active, inactive, suspendue, hors limite, en échec ou supprimée. Choix : 1 = en préparation ; 2 = actif ; 3 = inactif ; 4 = suspendu ; 5 = hors limite de l’abonnement ; 6 = préparation échouée ; 9 = supprimé. |
| **`is_primary`** | Donnée | Indique la boutique principale du propriétaire, notamment lorsqu’une seule boutique peut rester active. |
| **`activation_priority`** | Donnée | L’ordre de préférence pour décider quelles boutiques peuvent rester actives dans les limites de l’offre. |
| **`over_quota_since_at`** | Donnée | La date depuis laquelle la boutique dépasse les possibilités de l’abonnement. |
| **`data`** | Donnée | Les réglages techniques nécessaires pour retrouver et ouvrir la bonne base de cette boutique. |
| **`schema_version`** | Donnée | La version de la structure de sa base de données. |
| **`provisioned_at`** | Donnée | La date où la préparation initiale de la base de la boutique a été terminée. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 4. `domains`

Les adresses Internet des boutiques. Elle indique à quelle boutique appartient chaque adresse.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`tenant_id`** | Clé étrangère | La boutique concernée, à retrouver dans tenants. |
| **`domain`** | Donnée | L’adresse Internet complète de la boutique. Exemple : karim-shoes.exemple.com. |
| **`type`** | Donnée | Indique si l’adresse est un sous-domaine du SaaS ou un domaine personnalisé. Choix : 1 = sous-domaine ; 2 = domaine personnalisé. |
| **`is_primary`** | Donnée | Indique l’adresse principale utilisée pour cette boutique. |
| **`verification_status`** | Donnée | L’état de vérification permettant de confirmer que cette adresse peut être utilisée. Choix : 1 = en attente ; 2 = vérifié ; 3 = échec ; 4 = expiré ; 5 = incomplet. |
| **`verified_at`** | Donnée | La date où le contrôle de cette adresse a été validé. |
| **`certificate_status`** | Donnée | L’état du certificat de sécurité HTTPS de cette adresse. Choix : 1 = en attente ; 2 = actif ; 3 = erreur ; 4 = expiré. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 5. `contact_verifications`

Les demandes de vérification d’un e-mail ou d’un téléphone : code protégé, expiration, essais et résultat.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le compte dont on vérifie l’e-mail ou le téléphone. |
| **`channel`** | Donnée | Le moyen utilisé pour envoyer le code de vérification. Choix : 1 = SMS ; 2 = WhatsApp ; 3 = e-mail. |
| **`normalized_destination`** | Donnée | L’e-mail ou le téléphone écrit dans un format uniforme, pour toujours reconnaître le même contact. |
| **`code_hash`** | Donnée | La version protégée du code envoyé, pour pouvoir vérifier la réponse. |
| **`expires_at`** | Donnée | La date et l’heure à partir desquelles cet élément n’est plus valable. |
| **`attempts_count`** | Donnée | Le nombre de fois où une réponse au code de vérification a été essayée. |
| **`consumed_at`** | Donnée | La date où le bon code a été utilisé. Il ne doit servir qu’une fois. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 6. `features`

Le catalogue des possibilités des abonnements. Exemple : pouvoir créer plusieurs boutiques ou utiliser une fonctionnalité particulière.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`code`** | Donnée | Le code stable qui reconnaît la fonctionnalité dans le SaaS. |
| **`name`** | Donnée | Le nom lisible de cette fonctionnalité. |
| **`value_type`** | Donnée | Indique si la possibilité se règle par oui/non ou par une quantité maximale. Choix : 1 = oui/non ; 2 = quantité. |
| **`unit`** | Donnée | Ce que l’on compte. Exemple : nombre de boutiques. |
| **`quota_scope`** | Donnée | Indique si la limite concerne tout le propriétaire ou chaque boutique séparément. Choix : 1 = propriétaire entier ; 2 = boutique. |
| **`period`** | Donnée | La période pendant laquelle la quantité est comptée. Choix : 1 = sur toute la durée ; 2 = par jour ; 3 = par mois ; 4 = par année. |
| **`is_active`** | Donnée | Indique si cette fonctionnalité fait encore partie du catalogue disponible. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 7. `permissions`

La liste des actions qu’une personne peut être autorisée à faire. Exemple : gérer les offres ou valider un paiement.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`name`** | Donnée | Le nom technique de l’action autorisée. Exemple : saas.plans.manage pour gérer les offres. |
| **`guard_name`** | Donnée | L’espace d’authentification auquel ce rôle ou cette permission appartient. Ici : central, pour le SaaS. |
| **`label`** | Donnée | Le nom lisible affiché à l’utilisateur, à la place du code technique. |
| **`feature_code`** | Donnée | Le code d’une fonctionnalité liée à cette action lorsque cette liaison est prévue. Il reste vide pour les permissions d’administration centrale. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 8. `roles`

Les groupes d’autorisations. Exemple : le rôle « gestionnaire des abonnements » rassemble les actions utiles à ce travail.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`name`** | Donnée | Le nom technique qui reconnaît ce rôle. |
| **`guard_name`** | Donnée | L’espace d’authentification auquel ce rôle ou cette permission appartient. Ici : central, pour le SaaS. |
| **`label`** | Donnée | Le nom lisible affiché à l’utilisateur, à la place du code technique. |
| **`is_system`** | Donnée | Indique un rôle créé et géré par le système. |
| **`is_protected`** | Donnée | Indique un rôle dont les modifications et la suppression sont spécialement limitées. |
| **`is_super_admin`** | Donnée | Indique le rôle racine qui donne les droits complets dans l’administration centrale. |
| **`super_admin_slot`** | Calculé | Vaut 1 pour le rôle racine et reste vide pour les autres. Cette valeur calculée empêche d’avoir deux rôles racines dans cette base. |
| **`permission_version`** | Donnée | Un numéro qui augmente quand les autorisations du rôle changent, pour actualiser les droits gardés en mémoire. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 9. `role_has_permissions`

Indique quelles actions sont autorisées pour chaque rôle. Exemple : un gestionnaire peut attribuer un abonnement.

Ses deux références forment ensemble la clé primaire.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`permission_id`** | Clé étrangère | L’action que ce rôle permet, à retrouver dans permissions. |
| **`role_id`** | Clé étrangère | Le rôle qui reçoit cette autorisation, à retrouver dans roles. |

### 10. `model_has_roles`

Indique quel compte possède quel rôle. Exemple : Ahmed possède le rôle de gestionnaire.

role_id, model_id et model_type forment ensemble la clé primaire.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`role_id`** | Clé étrangère | Le rôle attribué à ce compte, à retrouver dans roles. |
| **`model_type`** | Donnée | Le type du compte recevant le rôle. Ici, central_user désigne un compte de users. |
| **`model_id`** | Lien selon son type | Le numéro du compte qui reçoit ce rôle. Son sens est donné par model_type. |

### 11. `model_has_permissions`

Donne une autorisation directement à un compte. Exemple : Ahmed peut valider un paiement grâce à une autorisation personnelle.

permission_id, model_id et model_type forment ensemble la clé primaire.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`permission_id`** | Clé étrangère | L’autorisation donnée directement à ce compte, à retrouver dans permissions. |
| **`model_type`** | Donnée | Le type du compte recevant l’autorisation. Ici, central_user désigne un compte de users. |
| **`model_id`** | Lien selon son type | Le numéro du compte qui reçoit directement l’autorisation. Son sens est donné par model_type. |

### 12. `permission_overrides`

Les autorisations ou interdictions exceptionnelles, avec leurs dates. Exemple : interdire temporairement une action à un compte.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le compte qui bénéficie de cette autorisation ou subit cette interdiction. |
| **`permission_id`** | Clé étrangère | L’action autorisée concernée, à retrouver dans permissions. |
| **`assigned_by_id`** | Clé étrangère | Le compte de la personne qui a accordé ce droit ou cette exception. |
| **`effect`** | Donnée | Indique si l’action est autorisée ou interdite par cette exception. Choix : 1 = autoriser ; 2 = interdire. |
| **`status`** | Donnée | L’état de cette exception : active, révoquée, expirée ou clôturée. Choix : 1 = actif ; 2 = révoqué ; 3 = expiré ; 4 = clôturé. |
| **`started_at`** | Donnée | Le début de la période pendant laquelle cette exception s’applique. |
| **`ended_at`** | Donnée | La fin de la période pendant laquelle cette exception s’applique, lorsqu’une date de fin est connue. |
| **`active_slot`** | Calculé | Vaut 1 pour une exception marquée active et non supprimée. Cette valeur calculée empêche un doublon actif pour ce compte et cette permission ; les dates sont vérifiées séparément. |
| **`expires_at`** | Donnée | La date d’expiration prévue de cette exception. |
| **`reason`** | Donnée | Le texte qui explique pourquoi cette décision ou cette opération a été prise. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 13. `admin_restrictions`

Limite les comptes, boutiques ou rôles sur lesquels un administrateur peut agir dans l’administration centrale.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`admin_id`** | Clé étrangère | L’administrateur dont on limite les possibilités. |
| **`permission_id`** | Clé étrangère | L’action à laquelle cette restriction s’applique. |
| **`target_tenant_id`** | Clé étrangère | La boutique centrale visée par la restriction. |
| **`target_user_id`** | Clé étrangère | Le compte central visé par la restriction. |
| **`target_role_id`** | Clé étrangère | Le rôle central visé par la restriction. |
| **`created_by_id`** | Clé étrangère | Le compte de la personne qui a créé cet élément. |
| **`effect`** | Donnée | Indique si l’action est interdite sur cette cible ou autorisée seulement sur les cibles désignées. Choix : 1 = interdire ; 2 = autoriser seulement les cibles désignées. |
| **`status`** | Donnée | L’état de cette restriction. Choix : 1 = actif ; 2 = révoqué ; 3 = expiré ; 4 = clôturé. |
| **`started_at`** | Donnée | La date et l’heure à partir desquelles cette période ou cette opération commence. |
| **`ended_at`** | Donnée | La date et l’heure où cette période ou cette opération se termine. |
| **`normalized_target_type`** | Calculé | Une valeur calculée qui précise le genre de cible : boutique, compte, rôle ou ensemble global. |
| **`normalized_target_id`** | Calculé | Le numéro calculé de la cible choisie. La valeur 0 représente une restriction globale. |
| **`active_slot`** | Calculé | Une valeur calculée pour éviter de répéter la même restriction active pour cet administrateur, cette action et cette cible. |
| **`expires_at`** | Donnée | La date et l’heure à partir desquelles cet élément n’est plus valable. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 14. `plans`

Les offres d’abonnement : Gratuit, Pro, etc., avec leurs prix et leurs versions.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`code`** | Donnée | Le code stable de l’offre. Il permet de regrouper ses différentes versions. |
| **`version`** | Donnée | Le numéro de version de cet élément. Une nouvelle version garde les anciennes informations dans l’historique. |
| **`name`** | Donnée | Le nom affiché de l’offre. Exemple : Gratuit ou Pro. |
| **`description`** | Donnée | Un texte qui présente cette offre d’abonnement. |
| **`monthly_price`** | Donnée | Le prix prévu pour un mois, en dinars algériens au lancement. |
| **`annual_price`** | Donnée | Le prix prévu pour une année, en dinars algériens au lancement. |
| **`is_active`** | Donnée | Indique si cette version de l’offre peut encore être proposée. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 15. `plan_features`

Indique les possibilités et limites de chaque offre. Exemple : Gratuit permet 1 boutique, Pro en permet 3.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`plan_id`** | Clé étrangère | L’offre d’abonnement concernée, à retrouver dans plans. |
| **`feature_id`** | Clé étrangère | La fonctionnalité concernée, à retrouver dans features. |
| **`is_active`** | Donnée | Indique si cette fonctionnalité est autorisée dans cette offre : oui ou non. |
| **`limit`** | Donnée | Le nombre maximum autorisé. Exemple : 3 boutiques. Vide signifie illimité pour un quota autorisé ; pour une fonction oui/non, ce champ reste vide. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 16. `subscriptions`

Les abonnements et leurs échéances à payer. Exemple : Karim possède Pro ; une autre ligne indique qu’il doit payer 3 000 DA pour septembre.

Une ligne de type 1 décrit un abonnement ; les lignes de type 2 sont ses échéances à payer.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire de l’abonnement et de ses échéances, à retrouver dans users. |
| **`parent_subscription_id`** | Clé étrangère | Pour une échéance, l’abonnement auquel elle appartient. Ce champ reste vide sur la ligne d’abonnement. |
| **`tenant_id`** | Clé étrangère | La boutique spécialement visée par l’abonnement, si l’on en choisit une. Vide signifie un abonnement au niveau du propriétaire. |
| **`plan_id`** | Clé étrangère | L’offre choisie sur la ligne d’abonnement. Une échéance retrouve l’offre depuis son abonnement parent. |
| **`assigned_by_id`** | Clé étrangère | L’administrateur qui a attribué l’abonnement. |
| **`record_type`** | Donnée | Indique ce que représente la ligne : un abonnement ou une échéance à payer. Choix : 1 = abonnement ; 2 = échéance. |
| **`parent_record_type`** | Calculé | Vaut automatiquement 1 pour une échéance, pour garantir que son parent est bien un abonnement. |
| **`status`** | Donnée | L’état de l’abonnement. Il reste vide sur une échéance. Choix : 1 = prévu pour plus tard ; 2 = en essai ; 3 = actif ; 4 = expiré ; 5 = annulé. |
| **`period`** | Donnée | Indique si l’abonnement suit une période mensuelle ou annuelle. Choix : 1 = par mois ; 2 = par année. |
| **`agreed_amount`** | Donnée | Le prix convenu pour l’abonnement lors de son attribution. |
| **`started_at`** | Donnée | La date de début de l’abonnement. |
| **`period_starts_at`** | Donnée | Le début de la période de droits pour un abonnement, ou de la période facturée pour une échéance. |
| **`period_ends_at`** | Donnée | La fin de cette période. Elle peut rester vide pour l’abonnement gratuit ; une échéance garde sa date de fin. |
| **`trial_ends_at`** | Donnée | La date de fin de l’essai, lorsque l’abonnement en possède un. |
| **`ended_at`** | Donnée | La date où cette attribution d’abonnement a été clôturée. |
| **`auto_renew`** | Donnée | Indique si le prochain renouvellement doit être préparé. Les virements restent effectués manuellement. |
| **`installment_number`** | Donnée | Le numéro unique et lisible de l’échéance à payer. |
| **`installment_amount`** | Donnée | La somme initialement due pour cette échéance, en dinars algériens. |
| **`due_at`** | Donnée | La date limite pour régler cette échéance. |
| **`installment_status`** | Donnée | Indique si l’échéance attend son règlement, est partiellement payée, soldée ou annulée. Choix : 1 = en attente ; 2 = partiellement payé ; 3 = soldé ; 4 = annulé. |
| **`active_owner_slot`** | Calculé | Vaut automatiquement 1 sur l’abonnement actif du propriétaire et reste vide sur les autres lignes. Il empêche deux abonnements actifs en même temps. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 17. `feature_overrides`

Les exceptions aux possibilités ou limites d’une offre. Exemple : accorder temporairement une boutique supplémentaire à Karim.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire auquel cette exception de fonctionnalité s’applique. |
| **`tenant_id`** | Clé étrangère | La boutique précisément concernée. Vide signifie que l’exception concerne le propriétaire entier. |
| **`feature_id`** | Clé étrangère | La fonctionnalité concernée, à retrouver dans features. |
| **`assigned_by_id`** | Clé étrangère | Le compte de la personne qui a accordé ce droit ou cette exception. |
| **`is_active`** | Donnée | La valeur choisie pour la fonctionnalité : autorisée ou interdite. La durée de l’exception est déterminée par ses dates. |
| **`limit`** | Donnée | Le nombre maximum autorisé. Exemple : 3 boutiques. Vide signifie illimité pour un quota autorisé ; pour une fonction oui/non, ce champ reste vide. |
| **`started_at`** | Donnée | Le début de la période pendant laquelle cette exception s’applique. |
| **`expires_at`** | Donnée | La date et l’heure à partir desquelles cet élément n’est plus valable. |
| **`reason`** | Donnée | Le texte qui explique pourquoi cette décision ou cette opération a été prise. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 18. `feature_usage`

Les quantités déjà utilisées pour une fonctionnalité pendant une période. Elle aide à suivre les limites de l’abonnement.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire dont on compte l’utilisation. |
| **`tenant_id`** | Clé étrangère | La boutique dont on compte l’utilisation, si la limite fonctionne par boutique. Vide correspond à un suivi au niveau du propriétaire. |
| **`feature_id`** | Clé étrangère | La fonctionnalité concernée, à retrouver dans features. |
| **`period_starts_at`** | Donnée | Le début de la période concernée. |
| **`period_ends_at`** | Donnée | La fin de la période concernée. |
| **`quantity`** | Donnée | La quantité déjà utilisée pendant cette période. Exemple : le nombre d’utilisations d’une fonctionnalité. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 19. `geographic_areas`

Les wilayas et communes dans une seule table. Chaque commune est reliée à sa wilaya, et les zones à leur pays.

Une ligne de type 1 est une wilaya ; une ligne de type 2 est une commune du même pays reliée à sa wilaya.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`country_id`** | Clé étrangère | Le pays auquel cette wilaya ou cette commune appartient. |
| **`parent_id`** | Clé étrangère | Pour une commune, le numéro de sa wilaya. Une wilaya laisse ce champ vide. |
| **`type`** | Donnée | Indique si la ligne représente une wilaya ou une commune. Choix : 1 = wilaya ; 2 = commune. |
| **`parent_type`** | Calculé | Vaut automatiquement 1 pour une commune, pour imposer un parent de type wilaya. |
| **`parent_key`** | Calculé | Une valeur calculée pour classer les codes : le numéro de la wilaya pour une commune, ou 0 pour une wilaya. Elle aide à éviter les doublons. |
| **`code`** | Donnée | Le code officiel de cette wilaya ou commune. |
| **`name_fr`** | Donnée | Le nom affiché en français. |
| **`name_ar`** | Donnée | Le nom affiché en arabe. |
| **`is_active`** | Donnée | Indique si cette zone peut être choisie pour une nouvelle adresse. |
| **`reference_source`** | Donnée | Le document ou la source officielle d’où viennent les informations géographiques. |
| **`effective_at`** | Donnée | La date à partir de laquelle cette version du référentiel géographique s’applique. |
| **`reference_version`** | Donnée | La version du référentiel officiel utilisée pour cette zone. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

### 20. `activity_log`

Le journal des actions de la partie centrale : qui a fait quoi et quand. Exemple : un administrateur a validé un paiement.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`tenant_id`** | Clé étrangère | La boutique centrale concernée par l’action, lorsqu’une boutique précise est concernée. |
| **`log_name`** | Donnée | La famille de l’action. Exemple : connexions, abonnements ou facturation. |
| **`description`** | Donnée | Une phrase lisible qui raconte ce qui s’est passé. |
| **`subject_type`** | Donnée | Le type de l’objet sur lequel l’action a porté. Exemple : un abonnement ou une facture. |
| **`subject_id`** | Lien selon son type | Le numéro de cet objet, à retrouver selon subject_type. |
| **`event`** | Donnée | Le code stable de l’action réalisée. Exemple : subscription_assigned pour un abonnement attribué. |
| **`causer_type`** | Donnée | Le type du compte qui a réalisé l’action. Au central : central_user ; un événement système peut laisser ce champ vide. |
| **`causer_id`** | Lien selon son type | Le numéro du compte qui a réalisé l’action, à retrouver selon causer_type. |
| **`attribute_changes`** | Donnée | Les champs autorisés qui ont changé, avec leur ancienne et leur nouvelle valeur. |
| **`properties`** | Donnée | Les informations utiles pour comprendre l’action : résultat, raison, références ou contexte limité. |
| **`operation_key`** | Donnée | Le code unique d’une action précise et de son étape, pour éviter de journaliser deux fois le même résultat. |
| **`correlation_id`** | Donnée | Un code partagé entre les différentes étapes d’une même opération, pour pouvoir les retrouver ensemble. |
| **`origin`** | Donnée | Indique si l’action vient d’une personne, du système, d’un transporteur ou d’une tâche automatique. Choix : 1 = personne ; 2 = système ; 3 = transporteur ; 4 = tâche automatique. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | Un champ conservé pour la compatibilité du journal. Les activités restent protégées contre les modifications ordinaires. |

### 21. `tenant_schema_deployments`

L’historique des créations et mises à jour des bases de données des boutiques : ce qui a réussi, échoué ou doit être repris.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`tenant_id`** | Clé étrangère | La boutique concernée, à retrouver dans tenants. |
| **`source_version`** | Donnée | La version de la base de la boutique avant cette opération. Elle peut être vide lors de sa première création. |
| **`target_version`** | Donnée | La version que la base doit avoir après l’opération. |
| **`operation`** | Donnée | Indique s’il s’agit de créer la base ou de mettre à jour sa structure. Choix : 1 = création initiale de la base ; 2 = mise à jour de sa structure. |
| **`status`** | Donnée | L’état de cette exécution technique. Choix : 1 = en attente ; 2 = en cours ; 3 = réussi ; 4 = échoué ; 5 = annulé. |
| **`attempt_number`** | Donnée | Le numéro de l’essai. Exemple : 2 signifie que l’opération a été tentée une deuxième fois. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`started_at`** | Donnée | La date et l’heure à partir desquelles cette période ou cette opération commence. |
| **`ended_at`** | Donnée | La date et l’heure où cette période ou cette opération se termine. |
| **`error_code`** | Donnée | Un code court qui indique le genre d’erreur rencontré. |
| **`sanitized_error`** | Donnée | Le message d’erreur avec les informations privées retirées. |
| **`correlation_id`** | Donnée | Un code partagé entre les différentes étapes d’une même opération, pour pouvoir les retrouver ensemble. |
| **`runtime_versions`** | Donnée | Les versions techniques réellement utilisées pendant l’opération, par exemple PHP, Laravel et MySQL. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 22. `saas_invoices`

Les factures et les avoirs du SaaS. Chaque fiche indique qui doit payer, pour quel abonnement, la période, les montants et le PDF. Un avoir réduit une facture déjà émise.

Une facture ou un avoir peut avoir plusieurs lignes et envois. Une facture peut recevoir plusieurs paiements. Les données d’un document émis et son PDF sont conservés sans réécriture.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire qui reçoit cette facture ou cet avoir, à retrouver dans users. |
| **`subscription_id`** | Clé étrangère | L’abonnement lié à la facture ou à l’avoir. Le lien vise une ligne abonnement de subscriptions. |
| **`installment_id`** | Clé étrangère | L’échéance précise facturée, à retrouver parmi les lignes échéance de subscriptions. |
| **`billing_rule_id`** | Clé étrangère | La version de règle qui a servi au document. Elle vise une ligne RULE, de type 2, dans saas_billing_settings. |
| **`original_invoice_id`** | Clé étrangère | La facture d’origine que cet avoir ou sa ligne vient corriger, à retrouver dans saas_invoices. |
| **`sequence_id`** | Clé étrangère | Le compteur qui a donné le numéro au document. Il vise une ligne SEQUENCE, de type 1, dans saas_billing_settings. |
| **`document_media_id`** | Clé étrangère | Le fichier PDF privé de la facture ou de l’avoir, à retrouver dans media. Sa clé et son empreinte y restent protégées. |
| **`document_type`** | Donnée | Le genre de document : 1 = facture ; 2 = avoir. Un avoir réduit le montant d’une facture. |
| **`subscription_record_type`** | Calculé | Vaut automatiquement 1 si subscription_id est rempli, pour imposer un vrai abonnement. |
| **`installment_record_type`** | Calculé | Vaut automatiquement 2 si installment_id est rempli, pour imposer une vraie échéance. |
| **`billing_rule_record_type`** | Calculé | Vaut automatiquement 2 pour imposer une vraie règle de facturation. |
| **`original_invoice_document_type`** | Calculé | Vaut automatiquement 1 quand une facture d’origine est indiquée, pour imposer une vraie facture. |
| **`sequence_record_type`** | Calculé | Vaut automatiquement 1 quand un compteur de numérotation est indiqué. |
| **`billing_rule_snapshot`** | Donnée | La copie exacte de la règle appliquée, gardée pour comprendre une ancienne facture même si les règles changent. |
| **`fiscal_year`** | Donnée | L’année de la série qui a donné son numéro au document. Elle reste vide avant la réservation de ce numéro. |
| **`sequence_number`** | Donnée | Le nombre réservé dans le compteur pour cette facture ou cet avoir. |
| **`number`** | Donnée | Le numéro complet et lisible du document. Exemple : FAC-2026-000123. |
| **`net_amount`** | Donnée | Le total du document avant les taxes, égal à la somme de ses lignes. |
| **`taxes`** | Donnée | La ventilation historique des taxes du document, avec leurs bases, taux et montants. |
| **`tax_amount`** | Donnée | Le total des taxes du document, égal à la somme des taxes de ses lignes. |
| **`total_amount`** | Donnée | Le total du document avec les taxes, égal à la somme de ses lignes. |
| **`currency`** | Donnée | La monnaie du document. Au lancement : DZD, le dinar algérien. |
| **`status`** | Donnée | L’état du document : 1 = brouillon ; 2 = émis ; 3 = annulé ; 4 = en préparation du PDF. |
| **`reason`** | Donnée | La raison de l’avoir. Une facture ordinaire laisse ce champ vide. |
| **`period_starts_at`** | Donnée | Le début de la période couverte par la facture ou l’avoir. |
| **`period_ends_at`** | Donnée | La fin de la période couverte par la facture ou l’avoir. |
| **`due_at`** | Donnée | La date limite de paiement de la facture. Un avoir laisse ce champ vide. |
| **`saas_identity_snapshot`** | Donnée | La copie de l’identité professionnelle du SaaS au moment de l’émission du document. |
| **`customer_identity_snapshot`** | Donnée | La copie de l’identité du propriétaire qui paie le SaaS au moment de l’émission du document. |
| **`issued_at`** | Donnée | La date officielle d’émission de la facture ou de l’avoir. |
| **`cancelled_at`** | Donnée | La date où le brouillon de document a été annulé. |
| **`cancellation_reason`** | Donnée | Le texte qui explique pourquoi ce brouillon a été annulé. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`correlation_id`** | Donnée | Un code partagé avec les autres étapes de la même facturation, pour les retrouver ensemble. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 23. `saas_invoice_lines`

Le détail des factures et des avoirs : description, quantité, prix, remise, taxes et total. Une correction retrouve la vraie ligne de facture d’origine.

Une ligne d’avoir retrouve la facture et la ligne d’origine exactes. Elle ne recopie ni l’identité, ni la devise, ni le PDF de l’en-tête.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`document_id`** | Clé étrangère | La facture ou l’avoir auquel cette ligne appartient, à retrouver dans saas_invoices. |
| **`user_id`** | Clé étrangère | Le même propriétaire que celui du document. Cette référence empêche de mélanger les clients. |
| **`original_invoice_id`** | Clé étrangère | La facture d’origine que cet avoir ou sa ligne vient corriger, à retrouver dans saas_invoices. |
| **`original_invoice_line_id`** | Clé étrangère | La vraie ligne de facture corrigée par cette ligne d’avoir, à retrouver dans saas_invoice_lines. Une ligne de facture laisse ce champ vide. |
| **`document_type`** | Donnée | La même nature que le document parent : 1 = ligne de facture ; 2 = ligne d’avoir. Le serveur fixe cette valeur. |
| **`original_line_document_type`** | Calculé | Vaut automatiquement 1 quand une ligne d’origine est indiquée, pour imposer une ligne de facture. |
| **`line_number`** | Donnée | La position de cette ligne dans la facture ou l’avoir. Exemple : 1 pour la première ligne. |
| **`description`** | Donnée | Le texte qui explique ce qui est facturé ou corrigé sur cette ligne. |
| **`quantity`** | Donnée | La quantité facturée ou corrigée sur cette ligne. |
| **`net_unit_price`** | Donnée | Le prix d’une unité avant les taxes, sur une ligne de facture. |
| **`net_discount`** | Donnée | La réduction appliquée avant les taxes sur cette ligne de facture. |
| **`net_amount`** | Donnée | Le montant de cette ligne avant les taxes. |
| **`taxes`** | Donnée | Le détail historique des taxes appliquées sur cette ligne. |
| **`tax_amount`** | Donnée | Le total des taxes de cette ligne. |
| **`total_amount`** | Donnée | Le montant de cette ligne avec les taxes. |
| **`reason`** | Donnée | La raison de cette ligne d’avoir. Une ligne de facture laisse ce champ vide. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`correlation_id`** | Donnée | Un code pour retrouver cette ligne avec les autres étapes de la même facturation ou correction. Il peut rester vide. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 24. `saas_billing_settings`

Les réglages de la facturation : les règles qui disent quand facturer et les compteurs qui donnent des numéros uniques aux factures et aux avoirs.

Un compteur donne les numéros ; une règle décide quand facturer. Chaque ligne possède un seul de ces deux rôles.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`created_by_id`** | Clé étrangère | Le compte de la personne qui a créé cet élément. Il peut rester vide pour une création du système. |
| **`validated_by_id`** | Clé étrangère | L’administrateur qui a validé cette version de règle. Un compteur laisse ce champ vide. |
| **`record_type`** | Donnée | Le rôle de ce réglage : 1 = compteur de numérotation ; 2 = règle de facturation. |
| **`document_type`** | Donnée | Pour un compteur, le document qu’il numérote : 1 = facture ; 2 = avoir. Une règle laisse ce champ vide. |
| **`fiscal_year`** | Donnée | L’année à laquelle ce compteur de numéros s’applique. Une règle laisse ce champ vide. |
| **`prefix`** | Donnée | Le début ajouté devant les numéros de cette série. Exemple : FAC. |
| **`next_number`** | Donnée | Le prochain nombre à réserver dans ce compteur. |
| **`sequence_slot`** | Calculé | Vaut automatiquement 1 pour un compteur et reste vide pour les autres lignes. Il permet un seul compteur par année et genre de document. |
| **`code`** | Donnée | Le code stable de la règle de facturation, pour regrouper ses différentes versions. |
| **`version`** | Donnée | Le numéro de version de cette règle. Les anciens documents gardent leur ancienne version. |
| **`trigger_event`** | Donnée | L’événement qui déclenche la règle. Exemple : une nouvelle échéance à facturer. |
| **`numbering_scope`** | Donnée | Indique que les numéros sont ceux des documents émis par le SaaS. La valeur prévue est saas_issuer. |
| **`parameters`** | Donnée | Les réglages de cette version de règle : conditions, document à produire et paramètres autorisés. |
| **`policy_status`** | Donnée | L’état de la règle de facturation. Choix : 1 = brouillon ; 2 = validé ; 3 = actif ; 4 = retiré. |
| **`validation_reference`** | Donnée | Le texte ou la référence qui justifie la validation de cette règle. |
| **`effective_at`** | Donnée | La date à partir de laquelle cette version de règle peut s’appliquer. |
| **`ends_at`** | Donnée | La date où cette version de règle cesse de s’appliquer. |
| **`validated_at`** | Donnée | La date de validation de cette règle. Un compteur laisse ce champ vide. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`correlation_id`** | Donnée | Le code qui relie la création ou la validation de cette règle à ses autres étapes. Il peut rester vide sur un compteur. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 25. `saas_document_deliveries`

Les envois des factures et des avoirs : à qui, par quel moyen, quand, combien d’essais et si le document a bien été remis.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire auquel le document envoyé appartient. |
| **`document_id`** | Clé étrangère | La facture ou l’avoir déjà émis que l’on transmet, à retrouver dans saas_invoices. |
| **`created_by_id`** | Clé étrangère | Le compte de la personne qui a créé cet élément. Il peut rester vide pour une création du système. |
| **`proof_media_id`** | Clé étrangère | Le fichier privé qui prouve une remise documentée, s’il existe. Sa clé et son empreinte sont protégées dans media. |
| **`document_type`** | Donnée | La même nature que le document envoyé : 1 = facture ; 2 = avoir. Le serveur fixe cette valeur. |
| **`channel`** | Donnée | Le moyen utilisé pour transmettre le document. Choix : 1 = e-mail ; 2 = lien par SMS ; 3 = lien par WhatsApp ; 4 = remise avec preuve. |
| **`encrypted_recipient`** | Donnée | Les coordonnées du destinataire conservées de manière protégée. Exemple : l’e-mail recevant la facture. |
| **`delivery_status`** | Donnée | L’état de l’envoi du document, pour suivre son départ et sa réception. Choix : 1 = en attente ; 2 = en cours ; 3 = envoyé ; 4 = remis ou reçu ; 5 = échec pouvant être retenté ; 6 = échec définitif ; 7 = résultat incertain ; 8 = annulé. |
| **`attempts_count`** | Donnée | Le nombre d’essais d’envoi de ce document. |
| **`delivery_attempts`** | Donnée | L’historique technique limité des essais d’envoi et de leurs résultats. |
| **`next_attempt_at`** | Donnée | La date prévue pour un nouvel essai d’envoi autorisé. |
| **`sent_at`** | Donnée | La date où le document a été envoyé. |
| **`delivered_at`** | Donnée | La date où sa remise ou sa réception a été confirmée. |
| **`provider_reference`** | Donnée | La référence de l’envoi donnée par le service de communication. |
| **`error_code`** | Donnée | Le code d’une erreur rencontrée pendant l’envoi. |
| **`sending_started_at`** | Donnée | La date enregistrée juste avant de tenter l’envoi du document. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`correlation_id`** | Donnée | Le code qui relie cet envoi aux autres étapes de la même facturation. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 26. `saas_transfers`

Les paiements reçus et remboursements effectués à distance par banque, CCP ou BaridiMob : montant, preuve PDF, référence, auteur et vérification.

Un reçu déposé attend la vérification réelle. Un avoir réduit le dû ; un remboursement enregistre un virement sortant réel. Une écriture inverse corrige la comptabilité et conserve l’original.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`user_id`** | Clé étrangère | Le propriétaire concerné par ce paiement ou ce remboursement. |
| **`document_id`** | Clé étrangère | La facture concernée par ce paiement ou remboursement, à retrouver dans saas_invoices. |
| **`original_payment_id`** | Clé étrangère | Le paiement vérifié qui finance ce remboursement. Il vise un PAYMENT de type 1 dans saas_transfers ; un paiement laisse ce champ vide. |
| **`credit_note_id`** | Clé étrangère | L’avoir émis qui justifie un remboursement lorsque le prix facturé a diminué. Il vise document_type=2 dans saas_invoices ; un simple trop-payé peut laisser ce champ vide. |
| **`proof_media_id`** | Clé étrangère | Le fichier privé de preuve, à retrouver dans media. Sa clé et son empreinte sont conservées et protégées dans media. |
| **`source_proof_media_id`** | Clé étrangère | La photo originale du reçu dans media lorsqu’un PDF a été fabriqué à partir de cette photo. |
| **`created_by_id`** | Clé étrangère | Le compte qui a déclaré le paiement ou préparé le remboursement. Le préparateur d’un remboursement est un administrateur. |
| **`validated_by_id`** | Clé étrangère | L’administrateur qui a vérifié les fonds réellement reçus ou reversés. |
| **`performed_by_id`** | Clé étrangère | L’administrateur qui a réellement effectué le virement de remboursement. |
| **`reversal_of_id`** | Clé étrangère | L’écriture financière d’origine que l’on corrige en ajoutant un montant opposé. L’original reste conservé. |
| **`record_type`** | Donnée | Le sens de l’opération : 1 = paiement reçu du client ; 2 = remboursement versé par le SaaS. |
| **`document_type`** | Calculé | Vaut automatiquement 1, pour garantir que le virement est relié à une facture. |
| **`original_payment_record_type`** | Calculé | Vaut automatiquement 1 quand original_payment_id est rempli, pour imposer un vrai paiement source. |
| **`credit_note_document_type`** | Calculé | Vaut automatiquement 2 quand credit_note_id est rempli, pour imposer un vrai avoir. |
| **`transfer_method`** | Donnée | Le moyen utilisé pour effectuer le paiement ou le remboursement à distance. Choix : 1 = virement bancaire ; 2 = virement CCP ; 3 = BaridiMob ; 4 = autre transfert manuel. |
| **`transfer_status`** | Donnée | L’état du virement. Une correction par écriture inverse conserve l’original et son historique. Choix : 1 = déclaré ; 2 = approuvé ; 3 = vérifié ; 4 = refusé ; 5 = annulé ; 6 = résultat incertain ; 7 = corrigé par une écriture inverse. |
| **`refund_reason`** | Donnée | Le genre de raison qui justifie le remboursement. Choix : 1 = avoir ; 2 = trop-payé ; 3 = transfert reçu en double ; 4 = exception approuvée. |
| **`amount`** | Donnée | Le montant du paiement ou du remboursement. Il est positif pour un virement ordinaire ; un montant négatif sert uniquement à corriger une écriture. |
| **`currency`** | Donnée | La monnaie du virement, identique à celle de la facture. Au lancement : DZD, le dinar algérien. |
| **`reason`** | Donnée | Le motif du remboursement, du refus, du résultat incertain ou de la correction. Il peut rester vide sur une simple déclaration de paiement. |
| **`transfer_reference`** | Donnée | La référence réelle donnée pour le virement bancaire, CCP ou BaridiMob. |
| **`financial_account_key`** | Donnée | Le code interne qui reconnaît le compte bancaire ou CCP utilisé par le SaaS. |
| **`transaction_fingerprint`** | Donnée | Un résumé calculé du réseau, du compte, du sens et de la référence du virement, pour reconnaître la même transaction. |
| **`active_transaction_fingerprint`** | Calculé | Cette empreinte est activée automatiquement pour un virement vérifié et non corrigé par un inverse. Elle empêche de compter deux fois la même transaction. |
| **`encrypted_transfer_details`** | Donnée | Les informations bancaires nécessaires au transfert, conservées de manière protégée. |
| **`occurred_at`** | Donnée | La date réelle du virement, contrôlée lors de sa vérification. |
| **`sending_started_at`** | Donnée | La date enregistrée avant de faire le virement manuel de remboursement. Un paiement reçu laisse ce champ vide. |
| **`validated_at`** | Donnée | La date où l’administrateur a confirmé la vérification réelle de ce virement. |
| **`error_code`** | Donnée | Le code du problème rencontré lorsqu’un virement a un résultat incertain. |
| **`operation_key`** | Donnée | Un code stable qui permet de reconnaître une opération déjà traitée. Une demande répétée retrouve la même opération. |
| **`correlation_id`** | Donnée | Le code qui relie les étapes de ce paiement, remboursement ou correction. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |

### 27. `media`

Les informations pour retrouver les fichiers du SaaS : factures PDF, reçus, preuves, images… Le fichier lui-même est stocké séparément.

| Champ | Rôle | Explication très simple |
|---|---|---|
| **`id`** | Identifiant | Le numéro unique de cette ligne à l’intérieur de la base. Il est attribué automatiquement. |
| **`uuid`** | Identifiant | Un code public unique qui reconnaît cette ligne dans les liens, formulaires et exports. |
| **`created_by_id`** | Clé étrangère | Le compte de la personne qui a créé cet élément. |
| **`model_type`** | Donnée | Le type de l’objet auquel le fichier appartient. Exemple : facture SaaS ou paiement. |
| **`model_id`** | Lien selon son type | Le numéro de cet objet dans sa table, choisi selon model_type. |
| **`collection_name`** | Donnée | Le groupe d’utilisation du fichier. Exemple : facture, reçu, preuve ou logo. |
| **`disk`** | Donnée | L’espace de stockage dans lequel le fichier se trouve, local ou distant. |
| **`storage_key`** | Donnée | La clé ou le chemin qui permet de retrouver ce fichier dans cet espace. |
| **`mime_type`** | Donnée | Le format du fichier. Exemple : application/pdf pour un PDF. |
| **`original_name`** | Donnée | Le nom du fichier lorsqu’il a été envoyé. |
| **`size_bytes`** | Donnée | La taille du fichier, mesurée en octets. |
| **`width`** | Donnée | La largeur de l’image ou de la vidéo, en pixels. |
| **`height`** | Donnée | La hauteur de l’image ou de la vidéo, en pixels. |
| **`duration_seconds`** | Donnée | La durée de la vidéo, en secondes. |
| **`alt_text`** | Donnée | Un texte qui décrit l’image, utile notamment aux personnes qui ne peuvent pas la voir. |
| **`visibility`** | Donnée | Indique si le fichier est public ou accessible seulement après autorisation. Choix : 1 = public ; 2 = privé. |
| **`position`** | Donnée | L’ordre d’affichage du fichier dans son groupe. |
| **`is_primary`** | Donnée | Indique si ce fichier est le principal pour cet objet et ce groupe. |
| **`primary_slot`** | Calculé | Vaut automatiquement 1 pour un fichier principal non supprimé. Il empêche deux fichiers principaux dans le même groupe du même objet. |
| **`file_hash`** | Donnée | L’empreinte calculée à partir du fichier, pour vérifier que son contenu n’a pas changé. |
| **`created_at`** | Donnée | La date et l’heure où cette ligne a été créée. |
| **`updated_at`** | Donnée | La date et l’heure de la dernière modification de cette ligne. |
| **`deleted_at`** | Donnée | La date où cette ligne a été retirée de l’utilisation courante tout en restant conservée dans la base. |

# Diagramme complet de la BDD centrale

Source : [Schema-BDD-SaaS-Ecommerce-UUID.md](Schema-BDD-SaaS-Ecommerce-UUID.md), version V4.4 du 30 septembre 2026. Ce fichier rassemble **les 23 tables centrales et tous leurs champs dans un seul diagramme Mermaid**, avec chaque clé étrangère déclarée et les relations polymorphes. Le schéma principal reste inchangé.

**Lecture :** PK = clé primaire ; FK = clé étrangère SQL ; UK = unicité. Les liens marqués **FK** suivent la colonne indiquée. Les liens marqués **POLY** passent par un identifiant et son type de modèle ; ils ne créent pas de FK SQL universelle. Un trait plein correspond à une relation participant à la clé primaire de la table enfant ; les autres liens sont pointillés. Les extrémités indiquent un parent obligatoire ou facultatif et plusieurs lignes enfants possibles.

Les sujets du journal d’activité sont des liens conditionnels : subject_type choisit un seul modèle central autorisé pour subject_id, ou les deux sont NULL. Les lignes « si modèle auditable autorisé » montrent cette possibilité, sans imposer un nouvel alias ni une nouvelle FK. Les pivots Spatie ont seulement central_user comme modèle autorisé ; les comptes des boutiques restent dans leurs propres BDD. Pour media, le lien polymorphe documentaire explicite porte sur les factures/avoirs et reçus de paiement/remboursement ; les autres parents éventuels restent soumis aux modèles et collections autorisés au §7.6 du schéma principal.

```mermaid
erDiagram
    direction LR

    %% C1 - Identités centrales, pays et boutiques
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
        varchar name
        varchar first_name "nullable"
        varchar email UK
        varchar password
        varchar phone "nullable"
        datetime email_verified_at "nullable"
        datetime phone_verified_at "nullable"
        datetime whatsapp_verified_at "nullable"
        bigint_unsigned country_id FK "countries.id"
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
        bigint_unsigned legal_verified_by_id FK "nullable ; users.id ; administrateur central habilite"
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

    %% C2 - Fonctionnalités et autorisations centrales Spatie Permission
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

    %% C3 - Exceptions datées et restrictions de l’administration centrale
    permission_overrides {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        bigint_unsigned user_id FK "users.id"
        bigint_unsigned permission_id FK "permissions.id"
        tinyint_unsigned effect "PermissionEffectEnum"
        tinyint_unsigned status "OverrideStatusEnum"
        datetime started_at
        datetime ended_at "nullable"
        tinyint_unsigned active_slot "generated nullable"
        datetime expires_at "nullable"
        bigint_unsigned assigned_by_id FK "users.id"
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
        tinyint_unsigned effect "RestrictionEffectEnum"
        tinyint_unsigned status "OverrideStatusEnum"
        datetime started_at
        datetime ended_at "nullable"
        varchar(16) normalized_target_type "generated"
        bigint_unsigned normalized_target_id "generated ; 0 si global"
        tinyint_unsigned active_slot "generated nullable"
        datetime expires_at "nullable"
        bigint_unsigned created_by_id FK "users.id"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    %% C4 - Plans et abonnements
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
        tinyint_unsigned record_type "SubscriptionRecordTypeEnum ; NOT NULL"
        bigint_unsigned user_id FK "users.id ; proprietaire des deux types"
        bigint_unsigned parent_subscription_id FK "nullable ; subscriptions.id ; obligatoire pour echeance"
        tinyint_unsigned parent_record_type "generated STORED ; 1 pour echeance, sinon NULL"
        bigint_unsigned tenant_id FK "nullable ; tenants.id ; abonnement seulement"
        bigint_unsigned plan_id FK "nullable ; plans.id ; abonnement seulement"
        tinyint_unsigned status "nullable ; SubscriptionStatusEnum ; abonnement seulement"
        tinyint_unsigned period "nullable ; BillingPeriodEnum ; abonnement seulement"
        decimal agreed_amount "nullable ; abonnement seulement"
        datetime started_at "nullable ; abonnement seulement"
        datetime period_starts_at "requis ; periode de droit ou periode facturee"
        datetime period_ends_at "nullable pour abonnement gratuit ; requis pour echeance"
        datetime trial_ends_at "nullable ; abonnement seulement"
        datetime ended_at "nullable ; abonnement seulement"
        boolean auto_renew "nullable ; abonnement seulement"
        bigint_unsigned assigned_by_id FK "nullable ; users.id ; attribution abonnement"
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
        boolean is_active
        bigint limit "nullable"
        datetime started_at
        datetime expires_at "nullable"
        bigint_unsigned assigned_by_id FK "users.id"
        text reason "nullable"
        datetime created_at
        datetime updated_at
    }

    %% C5 - Suivi SaaS et référentiel géographique fusionné
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
        tinyint_unsigned type "GeoZoneTypeEnum"
        bigint_unsigned parent_id FK "nullable ; geographic_areas.id ; wilaya de la commune"
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

    %% C6 - Activités centrales avec Spatie Laravel Activity Log v5
    activity_log {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
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
        bigint_unsigned tenant_id FK "nullable ; tenants.id ; objet central concerné"
    }

    %% C7 - Historique des déploiements des BDD
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

    %% C8 - Facturation, règles, transmissions, paiements et remboursements SaaS : une seule table
    saas_invoices {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
        tinyint_unsigned record_type "SaasInvoiceRecordTypeEnum ; 1 a 9 ; NOT NULL"
        bigint_unsigned user_id FK "nullable types 1/6 ; users.id ; requis types 2/3/4/5/7/8/9"
        bigint_unsigned subscription_id FK "nullable ; subscriptions.id ; en-tetes types 2/3"
        tinyint_unsigned subscription_record_type "generated STORED ; 1 si subscription_id non NULL"
        bigint_unsigned installment_id FK "nullable ; subscriptions.id ; echeance type 2"
        tinyint_unsigned installment_record_type "generated STORED ; 2 si installment_id non NULL"
        bigint_unsigned billing_rule_id FK "nullable hors en-tetes 2/3 ; saas_invoices.id ; regle type 6"
        tinyint_unsigned billing_rule_record_type "generated STORED ; 6 si billing_rule_id non NULL"
        json billing_rule_snapshot "nullable hors en-tetes ; regle figee"
        bigint_unsigned parent_document_id FK "nullable ; saas_invoices.id ; requis lignes types 4/5"
        tinyint_unsigned parent_document_record_type "generated STORED ; 2 pour type 4, 3 pour type 5"
        bigint_unsigned original_invoice_id FK "nullable ; saas_invoices.id ; requis types 3/5"
        tinyint_unsigned original_invoice_record_type "generated STORED ; 2 si original_invoice_id non NULL"
        bigint_unsigned original_invoice_line_id FK "nullable ; saas_invoices.id ; requis type 5"
        tinyint_unsigned original_line_record_type "generated STORED ; 4 si original_invoice_line_id non NULL"
        bigint_unsigned sequence_id FK "nullable ; saas_invoices.id ; compteur type 1"
        tinyint_unsigned sequence_record_type "generated STORED ; 1 si sequence_id non NULL"
        tinyint_unsigned document_type "nullable hors types 1/2/3 ; DocumentTypeEnum ; facture ou avoir"
        int fiscal_year "nullable lignes/brouillon non numerote"
        varchar(32) prefix "nullable ; compteur seulement"
        bigint next_number "nullable ; compteur seulement"
        tinyint_unsigned sequence_slot "generated STORED ; 1 si record_type=1, sinon NULL"
        bigint sequence_number "nullable ; en-tete numerote seulement"
        varchar(191) number UK "nullable ; en-tete numerote seulement"
        int line_number "nullable ; lignes types 4/5 seulement"
        varchar description "nullable ; lignes seulement"
        decimal quantity "nullable ; lignes seulement"
        decimal net_unit_price "nullable ; ligne facture seulement"
        decimal net_discount "nullable ; ligne facture seulement"
        decimal net_amount "nullable hors types 2/3/4/5 ; HT document ou ligne"
        json taxes "nullable hors types 2/3/4/5 ; ventilation document ou ligne"
        decimal tax_amount "nullable hors types 2/3/4/5"
        decimal total_amount "nullable hors types 2/3/4/5"
        char(3) currency "requis types 2/3/8/9 ; NULL autres types ; DZD au lancement"
        tinyint_unsigned status "nullable hors en-tetes 2/3 ; DocumentStatusEnum"
        text reason "nullable ; requis avoir/ligne avoir, remboursement, refus ou correction"
        datetime period_starts_at "nullable hors en-tetes"
        datetime period_ends_at "nullable hors en-tetes"
        datetime due_at "nullable ; facture seulement"
        json saas_identity_snapshot "nullable hors en-tetes"
        json customer_identity_snapshot "nullable hors en-tetes"
        bigint_unsigned document_media_id FK "nullable avant generation ; media.id ; PDF prive"
        varchar immutable_document_key "nullable avant generation"
        char(64) document_hash "nullable avant generation"
        datetime issued_at "nullable avant emission"
        datetime cancelled_at "nullable ; brouillon annule"
        text cancellation_reason "nullable"
        bigint_unsigned document_id FK "nullable hors types 7/8/9 ; saas_invoices.id ; en-tete seulement"
        tinyint_unsigned document_record_type "nullable hors types 7/8/9 ; 2/3 pour envoi, 2 pour paiement/remboursement ; serveur"
        bigint_unsigned original_payment_id FK "nullable hors type 9 ; saas_invoices.id ; paiement source type 8"
        tinyint_unsigned original_payment_record_type "generated STORED ; 8 si original_payment_id non NULL"
        bigint_unsigned credit_note_id FK "nullable ; type 9 seulement ; saas_invoices.id ; avoir type 3"
        tinyint_unsigned credit_note_record_type "generated STORED ; 3 si credit_note_id non NULL"
        varchar(100) code "nullable ; regle seulement"
        int version "nullable ; regle seulement"
        varchar trigger_event "nullable ; regle seulement"
        varchar numbering_scope "nullable ; regle seulement ; saas_issuer"
        json parameters "nullable ; regle seulement"
        tinyint_unsigned policy_status "nullable ; PolicyStatusEnum ; regle seulement"
        text validation_reference "nullable avant validation regle"
        datetime effective_at "nullable ; regle seulement"
        datetime ends_at "nullable ; regle seulement"
        tinyint_unsigned channel "nullable ; DocumentDeliveryChannelEnum ; envoi seulement"
        text encrypted_recipient "nullable ; envoi seulement"
        tinyint_unsigned delivery_status "nullable ; DocumentDeliveryStatusEnum ; envoi seulement"
        int attempts_count "nullable ; envoi seulement"
        json delivery_attempts "nullable ; historique technique filtre des essais d'envoi"
        datetime next_attempt_at "nullable ; envoi seulement"
        datetime sent_at "nullable ; envoi seulement"
        datetime delivered_at "nullable ; envoi seulement"
        varchar provider_reference "nullable ; envoi seulement"
        varchar error_code "nullable ; envoi ou virement incertain"
        tinyint_unsigned transfer_method "nullable hors types 8/9 ; SaasTransferMethodEnum"
        tinyint_unsigned transfer_status "nullable hors types 8/9 ; SaasTransferStatusEnum"
        tinyint_unsigned refund_reason "nullable hors type 9 ; SaasRefundReasonEnum"
        decimal amount "nullable hors types 8/9 ; signe, positif hors contrepassation"
        varchar(191) transfer_reference "nullable avant verification ; reference bancaire/CCP"
        varchar(64) financial_account_key "nullable avant verification ; alias serveur du compte SaaS"
        char(64) transaction_fingerprint "nullable avant verification ; empreinte normalisee de transaction"
        char(64) active_transaction_fingerprint UK "generated STORED ; transaction physique verifiee non contre-passee"
        text encrypted_transfer_details "nullable ; informations bancaires minimales protegees"
        bigint_unsigned proof_media_id FK "nullable avant preuve ; media.id ; PDF prive"
        bigint_unsigned source_proof_media_id FK "nullable ; media.id ; original image si converti en PDF"
        char(64) proof_hash "nullable avant verification ; empreinte PDF"
        datetime occurred_at "nullable avant preuve ; date du virement reel"
        datetime sending_started_at "nullable ; avant envoi documentaire ou virement sortant manuel"
        bigint_unsigned created_by_id FK "nullable systeme ; users.id ; acteur central"
        bigint_unsigned validated_by_id FK "nullable avant validation ; users.id ; admin central"
        datetime validated_at "nullable avant validation"
        bigint_unsigned performed_by_id FK "nullable ; users.id ; admin auteur du remboursement"
        bigint_unsigned reversal_of_id FK "nullable ; types 8/9 ; saas_invoices.id ; erreur de verification"
        varchar(191) operation_key UK
        uuid correlation_id "nullable types 1 a 5 ; requis types 6 a 9 ; correlation stable"
        datetime created_at
        datetime updated_at
    }

    %% C9 - Fichiers centraux et relations polymorphes
    media {
        bigint_unsigned id PK "AUTO_INCREMENT ; interne"
        uuid uuid UK "UUID v4 ; public"
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
        bigint_unsigned created_by_id FK "nullable ; users.id"
        char(64) file_hash "nullable"
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable"
    }

    %% FK SQL : chaque colonne FK du schema central est representee
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
    subscriptions |o..o{ subscriptions : "FK parent_subscription_id - echeance type 2 vers abonnement type 1"
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
    geographic_areas |o..o{ geographic_areas : "FK parent_id - commune type 2 vers wilaya type 1"
    tenants |o..o{ activity_log : "FK tenant_id"
    tenants ||..o{ tenant_schema_deployments : "FK tenant_id"
    users |o..o{ saas_invoices : "FK user_id"
    subscriptions |o..o{ saas_invoices : "FK subscription_id - abonnement type 1"
    subscriptions |o..o{ saas_invoices : "FK installment_id - echeance type 2"
    saas_invoices |o..o{ saas_invoices : "FK billing_rule_id - regle type 6"
    saas_invoices |o..o{ saas_invoices : "FK parent_document_id - ligne 4 vers facture 2, ligne 5 vers avoir 3"
    saas_invoices |o..o{ saas_invoices : "FK original_invoice_id - facture type 2"
    saas_invoices |o..o{ saas_invoices : "FK original_invoice_line_id - ligne de facture type 4"
    saas_invoices |o..o{ saas_invoices : "FK sequence_id - compteur type 1"
    media |o..o{ saas_invoices : "FK document_media_id"
    saas_invoices |o..o{ saas_invoices : "FK document_id - envoi 7 vers 2/3, virement 8/9 vers 2"
    saas_invoices |o..o{ saas_invoices : "FK original_payment_id - paiement source type 8"
    saas_invoices |o..o{ saas_invoices : "FK credit_note_id - avoir type 3"
    media |o..o{ saas_invoices : "FK proof_media_id"
    media |o..o{ saas_invoices : "FK source_proof_media_id"
    users |o..o{ saas_invoices : "FK created_by_id"
    users |o..o{ saas_invoices : "FK validated_by_id"
    users |o..o{ saas_invoices : "FK performed_by_id"
    saas_invoices |o..o{ saas_invoices : "FK reversal_of_id - meme role financier 8 ou 9"
    users |o..o{ media : "FK created_by_id"

    %% Liens polymorphes : apparies avec model_type, subject_type ou causer_type
    users ||--o{ model_has_roles : "POLY model_id - central_user"
    users ||--o{ model_has_permissions : "POLY model_id - central_user"
    users |o..o{ activity_log : "POLY causer_id - acteur central_user"
    saas_invoices |o..o{ media : "POLY model_id - documents et recus types 2/3/8/9"
    countries |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    users |o..o{ activity_log : "POLY subject_id - central_user"
    tenants |o..o{ activity_log : "POLY subject_id - tenant"
    domains |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    contact_verifications |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    features |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    permissions |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    roles |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    permission_overrides |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    admin_restrictions |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    plans |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    plan_features |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    subscriptions |o..o{ activity_log : "POLY subject_id - subscription ou subscription_installment"
    feature_overrides |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    feature_usage |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    geographic_areas |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    tenant_schema_deployments |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
    saas_invoices |o..o{ activity_log : "POLY subject_id - alias du role 1 a 9"
    media |o..o{ activity_log : "POLY subject_id - si modele auditable autorise"
```

Les liens vers une même table représentent des lignes différentes : subscriptions type 1 = abonnement, type 2 = échéance ; geographic_areas type 1 = wilaya, type 2 = commune. Pour saas_invoices, les types sont 1 SEQUENCE, 2 INVOICE, 3 CREDIT_NOTE, 4 INVOICE_LINE, 5 CREDIT_NOTE_LINE, 6 RULE, 7 DELIVERY, 8 PAYMENT et 9 REFUND. Les champs facultatifs dans la table physique deviennent obligatoires pour les rôles concernés selon le schéma principal.

Les FK composites et règles de même propriétaire/pays/type/document d’origine restent celles des modules C1–C8 et du §6.7 du fichier source ; une flèche ne remplace pas ces contraintes. Les liens vers les bases de boutiques et les tables techniques Laravel/passkeys sont hors de ce diagramme central de 23 tables.

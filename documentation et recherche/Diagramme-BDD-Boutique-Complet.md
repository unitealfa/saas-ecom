# Diagramme complet de la BDD boutique

Source : [Schema-BDD-SaaS-Ecommerce-UUID.md](Schema-BDD-SaaS-Ecommerce-UUID.md), version V4.10 du 6 octobre 2026. Ce document présente **les 59 tables locales et leurs 1005 champs dans un seul diagramme Mermaid**, puis explique chaque table et chaque champ simplement. Chaque boutique possède cette structure dans sa propre BDD ; rôles et comptes restent indépendants. Aucune migration n’est exécutée.

**Identifiants :** chaque `id` métier est un numéro interne auto-incrémenté (1, 2, 3…), propre à sa table et à sa BDD. `uuid` reste un second champ unique pour les liens publics. Les FK locales utilisent les ID numériques ; les références au central, comme `shop.tenant_uuid` et `users.central_user_uuid`, restent des UUID sans FK SQL entre bases. Les trois pivots Spatie gardent leurs PK composites. Le numéro central utilisé par Tenancy ne devient jamais l’identité d’un utilisateur ou d’un produit local.

**Base de la boutique :** le central réserve `boutique_{slug_initial}`, par exemple `boutique_nour`, sans suffixe d’ID, et conserve ce nom dans `tenants.data.tenancy_db_name` avant le provisionnement. Le nom reste stable après renommage. Avec le préfixe `boutique_`, le slug initial est limité à 55 caractères ; les noms trop longs, déjà réservés ou déjà présents sont refusés, sans troncature ni adoption d’une autre base. Les bases déjà présentes ne changent que sur autorisation explicite ; l’utilisateur confirme aussi le retrait du suffixe des deux bases d’essai actuelles. Cette décision ne change aucune table ni relation du diagramme. Les espaces internes de cache, fichiers et jobs sont isolés par la clé Tenancy numérique ; aucun ID interne n’est exposé au client.

## 1. Les 59 tables expliquées très simplement

**Essais locaux :** le seeder explicite `Tenant\LocalDevelopmentSeeder` ajoute un employé, 20 produits et 50 commandes par boutique, avec des exemples fictifs de livraison, retours gratuit/payant, renvoi et facturation. `/_dev/database` est une exception strictement locale à la non-exposition des ID : environnement `local`/`testing`, debug actif et requête depuis la boucle locale. Il montre les liens, les nombres de lignes et des mesures de requêtes ; il n’effectue aucun appel transporteur ni envoi client. Aucune table ou relation du diagramme n’est modifiée ; voir §3.4 du schéma.

| Table | Explication très simple |
|---|---|
| **`shop`** | La fiche publique de la boutique : shop_name pour son nom affiché, logo, contacts et présentation. Ce nom est projeté depuis tenants.shop_name ; il reste distinct du prénom/nom d’une personne. |
| **`shop_addresses`** | Les adresses publiques et les liens sociaux de cette boutique : une ligne ADDRESS est un lieu, une ligne SOCIAL est un lien. Un lien peut concerner toute la boutique ou une adresse précise. |
| **`content_pages`** | Toutes les pages éditées de la vitrine : une page d’information comme « À propos », ou une page de vente consacrée à un produit. Le champ page_kind distingue les deux fonctions. |
| **`media`** | Les informations permettant de retrouver les fichiers de la boutique : images, vidéos, logos ou documents. Exemple : l’emplacement et le type de la photo d’un produit ; le fichier lui-même est stocké séparément. |
| **`categories`** | Les catégories et les étiquettes de produits : type 1 pour un rayon comme « Vêtements → T-shirts », type 2 pour un mot comme « Été ». Les produits restent dans leur propre table. |
| **`products`** | La présentation commune d’un produit : son nom, sa description et les informations partagées par ses versions. Exemple : le modèle « T-shirt coton », proposé ensuite en plusieurs tailles et couleurs. |
| **`product_variants`** | Les versions précises que l’on peut acheter, avec leur prix et leur stock. Exemple : « T-shirt rouge, taille M ». Un produit sans choix possède aussi une variante standard. |
| **`product_options`** | Les choix d’un produit, rangés dans une seule table : une ligne AXIS décrit « Taille » ; ses lignes VALUE proposent « M » et « L ». Une autre ligne AXIS peut décrire « Couleur », avec ses propres valeurs. |
| **`variant_option_values`** | Les choix qui composent une variante vendable. Exemple : le t-shirt précis a la taille M et la couleur rouge. La variante garde son prix, son SKU et son stock dans product_variants. |
| **`product_tags`** | Les liens entre un produit et ses étiquettes. Exemple : ce t-shirt porte les mots « Été » et « Nouveauté », conservés comme type 2 dans categories. |
| **`product_promotions`** | Les réductions appliquées automatiquement aux produits, sans code à saisir. Exemple : une réduction sur un produit pendant une période choisie. |
| **`product_reviews`** | Les notes et commentaires laissés sur les produits, même sans compte acheteur. Exemple : un client écrit « Très bon produit » ; la boutique décide ensuite de publier ou de masquer cet avis. |
| **`visitors`** | Un identifiant pour reconnaître un navigateur dans cette boutique, sans créer de compte acheteur. Exemple : reconnaître le même navigateur lors d’un retour sur le site, sans garantir qu’il s’agit de la même personne. |
| **`visit_sessions`** | Les différentes visites d’un navigateur sur la boutique. Exemple : une visite le matin puis une autre le soir peuvent former deux sessions pour le même visiteur. |
| **`navigation_events`** | Les actions minimales servant aux statistiques globales de la vitrine. Exemple : compter les vues de produits, ajouts au panier et débuts de commande, sans afficher le parcours individuel d’un navigateur. |
| **`carts`** | Les paniers conservés par le site pour les acheteurs invités. Exemple : un visiteur ajoute deux produits avant de renseigner ses coordonnées ; cela ne réserve pas encore le stock. |
| **`cart_items`** | Le contenu détaillé de chaque panier. Exemple : deux t-shirts rouges taille M, avec une éventuelle personnalisation, forment une ligne du panier. |
| **`orders`** | La fiche principale de chaque commande, avec son identité et son état commercial. Exemple : la commande de Karim reste la même commande même si son contenu est modifié avant expédition. |
| **`order_revisions`** | Les copies successives du contenu d’une commande à chaque modification. Exemple : la première version contient une taille M ; une nouvelle version contient une taille L, sans effacer l’ancienne. |
| **`order_items`** | Les produits et quantités d’une version précise de commande, avec les prix et coûts conservés à ce moment-là. Exemple : deux t-shirts à 2 000 DA chacun, même si le prix du catalogue change ensuite. |
| **`order_history`** | Le carnet des appels, rappels, propositions et changements concernant une commande. Exemple : noter un appel sans réponse ou un changement de taille ; le clic « Valider » est audité séparément dans `activity_log`. |
| **`stock_movements`** | Le carnet de tous les changements de stock. Exemple : recevoir dix articles, en réserver deux, les expédier ou constater une perte, en gardant l’explication de chaque changement. |
| **`order_returns`** | Les dossiers des colis qui reviennent à la boutique. Exemple : un client refuse son colis. Au lancement, le retour porte sur tout le colis ; la structure permet une évolution future. |
| **`return_items`** | Le détail de ce qui est attendu et constaté dans un retour. Exemple : sur trois articles attendus, deux sont reçus et un manque ; les articles reçus peuvent être revendables ou abîmés. |
| **`shipping_providers`** | Les personnes ou sociétés qui livrent pour la boutique. Exemple : un livreur interne ou EcoTrack, avec le suivi de l’argent qu’ils doivent reverser. |
| **`shipping_rates`** | Les trois sortes de tarifs dans une table : prix client, devis du prestataire et versions du tarif de retour du compte. Leur type empêche de les confondre. |
| **`free_shipping_rules`** | Les conditions qui rendent automatiquement la livraison gratuite pour le client. Exemple : offrir la livraison lorsque la commande remplit la règle définie par la boutique. |
| **`shipments`** | Le colis envoyé pour une commande et les informations permettant de le suivre. Exemple : une commande de trois produits part dans un seul colis avec un numéro de suivi. |
| **`shipment_events`** | Les étapes reçues pendant le transport, avec le message original du transporteur. Exemple : « en livraison », puis « livré » ou « refusé ». |
| **`carrier_operations`** | Les demandes à envoyer au transporteur, conservées pour pouvoir les suivre et les reprendre. Exemple : demander la création d’un colis sans créer un deuxième colis si la réponse est incertaine. |
| **`carrier_operation_attempts`** | Le résultat de chaque essai de communication avec le transporteur. Exemple : le premier essai échoue ; une nouvelle tentative est enregistrée séparément sans effacer la précédente. |
| **`collections`** | Le suivi de l’argent lié à un colis : ce qui doit être encaissé et reversé. Exemple : le client a payé le livreur, mais le commerçant attend encore son argent. |
| **`remittance_statements`** | Les documents de suivi d’un règlement avec le livreur ou le transporteur pour cette boutique. Exemple : expliquer le montant reçu en distinguant ventes, frais et indemnisations. |
| **`carrier_settlement_lines`** | Le détail des règlements : produits reversés, frais réglés, créances apurées ou indemnités. Chaque ligne indique clairement laquelle de ces quatre actions elle représente. |
| **`expenses`** | Les autres dépenses réelles de la boutique, hors frais transporteur et pertes de stock déjà suivis ailleurs. Exemple : publicité, emballages ou frais généraux. |
| **`customer_adjustments`** | Le suivi des remboursements aux acheteurs, avec leurs montants, motifs et états. Exemple : enregistrer un remboursement réellement effectué, sans créer de portefeuille client. |
| **`order_documents`** | Les bons de commande facultatifs correspondant à une version précise de la commande. Exemple : conserver un document indiquant exactement les articles et les prix de cette version. |
| **`activity_log`** | Le carnet de la boutique : qui a fait quoi, quand et sur quel élément. Il garde aussi la validation par clic et les opérations sensibles sur les données, sans seconde table de journal. |
| **`carrier_fees`** | Les frais liés au transport et la personne qui doit les payer. Exemple : des frais de retour à la charge du commerçant, distincts de la livraison payée par l’acheteur. |
| **`carrier_receivables`** | Les sommes que le transporteur doit rendre après correction de frais déjà payés. Exemple : 650 DA ont été payés au lieu de 600 DA ; le transporteur doit encore 50 DA au commerçant. |
| **`collection_entries`** | Les montants réellement encaissés auprès du client et vérifiés, avec leurs éventuelles corrections. Exemple : confirmer que le livreur a reçu 5 000 DA ; cela ne prouve pas encore leur reversement au commerçant. |
| **`invoices`** | Les factures de vente de la boutique et les avoirs qui les corrigent, avec leur contenu historique conservé. Exemple : garder la facture d’origine puis créer un avoir si son montant doit être réduit. |
| **`order_incidents`** | Le dossier d’un problème concernant une ligne de produits expédiée et les limites de sa prise en charge. Exemple : un article cassé pour lequel on examine un remplacement ou un remboursement. |
| **`order_incident_details`** | Les différents problèmes et quantités dans un dossier d’incident. Exemple : sur trois articles, un est cassé, un manque et le troisième est correct ; on ne compte pas deux fois le même article. |
| **`billing_rules`** | Une seule table locale contient les compteurs de numéros et les règles de facturation. Une ligne type 1 réserve les numéros de facture/avoir ; une ligne type 2 décrit quand et comment la boutique produit ses documents. |
| **`sales_terms_acceptances`** | Garder quelles conditions ont réellement été acceptées, à quelle date et pour quelle version de commande. Ce n'est ni un PDF d'accord téléphonique ni un envoi au client. |
| **`billing_obligations`** | Les factures ou avoirs à produire après un événement commercial prévu, avec leurs tentatives et le document finalement émis. Aucun crédit fictif pour un colis jamais payé. |
| **`commercial_corrections`** | Les décisions qui corrigent les montants des ventes, avec la date où elles comptent dans les statistiques. Exemple : enregistrer une réduction après un retour, séparément du retour physique et du remboursement réel. |
| **`commercial_correction_lines`** | Le détail d’une correction commerciale pour chaque ligne de produits concernée. Exemple : retirer 2 000 DA de ventes pour un article et indiquer aussi la correction de son coût dans les résultats. |
| **`users`** | Les comptes du propriétaire et des employés dans cette boutique : first_name pour leur prénom, last_name pour leur nom de famille, connexion et état d’accès à l’équipe. |
| **`permissions`** | La liste des actions qu’une personne peut être autorisée à faire dans cette boutique : créer un produit, valider une commande ou inviter un employé. |
| **`roles`** | Les groupes d’actions de cette boutique, avec un nom unique. Deux rôles de mêmes actions et mêmes durées sont refusés même avec des noms différents. |
| **`role_has_permissions`** | Les actions de chaque rôle et la durée de chacune : 9999 jours par défaut et au maximum, ou une durée plus courte. |
| **`model_has_roles`** | Les rôles attribués à chaque compte, avec leur date de départ propre. Plusieurs rôles sont possibles sans action commune entre eux. |
| **`model_has_permissions`** | Une autorisation directe native, si utilisée, avec ses dates obligatoires ; elle ne peut pas doubler une action déjà donnée par un rôle. |
| **`team_invitations`** | Les invitations permettant à un employé de rejoindre cette boutique avec un rôle précis et un lien secret qui expire. |
| **`contact_verifications`** | Les codes protégés utilisés pour vérifier les contacts des comptes du propriétaire et des employés ; les acheteurs ne reçoivent aucun message. |
| **`carrier_accounts`** | Les connexions de cette boutique aux services de livraison : compte transporteur, adresse API et secrets protégés. |
| **`carrier_remittance_batches`** | Les lots de versements annoncés par un compte transporteur, leur justificatif et la part vérifiée pour cette boutique. |

## 2. Un seul diagramme pour toute la BDD boutique

**Lecture :** PK = clé primaire ; FK = lien SQL dans cette même BDD ; UK = unicité ; REF = référence UUID externe, sans FK entre bases. u64 = BIGINT UNSIGNED ; u16 = SMALLINT UNSIGNED ; u8 = TINYINT UNSIGNED ; « ? » = champ pouvant être NULL selon sa phase/type. Identifiants d’abord, FK/références ensuite, autres champs après. Les pivots Spatie gardent leurs clés composites natives, sans id/uuid inventés.

**Un « ? » ne signifie pas « toujours facultatif » :** la date et la révision de confirmation sont obligatoires après le clic Valider ; un bureau de retrait et son snapshot sont obligatoires en stop desk ; le PDF et la date d'émission sont obligatoires pour une facture émise. Les règles du schéma imposent ces obligations. Les colonnes calculées sont remplies par la BDD, sans saisie utilisateur.

Les 192 liens FK couvrent toutes les colonnes marquées FK. Les 72 liens POLY sont conditionnels : subject_type, causer_type ou model_type choisissent un modèle explicitement autorisé ; aucune FK SQL universelle n’est créée. Les traits pleins participent à la PK ; les autres sont pointillés. Les FK composites, types, phases, plafonds et transactions restent obligatoires selon le schéma principal, même si le dessin montre chaque colonne séparément.

Les comptes, pivots, médias et audits utilisent seulement les modèles de cette boutique. Les alias des tables typées imposent leur rôle : catégorie/étiquette, adresse/lien social, contenu/page de vente, axe/valeur, type de tarif ou règlement. Les UUID centraux visibles ne recopient pas les tables centrales et ne donnent aucun accès à leurs comptes. Le stop desk garde l’UUID et le snapshot du bureau accepté.

```mermaid
erDiagram
    direction LR

shop {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  uuid tenant_uuid "REF central.tenants.uuid"
  u64 logo_media_id FK "?"
  u64 favicon_media_id FK "?"
  tinyint singleton UK
  bigint central_profile_version
  varchar shop_name
  text description "?"
  text about "?"
  varchar business_type
  varchar contact_email "?"
  varchar contact_phone "?"
  varchar contact_whatsapp "?"
  varchar locale
  char(3) currency
  varchar timezone
  varchar theme_code
  json colors
  json shipping_tax_configuration "?"
  int cart_lifetime_days
  datetime created_at
  datetime updated_at
}

shop_addresses {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 shop_id FK
  u64 shop_address_id FK "?"
  uuid province_uuid "? ; REF central.geographic_areas.uuid"
  uuid municipality_uuid "? ; REF central.geographic_areas.uuid"
  u8 record_type
  u8 shop_address_type "?"
  varchar label "?"
  int position
  boolean is_primary
  boolean visible
  json payload
  u8 primary_slot "?"
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

content_pages {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK "?"
  u8 page_kind
  varchar slug
  varchar type "?"
  varchar title
  json content
  varchar meta_title "?"
  text meta_description "?"
  varchar canonical_url "?"
  boolean indexable
  boolean is_published
  datetime published_at "?"
  int version
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

media {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 created_by_id FK "?"
  varchar storage_key UK
  u64 model_id
  varchar(64) model_type
  varchar(64) collection_name
  varchar(64) disk
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
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

categories {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 parent_id FK "?"
  u64 media_id FK "?"
  u8 record_type
  u8 parent_record_type "?"
  varchar name
  varchar slug
  text description "?"
  int position
  boolean is_active
  varchar meta_title "?"
  text meta_description "?"
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

products {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 category_id FK "?"
  u8 category_record_type "?"
  varchar name
  varchar slug
  text short_description "?"
  text description "?"
  json benefits "?"
  json faq "?"
  varchar brand "?"
  u8 type
  boolean allows_customization
  text customization_instructions "?"
  varchar sale_unit
  decimal content_quantity "?"
  varchar content_unit "?"
  u8 status
  datetime published_at "?"
  boolean is_featured
  varchar meta_title "?"
  text meta_description "?"
  boolean indexable
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

product_variants {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK
  varchar label
  varchar sku
  varchar barcode "?"
  char(64) combination_signature
  datetime used_at "?"
  decimal sale_price
  decimal unit_cost
  decimal previous_price "?"
  json tax_configuration "?"
  int physical_stock
  int reserved_stock
  int quarantine_stock
  int low_stock_threshold
  decimal weight_kg "?"
  decimal length_cm "?"
  decimal width_cm "?"
  decimal height_cm "?"
  boolean is_active
  int position
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

product_options {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK
  u64 parent_id FK "?"
  u8 record_type
  u8 parent_record_type "?"
  varchar name
  varchar identity_code "?"
  u8 display_type "?"
  char(7) color_hex "?"
  int position
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

variant_option_values {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK
  u64 variant_id FK
  u64 option_id FK
  u64 value_id FK
  u8 option_record_type
  u8 value_record_type
  datetime created_at
  datetime updated_at
}

product_tags {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK
  u64 tag_id FK
  u8 tag_record_type
  datetime created_at
  datetime updated_at
}

product_promotions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK
  u64 variant_id FK "?"
  u64 sales_page_id FK "?"
  varchar name
  u8 discount_type
  decimal value
  int minimum_quantity
  datetime started_at "?"
  datetime ended_at "?"
  int priority
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

product_reviews {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK
  u64 visitor_id FK "?"
  u64 order_item_id FK "?"
  u64 moderated_by_id FK "?"
  varchar display_name
  int note
  text comment
  u8 moderation_status
  datetime moderated_at "?"
  datetime published_at "?"
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

visitors {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar token_hash
  datetime first_visited_at
  datetime last_visited_at
  datetime expires_at
  datetime created_at
  datetime updated_at
}

visit_sessions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 visitor_id FK
  datetime started_at
  datetime last_activity_at
  datetime ended_at "?"
  varchar entry_path
  varchar source "?"
  varchar medium "?"
  varchar campaign "?"
  varchar referrer_host "?"
  u8 device_type "?"
  datetime created_at
  datetime updated_at
}

navigation_events {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 session_id FK
  u64 product_id FK "?"
  u64 variant_id FK "?"
  u64 sales_page_id FK "?"
  u64 content_page_id FK "?"
  u64 cart_id FK "?"
  u8 sales_page_kind "?"
  u8 content_page_kind "?"
  varchar type
  varchar path
  int quantity "?"
  datetime occurred_at
  datetime received_at
  datetime created_at
}

carts {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 visitor_id FK
  u8 status
  datetime last_activity_at
  datetime expires_at
  datetime converted_at "?"
  datetime created_at
  datetime updated_at
}

cart_items {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 cart_id FK
  u64 variant_id FK
  u64 product_id FK
  u64 sales_page_id FK "?"
  int quantity
  text customization_text "?"
  char(64) customization_signature
  datetime created_at
  datetime updated_at
}

orders {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 visitor_id FK "?"
  u64 cart_id FK "?"
  u64 original_session_id FK "?"
  u64 original_sales_page_id FK "?"
  u64 original_return_id FK "?"
  u64 original_order_id FK "?"
  u64 original_incident_id FK "?"
  u64 current_revision_id FK "?"
  u64 confirmed_revision_id FK "?"
  u64 confirmation_owner_id FK "?"
  u64 operationally_confirmed_by_id FK "?"
  u8 original_sales_page_kind "?"
  u8 unpaid_resend_slot "?"
  varchar number
  varchar data_policy_version
  datetime data_notice_acknowledged_at
  char(64) notice_text_hash "?"
  int original_incident_quantity "?"
  varchar replacement_reason "?"
  u8 order_type
  u8 channel
  u8 commercial_status
  datetime validated_at "?"
  datetime operationally_confirmed_at "?"
  varchar submission_key
  char(64) submission_hash
  int lock_version
  boolean retention_hold
  text retention_hold_reason "?"
  datetime hold_review_at "?"
  datetime created_at
  datetime updated_at
}

order_revisions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 author_id FK "?"
  u64 free_shipping_rule_id FK "?"
  uuid pickup_point_uuid "? ; REF central.pickup_points.uuid"
  uuid province_uuid "REF central.geographic_areas.uuid"
  uuid municipality_uuid "REF central.geographic_areas.uuid"
  int revision_number
  char(3) currency
  char(2) country_code
  json legal_seller_snapshot
  json shipping_tax_snapshot
  text reason "?"
  varchar recipient_last_name
  varchar recipient_first_name "?"
  varchar phone
  varchar secondary_phone "?"
  varchar email "?"
  text address
  varchar province_name
  varchar municipality_name
  varchar postal_code "?"
  u8 delivery_mode
  json pickup_point_snapshot "?"
  decimal catalog_subtotal
  decimal applied_subtotal
  decimal customer_shipping_fee
  decimal shipping_discount
  u8 shipping_charge_bearer
  decimal merchant_shipping_amount
  decimal order_total
  decimal return_cost_recovery_amount
  text return_cost_recovery_reason "?"
  decimal amount_to_collect
  text customer_note "?"
  varchar sales_terms_version
  json sales_terms_snapshot
  datetime created_at
}

order_items {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 revision_id FK
  u64 variant_id FK
  u64 product_id FK
  u64 promotion_id FK "?"
  u64 sales_page_id FK "?"
  varchar product_name
  varchar variant_name
  varchar sku
  json options_snapshot "?"
  text customization_text "?"
  int quantity
  decimal catalog_unit_price
  decimal applied_unit_price
  boolean is_price_overridden
  text price_change_reason "?"
  u8 price_origin
  json promotion_snapshot "?"
  decimal unit_cost_snapshot
  decimal line_total
  json tax_snapshot
  u8 reservation_status "?"
  datetime reserved_at "?"
  datetime reservation_released_at "?"
  datetime reservation_created_at "?"
  datetime reservation_updated_at "?"
  datetime created_at
}

order_history {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 previous_revision_id FK "?"
  u64 next_revision_id FK "?"
  u64 actor_id FK "?"
  varchar action
  u8 contact_outcome "?"
  datetime next_callback_at "?"
  u8 previous_status "?"
  u8 new_status "?"
  json changes "?"
  text note "?"
  uuid correlation_id
  u8 origin
  datetime created_at
}

stock_movements {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 variant_id FK
  u64 order_item_id FK "?"
  u64 return_item_id FK "?"
  u64 actor_id FK "?"
  u64 reversal_of_id FK "?"
  bigint variant_sequence
  u8 type
  int physical_delta
  int reserved_delta
  int quarantine_delta
  int return_received_delta
  int return_restocked_delta
  int return_lost_delta
  int return_missing_delta
  int physical_before
  int physical_after
  int reserved_before
  int reserved_after
  int quarantine_before
  int quarantine_after
  decimal unit_cost_snapshot
  decimal loss_amount
  varchar operation_key
  uuid correlation_id
  text note "?"
  datetime created_at
}

order_returns {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 shipment_id FK
  u64 order_id FK
  u64 shipped_revision_id FK
  u64 received_by_id FK "?"
  u8 reason
  text detail "?"
  u8 status
  datetime requested_at "?"
  datetime received_at "?"
  datetime closed_at "?"
  datetime created_at
  datetime updated_at
}

return_items {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 return_id FK
  u64 order_item_id FK
  u64 shipped_revision_id FK
  u64 variant_id FK
  u64 inspected_by_id FK "?"
  int expected_quantity
  int received_quantity
  int restocked_quantity
  int lost_quantity
  int quarantined_quantity
  int documented_missing_quantity
  text discrepancy_reason "?"
  decimal unit_cost_snapshot
  datetime inspected_at "?"
  text note "?"
  datetime created_at
  datetime updated_at
}

shipping_providers {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 user_id FK "?"
  u64 carrier_account_id FK "?"
  u8 type
  varchar name
  varchar phone "?"
  varchar email "?"
  json reference_configuration "?"
  datetime last_synced_at "?"
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

shipping_rates {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 provider_id FK "?"
  u64 carrier_account_id FK "?"
  u64 created_by_id FK "?"
  uuid province_uuid "? ; REF central.geographic_areas.uuid"
  uuid municipality_uuid "? ; REF central.geographic_areas.uuid"
  u8 record_type
  u8 delivery_mode "?"
  u8 service_type "?"
  decimal amount
  u8 source "?"
  datetime retrieved_at "?"
  datetime starts_at "?"
  datetime ends_at "?"
  boolean is_active
  u64 provider_scope_id
  uuid municipality_scope_uuid "?"
  u8 current_slot "?"
  datetime created_at
  datetime updated_at "?"
  datetime deleted_at "?"
}

free_shipping_rules {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK "?"
  uuid province_uuid "? ; REF central.geographic_areas.uuid"
  varchar name
  u8 delivery_mode "?"
  decimal minimum_cart_amount "?"
  datetime started_at "?"
  datetime ended_at "?"
  int priority
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

shipments {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 shipped_revision_id FK
  u64 provider_id FK
  u64 label_media_id FK "?"
  u64 assigned_by_id FK
  uuid pickup_point_uuid "? ; REF central.pickup_points.uuid"
  u8 delivery_mode
  u8 status
  varchar raw_external_status "?"
  varchar tracking "?"
  varchar merchant_reference "?"
  varchar external_reference "?"
  decimal cod_amount
  decimal estimated_cost
  decimal weight_kg "?"
  boolean is_fragile
  datetime shipped_at "?"
  datetime carrier_validated_at "?"
  datetime delivered_at "?"
  datetime last_synced_at "?"
  datetime created_at
  datetime updated_at
}

shipment_events {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 shipment_id FK
  u64 actor_id FK "?"
  u8 logistics_status "?"
  u8 financial_status "?"
  varchar external_code "?"
  varchar event_type
  varchar raw_external_activity "?"
  varchar raw_external_status "?"
  varchar adapter_version
  json sanitized_external_payload "?"
  char(64) payload_hash
  datetime payload_expires_at "?"
  datetime payload_purged_at "?"
  text reason "?"
  text comment "?"
  varchar station "?"
  varchar courier_label "?"
  datetime next_delivery_attempt_at "?"
  datetime occurred_at "?"
  datetime observed_at
  u8 source
  varchar deduplication_key
  datetime created_at
}

carrier_operations {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 provider_id FK
  u64 shipment_id FK "?"
  u64 order_id FK "?"
  u64 return_id FK "?"
  u64 revision_id FK "?"
  u64 superseded_by_operation_id FK "?"
  u64 triggered_by_id FK "?"
  u8 type
  varchar operation_key
  json sanitized_request
  text encrypted_personal_request "?"
  char(64) request_hash
  datetime request_expires_at "?"
  datetime request_purged_at "?"
  varchar merchant_reference "?"
  varchar adapter_version
  json technical_result "?"
  u8 status
  int attempts_count
  datetime next_attempt_at "?"
  datetime ended_at "?"
  datetime sending_started_at "?"
  datetime superseded_at "?"
  datetime created_at
  datetime updated_at
}

carrier_operation_attempts {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 operation_id FK
  int attempt_number
  int http_status_code "?"
  json sanitized_response "?"
  datetime payload_expires_at "?"
  datetime payload_purged_at "?"
  varchar error_code "?"
  int duration_ms
  datetime started_at
  datetime ended_at "?"
  datetime created_at
}

collections {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 shipment_id FK
  u8 declared_status
  decimal expected_amount
  decimal declared_collected_amount "?"
  datetime collected_at "?"
  datetime payment_ready_at "?"
  datetime declared_paid_at "?"
  varchar source
  datetime reconciled_at "?"
  datetime created_at
  datetime updated_at
}

remittance_statements {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 provider_id FK
  u64 carrier_remittance_batch_id FK "?"
  u64 validated_by_id FK "?"
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  varchar number
  varchar external_reference "?"
  u8 type
  u8 status
  decimal gross_amount
  decimal fee_amount
  decimal expected_net_amount
  decimal received_net_amount "?"
  datetime declared_at "?"
  datetime received_at "?"
  text note "?"
  varchar operation_key
  datetime reconciled_at "?"
  datetime created_at
  datetime updated_at
}

carrier_settlement_lines {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 provider_id FK
  u64 remittance_statement_id FK "?"
  u64 collection_id FK "?"
  u64 carrier_fee_id FK "?"
  u64 receivable_id FK "?"
  u64 shipment_id FK "?"
  u64 replacement_order_id FK "?"
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  varchar operation_key UK
  u8 record_type
  decimal amount
  u8 fee_payment_mode "?"
  u8 receivable_settlement_type "?"
  varchar reason "?"
  varchar external_reference "?"
  datetime performed_at "?"
  datetime created_at
}

expenses {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 product_id FK "?"
  u64 order_id FK "?"
  u64 shipment_id FK "?"
  u64 return_id FK "?"
  u64 proof_media_id FK "?"
  u64 author_id FK
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  varchar category
  varchar label
  decimal amount
  datetime expense_date
  u8 status
  varchar source
  varchar operation_key
  datetime cancelled_at "?"
  text note "?"
  datetime created_at
  datetime updated_at
}

customer_adjustments {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 return_id FK "?"
  u64 incident_id FK
  u64 credit_note_id FK "?"
  u64 validated_by_id FK "?"
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  int compensated_quantity
  u8 amount_kind
  u8 type
  decimal amount
  u8 status
  datetime performed_at "?"
  varchar reference "?"
  text reason
  varchar operation_key
  datetime created_at
  datetime updated_at
}

order_documents {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 revision_id FK
  u64 media_id FK
  u64 generated_by_id FK
  varchar number
  int document_version
  json issuer_snapshot
  datetime generated_at
  datetime created_at
}

activity_log {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar(191) operation_key UK "?"
  u64 subject_id "?"
  u64 causer_id "?"
  varchar(64) log_name "?"
  text description
  varchar(64) subject_type "?"
  varchar(100) event "?"
  varchar(64) causer_type "?"
  json attribute_changes "?"
  json properties "?"
  uuid correlation_id "?"
  u8 origin
  datetime performed_at "?"
  datetime created_at
  datetime updated_at
}

carrier_fees {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 shipment_id FK
  u64 provider_id FK
  u64 return_id FK "?"
  u64 carrier_account_id FK "?"
  u64 source_rate_id FK "?"
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  json rate_snapshot "?"
  u8 source_rate_record_type "?"
  u8 fee_type
  u8 payer
  u8 settlement_mode
  decimal amount
  u8 status
  datetime triggered_at
  varchar date_source
  datetime recognized_at "?"
  varchar external_reference "?"
  varchar operation_key
  datetime created_at
  datetime updated_at
}

carrier_receivables {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 provider_id FK
  u64 carrier_fee_id FK
  u64 original_fee_payment_id FK "?"
  u64 reversal_of_id FK "?"
  decimal initial_amount
  decimal remaining_amount
  varchar reason
  u8 original_fee_payment_record_type "?"
  u8 status
  varchar operation_key
  datetime recognized_at
  datetime settled_at "?"
  datetime created_at
  datetime updated_at
}

collection_entries {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 collection_id FK
  u64 verified_by_id FK
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  decimal amount
  datetime collected_at
  datetime verified_at
  varchar reference
  text reason
  varchar operation_key
  datetime created_at
}

invoices {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 revision_id FK
  u64 original_invoice_id FK "?"
  u64 sequence_id FK "?"
  u64 media_id FK "?"
  u64 issued_by_id FK "?"
  u64 incident_id FK "?"
  u8 document_type
  u8 sequence_record_type "?"
  int fiscal_year "?"
  bigint sequence_number "?"
  int snapshot_format_version
  char(3) currency
  varchar number "?"
  u8 status
  json seller_snapshot
  json client_snapshot
  json items_snapshot
  json totals_snapshot
  datetime issued_at "?"
  datetime cancelled_at "?"
  text cancellation_reason "?"
  varchar operation_key
  varchar document_reason "?"
  datetime created_at
  datetime updated_at
}

order_incidents {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 shipment_id FK
  u64 shipped_revision_id FK
  u64 order_item_id FK,UK
  u64 return_id FK "?"
  u64 opened_by_id FK "?"
  u64 validated_by_id FK "?"
  varchar operation_key UK
  int affected_quantity
  decimal eligible_product_amount
  decimal eligible_shipping_amount
  u8 status
  text reason
  datetime validated_at "?"
  datetime closed_at "?"
  datetime created_at
  datetime updated_at
}

order_incident_details {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 incident_id FK
  u64 author_id FK "?"
  u8 type
  int quantity
  text reason
  datetime created_at
  datetime updated_at
}

billing_rules {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 validated_by_id FK "?"
  u8 record_type
  u8 document_type "?"
  int fiscal_year "?"
  varchar(32) shop_prefix "?"
  bigint next_number "?"
  u8 sequence_slot "?"
  varchar(100) code "?"
  int version "?"
  u64 seller_profile_version "?"
  varchar trigger_event "?"
  varchar return_resend_rule "?"
  varchar numbering_scope "?"
  json parameters "?"
  u8 policy_status "?"
  text validation_reference "?"
  datetime validated_at "?"
  datetime effective_at "?"
  datetime ends_at "?"
  datetime created_at
  datetime updated_at
}

sales_terms_acceptances {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 revision_id FK
  varchar operation_key UK
  varchar sales_terms_version
  char(64) terms_hash
  datetime accepted_at
  u8 acceptance_mode
  json sanitized_proof "?"
  datetime created_at
}

billing_obligations {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 revision_id FK
  u64 billing_rule_id FK
  u64 original_invoice_id FK "?"
  u64 invoice_id FK "?"
  varchar operation_key UK
  u64 event_id
  u8 billing_rule_record_type
  json rule_snapshot
  varchar event_type
  datetime triggered_at
  u8 document_type
  u8 status
  int attempts_count
  datetime next_attempt_at "?"
  varchar error_code "?"
  datetime created_at
  datetime updated_at
}

commercial_corrections {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 order_id FK
  u64 source_revision_id FK
  u64 incident_id FK "?"
  u64 correction_of_id FK "?"
  u64 actor_id FK "?"
  varchar operation_key UK
  u8 correction_type
  u8 status
  decimal non_product_revenue_delta
  u8 non_product_kind
  datetime effective_at
  datetime recorded_at
  text reason
  datetime created_at
}

commercial_correction_lines {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 correction_id FK
  u64 source_revision_id FK
  u64 order_item_id FK
  int affected_quantity
  decimal reference_sale_amount
  decimal revenue_delta
  decimal sold_cost_delta
  text detailed_reason "?"
  datetime created_at
}

users {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  uuid central_user_uuid UK "? ; REF central.users.uuid"
  varchar email UK
  varchar last_name
  varchar first_name "?"
  varchar password
  varchar phone "?"
  datetime email_verified_at "?"
  varchar(10) locale
  u8 status
  u8 membership_status
  datetime joined_at "?"
  datetime last_login_at "?"
  varchar(100) remember_token "?"
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

permissions {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  varchar(125) name
  varchar(32) guard_name
  varchar label
  varchar(100) feature_code "?"
  datetime created_at
  datetime updated_at
}

roles {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u8 super_admin_slot UK "?"
  char(64) permission_signature UK
  varchar(125) name
  varchar(32) guard_name
  varchar label
  boolean is_system
  boolean is_protected
  boolean is_super_admin
  u64 permission_version
  datetime created_at
  datetime updated_at
}

role_has_permissions {
  u64 permission_id PK,FK
  u64 role_id PK,FK
  u16 duration_days
}

model_has_roles {
  u64 role_id PK,FK
  varchar(64) model_type PK
  u64 model_id PK
  datetime assigned_at
}

model_has_permissions {
  u64 permission_id PK,FK
  varchar(64) model_type PK
  u64 model_id PK
  datetime assigned_at
  datetime expires_at
}

team_invitations {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 initial_role_id FK
  u64 invited_by_id FK
  varchar token_hash UK
  varchar email
  u64 role_permission_version
  datetime expires_at
  datetime accepted_at "?"
  datetime revoked_at "?"
  datetime created_at
  datetime updated_at
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
  datetime created_at
  datetime updated_at
}

carrier_accounts {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 created_by_id FK
  uuid carrier_uuid "REF central.shipping_carriers.uuid"
  varchar label
  varchar adapter
  varchar external_account_id "?"
  varchar api_url "?"
  text encrypted_api_credentials "?"
  varchar encryption_key_version "?"
  boolean is_active
  datetime last_synced_at "?"
  datetime created_at
  datetime updated_at
}

carrier_remittance_batches {
  u64 id PK "AUTO_INCREMENT ; interne"
  uuid uuid UK "UUID v4 ; public"
  u64 carrier_account_id FK
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 validated_by_id FK "?"
  varchar operation_key UK
  varchar external_reference "?"
  decimal reported_account_net_amount "?"
  decimal computed_shop_net_amount
  decimal verified_net_amount "?"
  u8 status
  datetime received_at "?"
  datetime created_at
  datetime updated_at
}

media |o..o{ shop : "FK logo_media_id"
media |o..o{ shop : "FK favicon_media_id"
shop ||..o{ shop_addresses : "FK shop_id"
shop_addresses |o..o{ shop_addresses : "FK shop_address_id"
products |o..o{ content_pages : "FK product_id"
users |o..o{ media : "FK created_by_id"
categories |o..o{ categories : "FK parent_id"
media |o..o{ categories : "FK media_id"
categories |o..o{ products : "FK category_id"
products ||..o{ product_variants : "FK product_id"
products ||..o{ product_options : "FK product_id"
product_options |o..o{ product_options : "FK parent_id"
products ||..o{ variant_option_values : "FK product_id"
product_variants ||..o{ variant_option_values : "FK variant_id"
product_options ||..o{ variant_option_values : "FK option_id"
product_options ||..o{ variant_option_values : "FK value_id"
products ||..o{ product_tags : "FK product_id"
categories ||..o{ product_tags : "FK tag_id"
products ||..o{ product_promotions : "FK product_id"
product_variants |o..o{ product_promotions : "FK variant_id"
content_pages |o..o{ product_promotions : "FK sales_page_id"
products ||..o{ product_reviews : "FK product_id"
visitors |o..o{ product_reviews : "FK visitor_id"
order_items |o..o{ product_reviews : "FK order_item_id"
users |o..o{ product_reviews : "FK moderated_by_id"
visitors ||..o{ visit_sessions : "FK visitor_id"
visit_sessions ||..o{ navigation_events : "FK session_id"
products |o..o{ navigation_events : "FK product_id"
product_variants |o..o{ navigation_events : "FK variant_id"
content_pages |o..o{ navigation_events : "FK sales_page_id"
content_pages |o..o{ navigation_events : "FK content_page_id"
carts |o..o{ navigation_events : "FK cart_id"
visitors ||..o{ carts : "FK visitor_id"
carts ||..o{ cart_items : "FK cart_id"
product_variants ||..o{ cart_items : "FK variant_id"
products ||..o{ cart_items : "FK product_id"
content_pages |o..o{ cart_items : "FK sales_page_id"
visitors |o..o{ orders : "FK visitor_id"
carts |o..o{ orders : "FK cart_id"
visit_sessions |o..o{ orders : "FK original_session_id"
content_pages |o..o{ orders : "FK original_sales_page_id"
order_returns |o..o{ orders : "FK original_return_id"
orders |o..o{ orders : "FK original_order_id"
order_incidents |o..o{ orders : "FK original_incident_id"
order_revisions |o..o{ orders : "FK current_revision_id"
order_revisions |o..o{ orders : "FK confirmed_revision_id"
users |o..o{ orders : "FK confirmation_owner_id"
users |o..o{ orders : "FK operationally_confirmed_by_id"
orders ||..o{ order_revisions : "FK order_id"
users |o..o{ order_revisions : "FK author_id"
free_shipping_rules |o..o{ order_revisions : "FK free_shipping_rule_id"
order_revisions ||..o{ order_items : "FK revision_id"
product_variants ||..o{ order_items : "FK variant_id"
products ||..o{ order_items : "FK product_id"
product_promotions |o..o{ order_items : "FK promotion_id"
content_pages |o..o{ order_items : "FK sales_page_id"
orders ||..o{ order_history : "FK order_id"
order_revisions |o..o{ order_history : "FK previous_revision_id"
order_revisions |o..o{ order_history : "FK next_revision_id"
users |o..o{ order_history : "FK actor_id"
product_variants ||..o{ stock_movements : "FK variant_id"
order_items |o..o{ stock_movements : "FK order_item_id"
return_items |o..o{ stock_movements : "FK return_item_id"
users |o..o{ stock_movements : "FK actor_id"
stock_movements |o..o{ stock_movements : "FK reversal_of_id"
shipments ||..o{ order_returns : "FK shipment_id"
orders ||..o{ order_returns : "FK order_id"
order_revisions ||..o{ order_returns : "FK shipped_revision_id"
users |o..o{ order_returns : "FK received_by_id"
order_returns ||..o{ return_items : "FK return_id"
order_items ||..o{ return_items : "FK order_item_id"
order_revisions ||..o{ return_items : "FK shipped_revision_id"
product_variants ||..o{ return_items : "FK variant_id"
users |o..o{ return_items : "FK inspected_by_id"
users |o..o{ shipping_providers : "FK user_id"
carrier_accounts |o..o{ shipping_providers : "FK carrier_account_id"
shipping_providers |o..o{ shipping_rates : "FK provider_id"
carrier_accounts |o..o{ shipping_rates : "FK carrier_account_id"
users |o..o{ shipping_rates : "FK created_by_id"
products |o..o{ free_shipping_rules : "FK product_id"
orders ||..o| shipments : "FK order_id"
order_revisions ||..o{ shipments : "FK shipped_revision_id"
shipping_providers ||..o{ shipments : "FK provider_id"
media |o..o{ shipments : "FK label_media_id"
users ||..o{ shipments : "FK assigned_by_id"
shipments ||..o{ shipment_events : "FK shipment_id"
users |o..o{ shipment_events : "FK actor_id"
shipping_providers ||..o{ carrier_operations : "FK provider_id"
shipments |o..o{ carrier_operations : "FK shipment_id"
orders |o..o{ carrier_operations : "FK order_id"
order_returns |o..o{ carrier_operations : "FK return_id"
order_revisions |o..o{ carrier_operations : "FK revision_id"
carrier_operations |o..o{ carrier_operations : "FK superseded_by_operation_id"
users |o..o{ carrier_operations : "FK triggered_by_id"
carrier_operations ||..o{ carrier_operation_attempts : "FK operation_id"
shipments ||..o| collections : "FK shipment_id"
shipping_providers ||..o{ remittance_statements : "FK provider_id"
carrier_remittance_batches |o..o{ remittance_statements : "FK carrier_remittance_batch_id"
users |o..o{ remittance_statements : "FK validated_by_id"
media |o..o{ remittance_statements : "FK proof_media_id"
remittance_statements |o..o{ remittance_statements : "FK reversal_of_id"
shipping_providers ||..o{ carrier_settlement_lines : "FK provider_id"
remittance_statements |o..o{ carrier_settlement_lines : "FK remittance_statement_id"
collections |o..o{ carrier_settlement_lines : "FK collection_id"
carrier_fees |o..o{ carrier_settlement_lines : "FK carrier_fee_id"
carrier_receivables |o..o{ carrier_settlement_lines : "FK receivable_id"
shipments |o..o{ carrier_settlement_lines : "FK shipment_id"
orders |o..o{ carrier_settlement_lines : "FK replacement_order_id"
media |o..o{ carrier_settlement_lines : "FK proof_media_id"
carrier_settlement_lines |o..o{ carrier_settlement_lines : "FK reversal_of_id"
carrier_settlement_lines |o..o{ carrier_settlement_lines : "FK correction_of_id"
products |o..o{ expenses : "FK product_id"
orders |o..o{ expenses : "FK order_id"
shipments |o..o{ expenses : "FK shipment_id"
order_returns |o..o{ expenses : "FK return_id"
media |o..o{ expenses : "FK proof_media_id"
users ||..o{ expenses : "FK author_id"
expenses |o..o{ expenses : "FK reversal_of_id"
expenses |o..o{ expenses : "FK correction_of_id"
orders ||..o{ customer_adjustments : "FK order_id"
order_returns |o..o{ customer_adjustments : "FK return_id"
order_incidents ||..o{ customer_adjustments : "FK incident_id"
invoices |o..o{ customer_adjustments : "FK credit_note_id"
users |o..o{ customer_adjustments : "FK validated_by_id"
media |o..o{ customer_adjustments : "FK proof_media_id"
customer_adjustments |o..o{ customer_adjustments : "FK reversal_of_id"
customer_adjustments |o..o{ customer_adjustments : "FK correction_of_id"
orders ||..o{ order_documents : "FK order_id"
order_revisions ||..o{ order_documents : "FK revision_id"
media ||..o{ order_documents : "FK media_id"
users ||..o{ order_documents : "FK generated_by_id"
shipments ||..o{ carrier_fees : "FK shipment_id"
shipping_providers ||..o{ carrier_fees : "FK provider_id"
order_returns |o..o{ carrier_fees : "FK return_id"
carrier_accounts |o..o{ carrier_fees : "FK carrier_account_id"
shipping_rates |o..o{ carrier_fees : "FK source_rate_id"
media |o..o{ carrier_fees : "FK proof_media_id"
carrier_fees |o..o{ carrier_fees : "FK reversal_of_id"
carrier_fees |o..o{ carrier_fees : "FK correction_of_id"
shipping_providers ||..o{ carrier_receivables : "FK provider_id"
carrier_fees ||..o{ carrier_receivables : "FK carrier_fee_id"
carrier_settlement_lines |o..o{ carrier_receivables : "FK original_fee_payment_id"
carrier_receivables |o..o{ carrier_receivables : "FK reversal_of_id"
collections ||..o{ collection_entries : "FK collection_id"
users ||..o{ collection_entries : "FK verified_by_id"
media |o..o{ collection_entries : "FK proof_media_id"
collection_entries |o..o{ collection_entries : "FK reversal_of_id"
collection_entries |o..o{ collection_entries : "FK correction_of_id"
orders ||..o{ invoices : "FK order_id"
order_revisions ||..o{ invoices : "FK revision_id"
invoices |o..o{ invoices : "FK original_invoice_id"
billing_rules |o..o{ invoices : "FK sequence_id"
media |o..o{ invoices : "FK media_id"
users |o..o{ invoices : "FK issued_by_id"
order_incidents |o..o{ invoices : "FK incident_id"
orders ||..o{ order_incidents : "FK order_id"
shipments ||..o{ order_incidents : "FK shipment_id"
order_revisions ||..o{ order_incidents : "FK shipped_revision_id"
order_items ||..o| order_incidents : "FK order_item_id"
order_returns |o..o{ order_incidents : "FK return_id"
users |o..o{ order_incidents : "FK opened_by_id"
users |o..o{ order_incidents : "FK validated_by_id"
order_incidents ||..o{ order_incident_details : "FK incident_id"
users |o..o{ order_incident_details : "FK author_id"
users |o..o{ billing_rules : "FK validated_by_id"
orders ||..o{ sales_terms_acceptances : "FK order_id"
order_revisions ||..o{ sales_terms_acceptances : "FK revision_id"
orders ||..o{ billing_obligations : "FK order_id"
order_revisions ||..o{ billing_obligations : "FK revision_id"
billing_rules ||..o{ billing_obligations : "FK billing_rule_id"
invoices |o..o{ billing_obligations : "FK original_invoice_id"
invoices |o..o{ billing_obligations : "FK invoice_id"
orders ||..o{ commercial_corrections : "FK order_id"
order_revisions ||..o{ commercial_corrections : "FK source_revision_id"
order_incidents |o..o{ commercial_corrections : "FK incident_id"
commercial_corrections |o..o{ commercial_corrections : "FK correction_of_id"
users |o..o{ commercial_corrections : "FK actor_id"
commercial_corrections ||..o{ commercial_correction_lines : "FK correction_id"
order_revisions ||..o{ commercial_correction_lines : "FK source_revision_id"
order_items ||..o{ commercial_correction_lines : "FK order_item_id"
permissions ||--o{ role_has_permissions : "FK permission_id"
roles ||--o{ role_has_permissions : "FK role_id"
roles ||--o{ model_has_roles : "FK role_id"
permissions ||--o{ model_has_permissions : "FK permission_id"
roles ||..o{ team_invitations : "FK initial_role_id"
users ||..o{ team_invitations : "FK invited_by_id"
users ||..o{ contact_verifications : "FK user_id"
users ||..o{ carrier_accounts : "FK created_by_id"
carrier_accounts ||..o{ carrier_remittance_batches : "FK carrier_account_id"
media |o..o{ carrier_remittance_batches : "FK proof_media_id"
carrier_remittance_batches |o..o{ carrier_remittance_batches : "FK reversal_of_id"
users |o..o{ carrier_remittance_batches : "FK validated_by_id"
users ||..o{ model_has_roles : "POLY model_id si shop_user"
users ||..o{ model_has_permissions : "POLY model_id si shop_user"
users |o..o{ activity_log : "POLY causer_id si shop_user"
shop |o..o{ activity_log : "POLY subject_id si modèle autorisé"
shop_addresses |o..o{ activity_log : "POLY subject_id si modèle autorisé"
content_pages |o..o{ activity_log : "POLY subject_id si modèle autorisé"
media |o..o{ activity_log : "POLY subject_id si modèle autorisé"
categories |o..o{ activity_log : "POLY subject_id si modèle autorisé"
products |o..o{ activity_log : "POLY subject_id si modèle autorisé"
product_variants |o..o{ activity_log : "POLY subject_id si modèle autorisé"
product_options |o..o{ activity_log : "POLY subject_id si modèle autorisé"
variant_option_values |o..o{ activity_log : "POLY subject_id si modèle autorisé"
product_tags |o..o{ activity_log : "POLY subject_id si modèle autorisé"
product_promotions |o..o{ activity_log : "POLY subject_id si modèle autorisé"
product_reviews |o..o{ activity_log : "POLY subject_id si modèle autorisé"
visitors |o..o{ activity_log : "POLY subject_id si modèle autorisé"
visit_sessions |o..o{ activity_log : "POLY subject_id si modèle autorisé"
navigation_events |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carts |o..o{ activity_log : "POLY subject_id si modèle autorisé"
cart_items |o..o{ activity_log : "POLY subject_id si modèle autorisé"
orders |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_revisions |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_items |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_history |o..o{ activity_log : "POLY subject_id si modèle autorisé"
stock_movements |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_returns |o..o{ activity_log : "POLY subject_id si modèle autorisé"
return_items |o..o{ activity_log : "POLY subject_id si modèle autorisé"
shipping_providers |o..o{ activity_log : "POLY subject_id si modèle autorisé"
shipping_rates |o..o{ activity_log : "POLY subject_id si modèle autorisé"
free_shipping_rules |o..o{ activity_log : "POLY subject_id si modèle autorisé"
shipments |o..o{ activity_log : "POLY subject_id si modèle autorisé"
shipment_events |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_operations |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_operation_attempts |o..o{ activity_log : "POLY subject_id si modèle autorisé"
collections |o..o{ activity_log : "POLY subject_id si modèle autorisé"
remittance_statements |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_settlement_lines |o..o{ activity_log : "POLY subject_id si modèle autorisé"
expenses |o..o{ activity_log : "POLY subject_id si modèle autorisé"
customer_adjustments |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_documents |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_fees |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_receivables |o..o{ activity_log : "POLY subject_id si modèle autorisé"
collection_entries |o..o{ activity_log : "POLY subject_id si modèle autorisé"
invoices |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_incidents |o..o{ activity_log : "POLY subject_id si modèle autorisé"
order_incident_details |o..o{ activity_log : "POLY subject_id si modèle autorisé"
billing_rules |o..o{ activity_log : "POLY subject_id si modèle autorisé"
sales_terms_acceptances |o..o{ activity_log : "POLY subject_id si modèle autorisé"
billing_obligations |o..o{ activity_log : "POLY subject_id si modèle autorisé"
commercial_corrections |o..o{ activity_log : "POLY subject_id si modèle autorisé"
commercial_correction_lines |o..o{ activity_log : "POLY subject_id si modèle autorisé"
users |o..o{ activity_log : "POLY subject_id si modèle autorisé"
permissions |o..o{ activity_log : "POLY subject_id si modèle autorisé"
roles |o..o{ activity_log : "POLY subject_id si modèle autorisé"
team_invitations |o..o{ activity_log : "POLY subject_id si modèle autorisé"
contact_verifications |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_accounts |o..o{ activity_log : "POLY subject_id si modèle autorisé"
carrier_remittance_batches |o..o{ activity_log : "POLY subject_id si modèle autorisé"
products ||..o{ media : "POLY model_id si parent autorisé"
product_variants ||..o{ media : "POLY model_id si parent autorisé"
shop ||..o{ media : "POLY model_id si parent autorisé"
categories ||..o{ media : "POLY model_id si parent autorisé"
shipments ||..o{ media : "POLY model_id si parent autorisé"
remittance_statements ||..o{ media : "POLY model_id si parent autorisé"
carrier_settlement_lines ||..o{ media : "POLY model_id si parent autorisé"
expenses ||..o{ media : "POLY model_id si parent autorisé"
customer_adjustments ||..o{ media : "POLY model_id si parent autorisé"
order_documents ||..o{ media : "POLY model_id si parent autorisé"
carrier_fees ||..o{ media : "POLY model_id si parent autorisé"
collection_entries ||..o{ media : "POLY model_id si parent autorisé"
invoices ||..o{ media : "POLY model_id si parent autorisé"
carrier_remittance_batches ||..o{ media : "POLY model_id si parent autorisé"
```

## 3. Les liens entre central et boutique

| Champ local | Référence logique | Explication très simple |
|---|---|---|
| `shop.tenant_uuid` | `central.tenants.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `shop_addresses.province_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `shop_addresses.municipality_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `order_revisions.pickup_point_uuid` | `central.pickup_points.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `order_revisions.province_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `order_revisions.municipality_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `shipping_rates.province_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `shipping_rates.municipality_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `free_shipping_rules.province_uuid` | `central.geographic_areas.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `shipments.pickup_point_uuid` | `central.pickup_points.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `users.central_user_uuid` | `central.users.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |
| `carrier_accounts.carrier_uuid` | `central.shipping_carriers.uuid` | Identifiant contrôlé par le serveur dans son contexte, sans FK SQL entre bases. |

Les codes de fonctionnalités restent des codes métier, pas des comptes partagés. Les secrets et tarifs transporteur ne remontent pas dans le catalogue commun.

## 4. Chaque champ expliqué simplement

Une ligne est une fiche ; un champ est une case de cette fiche. Un champ calculé est rempli par la BDD, et un lien permet de retrouver une autre fiche. « Vide » signifie NULL dans les cas prévus. Les valeurs d’enums et contraintes exactes restent dans le schéma principal.

### 1. `shop` — 23 champs

La fiche publique de la boutique : son nom affiché, son logo, ses contacts et sa présentation. Exemple : les informations que les visiteurs voient sur le site de Karim.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `tenant_uuid` | Le UUID de la boutique dans central.tenants.uuid. Cette référence entre BDD reste distincte de l’ID numérique utilisé par Tenancy pour ouvrir sa base. |
| `logo_media_id` | le fichier utilisé comme logo de la boutique. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `favicon_media_id` | la petite image affichée dans l’onglet du navigateur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `singleton` | un petit verrou technique qui garantit qu’il n’existe qu’une seule ligne de ce type dans la base. Exemple : une seule fiche `shop`. |
| `central_profile_version` | la dernière version du profil central que cette boutique a reçue. Cela permet de voir si elle est à jour. |
| `shop_name` | Le nom public de cette boutique, projeté depuis tenants.shop_name au central ; il ne contient pas le nom de famille de son propriétaire. |
| `description` | un texte qui explique l’élément plus en détail. Il peut rester vide si aucune explication supplémentaire n’est nécessaire. |
| `about` | le texte de présentation de la boutique. Exemple : son histoire ou ce qu’elle vend. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `business_type` | le type d’activité de la boutique. Exemple : vêtements, restaurant ou salon. |
| `contact_email` | l’email public que les visiteurs peuvent utiliser pour contacter la boutique. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `contact_phone` | le téléphone public de la boutique. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `contact_whatsapp` | le numéro WhatsApp public de la boutique. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `locale` | la langue préférée pour l’affichage. Exemple : `fr` ou `ar`. |
| `currency` | la monnaie utilisée. Exemple : `DZD` pour le dinar algérien. |
| `timezone` | la zone utilisée pour afficher les dates et heures. Exemple : `Africa/Algiers`. |
| `theme_code` | le modèle visuel choisi pour le site. Exemple : `standard`. |
| `colors` | les couleurs choisies pour le site, enregistrées ensemble. Exemple : couleur principale et couleur des boutons. |
| `shipping_tax_configuration` | les réglages qui expliquent comment les frais de livraison doivent être traités dans les calculs fiscaux. Ils doivent être validés avant la vente réelle. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `cart_lifetime_days` | le nombre de jours pendant lesquels un panier invité peut rester conservé avant d’expirer. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 2. `shop_addresses` — 17 champs

Les adresses publiques et les liens sociaux de cette boutique : une ligne ADDRESS est un lieu, une ligne SOCIAL est un lien. Un lien peut concerner toute la boutique ou une adresse précise.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro interne de cette adresse ou de ce lien social. |
| `uuid` | son identifiant public unique, utilisé dans les routes et formulaires. |
| `shop_id` | le profil local auquel appartient cette ligne. |
| `shop_address_id` | l’adresse à laquelle un lien social est associé ; vide pour un lien général et toujours vide sur une adresse. |
| `province_uuid` | la wilaya officielle de l’adresse ; vide sur un lien social. La référence centrale reste un UUID externe. |
| `municipality_uuid` | la commune officielle de cette adresse, appartenant à cette wilaya ; vide sur un lien social. |
| `record_type` | 1 ADDRESS pour une adresse publique ; 2 SOCIAL pour un lien vers un réseau. Ce rôle ne change jamais après création. |
| `shop_address_type` | la valeur 1 calculée quand un lien vise une adresse ; elle empêche de le rattacher à un autre lien social. |
| `label` | le nom du lieu, ou un petit titre facultatif pour reconnaître un lien. |
| `position` | l’ordre d’affichage parmi les adresses ou parmi les liens de la sélection concernée. |
| `is_primary` | indique l’adresse principale ; un lien social ne peut pas être une adresse principale. |
| `visible` | indique si cette adresse ou ce lien peut être montré au public ; masquer ne supprime pas la ligne. |
| `payload` | les détails publics propres au rôle : rue/carte/horaires pour une adresse, réseau/URL pour un lien. Toutes les clés sont expliquées ci-dessous. |
| `primary_slot` | la valeur technique 1 pour l’adresse principale non archivée ; elle permet de n’en avoir qu’une, même si elle est masquée. |
| `created_at` | la date d’origine de création de cette adresse ou de ce lien. |
| `updated_at` | la date de sa dernière modification autorisée. |
| `deleted_at` | la date de son archivage ; archiver conserve ses données et ses associations historiques. |

#### Détails du JSON public, sans perte de champs

Le payload est un objet obligatoire. Son schéma dépend exclusivement du record_type fixé par le modèle, avec schema_version=1 et clés autorisées ; des valeurs facultatives utilisent NULL plutôt qu’une information inventée. Les schémas ADDRESS et SOCIAL sont distincts, pas un objet mélangeant leurs attributs. Les contrôles SQL gardent identités, parents et géographie ; le validateur serveur contrôle les détails JSON avant chaque create/update/import, y compris le DTO public. Les futures versions demandent une transformation explicite, jamais l’effacement silencieux d’une ancienne clé.

**Schéma ADDRESS :**

| Clé du payload | Type et obligation | Explication très simple |
|---|---|---|
| schema_version | entier 1 requis | La version du format des détails. |
| address | texte requis, non vide | La rue, le quartier et les indications publiques de cette adresse. |
| postal_code | chaîne facultative | Le code postal, différent du code de commune. |
| latitude | décimal canonique facultatif, précision DECIMAL(10,7) | La position nord/sud sur une carte, de -90 à 90. |
| longitude | décimal canonique facultatif, précision DECIMAL(10,7) | La position est/ouest sur une carte, de -180 à 180. |
| map_url | URL publique facultative | Le lien de la carte ; il peut exister même sans coordonnées. |
| phone | chaîne facultative | Le téléphone public de ce lieu ; garder le 0 et l’indicatif. |
| opening_hours | objet facultatif organisé par jour | Les horaires publics ; aucune réservation, caisse ou gestion de stock n’est ajoutée. |
| opening_hours.<jour> | liste d’intervalles ; jours mon/tue/wed/thu/fri/sat/sun | Les plages d’ouverture de ce jour ; une liste vide signifie fermé. |
| opening_hours.<jour>[].opens_at | chaîne HH:mm requise par intervalle | L’heure à laquelle ce lieu ouvre. |
| opening_hours.<jour>[].closes_at | chaîne HH:mm requise par intervalle | L’heure à laquelle il ferme. |
| opening_hours.<jour>[].closes_next_day | booléen requis par intervalle | Indique si cette fermeture arrive le lendemain ; permet les horaires traversant minuit. |

**Schéma SOCIAL :**

| Clé du payload | Type et obligation | Explication très simple |
|---|---|---|
| schema_version | entier 1 requis | La version du format de ce lien. |
| network | chaîne requise, extensible | Le réseau concerné, par exemple Facebook, Instagram ou TikTok. |
| url | URL publique requise | La page exacte de cette boutique ou de ce lieu sur ce réseau. |

**Validation des détails :** latitude et longitude sont toutes deux NULL ou toutes deux fournies ; normaliser leurs valeurs décimales sans arrondi au-delà de la précision précédente. map_url reste indépendant de cette paire. Les URL map_url/url acceptent seulement http/https, au maximum les 255 caractères du VARCHAR d’origine, sans identifiants de connexion ni clé API/token secret ; les paramètres publics de carte nécessaires restent possibles. Le serveur ne consulte pas ces URL pour enregistrer le profil. network, postal_code et phone conservent la limite VARCHAR(255) ; address conserve la capacité TEXT initiale, avec limite d’octets contrôlée. Les jours/intervalles d’horaires et leurs traversées de minuit sont validés selon le fuseau shop.timezone, sans code exécutable, HTML arbitraire ni secret. Les limites du formulaire/payload sont bornées et ne suppriment aucune entrée historique pendant une migration. Aucun service Maps, compte social, publication automatisée ou calendrier marketing n’est créé.

### 3. `content_pages` — 18 champs

Toutes les pages éditées de la vitrine : une page d’information comme « À propos », ou une page de vente consacrée à un produit. Le champ page_kind distingue les deux fonctions.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro interne de cette page. |
| `uuid` | son identifiant public unique ; il est conservé lors de la fusion. |
| `product_id` | le produit présenté par une page de vente. Une page d’information n’a pas de produit. |
| `page_kind` | 1 CONTENT pour une page d’information ; 2 SALES pour une page de vente. Ce rôle ne change pas après création. |
| `slug` | la partie lisible de son adresse web. Elle est unique parmi les pages du même rôle. |
| `type` | le sous-type d’une page d’information, par exemple about, contact, faq ou returns. Il reste extensible et est vide pour une page de vente. |
| `title` | le titre affiché sur la page. |
| `content` | ses textes et blocs autorisés par le template, dans un JSON structuré ; aucun code arbitraire ni prix indépendant. |
| `meta_title` | son titre pour les moteurs de recherche et le partage, s’il est renseigné. |
| `meta_description` | sa courte description pour les moteurs de recherche, si nécessaire. |
| `canonical_url` | l’adresse principale d’une page de vente pour les moteurs de recherche, lorsqu’elle est renseignée. |
| `indexable` | indique si les moteurs de recherche peuvent indexer la page. |
| `is_published` | indique si la page peut être montrée au public. |
| `published_at` | la date de sa publication ; vide avant publication. |
| `version` | le numéro de version de ses blocs et paramètres éditoriaux ; une modification autorisée l’incrémente. |
| `created_at` | la date de création de cette page. |
| `updated_at` | la date de sa dernière modification autorisée. |
| `deleted_at` | sa date d’archivage ; archiver conserve la page et ses liens historiques. |

### 4. `media` — 23 champs

Les informations permettant de retrouver les fichiers de la boutique : images, vidéos, logos ou documents. Exemple : l’emplacement et le type de la photo d’un produit ; le fichier lui-même est stocké séparément.

| Champ | Explication très simple |
|---|---|
| `id` | clé primaire numérique interne, auto-incrémentée ; jamais envoyée au client. |
| `uuid` | identifiant public unique et indexé, utilisé dans les routes, formulaires, exports et ressources JSON. |
| `model_id` | Le numéro de ce parent dans sa table locale. Un fichier n’a qu’un parent. |
| `created_by_id` | Le compte local qui a ajouté ce fichier ; vide pour un ajout système autorisé. |
| `model_type` | Le type de parent local du fichier, par exemple produit ou facture ; il utilise un alias autorisé. |
| `collection_name` | usage (gallery, logo, invoice, proof...) et ordre d’affichage. |
| `disk` | disque, clé unique et visibilité publique/privée, avec isolation physique par tenant. |
| `storage_key` | disque, clé unique et visibilité publique/privée, avec isolation physique par tenant. |
| `mime_type` | type contrôlé, nom original et taille du fichier. |
| `original_name` | type contrôlé, nom original et taille du fichier. |
| `size_bytes` | type contrôlé, nom original et taille du fichier. |
| `width` | métadonnées utiles et accessibilité, facultatives selon le fichier. |
| `height` | métadonnées utiles et accessibilité, facultatives selon le fichier. |
| `duration_seconds` | métadonnées utiles et accessibilité, facultatives selon le fichier. |
| `alt_text` | métadonnées utiles et accessibilité, facultatives selon le fichier. |
| `visibility` | disque, clé unique et visibilité publique/privée, avec isolation physique par tenant. |
| `position` | usage (gallery, logo, invoice, proof...) et ordre d’affichage. |
| `is_primary` | usage (gallery, logo, invoice, proof...) et ordre d’affichage. |
| `primary_slot` | Une valeur calculée qui empêche deux images principales actives pour le même élément et usage. |
| `file_hash` | auteur local facultatif et empreinte de contrôle. |
| `created_at` | cycle du média ; un retrait logique n’efface pas une preuve requise. |
| `updated_at` | cycle du média ; un retrait logique n’efface pas une preuve requise. |
| `deleted_at` | cycle du média ; un retrait logique n’efface pas une preuve requise. |

### 5. `categories` — 16 champs

Les catégories et les étiquettes de produits : type 1 pour un rayon comme « Vêtements → T-shirts », type 2 pour un mot comme « Été ». Les produits restent dans leur propre table.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `parent_id` | La catégorie au-dessus de ce rayon ; vide pour une catégorie principale et toujours vide pour une étiquette. |
| `media_id` | l’identifiant du fichier/image/vidéo. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `record_type` | Le rôle de cette fiche : catégorie/rayon (1) ou étiquette/mot-clé (2). |
| `parent_record_type` | Valeur calculée qui oblige le parent à être une catégorie. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `slug` | Le nom court utilisé dans ses liens. Il est unique parmi les fiches du même type ; une catégorie et une étiquette peuvent partager ce texte. |
| `description` | un texte qui explique l’élément plus en détail. Il peut rester vide si aucune explication supplémentaire n’est nécessaire. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `meta_title` | le titre prévu pour les moteurs de recherche et le partage web. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `meta_description` | la petite description prévue pour les moteurs de recherche. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 6. `products` — 26 champs

La présentation commune d’un produit : son nom, sa description et les informations partagées par ses versions. Exemple : le modèle « T-shirt coton », proposé ensuite en plusieurs tailles et couleurs.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `category_id` | l’identifiant de la catégorie. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `category_record_type` | Valeur calculée qui empêche de prendre une étiquette pour le rayon principal du produit. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `slug` | la partie simple de l’adresse web. Exemple : `a-propos` dans `/a-propos`. |
| `short_description` | une petite description affichée rapidement, plus courte que la description complète. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `description` | un texte qui explique l’élément plus en détail. Il peut rester vide si aucune explication supplémentaire n’est nécessaire. |
| `benefits` | plusieurs petits réglages liés à **avantages**, regroupés ensemble de manière structurée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `faq` | plusieurs petits réglages liés à **faq**, regroupés ensemble de manière structurée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `brand` | la marque du produit lorsqu’il en a une. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `type` | code de `ProductTypeEnum` : `1 STANDARD` pour un produit physique standard, `2 CUSTOMIZED` pour un produit physique personnalisable. |
| `allows_customization` | un **oui/non** pour indiquer si **personnalisation autorisee** est vrai ou autorisé. `true` = oui ; `false` = non. |
| `customization_instructions` | les instructions données au client pour personnaliser le produit. Exemple : « Écrivez le prénom à imprimer ». Ce champ peut rester vide pour un produit normal. |
| `sale_unit` | ce que représente une unité vendue. Exemple : `pièce`, `boîte` ou `bouquet`. |
| `content_quantity` | le nombre d’unités correspondant à **contenu**. Exemple : `2` signifie deux unités. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `content_unit` | l’unité utilisée pour décrire le contenu. Exemple : une bouteille de `500 ml`. Ce champ peut rester vide si ce n’est pas utile. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `published_at` | la date où l’élément a été publié. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_featured` | indique si l’élément doit être davantage mis en évidence sur le site. |
| `meta_title` | le titre prévu pour les moteurs de recherche et le partage web. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `meta_description` | la petite description prévue pour les moteurs de recherche. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `indexable` | indique si les moteurs de recherche sont autorisés à indexer cette page. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 7. `product_variants` — 25 champs

Les versions précises que l’on peut acheter, avec leur prix et leur stock. Exemple : « T-shirt rouge, taille M ». Un produit sans choix possède aussi une variante standard.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `label` | un nom court utilisé pour reconnaître facilement l’élément à l’écran. |
| `sku` | la référence utilisée pour reconnaître **SKU** sans se baser seulement sur son nom. |
| `barcode` | le code-barres de la variante lorsqu’il existe. |
| `combination_signature` | une empreinte SHA-256 de 64 caractères calculée à partir des identifiants stables des axes et valeurs, pour empêcher deux variantes du même produit représentant exactement la même combinaison. Une correction de libellé ne change pas cette empreinte. |
| `used_at` | la date et l’heure liées à **utilisee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sale_price` | le prix de vente actuel de cette variante. |
| `unit_cost` | le coût d’achat ou de revient d’une unité pour le commerçant. |
| `previous_price` | le prix affiché comme ancien prix avant la promotion. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `tax_configuration` | les informations nécessaires pour appliquer la règle fiscale prévue à ce moment-là, sans changer l’historique plus tard. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `physical_stock` | le nombre d’unités réellement présentes physiquement. |
| `reserved_stock` | le nombre d’unités gardées de côté pour des commandes déjà confirmées. |
| `quarantine_stock` | le nombre d’unités mises de côté parce qu’elles doivent être vérifiées et ne peuvent pas être vendues tout de suite. |
| `low_stock_threshold` | le niveau à partir duquel le système doit prévenir que le stock devient faible. |
| `weight_kg` | le poids en kilogrammes. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `length_cm` | la longueur en centimètres. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `width_cm` | la largeur en centimètres. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `height_cm` | la hauteur en centimètres. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 8. `product_options` — 14 champs

Les choix d’un produit, rangés dans une seule table : une ligne AXIS décrit « Taille » ; ses lignes VALUE proposent « M » et « L ». Une autre ligne AXIS peut décrire « Couleur », avec ses propres valeurs.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro interne de cet axe ou de ce choix. |
| `uuid` | son identifiant public unique, utilisé dans les routes et formulaires. |
| `product_id` | le produit qui propose ce choix ; l’axe et toutes ses valeurs ont le même produit. |
| `parent_id` | pour une valeur comme M, l’axe auquel elle appartient, par exemple Taille. Un axe n’a pas de parent. |
| `record_type` | 1 AXIS pour la question « quelle taille ? » ; 2 VALUE pour un choix comme M. Ce rôle ne change pas après création. |
| `parent_record_type` | une valeur technique calculée, égale à 1 lorsqu’un parent est indiqué ; elle empêche de placer une valeur sous une autre valeur. |
| `name` | le nom affiché de l’axe, comme Taille, ou le libellé affiché de la valeur, comme M. |
| `identity_code` | pour une valeur, son code stable dans l’axe ; vide pour un axe. Le code distingue son identité de son libellé. |
| `display_type` | pour un axe, la façon d’afficher ses choix : liste, boutons ou couleurs. Vide pour une valeur. |
| `color_hex` | pour un choix de couleur, son code web éventuel, par exemple #FF0000 ; vide pour les axes et les autres valeurs. |
| `position` | l’ordre des axes d’un produit ou des valeurs d’un axe. |
| `created_at` | la date de création de cette ligne. |
| `updated_at` | la date de sa dernière correction autorisée. |
| `deleted_at` | sa date d’archivage ; archiver conserve ses liens historiques. |

### 9. `variant_option_values` — 10 champs

Les choix qui composent une variante vendable. Exemple : le t-shirt précis a la taille M et la couleur rouge. La variante garde son prix, son SKU et son stock dans product_variants.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro interne de ce choix pour une variante. |
| `uuid` | son identifiant public unique. |
| `product_id` | le produit commun à la variante, à l’axe et à la valeur. |
| `variant_id` | la variante vendable composée de ces choix. |
| `option_id` | l’axe concerné, par exemple Taille ; il pointe exclusivement une ligne AXIS. |
| `value_id` | le choix retenu, par exemple M ; il pointe exclusivement une ligne VALUE de cet axe. |
| `option_record_type` | la constante technique 1 calculée par la BDD pour imposer un axe. |
| `value_record_type` | la constante technique 2 calculée par la BDD pour imposer une valeur. |
| `created_at` | la date de création de cette composition. |
| `updated_at` | la date d’une correction autorisée avant le premier usage ; ensuite la composition est figée. |

### 10. `product_tags` — 7 champs

Les liens entre un produit et ses étiquettes. Exemple : ce t-shirt porte les mots « Été » et « Nouveauté », conservés comme type 2 dans categories.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `tag_id` | L’étiquette liée à ce produit, conservée comme une fiche de type 2 dans categories. Une catégorie de type 1 est refusée. |
| `tag_record_type` | La valeur calculée qui empêche de relier une catégorie comme étiquette. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 11. `product_promotions` — 16 champs

Les réductions appliquées automatiquement aux produits, sans code à saisir. Exemple : une réduction sur un produit pendant une période choisie.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sales_page_id` | l’identifiant de la page de vente. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `discount_type` | code de `DiscountTypeEnum` : `1 PERCENTAGE`, `2 UNIT_AMOUNT`, `3 FIXED_UNIT_PRICE`. |
| `value` | la valeur enregistrée. Exemple : `Rouge`, `XL` ou une autre valeur selon la table. |
| `minimum_quantity` | le nombre d’unités correspondant à **minimum**. Exemple : `2` signifie deux unités. |
| `started_at` | la date et l’heure où la période ou l’action commence. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `ended_at` | la date et l’heure où elle s’est terminée. Peut rester vide tant que ce n’est pas terminé. |
| `priority` | un nombre utilisé pour décider quel élément passe avant un autre. Exemple : priorité 1 avant priorité 2. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 12. `product_reviews` — 15 champs

Les notes et commentaires laissés sur les produits, même sans compte acheteur. Exemple : un client écrit « Très bon produit » ; la boutique décide ensuite de publier ou de masquer cet avis.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `visitor_id` | l’identifiant du visiteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `order_item_id` | l’identifiant de la ligne de produit de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `moderated_by_id` | l’identifiant de la personne qui a modéré. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `display_name` | le nom réellement montré aux visiteurs. |
| `note` | une information libre ajoutée pour aider à comprendre la ligne. Selon la table, cela peut être une note interne ou une note chiffrée. |
| `comment` | le texte écrit par le client ou l’utilisateur. |
| `moderation_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `moderated_at` | la date où l’avis a été vérifié. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `published_at` | la date où l’élément a été publié. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 13. `visitors` — 8 champs

Un identifiant pour reconnaître un navigateur dans cette boutique, sans créer de compte acheteur. Exemple : reconnaître le même navigateur lors d’un retour sur le site, sans garantir qu’il s’agit de la même personne.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `token_hash` | L’empreinte du secret du navigateur ; elle retrouve son panier sans créer un compte acheteur. |
| `first_visited_at` | la date de la première visite connue de ce visiteur. |
| `last_visited_at` | la date de sa dernière visite connue. |
| `expires_at` | la date où l’élément n’est plus valable. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 14. `visit_sessions` — 14 champs

Les différentes visites d’un navigateur sur la boutique. Exemple : une visite le matin puis une autre le soir peuvent former deux sessions pour le même visiteur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `visitor_id` | l’identifiant du visiteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `started_at` | la date et l’heure où la période ou l’action commence. |
| `last_activity_at` | la dernière activité connue de cette visite ; elle aide à déterminer quand une session devient inactive. |
| `ended_at` | la date et l’heure où elle s’est terminée. Peut rester vide tant que ce n’est pas terminé. |
| `entry_path` | la première page visitée dans cette session. Exemple : `/produit/chaussure-noire`. |
| `source` | l’origine connue de la visite, par exemple une campagne, un moteur de recherche ou un réseau social. C’est une étiquette de mesure facultative ; elle ne connecte pas la boutique à ce service. |
| `medium` | une information sur le support utilisé pour arriver sur le site, par exemple une source marketing ou un canal suivi. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `campaign` | le nom ou code d’une campagne marketing utilisé pour savoir d’où vient la visite. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `referrer_host` | le site ou domaine qui a envoyé le visiteur vers la boutique. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `device_type` | le type d’appareil utilisé. Exemple : téléphone, ordinateur ou tablette. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 15. `navigation_events` — 16 champs

Les actions minimales servant aux statistiques globales de la vitrine. Exemple : compter les vues de produits, ajouts au panier et débuts de commande, sans afficher le parcours individuel d’un navigateur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `session_id` | la session de navigation concernée. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sales_page_id` | l’identifiant de la page de vente. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `content_page_id` | l’identifiant de la page de contenu. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `cart_id` | l’identifiant du panier. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sales_page_kind` | la valeur technique 2 calculée quand une page de vente est indiquée ; elle empêche de pointer une page d’information. |
| `content_page_kind` | la valeur technique 1 calculée quand une page d’information est indiquée ; elle empêche de pointer une page de vente. |
| `type` | code d’événement de navigation extensible, par exemple `product_view`, `add_to_cart` ou `checkout_started`. Il reste textuel car de nouveaux événements analytiques peuvent être ajoutés sans migration. |
| `path` | Le chemin de la page visitée, sans paramètres contenant des données sensibles. |
| `quantity` | le nombre d’éléments concernés. Exemple : `2` signifie deux unités du produit. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `occurred_at` | la date et l’heure où l’événement s’est produit. |
| `received_at` | la date à laquelle le serveur a reçu cet événement ; elle peut être différente de la date où le navigateur l’a produit. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 16. `carts` — 9 champs

Les paniers conservés par le site pour les acheteurs invités. Exemple : un visiteur ajoute deux produits avant de renseigner ses coordonnées ; cela ne réserve pas encore le stock.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `visitor_id` | l’identifiant du visiteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `last_activity_at` | la date et l’heure liées à **derniere activite**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `expires_at` | la date où l’élément n’est plus valable. |
| `converted_at` | la date où le panier a été transformé en commande. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 17. `cart_items` — 11 champs

Le contenu détaillé de chaque panier. Exemple : deux t-shirts rouges taille M, avec une éventuelle personnalisation, forment une ligne du panier.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `cart_id` | l’identifiant du panier. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `sales_page_id` | l’identifiant de la page de vente. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `quantity` | le nombre d’éléments concernés. Exemple : `2` signifie deux unités du produit. |
| `customization_text` | Le texte libre demandé pour cet article ; aucun supplément automatique n’est calculé. |
| `customization_signature` | L’empreinte du texte normalisé, pour garder deux lignes distinctes si leurs demandes sont différentes. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 18. `orders` — 34 champs

La fiche principale de chaque commande, avec son identité et son état commercial. Exemple : la commande de Karim reste la même commande même si son contenu est modifié avant expédition.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `visitor_id` | l’identifiant du visiteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `cart_id` | l’identifiant du panier. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_session_id` | l’identifiant de la session d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_sales_page_id` | l’identifiant de la page de vente d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_return_id` | Le retour du colis précédent ; obligatoire pour un renvoi impayé. Il permet de retrouver les articles revenus et les vrais frais. |
| `original_order_id` | l’identifiant de la commande d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_incident_id` | l’identifiant de l’incident d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `current_revision_id` | l’identifiant de la version actuelle de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `confirmed_revision_id` | la version précise que le commerçant a validée après son appel. Si une nouvelle proposition est préparée, cette ancienne version validée reste connue jusqu’au prochain clic « Valider ». |
| `confirmation_owner_id` | l’identifiant de la personne responsable de la confirmation. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `operationally_confirmed_by_id` | l’identifiant de la personne qui a validé le contrôle opérationnel. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_sales_page_kind` | la valeur 2 calculée quand une page de vente d’origine est indiquée ; une page d’information ne peut pas devenir une origine commerciale. |
| `unpaid_resend_slot` | Une valeur calculée qui interdit deux commandes de renvoi impayé pour le même retour. |
| `number` | le numéro lisible de l’élément. Exemple : numéro d’échéance ou de facture selon la table. |
| `data_policy_version` | la version de la politique d’information sur les données personnelles montrée au client pendant ce checkout. |
| `data_notice_acknowledged_at` | la date où le client a continué le checkout après que l’information sur l’utilisation de ses données lui a été présentée. |
| `notice_text_hash` | une empreinte du texte montré au client, pour pouvoir prouver quelle version a été présentée sans dupliquer inutilement le texte. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_incident_quantity` | le nombre d’unités correspondant à **incident origine**. Exemple : `2` signifie deux unités. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `replacement_reason` | explique la raison de **remplacement**. Cela permet de comprendre plus tard pourquoi la décision a été prise. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `order_type` | Le genre de dossier : vente (1), remplacement gratuit (2) ou renvoi après retour sans premier paiement (4). Le code 3 est retiré. |
| `channel` | code de `OrderChannelEnum` qui indique l’origine de la commande : `STOREFRONT` ou `MANUAL`. |
| `commercial_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `validated_at` | la date du dernier clic « Valider » réussi pour `confirmed_revision_id`. La personne qui a cliqué, l’événement et la clé de validation sont conservés dans `activity_log`. |
| `operationally_confirmed_at` | la date et l’heure liées à **confirme operationnellement**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `submission_key` | une clé qui reconnaît une soumission précise. Elle évite qu’un double clic ou un nouvel envoi réseau crée deux fois la même chose. |
| `submission_hash` | une empreinte du contenu envoyé. Exemple : si la même clé revient avec un autre panier, le système voit que le contenu n’est pas identique. |
| `lock_version` | le numéro de version de verrou. Exemple : version 1, puis version 2 après une évolution ; l’ancienne version peut rester conservée pour comprendre l’historique. |
| `retention_hold` | un **oui/non** pour indiquer si **gel conservation** est vrai ou autorisé. `true` = oui ; `false` = non. |
| `retention_hold_reason` | explique la raison de **gel conservation**. Cela permet de comprendre plus tard pourquoi la décision a été prise. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `hold_review_at` | la date et l’heure liées à **revue gel**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 19. `order_revisions` — 39 champs

Les copies successives du contenu d’une commande à chaque modification. Exemple : la première version contient une taille M ; une nouvelle version contient une taille L, sans effacer l’ancienne.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `author_id` | l’identifiant de la personne qui a créé l’élément. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `pickup_point_uuid` | L’identifiant public du bureau dans le catalogue central. Vide à domicile ; requis en stop desk, avec une copie de son adresse/code choisis. |
| `free_shipping_rule_id` | l’identifiant de la règle de livraison gratuite. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `municipality_uuid` | l’identifiant de la commune. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `revision_number` | le numéro de version de la commande. Exemple : 1 pour la première version, 2 après une modification avant expédition. |
| `currency` | la monnaie utilisée. Exemple : `DZD` pour le dinar algérien. |
| `country_code` | le code court du pays. Exemple : `DZ` pour l’Algérie. |
| `legal_seller_snapshot` | une copie figée des informations légales du vendeur au moment de la facture. |
| `shipping_tax_snapshot` | une copie figée de la manière dont les frais de livraison ont été traités fiscalement pour ce document. |
| `reason` | explique pourquoi l’action ou la décision a été faite. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `recipient_last_name` | le nom de **destinataire** affiché ou conservé pour cette ligne. |
| `recipient_first_name` | le prénom de la personne qui recevra le colis. Il peut rester vide si seul le nom nécessaire est renseigné. |
| `phone` | le numéro de téléphone. Il est gardé comme texte pour ne pas perdre le `0`, le `+213` ou d’autres signes utiles. |
| `secondary_phone` | le numéro de téléphone utilisé pour **secondaire**. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `email` | L’e-mail de contact de l’acheteur gardé dans cette version, s’il est renseigné ; aucun message ne lui est envoyé. |
| `address` | l’adresse écrite. Exemple : rue, cité ou quartier. |
| `province_name` | le nom de la wilaya copié dans la version de commande pour garder l’historique tel qu’il était au moment de la vente. |
| `municipality_name` | le nom de la commune copié dans la version de commande pour garder l’historique. |
| `postal_code` | le code postal lorsqu’il est connu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `delivery_mode` | la façon de livrer choisie. Exemple : domicile ou point relais. |
| `pickup_point_snapshot` | La copie figée du bureau choisi : identifiant, adresse, code et version utilisés ; les changements du catalogue central ne la modifient pas. |
| `catalog_subtotal` | le total calculé avec les prix normaux du catalogue avant les changements manuels appliqués à la commande. |
| `applied_subtotal` | le total réellement utilisé après les changements de prix ou remises prévus. |
| `customer_shipping_fee` | Le prix de base de livraison annoncé pour ce nouveau colis ; le commerçant peut le modifier dans une nouvelle version. |
| `shipping_discount` | la réduction appliquée aux frais de livraison. |
| `shipping_charge_bearer` | indique qui prend en charge les frais de livraison selon la règle choisie. |
| `merchant_shipping_amount` | la somme d’argent correspondant à **livraison commercant**. Exemple : `1500` représente 1 500 DA au lancement. |
| `order_total` | Le prix de tous les produits de cette version, plus la livraison après remise et l’ajout manuel éventuel des frais du premier retour. |
| `return_cost_recovery_amount` | Le montant ajouté manuellement à cette livraison pour récupérer tout ou partie du retour précédent. Il reste à 0 si le commerçant ne décide pas d’en ajouter. |
| `return_cost_recovery_reason` | L’explication de cet ajout ; obligatoire si le montant est positif. |
| `amount_to_collect` | Le total entier à payer pour ce colis. Un premier colis refusé sans paiement ne donne aucun crédit à déduire. |
| `customer_note` | la note donnée par le client. Exemple : 4 sur 5. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sales_terms_version` | la version des conditions de vente applicables à cette commande. |
| `sales_terms_snapshot` | une copie figée du texte ou des informations importantes des conditions acceptées. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 20. `order_items` — 28 champs

Les produits et quantités d’une version précise de commande, avec les prix et coûts conservés à ce moment-là. Exemple : deux t-shirts à 2 000 DA chacun, même si le prix du catalogue change ensuite.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `promotion_id` | l’identifiant de la promotion. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sales_page_id` | l’identifiant de la page de vente. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `product_name` | le nom de **produit** affiché ou conservé pour cette ligne. |
| `variant_name` | le nom de **variante** affiché ou conservé pour cette ligne. |
| `sku` | la référence utilisée pour reconnaître **SKU** sans se baser seulement sur son nom. |
| `options_snapshot` | une **copie figée** de options au moment important de l’opération. Si l’information d’origine change plus tard, cette ancienne ligne garde la valeur utilisée à ce moment-là. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `customization_text` | Le texte de personnalisation gardé dans cette version de la commande. |
| `quantity` | le nombre d’éléments concernés. Exemple : `2` signifie deux unités du produit. |
| `catalog_unit_price` | le prix du produit tel qu’il était dans le catalogue au moment de l’ajout. |
| `applied_unit_price` | le prix réellement utilisé pour cette ligne de commande. Il peut être différent du prix actuel du catalogue. |
| `is_price_overridden` | indique si le commerçant a changé manuellement le prix de cette ligne. |
| `price_change_reason` | explique pourquoi le prix a été changé manuellement. Exemple : remise faite à un ami. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `price_origin` | indique d’où vient le prix utilisé. Exemple : catalogue, promotion ou modification manuelle. |
| `promotion_snapshot` | une **copie figée** de promotion au moment important de l’opération. Si l’information d’origine change plus tard, cette ancienne ligne garde la valeur utilisée à ce moment-là. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `unit_cost_snapshot` | une copie du coût unitaire au moment de la vente, afin que la marge historique ne change pas si le coût catalogue change plus tard. |
| `line_total` | le total de cette ligne après quantité et règles de prix prévues. |
| `tax_snapshot` | une copie figée des règles ou informations fiscales utilisées pour calculer ce document. |
| `reservation_status` | l’état de la quantité mise de côté : vide avant validation, 1 réservée, 2 libérée ou 3 expédiée/consommée. |
| `reserved_at` | la date du premier clic Valider qui a réservé la quantité de cette ligne ; vide avant réservation. |
| `reservation_released_at` | la date de libération technique de cette quantité, seulement si l’état vaut 2 RELEASED. |
| `reservation_created_at` | la date où la projection de réservation a été créée ; elle conserve aussi la date exacte d’une ancienne réservation lors d’une migration. |
| `reservation_updated_at` | la date de la dernière transition de réservation, distincte de la création immuable de la ligne commerciale. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 21. `order_history` — 16 champs

Le carnet des appels, rappels, propositions et changements concernant une commande. Exemple : noter un appel sans réponse ou un changement de taille ; le clic « Valider » est audité séparément dans `activity_log`.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `previous_revision_id` | l’identifiant de l’ancienne version de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `next_revision_id` | l’identifiant de la nouvelle version de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `actor_id` | l’identifiant de la personne qui a fait l’action. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `action` | le nom de l’action réalisée. Exemple : `abonnement.modifier`. |
| `contact_outcome` | le résultat de l’appel ou du contact avec le client. Exemple : confirmé, injoignable ou refusé selon les valeurs prévues. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `next_callback_at` | la date et l’heure liées à **prochain rappel**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `previous_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `new_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `changes` | un résumé structuré de ce qui a changé, sans recopier des secrets ou toutes les données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `note` | une information libre ajoutée pour aider à comprendre la ligne. Selon la table, cela peut être une note interne ou une note chiffrée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correlation_id` | un numéro commun utilisé pour relier plusieurs traces qui appartiennent à la même grande opération. Exemple : une création de boutique qui produit plusieurs actions techniques. |
| `origin` | indique d’où vient l’action. Exemple : utilisateur, serveur, tâche automatique ou transporteur. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 22. `stock_movements` — 28 champs

Le carnet de tous les changements de stock. Exemple : recevoir dix articles, en réserver deux, les expédier ou constater une perte, en gardant l’explication de chaque changement.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `order_item_id` | l’identifiant de la ligne de produit de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `return_item_id` | l’identifiant de la ligne du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `actor_id` | l’identifiant de la personne qui a fait l’action. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `variant_sequence` | le numéro d’ordre des mouvements de stock pour cette variante. Il aide à remettre les mouvements dans le bon ordre. |
| `type` | code de `StockMovementTypeEnum` décrivant la nature exacte du mouvement : réception, réservation, libération, expédition, retour, ajustement, contrepassation, etc. |
| `physical_delta` | le changement du stock physique. Exemple : `-2` signifie que deux unités ont quitté le stock physique. |
| `reserved_delta` | le changement du stock réservé. Exemple : `+1` réserve une unité ; `-1` la libère. |
| `quarantine_delta` | le changement du stock en quarantaine. |
| `return_received_delta` | le nombre d’unités ajoutées au journal parce qu’elles ont été reçues lors d’un retour. |
| `return_restocked_delta` | le nombre d’unités revenues dans le stock vendable après contrôle du retour. |
| `return_lost_delta` | le nombre d’unités reconnues perdues pendant le traitement d’un retour. |
| `return_missing_delta` | le nombre d’unités attendues dans le retour mais non reçues. |
| `physical_before` | le stock physique juste avant le mouvement. |
| `physical_after` | le stock physique juste après le mouvement. |
| `reserved_before` | le stock réservé juste avant le mouvement. |
| `reserved_after` | le stock réservé juste après le mouvement. |
| `quarantine_before` | le stock en quarantaine juste avant le mouvement. |
| `quarantine_after` | le stock en quarantaine juste après le mouvement. |
| `unit_cost_snapshot` | une copie du coût unitaire au moment de la vente, afin que la marge historique ne change pas si le coût catalogue change plus tard. |
| `loss_amount` | le montant estimé de la perte liée à l’incident. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `correlation_id` | un numéro commun utilisé pour relier plusieurs traces qui appartiennent à la même grande opération. Exemple : une création de boutique qui produit plusieurs actions techniques. |
| `note` | une information libre ajoutée pour aider à comprendre la ligne. Selon la table, cela peut être une note interne ou une note chiffrée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 23. `order_returns` — 14 champs

Les dossiers des colis qui reviennent à la boutique. Exemple : un client refuse son colis. Au lancement, le retour porte sur tout le colis ; la structure permet une évolution future.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipped_revision_id` | l’identifiant de la version réellement expédiée. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `received_by_id` | l’identifiant de la personne qui a reçu. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reason` | la raison principale de l’action. Exemple : retour parce que le client a refusé le colis. |
| `detail` | quelques détails utiles sur l’événement, sans y mettre de secrets. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `requested_at` | la date et l’heure liées à **demande**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `received_at` | la date et l’heure liées à **recu**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `closed_at` | La date à laquelle le dossier de retour est terminé ; elle ne clôture pas la commande commerciale. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 24. `return_items` — 19 champs

Le détail de ce qui est attendu et constaté dans un retour. Exemple : sur trois articles attendus, deux sont reçus et un manque ; les articles reçus peuvent être revendables ou abîmés.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `order_item_id` | l’identifiant de la ligne de produit de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipped_revision_id` | l’identifiant de la version réellement expédiée. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `inspected_by_id` | l’identifiant de la personne qui a inspecté. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `expected_quantity` | le nombre d’unités que l’on s’attend à recevoir ou traiter. |
| `received_quantity` | le nombre d’unités réellement reçues. |
| `restocked_quantity` | le nombre d’unités contrôlées puis remises dans le stock vendable. |
| `lost_quantity` | le nombre d’unités considérées comme perdues. |
| `quarantined_quantity` | le nombre d’unités gardées à part pour vérification. |
| `documented_missing_quantity` | le nombre d’unités qui devaient revenir mais qui manquent, avec une explication enregistrée. |
| `discrepancy_reason` | explique pourquoi le montant reçu est différent du montant attendu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `unit_cost_snapshot` | une copie du coût unitaire au moment de la vente, afin que la marge historique ne change pas si le coût catalogue change plus tard. |
| `inspected_at` | la date et l’heure liées à **inspecte**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `note` | une information libre ajoutée pour aider à comprendre la ligne. Selon la table, cela peut être une note interne ou une note chiffrée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 25. `shipping_providers` — 14 champs

Les personnes ou sociétés qui livrent pour la boutique. Exemple : un livreur interne ou EcoTrack, avec le suivi de l’argent qu’ils doivent reverser.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `user_id` | l’identifiant du compte utilisateur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `carrier_account_id` | l’identifiant du compte transporteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `type` | code de `ShippingProviderTypeEnum` : `1 CARRIER`, `2 EMPLOYEE`, `3 OWNER`. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `phone` | le numéro de téléphone. Il est gardé comme texte pour ne pas perdre le `0`, le `+213` ou d’autres signes utiles. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `email` | L’e-mail de contact de ce livreur ou transporteur, s’il est renseigné. |
| `reference_configuration` | Les bureaux autorisés/masqués par le commerçant et les exceptions privées de codes de son compte, dans un format JSON limité. Aucun secret ni tarif global dupliqué. |
| `last_synced_at` | la dernière fois où le SaaS a synchronisé ce compte avec le service externe. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_active` | indique si l’élément peut encore être utilisé. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 26. `shipping_rates` — 22 champs

Les trois sortes de tarifs dans une table : prix client, devis du prestataire et versions du tarif de retour du compte. Leur type empêche de les confondre.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne du tarif. |
| `uuid` | Son identifiant public. |
| `provider_id` | Le prestataire concerné par le devis. |
| `carrier_account_id` | Le compte transporteur concerné par le tarif de retour. |
| `created_by_id` | Le compte local qui a créé la version du tarif de retour. |
| `province_uuid` | La wilaya concernée par le prix client ou le devis. |
| `municipality_uuid` | La commune précise, si le tarif est plus détaillé que la wilaya. |
| `record_type` | 1 : prix client ; 2 : coût prévu du prestataire ; 3 : version du prix d’un retour. |
| `delivery_mode` | Le mode domicile ou stop desk du prix client ou du devis. |
| `service_type` | Le service du devis ; le prix client concerne l’envoi aller. |
| `amount` | Le montant de ce tarif ; ce n’est pas encore une charge ou un paiement. |
| `source` | Indique si le devis ou le tarif de retour a été saisi ou obtenu par API. |
| `retrieved_at` | La date où le devis a été obtenu. |
| `starts_at` | Le début de la période du tarif de retour. |
| `ends_at` | La fin éventuelle de cette période. |
| `is_active` | Indique si ce tarif est utilisable selon son type. |
| `provider_scope_id` | Une valeur calculée permettant l’unicité du prix client sans prestataire. |
| `municipality_scope_uuid` | Une valeur calculée pour l’unicité du tarif général d’une wilaya sans commune. |
| `current_slot` | Une valeur calculée empêchant deux tarifs courants pour la même combinaison. |
| `created_at` | La date de création de la ligne. |
| `updated_at` | La date du dernier changement autorisé, sans réécrire un ancien montant de retour. |
| `deleted_at` | L’archivage d’un prix client ou devis ; les versions de retour restent conservées. |

### 27. `free_shipping_rules` — 14 champs

Les conditions qui rendent automatiquement la livraison gratuite pour le client. Exemple : offrir la livraison lorsque la commande remplit la règle définie par la boutique.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `delivery_mode` | la façon de livrer choisie. Exemple : domicile ou point relais. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `minimum_cart_amount` | la somme d’argent correspondant à **panier minimum**. Exemple : `1500` représente 1 500 DA au lancement. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `started_at` | la date et l’heure où la période ou l’action commence. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `ended_at` | la date et l’heure où elle s’est terminée. Peut rester vide tant que ce n’est pas terminé. |
| `priority` | un nombre utilisé pour décider quel élément passe avant un autre. Exemple : priorité 1 avant priorité 2. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 28. `shipments` — 24 champs

Le colis envoyé pour une commande et les informations permettant de le suivre. Exemple : une commande de trois produits part dans un seul colis avec un numéro de suivi.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipped_revision_id` | l’identifiant de la version réellement expédiée. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `pickup_point_uuid` | L’identifiant du bureau central choisi dans la version expédiée ; il doit être exactement le même. |
| `label_media_id` | l’identifiant lié à **etiquete media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `assigned_by_id` | l’identifiant de la personne qui a affecté. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `delivery_mode` | la façon de livrer choisie. Exemple : domicile ou point relais. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `raw_external_status` | le statut exact reçu du transporteur avant de le traduire dans les statuts internes du SaaS. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `tracking` | le numéro de suivi du colis donné par le transporteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `merchant_reference` | La référence de colis créée par cette boutique ; elle aide à retrouver ses colis dans le compte transporteur. |
| `external_reference` | le numéro donné par un système extérieur. Exemple : référence d’un reversement chez le transporteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `cod_amount` | la somme d’argent correspondant à **COD**. Exemple : `1500` représente 1 500 DA au lancement. |
| `estimated_cost` | le coût correspondant à **estime**, utilisé pour connaître ce que cela coûte réellement au commerçant. |
| `weight_kg` | le poids en kilogrammes. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_fragile` | indique si le produit ou la variante doit être traité comme fragile. |
| `shipped_at` | la date et l’heure liées à **expediee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `carrier_validated_at` | la date et l’heure liées à **validee transporteur**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `delivered_at` | la date et l’heure liées à **livree**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `last_synced_at` | la dernière fois où le SaaS a synchronisé ce compte avec le service externe. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 29. `shipment_events` — 25 champs

Les étapes reçues pendant le transport, avec le message original du transporteur. Exemple : « en livraison », puis « livré » ou « refusé ».

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `actor_id` | l’identifiant de la personne qui a fait l’action. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `logistics_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `financial_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `external_code` | le code utilisé pour reconnaître **externe** de manière stable dans le programme ou chez un service externe. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `event_type` | le type d’événement enregistré. Exemple : vue produit, ajout au panier ou début de checkout. |
| `raw_external_activity` | le texte ou code d’activité exact reçu du transporteur, conservé seulement si nécessaire pour comprendre son événement. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `raw_external_status` | le statut exact reçu du transporteur avant de le traduire dans les statuts internes du SaaS. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `adapter_version` | le numéro de version de adaptateur. Exemple : version 1, puis version 2 après une évolution ; l’ancienne version peut rester conservée pour comprendre l’historique. |
| `sanitized_external_payload` | une copie nettoyée de la réponse externe, sans secrets inutiles. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `payload_hash` | une petite signature calculée à partir de payload. Elle permet de vérifier que le contenu n’a pas changé sans stocker une deuxième copie complète. |
| `payload_expires_at` | la date et l’heure liées à **payload expire**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `payload_purged_at` | la date et l’heure liées à **payload purge**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reason` | explique pourquoi l’action ou la décision a été faite. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `comment` | le texte écrit par le client ou l’utilisateur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `station` | la station ou agence du transporteur concernée lorsqu’elle existe. |
| `courier_label` | le nom ou libellé du livreur fourni par le transporteur lorsqu’il existe. |
| `next_delivery_attempt_at` | la date et l’heure liées à **prochain passage**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `occurred_at` | la date et l’heure où l’événement s’est produit. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `observed_at` | la date où le SaaS a vu cet événement. |
| `source` | indique d’où vient l’information. Exemple : saisie manuelle ou réponse de l’API. |
| `deduplication_key` | une clé utilisée pour repérer deux messages ou événements qui représentent en réalité la même action. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 30. `carrier_operations` — 27 champs

Les demandes à envoyer au transporteur, conservées pour pouvoir les suivre et les reprendre. Exemple : demander la création d’un colis sans créer un deuxième colis si la réponse est incertaine.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `superseded_by_operation_id` | l’identifiant de l’opération plus récente qui remplace celle-ci. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `triggered_by_id` | l’identifiant de la personne ou action qui a déclenché. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `type` | code de `CarrierOperationTypeEnum` indiquant l’opération technique demandée au transporteur : création, mise à jour, annulation, retour, synchronisation, rapprochement, étiquette, etc. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `sanitized_request` | une copie de la demande envoyée à l’API après retrait des mots de passe, clés et données inutiles. |
| `encrypted_personal_request` | les données personnelles nécessaires à l’appel transporteur, enregistrées sous forme chiffrée seulement tant qu’elles sont encore utiles. Elles peuvent ensuite être supprimées. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `request_hash` | une petite signature calculée à partir de requete. Elle permet de vérifier que le contenu n’a pas changé sans stocker une deuxième copie complète. |
| `request_expires_at` | la date et l’heure liées à **requete expire**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `request_purged_at` | la date et l’heure liées à **requete purge**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `merchant_reference` | un numéro stable créé côté marchand/SaaS pour reconnaître le colis chez le transporteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `adapter_version` | le numéro de version de adaptateur. Exemple : version 1, puis version 2 après une évolution ; l’ancienne version peut rester conservée pour comprendre l’historique. |
| `technical_result` | indique si l’appel technique a réussi, échoué ou reste incertain. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `attempts_count` | le nombre de **tentatives**. Exemple : `3` signifie qu’il y en a trois. |
| `next_attempt_at` | la date prévue pour retenter l’opération après un échec récupérable. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `ended_at` | la date et l’heure où cette attribution ou règle a pris fin. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sending_started_at` | la date et l’heure liées à **envoi commence**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `superseded_at` | la date et l’heure liées à **supersedee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 31. `carrier_operation_attempts` — 13 champs

Le résultat de chaque essai de communication avec le transporteur. Exemple : le premier essai échoue ; une nouvelle tentative est enregistrée séparément sans effacer la précédente.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `operation_id` | l’identifiant de l’opération. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `attempt_number` | le numéro de l’essai. Exemple : 1 pour le premier essai, 2 après un nouvel essai. |
| `http_status_code` | le code renvoyé par l’API. Exemple : 200, 400 ou 500. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sanitized_response` | une copie nettoyée de la réponse reçue de l’API. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `payload_expires_at` | la date et l’heure liées à **payload expire**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `payload_purged_at` | la date et l’heure liées à **payload purge**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `error_code` | un petit code qui permet de reconnaître le type d’erreur sans stocker un long message sensible. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `duration_ms` | le temps pris par l’appel, en millisecondes. |
| `started_at` | la date et l’heure où la période ou l’action commence. |
| `ended_at` | la date et l’heure où elle s’est terminée. Peut rester vide tant que ce n’est pas terminé. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 32. `collections` — 13 champs

Le suivi de l’argent lié à un colis : ce qui doit être encaissé et reversé. Exemple : le client a payé le livreur, mais le commerçant attend encore son argent.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `declared_status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `expected_amount` | le montant que l’on pense devoir recevoir selon les ventes et frais connus. |
| `declared_collected_amount` | le montant déclaré comme encaissé avant ou pendant la vérification. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `collected_at` | la date où l’argent a réellement été encaissé auprès du client. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `payment_ready_at` | la date et l’heure liées à **paiement pret**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `declared_paid_at` | la date et l’heure liées à **paye declare**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `source` | indique d’où vient l’information. Exemple : saisie manuelle ou réponse de l’API. |
| `reconciled_at` | la date où le montant reçu a été comparé et rapproché avec ce qui était attendu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 33. `remittance_statements` — 22 champs

Les documents de suivi d’un règlement avec le livreur ou le transporteur pour cette boutique. Exemple : expliquer le montant reçu en distinguant ventes, frais et indemnisations.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `carrier_remittance_batch_id` | Le lot de versement transporteur auquel appartient ce règlement local. |
| `validated_by_id` | l’identifiant de la personne qui a validé. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `number` | le numéro lisible de l’élément. Exemple : numéro d’échéance ou de facture selon la table. |
| `external_reference` | le numéro donné par un système extérieur. Exemple : référence d’un reversement chez le transporteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `type` | code de `StatementTypeEnum` décrivant la nature du bordereau financier. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `gross_amount` | la somme d’argent correspondant à **brut**. Exemple : `1500` représente 1 500 DA au lancement. |
| `fee_amount` | la somme d’argent correspondant à **frais**. Exemple : `1500` représente 1 500 DA au lancement. |
| `expected_net_amount` | la somme d’argent correspondant à **net attendu**. Exemple : `1500` représente 1 500 DA au lancement. |
| `received_net_amount` | le montant net réellement reçu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `declared_at` | la date et l’heure liées à **declare**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `received_at` | la date et l’heure liées à **recu**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `note` | une information libre ajoutée pour aider à comprendre la ligne. Selon la table, cela peut être une note interne ou une note chiffrée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `reconciled_at` | la date où le montant reçu a été comparé et rapproché avec ce qui était attendu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 34. `carrier_settlement_lines` — 21 champs

Le détail des règlements : produits reversés, frais réglés, créances apurées ou indemnités. Chaque ligne indique clairement laquelle de ces quatre actions elle représente.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne de règlement. |
| `uuid` | Son identifiant public. |
| `provider_id` | Le prestataire auquel appartiennent cette ligne et ses autres liens. |
| `remittance_statement_id` | Le bordereau réel qui explique ce règlement, lorsqu’il existe. |
| `collection_id` | Le recouvrement du colis dont une part est reversée. |
| `carrier_fee_id` | Le frais payé ou le futur frais sur lequel une créance est compensée. |
| `receivable_id` | La créance remboursée ou compensée. |
| `shipment_id` | Le colis concerné ; obligatoire pour produits reversés, frais payés et indemnité. |
| `replacement_order_id` | La commande de remplacement éventuellement liée à l’indemnité. |
| `proof_media_id` | Le justificatif privé propre à un apurement ou une indemnité ; les autres types utilisent le bordereau et ses preuves. |
| `reversal_of_id` | La ligne inversée exactement, sans inventer un transfert d’argent. |
| `correction_of_id` | L’ancienne ligne à laquelle une nouvelle écriture correcte se rattache. |
| `record_type` | 1 : reversement produits ; 2 : paiement de frais ; 3 : règlement d’une créance ; 4 : indemnité de sinistre. |
| `amount` | Le montant de cette opération ; chaque type garde son rôle dans les calculs. |
| `fee_payment_mode` | Pour les frais : paiement séparé ou compensation sur un reversement. |
| `receivable_settlement_type` | Pour une créance : remboursement bancaire, compensation de frais/bordereau ou autre règlement validé. |
| `reason` | La raison de l’indemnité, par exemple un colis perdu. |
| `external_reference` | La référence de l’apurement ou de l’indemnité, utile contre les doublons. |
| `operation_key` | Une clé stable empêchant le même fait d’être enregistré deux fois. |
| `performed_at` | Quand la créance a réellement été réglée ou compensée. |
| `created_at` | La date de création de cette ligne. |

### 35. `expenses` — 21 champs

Les autres dépenses réelles de la boutique, hors frais transporteur et pertes de stock déjà suivis ailleurs. Exemple : publicité, emballages ou frais généraux.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `author_id` | l’identifiant de la personne qui a créé l’élément. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `category` | la catégorie de l’élément. Exemple : transport, publicité ou autre type de dépense. |
| `label` | un nom court utilisé pour reconnaître facilement l’élément à l’écran. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `expense_date` | la date à laquelle la dépense doit être comptée. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `source` | indique d’où vient l’information. Exemple : saisie manuelle ou réponse de l’API. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `cancelled_at` | La date d’abandon d’une dépense encore en brouillon ; une dépense reconnue se corrige avec une écriture inverse. |
| `note` | une information libre ajoutée pour aider à comprendre la ligne. Selon la table, cela peut être une note interne ou une note chiffrée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 36. `customer_adjustments` — 21 champs

Le suivi des remboursements aux acheteurs, avec leurs montants, motifs et états. Exemple : enregistrer un remboursement réellement effectué, sans créer de portefeuille client.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `incident_id` | l’identifiant de l’incident de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `credit_note_id` | l’identifiant de l’avoir. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `validated_by_id` | l’identifiant de la personne qui a validé. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `compensated_quantity` | le nombre d’unités correspondant à **compensee**. Exemple : `2` signifie deux unités. |
| `amount_kind` | Indique si le remboursement/régularisation concerne les produits, la livraison ou un montant global contrôlé. La différence d’échange payée est retirée. |
| `type` | code de `AdjustmentTypeEnum` indiquant la nature de l’ajustement financier appliqué au client. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `performed_at` | la date où l’opération a réellement été faite. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reference` | un numéro ou texte de référence qui aide à reconnaître l’opération. Exemple : numéro d’un reçu ou référence externe. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 37. `order_documents` — 11 champs

Les bons de commande facultatifs correspondant à une version précise de la commande. Exemple : conserver un document indiquant exactement les articles et les prix de cette version.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `media_id` | l’identifiant du fichier/image/vidéo. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `generated_by_id` | l’identifiant de la personne qui a généré. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `number` | le numéro lisible de l’élément. Exemple : numéro d’échéance ou de facture selon la table. |
| `document_version` | le numéro de version de document. Exemple : version 1, puis version 2 après une évolution ; l’ancienne version peut rester conservée pour comprendre l’historique. |
| `issuer_snapshot` | une **copie figée** de emetteur au moment important de l’opération. Si l’information d’origine change plus tard, cette ancienne ligne garde la valeur utilisée à ce moment-là. |
| `generated_at` | la date et l’heure liées à **genere**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 38. `activity_log` — 17 champs

Le carnet de la boutique : qui a fait quoi, quand et sur quel élément. Il garde aussi la validation par clic et les opérations sensibles sur les données, sans seconde table de journal.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro interne de cette action. |
| `uuid` | son identifiant public unique. |
| `subject_id` | le numéro local de cet élément ; ce lien polymorphe n'est pas une FK SQL universelle. |
| `causer_id` | le numéro du compte local qui a agi ; il permet de retrouver qui a cliqué Valider. |
| `log_name` | la famille de l'action ; privacy désigne une opération sur les données personnelles. |
| `description` | une phrase courte expliquant l'action. |
| `subject_type` | le type d'élément concerné, par exemple une commande ou un produit. |
| `event` | le code précis de l'action, par exemple order.validated. |
| `causer_type` | le type de compte local qui a agi ; vide pour une action système selon origin. |
| `attribute_changes` | les anciennes et nouvelles valeurs des champs autorisés à être journalisés. |
| `properties` | les informations utiles et contrôlées de l'action ; jamais mots de passe, clés API ou copies des coordonnées. |
| `operation_key` | une clé unique qui empêche d'enregistrer deux fois le même fait lors d'une reprise. |
| `correlation_id` | le lien entre plusieurs actions de la même opération. |
| `origin` | indique si l'action vient d'un compte, du système ou d'une autre origine autorisée. |
| `performed_at` | la date réelle de la phase tracée sur les données : intention, réussite, refus, échec ou résultat incertain. Une date d’intention ne prouve pas qu’une transmission a réussi ; created_at reste la date d’enregistrement. |
| `created_at` | la date à laquelle l'activité a été enregistrée. |
| `updated_at` | champ technique du modèle Spatie ; ne permet pas de réécrire un fait historique. |

### 39. `carrier_fees` — 24 champs

Les frais liés au transport et la personne qui doit les payer. Exemple : des frais de retour à la charge du commerçant, distincts de la livraison payée par l’acheteur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `carrier_account_id` | l’identifiant du compte transporteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `source_rate_id` | l’identifiant lié à **tarif source**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `rate_snapshot` | une copie figée du tarif utilisé pour calculer ce frais. Si le tarif change demain, l’ancien frais garde son ancien prix. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `source_rate_record_type` | Le code calculé 3 impose une version historique du tarif de retour, jamais un prix client ou un devis. |
| `fee_type` | le type de frais du transporteur. Exemple : frais de retour ou autre frais prévu. |
| `payer` | indique qui doit supporter le frais. Exemple : commerçant ou autre partie prévue. |
| `settlement_mode` | la façon dont le règlement a été fait. Exemple : déduction, versement ou autre mode autorisé. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `triggered_at` | la date de l’événement qui fait réellement naître le frais. Exemple : la date où le transporteur accepte le retour. |
| `date_source` | indique d’où vient la date utilisée. Exemple : date fournie par le transporteur ou première date observée par le SaaS. |
| `recognized_at` | la date et l’heure liées à **constate**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `external_reference` | le numéro donné par un système extérieur. Exemple : référence d’un reversement chez le transporteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 40. `carrier_receivables` — 16 champs

Les sommes que le transporteur doit rendre après correction de frais déjà payés. Exemple : 650 DA ont été payés au lieu de 600 DA ; le transporteur doit encore 50 DA au commerçant.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `carrier_fee_id` | l’identifiant des frais du transporteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `original_fee_payment_id` | l’identifiant du règlement de frais d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `initial_amount` | le montant du frais au moment où il a été constaté. |
| `remaining_amount` | la partie du montant qui n’a pas encore été réglée ou affectée. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `original_fee_payment_record_type` | Le code calculé 2 impose un règlement de frais comme paiement d’origine du trop-payé. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `recognized_at` | la date et l’heure liées à **reconnue**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `settled_at` | la date et l’heure liées à **soldee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 41. `collection_entries` — 14 champs

Les montants réellement encaissés auprès du client et vérifiés, avec leurs éventuelles corrections. Exemple : confirmer que le livreur a reçu 5 000 DA ; cela ne prouve pas encore leur reversement au commerçant.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `collection_id` | l’identifiant du recouvrement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `verified_by_id` | l’identifiant de la personne qui a vérifié. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `collected_at` | la date où l’argent a réellement été encaissé auprès du client. |
| `verified_at` | la date où l’information a été vérifiée. |
| `reference` | un numéro ou texte de référence qui aide à reconnaître l’opération. Exemple : numéro d’un reçu ou référence externe. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 42. `invoices` — 28 champs

Les factures de vente de la boutique et les avoirs qui les corrigent, avec leur contenu historique conservé. Exemple : garder la facture d’origine puis créer un avoir si son montant doit être réduit.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `original_invoice_id` | l’identifiant de la facture d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sequence_id` | Le compteur local utilisé pour attribuer le numéro, dans billing_rules avec record_type=1 SEQUENCE. Vide tant que le numéro n’a pas été réservé. |
| `media_id` | Le PDF privé produit par la boutique. Son emplacement et son empreinte canonique sont dans media.storage_key et media.file_hash ; le fichier et ces métadonnées sont protégés après préparation/émission. |
| `issued_by_id` | l’identifiant de la personne qui a émis. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `incident_id` | l’identifiant de l’incident de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `document_type` | 1 INVOICE pour une facture de cette boutique ; 2 CREDIT_NOTE pour un avoir qui corrige une facture. |
| `sequence_record_type` | Colonne SQL calculée qui impose que sequence_id vise un compteur, jamais une règle de facturation ; reste vide avant réservation du numéro. |
| `fiscal_year` | L’exercice du numéro réservé ; il doit être identique à celui du compteur choisi. |
| `sequence_number` | le nombre utilisé à l’intérieur de la série du document. Exemple : `123` dans `FAC-2026-000123`. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `snapshot_format_version` | une **copie figée** de version format au moment important de l’opération. Si l’information d’origine change plus tard, cette ancienne ligne garde la valeur utilisée à ce moment-là. |
| `currency` | la monnaie utilisée. Exemple : `DZD` pour le dinar algérien. |
| `number` | le numéro lisible de l’élément. Exemple : numéro d’échéance ou de facture selon la table. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `seller_snapshot` | une copie figée des informations du vendeur utilisées pour cette commande. |
| `client_snapshot` | une copie figée des informations client utilisées par cette version de commande. |
| `items_snapshot` | une **copie figée** de articles au moment important de l’opération. Si l’information d’origine change plus tard, cette ancienne ligne garde la valeur utilisée à ce moment-là. |
| `totals_snapshot` | une copie figée des totaux de la commande à ce moment précis. |
| `issued_at` | la date et l’heure liées à **emise**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `cancelled_at` | La date d’abandon d’un brouillon fiscal ; une facture déjà émise reste conservée et se corrige par un avoir. |
| `cancellation_reason` | Le motif d’abandon d’un brouillon de document fiscal. Ce champ n’annule pas et ne clôture pas une commande. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `document_reason` | explique la raison de **document**. Cela permet de comprendre plus tard pourquoi la décision a été prise. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 43. `order_incidents` — 19 champs

Le dossier d’un problème concernant une ligne de produits expédiée et les limites de sa prise en charge. Exemple : un article cassé pour lequel on examine un remplacement ou un remboursement.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipped_revision_id` | l’identifiant de la version réellement expédiée. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `order_item_id` | l’identifiant de la ligne de produit de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `opened_by_id` | l’identifiant de la personne qui a ouvert. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `validated_by_id` | l’identifiant de la personne qui a validé. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `affected_quantity` | la quantité de la créance ou du montant affectée par cette ligne. |
| `eligible_product_amount` | la somme d’argent correspondant à **eligible produits**. Exemple : `1500` représente 1 500 DA au lancement. |
| `eligible_shipping_amount` | la somme d’argent correspondant à **eligible livraison**. Exemple : `1500` représente 1 500 DA au lancement. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `validated_at` | la date et l’heure liées à **valide**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `closed_at` | La date à laquelle le dossier d’incident est terminé, sans effacer ses remèdes ou libérer un budget déjà consommé. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 44. `order_incident_details` — 9 champs

Les différents problèmes et quantités dans un dossier d’incident. Exemple : sur trois articles, un est cassé, un manque et le troisième est correct ; on ne compte pas deux fois le même article.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `incident_id` | l’identifiant de l’incident de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `author_id` | l’identifiant de la personne qui a créé l’élément. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `type` | code de `IncidentTypeEnum` : `DAMAGED`, `DEFECTIVE`, `INCORRECT`, `MISSING`, `LOST` ou `OTHER`. |
| `quantity` | le nombre d’éléments concernés. Exemple : `2` signifie deux unités du produit. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 45. `billing_rules` — 23 champs

Une seule table locale contient les compteurs de numéros et les règles de facturation. Une ligne type 1 réserve les numéros de facture/avoir ; une ligne type 2 décrit quand et comment la boutique produit ses documents.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de ce réglage. |
| `uuid` | Son identifiant public, utilisé dans les écrans et les liens autorisés. |
| `validated_by_id` | Le compte local habilité qui a validé une règle ; vide sur un compteur ou avant validation. |
| `record_type` | 1 SEQUENCE : un compteur de numéros ; 2 RULE : une version de règle de facturation. |
| `document_type` | Le type du compteur : facture ou avoir ; une règle n’utilise pas ce champ. |
| `fiscal_year` | L’exercice du compteur, par exemple 2026. |
| `shop_prefix` | Le préfixe documentaire stable attribué à la boutique, qui compose ses numéros. |
| `next_number` | Le prochain numéro disponible ; il progresse sous verrou à chaque réservation. |
| `sequence_slot` | Une colonne calculée qui permet de réserver un seul compteur par type et exercice. |
| `code` | Le code stable de la règle, par exemple sales.invoice. |
| `version` | Le numéro de version de cette règle ; changer son contenu crée une nouvelle version. |
| `seller_profile_version` | La version du dossier professionnel du propriétaire prise en compte pour valider la règle. |
| `trigger_event` | L’événement métier autorisé qui doit provoquer une facture ou un avoir. |
| `return_resend_rule` | La règle fiscale validée pour traiter un retour et sa nouvelle vente de renvoi. Elle ne suppose aucun premier paiement ni crédit. |
| `numbering_scope` | La portée de numérotation de la règle ; elle reste propre à cette boutique. |
| `parameters` | Les paramètres structurés de la règle, interprétés seulement par du code serveur autorisé. |
| `policy_status` | L’état de la règle : brouillon, validée, active ou retirée ; pas l’état d’une facture. |
| `validation_reference` | La référence expliquant sur quoi repose la validation de la règle. |
| `validated_at` | La date de validation de la règle. |
| `effective_at` | La date à laquelle la règle devient applicable. |
| `ends_at` | La fin éventuelle de la période d’application de la règle. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 46. `sales_terms_acceptances` — 11 champs

Garder quelles conditions ont réellement été acceptées, à quelle date et pour quelle version de commande. Ce n'est ni un PDF d'accord téléphonique ni un envoi au client.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `sales_terms_version` | la version des conditions de vente applicables à cette commande. |
| `terms_hash` | une petite signature calculée à partir de conditions. Elle permet de vérifier que le contenu n’a pas changé sans stocker une deuxième copie complète. |
| `accepted_at` | la date et l’heure liées à **accepte**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `acceptance_mode` | indique la manière choisie pour **acceptation**. |
| `sanitized_proof` | plusieurs petits réglages liés à **preuve filtree**, regroupés ensemble de manière structurée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 47. `billing_obligations` — 20 champs

Les factures ou avoirs à produire après un événement commercial prévu, avec leurs tentatives et le document finalement émis. Aucun crédit fictif pour un colis jamais payé.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `billing_rule_id` | La version de règle locale dans billing_rules, obligatoirement de type 2 RULE ; elle est figée pour cette occurrence. |
| `original_invoice_id` | l’identifiant de la facture d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `invoice_id` | l’identifiant de la facture. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `event_id` | l’identifiant de l’événement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `billing_rule_record_type` | Colonne SQL calculée qui interdit de prendre un compteur pour une règle. |
| `rule_snapshot` | une **copie figée** de regle au moment important de l’opération. Si l’information d’origine change plus tard, cette ancienne ligne garde la valeur utilisée à ce moment-là. |
| `event_type` | le type d’événement qui a créé l’obligation de facturer. Exemple : vente finalisée, avoir à produire ou autre événement prévu. |
| `triggered_at` | la date de l’événement qui fait réellement naître le frais. Exemple : la date où le transporteur accepte le retour. |
| `document_type` | indique quel document c’est. Exemple : facture, avoir ou autre type prévu. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `attempts_count` | le nombre de **tentatives**. Exemple : `3` signifie qu’il y en a trois. |
| `next_attempt_at` | la date prévue pour retenter l’opération après un échec récupérable. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `error_code` | un petit code qui permet de reconnaître le type d’erreur sans stocker un long message sensible. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 48. `commercial_corrections` — 16 champs

Les décisions qui corrigent les montants des ventes, avec la date où elles comptent dans les statistiques. Exemple : enregistrer une réduction après un retour, séparément du retour physique et du remboursement réel.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `source_revision_id` | l’identifiant de la version de commande servant de source. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `incident_id` | l’identifiant de l’incident de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `actor_id` | l’identifiant de la personne qui a fait l’action. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_type` | le type de correction économique. Exemple : réduction après retour, geste commercial ou autre correction prévue. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `non_product_revenue_delta` | la correction de revenu qui ne correspond pas directement à une ligne produit. Exemple : corriger 500 DA de livraison. |
| `non_product_kind` | indique ce que représente la correction hors produit. Exemple : livraison, geste global ou autre. |
| `effective_at` | la date à partir de laquelle l’information ou la correction doit compter. Exemple : une correction enregistrée aujourd’hui peut devoir compter pour la vente d’hier. |
| `recorded_at` | la date où la correction a été saisie dans le système ; elle peut être différente de la date où elle doit compter. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 49. `commercial_correction_lines` — 11 champs

Le détail d’une correction commerciale pour chaque ligne de produits concernée. Exemple : retirer 2 000 DA de ventes pour un article et indiquer aussi la correction de son coût dans les résultats.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `correction_id` | l’identifiant de la correction commerciale. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `source_revision_id` | l’identifiant de la version de commande servant de source. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `order_item_id` | l’identifiant de la ligne de produit de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `affected_quantity` | le nombre d’unités correspondant à **concernee**. Exemple : `2` signifie deux unités. |
| `reference_sale_amount` | la somme d’argent correspondant à **vente reference**. Exemple : `1500` représente 1 500 DA au lancement. |
| `revenue_delta` | le changement à appliquer au chiffre d’affaires pour cette ligne. Exemple : `-2000` retire 2 000 DA des ventes reconnues. |
| `sold_cost_delta` | le changement à appliquer au coût des produits vendus pour calculer correctement la marge. |
| `detailed_reason` | explique la raison de **detaille**. Cela permet de comprendre plus tard pourquoi la décision a été prise. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 50. `users` — 18 champs

Les comptes du propriétaire et des employés de cette boutique : nom, e-mail, mot de passe protégé, état du compte et droit d’entrer dans l’équipe. Chaque boutique garde ses propres comptes.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `central_user_uuid` | L’identifiant du propriétaire dans la BDD centrale ; vide pour les employés. Ce n’est pas un compte partagé. |
| `email` | L’adresse e-mail du compte ou de la personne invitée. |
| `last_name` | Le nom de famille du titulaire de ce compte local, par exemple Amrani. |
| `first_name` | Le prénom du titulaire de ce compte local, par exemple Karim, si renseigné. |
| `password` | Le mot de passe haché du compte ; le mot de passe lisible n’est jamais gardé. |
| `phone` | Le numéro de téléphone du compte, si renseigné. |
| `email_verified_at` | La date à laquelle l’e-mail du compte a été vérifié. |
| `locale` | La langue utilisée par ce compte. |
| `status` | L’état du compte : actif, inactif, suspendu ou supprimé. |
| `membership_status` | Le droit du compte d’entrer dans l’équipe : actif, invité, suspendu ou révoqué. |
| `joined_at` | La date de sa première activation dans l’équipe ; elle reste la même après une réactivation. |
| `last_login_at` | La date de sa dernière connexion. |
| `remember_token` | Le jeton technique utilisé par le mécanisme de connexion prolongée du compte. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |
| `deleted_at` | La date d’archivage ; vide tant que cette ligne n’est pas archivée. |

### 51. `permissions` — 8 champs

La liste des actions qu’une personne peut être autorisée à faire dans cette boutique : créer un produit, valider une commande ou inviter un employé.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `name` | Le nom technique de l’action autorisable, par exemple order.confirm. |
| `guard_name` | Le contexte de connexion de ces droits ; ici tenant, pour cette boutique. |
| `label` | Le nom lisible affiché dans les écrans. |
| `feature_code` | Le code de la possibilité d’abonnement liée à cette permission, si nécessaire ; il est vérifié dans le catalogue central. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 52. `roles` — 13 champs

Les groupes d’autorisations de la boutique. Exemple : le rôle de préparateur réunit les actions nécessaires pour préparer les colis.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `super_admin_slot` | Une valeur calculée qui empêche de créer deux rôles de propriétaire dans cette base. |
| `permission_signature` | Une empreinte des actions et de leurs durées, pour refuser deux rôles identiques dans cette boutique même avec des noms différents. |
| `name` | Le nom technique du rôle, par exemple shop-owner. |
| `guard_name` | Le contexte de connexion de ces droits ; ici tenant, pour cette boutique. |
| `label` | Le nom lisible affiché dans les écrans. |
| `is_system` | Indique si ce rôle est fourni par le système. |
| `is_protected` | Indique si les écrans ordinaires ne peuvent pas modifier ou retirer ce rôle. |
| `is_super_admin` | Indique si ce rôle dispose de l’exception de propriétaire dans cette boutique, avec les protections prévues. |
| `permission_version` | La version des droits de ce rôle ; elle change quand ses autorisations ou leurs durées changent. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 53. `role_has_permissions` — 3 champs

Indique quelles actions sont autorisées pour chaque rôle.

| Champ | Explication très simple |
|---|---|
| `permission_id` | Le lien local vers permissions correspondant à permission_id ; les informations ne sont pas recopiées. |
| `role_id` | Le lien local vers roles correspondant à role_id ; les informations ne sont pas recopiées. |
| `duration_days` | La durée en jours de cette action à partir de l’attribution du rôle : 9999 par défaut et au maximum, 1 au minimum. |

### 54. `model_has_roles` — 4 champs

Indique quel compte possède quel rôle dans cette boutique.

| Champ | Explication très simple |
|---|---|
| `role_id` | Le lien local vers roles correspondant à role_id ; les informations ne sont pas recopiées. |
| `model_type` | Le type du modèle auquel appartient ce lien, sous forme d’alias autorisé. |
| `model_id` | Le numéro local de ce modèle ; son type indique dans quelle table le retrouver. |
| `assigned_at` | Le début des durées pour ce compte ; lors d’une invitation, c’est l’acceptation effective. Un retry ne remet pas le compteur à zéro. |

### 55. `model_has_permissions` — 5 champs

Donne une autorisation directement à un compte de cette boutique.

| Champ | Explication très simple |
|---|---|
| `permission_id` | Le lien local vers permissions correspondant à permission_id ; les informations ne sont pas recopiées. |
| `model_type` | Le type du modèle auquel appartient ce lien, sous forme d’alias autorisé. |
| `model_id` | Le numéro local de ce modèle ; son type indique dans quelle table le retrouver. |
| `assigned_at` | La date de début de cette attribution directe, produite par le serveur. |
| `expires_at` | La fin obligatoire de ce droit direct, après le début et au maximum 9999 jours plus tard. |

### 56. `team_invitations` — 12 champs

Les invitations permettant à un employé de rejoindre cette boutique avec un rôle précis et un lien secret qui expire.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `initial_role_id` | Le rôle local prévu pour la personne invitée. |
| `invited_by_id` | Le compte local qui a envoyé cette invitation. |
| `email` | L’adresse e-mail du compte ou de la personne invitée. |
| `token_hash` | L’empreinte du secret de l’invitation ; le lien expire et n’est utilisable qu’une fois. |
| `role_permission_version` | La version des permissions ET des durées du rôle lors de l’invitation ; un changement impose une revalidation avant acceptation. |
| `expires_at` | La date après laquelle cette donnée ou ce jeton n’est plus utilisable. |
| `accepted_at` | La date de l’acceptation effective ; les durées d’un rôle nouvellement attribué commencent ici, pas à l’envoi. |
| `revoked_at` | La date à laquelle l’invitation a été retirée. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 57. `contact_verifications` — 11 champs

Les codes protégés utilisés pour vérifier les contacts des comptes du propriétaire et des employés ; les acheteurs ne reçoivent aucun message.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `user_id` | Le lien local vers users correspondant à user_id ; les informations ne sont pas recopiées. |
| `channel` | Le contact du compte à vérifier : e-mail ou téléphone selon le canal activé. |
| `normalized_destination` | L’adresse ou le numéro remis dans un format uniforme avant vérification. |
| `code_hash` | L’empreinte du code de vérification ; le code lisible n’est pas enregistré. |
| `expires_at` | La date après laquelle cette donnée ou ce jeton n’est plus utilisable. |
| `attempts_count` | Le nombre d’essais déjà effectués avec ce code. |
| `consumed_at` | La date à laquelle ce code a été utilisé avec succès ; il ne sert qu’une fois. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 58. `carrier_accounts` — 14 champs

Les connexions de cette boutique aux services de livraison : compte transporteur, adresse API et secrets protégés.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `created_by_id` | Le lien local vers users correspondant à created_by_id ; les informations ne sont pas recopiées. |
| `carrier_uuid` | L’identifiant public du réseau de livraison dans le catalogue central. Le compte API et ses secrets appartiennent uniquement à cette boutique. |
| `label` | Le nom lisible affiché dans les écrans. |
| `adapter` | Le module serveur chargé de comprendre et utiliser l’API de ce transporteur. |
| `external_account_id` | La référence de ce compte chez le transporteur, si disponible. |
| `api_url` | L’adresse de l’API du transporteur, contrôlée par le serveur. |
| `encrypted_api_credentials` | Les secrets du compte API conservés chiffrés ; ils ne sont pas envoyés au navigateur. |
| `encryption_key_version` | La version de la clé ayant servi à chiffrer les secrets. |
| `is_active` | Indique si cet élément peut encore être utilisé. |
| `last_synced_at` | La dernière date de synchronisation réussie de ce compte. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 59. `carrier_remittance_batches` — 15 champs

Les lots de versements annoncés par un compte transporteur, leur justificatif et la part vérifiée pour cette boutique.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `carrier_account_id` | Le lien local vers carrier_accounts correspondant à carrier_account_id ; les informations ne sont pas recopiées. |
| `proof_media_id` | Le lien local vers media correspondant à proof_media_id ; les informations ne sont pas recopiées. |
| `reversal_of_id` | Le lien local vers carrier_remittance_batches correspondant à reversal_of_id ; les informations ne sont pas recopiées. |
| `validated_by_id` | Le lien local vers users correspondant à validated_by_id ; les informations ne sont pas recopiées. |
| `external_reference` | La référence du lot ou du versement chez le transporteur. |
| `reported_account_net_amount` | Le total net annoncé pour le compte transporteur, qui peut couvrir plusieurs boutiques ; il ne prouve pas la part de celle-ci. |
| `computed_shop_net_amount` | La part calculée à partir des règlements et colis appartenant à cette boutique. |
| `verified_net_amount` | La part de ce lot réellement vérifiée pour cette boutique. |
| `status` | L’état du lot : déclaré, vérifié ou contrepassé. |
| `received_at` | La date du versement reçu. |
| `operation_key` | La clé unique qui empêche d’enregistrer deux fois la même opération lors d’une reprise. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

## 5. Décisions et contrôles de cette version

| Changement | Comportement conservé ou adapté |
|---|---|
| Identité | first_name/last_name pour la personne ; shop_name pour le nom public projeté |
| Rôles datés | 1..9999 jours par action, défaut/max 9999 ; début à l’attribution et à l’acceptation d’invitation |
| Unicité et recoupements | Noms/compositions identiques refusés localement ; plusieurs rôles par compte sans action commune |
| Exceptions de permissions | permission_overrides retirée ; durée dans les pivots, retrait/remplacement de rôle pour changer les droits |
| Catégories + étiquettes | categories type 1/2 ; produits séparés, product_tags multiple et hiérarchie contrôlée |
| Produits / variantes / choix | Prix, stocks et identité physique gardent leurs tables et contraintes |
| Bureaux et codes communs | Références au central ; autorisations/exceptions privées dans shipping_providers |
| Visiteurs et statistiques | visitors/visit_sessions/navigation_events conservés ; préférences accepter/refuser retirées |
| Caractéristiques structurées et registre des traitements | Tables retirées du MVP ; descriptions/champs directs et audit réel conservés |
| Renvoi après refus impayé | Nouvelle commande type 4 liée au retour, produits facturés au prix entier |
| Retour gratuit/payant/inconnu | Coût réel 0/positif/non confirmé ; aucune gratuité inventée |
| Récupération des frais de retour | Ajout manuel et motivé au prix de livraison du nouveau colis, 0 par défaut |
| Échange payé avec avoir affecté | Retiré ; vrais remboursements de paiements et pièces fiscales conservés |

#### Références transporteur propres à la boutique

carrier_accounts.carrier_uuid vise logiquement central.shipping_carriers.uuid ; il ne crée aucune FK entre bases. L’adaptateur et api_url locaux conservent les paramètres réellement validés du compte, avec valeurs centrales par défaut et dérogation contrôlée. Les secrets restent chiffrés dans cette BDD. shipping_providers.carrier_code n’est plus recopié : le code vient du réseau du compte. UNIQUE(carrier_uuid,external_account_id) hors NULL empêche deux connexions locales involontaires au même compte ; partager un compte entre deux boutiques reste permis, avec filtrage strict par colis local. Le réseau d’un compte utilisé ne change pas silencieusement.

reference_configuration suit un format serveur fermé, schema_version=1, NULL pour un livreur interne/propriétaire :

| Élément JSON | Rôle et validation |
|---|---|
| schema_version | Version entière 1 du format ; aucun code exécutable |
| pickup_policy | ALL_VALIDATED ou ALLOWLIST ; valeur initiale explicite décidée lors de la configuration |
| disabled_pickup_point_uuids | Liste sans doublons de bureaux centraux masqués volontairement ; aucune synchronisation ne l’efface |
| enabled_pickup_point_uuids | Liste sans doublons autorisée en ALLOWLIST ; UUID du bon réseau uniquement |
| validated_reference_version | Version du catalogue vérifiée pour ce compte, sans supposer que tous les bureaux restent disponibles |
| mapping_overrides | Exceptions privées uniquement : geographic_area_uuid, zone_type, external_code, external_name, external_province_code, verification_source, verified_at, source_version ; common_mapping_uuid facultatif si une référence globale est corrigée pour ce compte |
| pickup_code_overrides | Codes de bureaux différents pour ce compte : pickup_point_uuid central, external_code, external_province_code, verification_source, verified_at, source_version ; une exception maximum par bureau du bon réseau |

Les listes sont bornées par les entrées vérifiées du réseau et la limite serveur documentée du payload ; aucun plafond arbitraire ne tronque une migration. Une exception de zone est unique par geographic_area_uuid/type ; zone/type/wilaya/pays contrôlés, pas de tableau complet duplicatif du référentiel public. Tout bureau proposé est actif côté central ET autorisé par la boutique ET utilisable par son vrai compte. Les exclusions manuelles survivent à la synchronisation ; un changement global ne les remet jamais à zéro. Les codes privé/public sont résolus sous contrôle avant préparation HTTP ; absence ou ambiguïté bloque la route. Une exception bureau change seulement le code du compte, jamais l’identité/adresse du bureau public ni son réseau. Le JSON garde seulement ces réglages/exceptions peu nombreux et n’abrite ni secret, tarif, commande, stock, bureau global entier ni historique d’action.

Le choix de bureau est conservé dans order_revisions.pickup_point_uuid avec pickup_point_snapshot (UUID, nom, adresse, réseau, zone, code externe et version effectivement validés). shipments.pickup_point_uuid reprend exactement la révision expédiée. carrier_operations.request_payload garde une enveloppe versionnée : payload fournisseur et reference_context filtré (UUID réseau/zone/bureau, versions et codes résolus, exception utilisée). L’adaptateur n’envoie au fournisseur que les clés de son payload ; reference_context ne lui est pas transmis. Une reprise rapproche l’intention déjà préparée et conserve ses données, sans recompiler silencieusement un nouveau code depuis un catalogue actualisé. La purge de diagnostic prévue ne supprime pas les snapshots/projections nécessaires à la preuve métier. L’indisponibilité du central bloque un nouveau choix non vérifiable ; elle ne réécrit pas un ancien envoi. Aucun tarif, secret ou acheteur n’est remonté au central pour remplir ces références.

**Retour puis renvoi sans premier paiement :** le client refuse le premier contenu ou demande une modification sans le prendre ni payer. Le transporteur ramène le colis, puis le commerçant le récupère réellement et inspecte les quantités. Le retour annoncé ou reçu chez le transporteur ne suffit pas pour remettre du stock en boutique. La première commande, sa livraison et son retour restent intacts. Le commerçant crée une nouvelle commande liée de type 4 RESEND_UNPAID ; elle contient le produit finalement choisi à son prix entier et possède sa nouvelle livraison. Les coordonnées peuvent être reprises dans un nouveau snapshot ; prix, texte libre et articles modifiés sont revalidés après appel par un clic audité. Aucun envoi au client n’est ajouté.

**Coût réel du retour :** carrier_fees de la première livraison conserve le frais réel de service RETURN, son payeur, source et tarif/version local. Le montant confirmé peut être 0 si gratuit ou positif si payant. Un vrai retour gratuit conserve sa source/preuve de gratuité et son tarif snapshot, amount=0, avec zéro dette/cash ; aucune carrier_settlement_lines à 0 n’est créée pour simuler son paiement. Tant que le prix n’est pas connu, ne créer aucun carrier_fees effectif valorisé par défaut ; conserver l’anomalie/source à vérifier dans les faits du retour et l’audit, sans écrire un faux frais effectif de 0. Bloquer toute reconnaissance/rapprochement qui exigerait ce montant. Le premier retour payant est normalement supporté par le commerçant. Les tarifs négociés et le retour gratuit contractuel restent des données locales ; le catalogue central ne décide pas du prix.

**Ajout décidé par le commerçant :** le montant de livraison présenté pour le nouveau colis est customer_shipping_fee − shipping_discount + return_cost_recovery_amount. Le dernier terme est saisi manuellement, à 0 par défaut ; aucune règle, synchronisation ou tâche ne le calcule automatiquement. Le commerçant peut récupérer tout ou partie des frais du retour précédent en fonction du prix qu’il accepte de proposer, avec return_cost_recovery_reason requis si positif et référence au retour via orders.original_return_id. Il peut aussi modifier le prix de base de la nouvelle livraison comme auparavant. Le client confirme par téléphone le nouveau total entier ; une modification après confirmation exige une nouvelle révision/validation, et après figement distant suit le protocole de rapprochement. Une gratuité de livraison de base n’efface pas silencieusement un supplément manuel annoncé : afficher séparément cette décision avant validation.

**Prix, dette et cash distincts :** return_cost_recovery_amount est une composante de revenu de livraison du nouveau dossier, avec son traitement fiscal dans shipping_tax_snapshot ; ce n’est ni une retenue de frais client du transporteur ni un deuxième carrier_fees. L’ancien frais commerçant reste une charge unique, même s’il est payé plus tard. Le transporteur collecte le COD entier validé ; seule sa vraie retenue client entre dans Fclient. La part de récupération destinée au commerçant reste reversable et devient revenu effectivement conservé lors de la vente, sans modifier le coût réel ancien. Si un contrat impose une retenue différente, enregistrer le vrai frais du nouveau colis sous sa règle contrôlée, sans le confondre avec le supplément commercial. Aucun remboursement ni crédit ne naît du premier refus impayé.

**Quantités et concurrence :** verrouiller la commande source, sa livraison/recouvrement, le retour et ses lignes puis les variantes selon l’ordre commun. Le renvoi type 4 est préparé depuis toutes les lignes du colis d’origine, avec écarts/manquants constatés, et peut proposer une autre composition complète. Au MVP, au plus un enfant type 4 existe pour un retour donné : UNIQUE(original_return_id,unpaid_resend_slot), où unpaid_resend_slot est calculé en §6.8. Plusieurs modifications avant départ créent des révisions de cet enfant ; un nouveau refus de son colis crée son propre retour et un nouvel enfant, jamais un deuxième enfant du premier retour. Les unités reçues restent en quarantaine jusqu’à inspection ; aucune unité manquante n’est fabriquée et toute nouvelle confirmation exige du stock vendable réel. Un retour déjà utilisé pour un remplacement gratuit ou remède incompatible n’est pas réutilisé pour ce renvoi ; contrôler les allocations de tous les enfants/remèdes sous le même verrou. Le budget monétaire de remboursement reste limité à un encaissement réel, et le budget SAV Qr ne transforme pas un refus jamais payé en remboursement. Pas de nouvel état d’annulation/clôture commerciale ni libération de budget par un refus d’appel.

**Modification physique :** texte libre seul modifié → nouveau snapshot de demande, même variante si l’article reste identique. Si la taille/couleur ou l’identité physique change, utiliser une autre variante/SKU ; used_at et les anciennes compositions restent immuables. Une transformation réelle enregistrée en boutique est une paire de mouvements ADJUSTMENT idempotents sous la même correlation_id : sortie des unités sources vendables inspectées et entrée des unités réellement produites, avec quantités/coûts validés. Pas de reclassification silencieuse d’une variante utilisée, de double réception, de perte fictive ou de réservation sur la quarantaine. Le service verrouille toutes les variantes de la paire et conserve le motif/transformation dans les faits audités avant la nouvelle réservation.

**Cas documentaires et financiers**

| Situation | Représentation et règle |
|---|---|
| Premier colis refusé sans paiement | Ancien colis/retour conservés, encaissement réel 0 ; aucune compensation de vente payée inventée |
| Article initial 8 000, nouvel article 10 000, livraison 650, retour gratuit confirmé | Nouvelle vente entière 10 000 ; ajout manuel par défaut 0 ; COD 10 650 |
| Même renvoi, retour payant 300 laissé au commerçant | Ancienne charge 300 ; nouveau COD 10 650 ; frais non récupérés |
| Même renvoi, retour payant 300 récupéré entièrement par choix manuel | Ancienne charge 300 unique ; supplément livraison 300 ; COD 10 950, jamais 2 000 de différence |
| Retour 300 récupéré partiellement à hauteur de 100 | Ancienne charge 300 ; supplément décidé 100 ; COD 10 750 |
| Tarif du retour encore inconnu | Aucun frais effectif 0 présumé ; prix commercial du renvoi explicite, coût restant à rapprocher |
| Retour d’une vente réellement encaissée | Inspection, remboursement réel plafonné, avoir lié si facture émise ; ce n’est pas le type 4 impayé |
| Correction d’une unité / frais de livraison remboursés | customer_adjustments et pièces correctives avec plafonds par ligne/commande ; aucun retour physique partiel volontaire au MVP |
| Remplacement gratuit après incident | Type 2, quantités SAV engagées, produits à 0 ; frais de nouvelle livraison annoncés séparément ; aucune facture valorisée incompatible |
| Règle fiscale du remplacement exigeant une valeur différente de sa révision | Route bloquée jusqu’à définition/validation du traitement fiscal adapté ; aucune fausse facture ni crédit supposé |
| Facture déjà émise avant refus impayé | Corriger selon la règle validée via avoir lié ; ne pas effacer le PDF ni créer un remboursement sans paiement |
| Colis perdu / cassé chez transporteur | Incident et indemnisation distincts du remboursement, remplacement et éventuel renvoi |
| COD réellement encaissé, reversement en attente | Vente/document selon règle ; encaissement et créance commerçant séparés |

**Périmètre et historique :** le mécanisme d’échange payé avec affectation d’avoir est retiré au MVP, conformément au circuit décrit par le commerçant. Les remboursements de vrais paiements et corrections commerciales T14/T18/T23 restent possibles. Après expédition, l’ancienne révision n’est jamais modifiée ; chaque renvoi/remplacement possède une nouvelle commande liée avec UNIQUE(shipments.order_id). Avant départ, modification par nouvelle révision de la même commande et nouveau clic explicite. Si un retour est ouvert au MVP, toutes les lignes expédiées restent attendues, y compris les manquants. Aucune facture ne porte un montant incompatible avec sa révision. Les factures/avoirs et obligations restent historisés même en cas de retour, sans préjuger leur fait générateur fiscal.

Les commandes/révisions historiques, journaux de stock, réservations atomiques, manquants, frais réels, plafonds SAV et contrepassations exactes restent préservés. Aucun suivi ou envoi aux acheteurs, preuve de réception, annulation/clôture commerciale ou PDF d’accord téléphonique n’est réintroduit. Les envois nécessaires à l’accès des comptes propriétaire/employés restent prévus.

## 6. Contraintes à respecter avec le dessin

### T24.1 — Durées locales, absence de doublons et invitations

Le contrat de C2.1 est appliqué dans cette seule BDD, guard tenant, morph shop_user. Le nom public projeté est shop.shop_name ; first_name/last_name désignent uniquement la personne. Les nouveaux champs des pivots sont obligatoires ; aucun id/uuid de pivot n’est ajouté. Les permissions ordinaires du rôle expirent chacune à model_has_roles.assigned_at + role_has_permissions.duration_days jours UTC, intervalle [début,fin). À fin exacte, le droit est refusé même si les autres actions du rôle restent valides. Les droits directs natifs sont eux aussi datés et bornés à 9999 jours ; ils ne servent pas à passer devant un rôle. Une permission temporelle ne remplace jamais le plan, le quota ni la Policy de l’objet.

**Concurrence et quota :** toutes les écritures locales de rôles, compositions/durées, attributions/retraits/renouvellements, droits directs et invitations prennent d’abord shop.singleton=1 FOR UPDATE sur la connexion tenant. Ce même verrou sert déjà aux quotas d’équipe ; aucune nouvelle table de verrou ni ligne centrale n’est créée. Prendre ensuite les rôles par id, les comptes par id et les invitations par id ; relire les parents/version/compositions/attributions, contrôler garde, acteur, dates, signatures et recoupements, puis quota, écrire et auditer atomiquement. Une modification de rôle est refusée si elle ferait doubler une permission chez l’un de ses bénéficiaires. Les UNIQUE SQL de nom/signature et les PK ferment les courses ; les contrôles inter-lignes passent par le service et les protections d’un écrivain SQL autorisé. Une invitation est consommée dans cette transaction, pas avant. Pas d’e-mail/HTTP dans le verrou ; les intentions des seuls messages d’accès partent après commit. Le provisioning crée le singleton avant ces opérations, conserve sa date d’attribution au retry et reste distinct de la gestion d’équipe ordinaire.

**Contrôles d’accès :** le hook tenant du catalogue de capacités locales vérifie compte, membership_status/joined_at, tenant accessible, date actuelle et droit effectif, ou shop-owner protégé. Pour une capacité connue absente/expirée, rendre false sans laisser le contrôle natif non daté réautoriser. Désactiver le callback natif par register_permission_check_method=false et utiliser le résolveur daté dans les modèles/relations d’autorisation, Gates et middleware ; l’ordre des callbacks ne doit pas permettre un true natif avant le refus temporel. Les noms génériques des actions de Policy retournent null pour exécuter ses contrôles d’objet et de contexte. APIs, Livewire, exports, champs de coût/marge et jobs passent par ce même contrat ; chaque worker le réévalue à l’exécution. Les coûts/marges sont masqués dès que leur permission dédiée n’est plus valide, y compris dans JSON et exports. Le propriétaire reste soumis aux états de compte/tenant, aux fonctionnalités et quotas, aux objets de sa boutique et aux invariants métier ; aucun privilège central n’est reconnu localement.

**Dates, cache et historique :** réattribuer volontairement un rôle exige une intention auditable distincte ; un simple retry, une réactivation d’appartenance ou un login ne relance aucun délai. Modifier une durée dans le rôle garde le début des bénéficiaires existants ; pour une différence destinée à une personne seulement, utiliser un rôle distinct et remplacer l’attribution atomiquement. Relire attributions/dates courantes et permission_version à chaque décision sensible ; ne pas utiliser un ancien pivot chargé comme autorité. Invalider caches/relations après commit ; les caches expirent au plus tard à la prochaine fin et un cron arrêté ne conserve aucun droit. Audit local explicite des anciennes/nouvelles dates/durées, attribution, retrait, renouvellement, composition, invitation et refus de nom/composition/recoupement, avec acteur/motif et UUID filtrés. Ni un rôle ni une activité interne de boutique n’est copié au central.

**Retrait des exceptions :** permission_overrides n’existe plus dans le modèle actif local. Pour retirer une action à un employé, retirer/remplacer son attribution ou utiliser un rôle qui n’a pas cette action ; pour la limiter dans le temps, régler sa durée dans la composition choisie. Il n’existe plus de DENY individuel superposé à un rôle. Les blocages de compte/appartenance, quotas et états métier restent leurs contrôles propres. D’anciennes règles et décisions restent traçables dans les archives/audits ; une éventuelle migration doit retrouver un état équivalent sans élargir les droits, et signaler les cas incompatibles plutôt que fabriquer une date d’attribution.

**Cas d’acceptation locaux à implémenter :**

| Cas | Résultat attendu |
|---|---|
| Durée absente / 0 / 10000 | 9999 jours par défaut / refus / refus ; CHECK 1..9999 |
| Noms différents, mêmes permissions/durées dans un autre ordre | Création refusée par signature dans cette boutique |
| Même nom/composition dans boutiques A et B | Permis : deux définitions locales indépendantes, aucune attribution commune |
| Deux rôles locaux avec une permission commune | Attribution refusée, même si ce droit a déjà expiré dans l’un |
| Modification d’un rôle créant un recoupement pour un membre | Mutation entière refusée, sans version ni succès d’audit partiel |
| Invitation envoyée lundi, acceptée vendredi | Début des permissions vendredi ; délai du jeton séparé |
| Durée/composition du rôle changée après invitation | Version différente ; revalidation explicite avant acceptation |
| Invitation acceptée pour un compte au rôle déjà attribué | Date existante conservée, aucun renouvellement implicite |
| Rôle d’invitation recoupant un autre droit du compte | Acceptation entière refusée, jeton/place non consommés |
| Deux attributions concurrentes, permissions communes | Une seule peut être validée ; relecture après verrou shop |
| Droit expiré, cron arrêté, worker/API/export ou cache encore présent | Refus ; coût/marge non exposés si leur droit expire |
| Suspension puis réactivation ou retry du provisioning | Aucune date de début réinitialisée |
| Tentative de cumul ou attribution de shop-owner à un employé | Refus, propriété immuable |


### 6.8 Classifications, références communes et renvoi impayé — V4.8

**Dictionnaire de classement local :** UNIQUE(categories.id,record_type), UNIQUE(categories.record_type,slug) ; FK(categories.parent_id,parent_record_type) → categories(id,record_type), FK(products.category_id,category_record_type) → categories(id,record_type), FK(product_tags.tag_id,tag_record_type) → categories(id,record_type). parent_record_type=CASE WHEN parent_id IS NOT NULL THEN 1 ELSE NULL END ; category_record_type=CASE WHEN category_id IS NOT NULL THEN 1 ELSE NULL END ; tag_record_type=2, tous GENERATED ALWAYS AS (...) STORED. record_type IN (1,2), TAG impose parent_id NULL. Références RESTRICT, types/identité immuables, cycles/auto-parent contrôlés sous verrou par service/trigger avec les colonnes de base. Les helpers calculés ne remplacent pas les CHECK/triggers de forme ; UNIQUE(product_id,tag_id) conserve la relation multiple.

**Référentiel central C10 :** UNIQUE(geographic_areas.id,type), UNIQUE(geographic_areas.id,parent_id,type). FK(carrier_geo_mappings.geographic_area_id,zone_type) → geographic_areas(id,type). province_type=1 et municipality_type=CASE WHEN municipality_id IS NOT NULL THEN 2 ELSE NULL END STORED pour pickup_points. FK(pickup_points.province_id,province_type) → geographic_areas(id,type) ; FK(pickup_points.municipality_id,province_id,municipality_type) → geographic_areas(id,parent_id,type). province_id non NULL ; municipality_id facultatif, mais s’il existe son parent correspond exactement à la wilaya. Les FK simples restent présentes. RESTRICT pour ces liens et ceux au réseau ; pas de cascade/codes réaffectés ni de FK vers une BDD tenant. Index réseau/état/zone et UUID selon lectures. Les 27 tables centrales antérieures conservent tous leurs champs ; les clés composites ajoutées sur geographic_areas ne changent ni ses valeurs ni son modèle pays/wilaya/commune.

**Bureau central dans les colis locaux :** order_revisions.pickup_point_uuid et shipments.pickup_point_uuid ont exactement le même stockage UUID/collation ; FK locale(shipped_revision_id,order_id,pickup_point_uuid) → order_revisions(id,order_id,pickup_point_uuid), avec UNIQUE parent. Aucun FK local ne cible central.pickup_points ou un pickup_points local retiré. À domicile UUID et snapshot NULL ; stop desk UUID/snapshot requis. CHECK de mode et FK(shipped_revision_id,order_id,delivery_mode) empêchent qu’un NULL fasse disparaître la protection. Le service résout l’UUID central, contrôle réseau du compte, géographie et autorisation boutique avant la révision, puis conserve le choix exact pour l’envoi. Un changement de disponibilité/code du catalogue ne change pas le snapshot historique.

**Renvoi type 4 :** unpaid_resend_slot=CASE WHEN order_type=4 THEN 1 ELSE NULL END STORED ; UNIQUE(original_return_id,unpaid_resend_slot). order_type IN (1,2,4) ; type 4 exige original_order_id et original_return_id non NULL. FK locale(original_return_id,original_order_id) → order_returns(id,order_id), avec UNIQUE parent ; chaîne sans cycle, source différente et origine effectivement expédiée. Type 1 n’a pas d’origine ; type 2 garde ses contrôles incident/SAV. Le retour source ne s’efface pas et ne donne pas deux enfants impayés. La forme de la nouvelle commande, son encaissement source réellement nul, sa réception/inspection, ses quantités et son allocation aux remèdes sont contrôlés sous verrou. original_incident_id/quantity sont tous deux NULL en renvoi sans incident ; s’ils sont renseignés, leur appartenance/valeur reste contrôlée sans limiter le renvoi du colis à cette seule ligne d’incident.

**Prix :** DECIMAL(14,2), return_cost_recovery_amount NOT NULL DEFAULT 0, >=0 ; motif non vide si >0. Montant 0 hors type 4 vérifié par service/trigger contre le parent. customer_shipping_fee >=0 ; 0<=shipping_discount<=customer_shipping_fee ; order_total=applied_subtotal+customer_shipping_fee−shipping_discount+return_cost_recovery_amount ; amount_to_collect=order_total. Les révisions étant immuables, une modification de prix crée une autre révision entière. Aucun ancien crédit, prix différentiel, champ ou table d’affectation d’avoir n’entre dans ce calcul. La dette du retour ancien et le revenu récupéré sur le nouveau dossier restent des faits distincts.

Les restrictions MySQL 8.4 sur les FK de colonnes générées STORED, les actions référentielles et les CHECK restent celles du §6.7 : aucun CASCADE/SET NULL et aucun contrôle NEW/OLD d’une colonne générée. Contrôler la forme à partir des colonnes de base, avec vérifications transactionnelles quand un parent est nécessaire. [Documentation MySQL : colonnes générées](https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html), [clés étrangères](https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html), [CHECK](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html).

Les autres contraintes locales des sections 6.0–6.6 restent applicables : appartenance commande/révision/colis, page de vente/produit, axe/valeur/variante, réservations et plafonds financiers typés. Les documents T17/T20/T22 conservent leur règle et leurs preuves. Les champs de montant et les pièces après effet sont immuables ; les corrections utilisent un inverse exact puis l’écriture correcte, sans faux cash. Voir le schéma principal pour chaque clé composite et ordre de verrouillage.

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

**Retraits et correspondances :** tags est fusionnée dans categories ; attributes/product_attributes, visitor_preferences, processing_activity_register et exchange_offsets sont retirées du périmètre. Aucun remplacement caché pour préférences ou registre. La description et les champs directs conservés suffisent aux informations produit prévues ; les options vendables restent inchangées. Les anciennes carrier_geo_mappings/pickup_points locales deviennent des références au catalogue central et seules les exceptions réellement privées restent locales. Le code OrderTypeEnum 3 EXCHANGE et AmountKindEnum 4 EXCHANGE_DIFFERENCE sont retirés sans réattribution ; RESEND_UNPAID reçoit 4. V4.9 adapte identités et permissions dans les deux contextes ; les cinq tables de facturation SaaS et ses paiements/remboursements restent inchangés.

**Préparation de migration, aucune exécution ici :** analyser les données réellement présentes avant bascule. Pour tags → categories, conserver UUID/name/slug/dates/deleted_at avec record_type=2 ; les catégories antérieures reçoivent 1. Réallouer les PK numériques si elles entrent en collision, réécrire product_tags et les morphs tag selon une correspondance vérifiée, conserver les signatures/options/produits et imposer les FK typées. Aucun tag ne devient un produit. Pour références de livraison, rapprocher par réseau officiel/code externe/zone, pas par nom approximatif ; les divergences non vérifiées restent bloquées. Conserver les UUID centraux stables, traduire les anciens pickup_point_id locaux en pickup_point_uuid, et figer les anciens snapshots avant tout retrait. Les désactivations commerçant deviennent disabled_pickup_point_uuids ou ALLOWLIST ; ne pas fusionner les contrats/secrets/tarifs privés. Les comptes transporteur gardent leurs UUID et clés locales ; carrier_uuid provient du réseau identifié, aucune identité ne se devine.

Si des écritures de l’ancien échange payé existent, archiver/réconcilier leurs avoirs, remboursements, affectations et COD avant retrait ; ne jamais les convertir en impayé ni effacer leurs pièces/audits. Aucun crédit payé n’est simplement réduit à 0 et aucun ancien code d’enum n’est réutilisé. Les caractéristiques/preferences/registres déjà présents exigent une décision explicite de conservation documentaire/export si nécessaire avant une suppression physique future ; ce document ne lance aucune suppression. Les anciennes activités/morphs disposent d’une correspondance historique sans rendre les modèles retirés créables. Vérifier commandes/revisions/colis, unicités, budgets, médias/PDF, réservations et projections de stock après toute migration. Conserver les règles de contrepassation exactes, reçus privés, remboursements réels, manquants et plafonds issus des notes professionnelles.

**Sources de décision :** demandes confirmées dans la conversation, truc.txt, notes du dépôt, recherches Laravel/Spatie et historique complet disponible (37 commits jusqu’à 374bbac). Les notes historiques ne remplacent pas les derniers choix métier explicites. Les diagrammes et glossaires décrivent seulement la version active. Aucune migration/application n’est créée ou exécutée.

Les 37 commits jusqu’à 374bbac et les notes servent à préserver les contraintes déjà identifiées. Ce document décrit la version active ; les anciennes décisions incompatibles restent uniquement dans la traçabilité historique du schéma principal. Aucune donnée ni migration n’est exécutée.

### Traçabilité V4.9 — identités et permissions dans les deux contextes

Décisions confirmées : first_name/last_name pour les personnes centrales et locales, tenants.shop_name pour chaque boutique et shop.shop_name pour sa projection ; retrait de legal_name/tax_regime du profil central courant et des nouveaux formats de snapshot ; retrait de feature_overrides au central et de permission_overrides au central et dans chaque boutique. Les quotas viennent de l’offre et plan_features. Les cinq tables Spatie sont conservées dans chaque BDD ; admin_restrictions reste uniquement centrale. Durées par permission de rôle, 9999 jours par défaut et au maximum ; noms uniques ; compositions identiques en permissions/durées refusées ; plusieurs rôles par compte sans aucune permission commune. La fin est calculée depuis la date d’attribution propre à cette personne. Aucun registre d’exceptions caché ni nouvelle table n’est ajouté.

**Portée et compatibilité :** la dernière instruction étend les règles aux boutiques. La BDD centrale passe de 30 à 28 tables, 464 champs ; chaque boutique passe de 60 à 59 tables, 1005 champs. users, roles et les trois pivots sont adaptés dans chaque contexte ; shop.name devient shop.shop_name sans créer une seconde source publique. Les autres définitions sont conservées. Toutes les boutiques gardent le même schéma, mais leurs rôles/permissions/dates sont indépendants ; les noms/compositions identiques entre BDD sont permis. Les invitations commencent les durées à leur acceptation, conservent un début existant et refusent les recoupements. Identifiants/UUID, guards, audit, propriété, quotas, facturation, stock et référentiels publics gardent leurs protections. PermissionEffectEnum est retiré du catalogue actif ; OverrideStatusEnum reste utilisé par admin_restrictions.

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

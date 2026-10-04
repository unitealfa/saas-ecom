# Diagramme complet de la BDD boutique

Source : [Schema-BDD-SaaS-Ecommerce-UUID.md](Schema-BDD-SaaS-Ecommerce-UUID.md), version V4.6 du 4 octobre 2026. Chaque boutique possède cette même structure dans sa propre BDD. Ce document réunit **les 77 tables locales et leurs 1169 champs dans un seul diagramme Mermaid**, puis explique chaque table et chacun de ses champs. Il décrit une conception ; aucune migration n’est exécutée. La BDD centrale et son diagramme restent inchangés.

## 1. Les 77 tables expliquées très simplement

| Table | Explication très simple |
|---|---|
| **`shop`** | La fiche publique de la boutique : son nom affiché, son logo, ses contacts et sa présentation. Exemple : les informations que les visiteurs voient sur le site de Karim. |
| **`shop_addresses`** | Les adresses publiques de la boutique et leur emplacement sur une carte. Exemple : une adresse pour le magasin et une autre pour un point de retrait. Cela n’ajoute pas une caisse de magasin. |
| **`social_links`** | Les liens vers les pages de la boutique sur les réseaux sociaux. Exemple : son compte Instagram et deux pages Facebook différentes. |
| **`content_pages`** | Le contenu des pages d’information du site. Exemple : Karim écrit le texte de « À propos », de « Contact » ou de sa politique de retour. |
| **`media`** | Les informations permettant de retrouver les fichiers de la boutique : images, vidéos, logos ou documents. Exemple : l’emplacement et le type de la photo d’un produit ; le fichier lui-même est stocké séparément. |
| **`categories`** | Les familles de produits et leurs sous-familles. Exemple : « Vêtements » contient « T-shirts ». Une seule table permet d’organiser les deux niveaux. |
| **`products`** | La présentation commune d’un produit : son nom, sa description et les informations partagées par ses versions. Exemple : le modèle « T-shirt coton », proposé ensuite en plusieurs tailles et couleurs. |
| **`product_variants`** | Les versions précises que l’on peut acheter, avec leur prix et leur stock. Exemple : « T-shirt rouge, taille M ». Un produit sans choix possède aussi une variante standard. |
| **`product_options`** | Les types de choix proposés pour un produit. Exemple : pour un t-shirt, le client peut choisir une taille et une couleur. |
| **`option_values`** | Les choix disponibles pour chaque option. Exemple : M et L pour la taille ; rouge et bleu pour la couleur. |
| **`variant_option_values`** | Indique les choix qui composent chaque variante. Exemple : cette variante correspond à la taille M et à la couleur rouge. |
| **`tags`** | Les petits mots utilisés pour classer ou mettre en avant les produits. Exemple : « Été » ou « Idée cadeau ». |
| **`product_tags`** | Indique quelles étiquettes sont attachées à chaque produit. Exemple : le même t-shirt peut porter les étiquettes « Été » et « Idée cadeau ». |
| **`attributes`** | La liste des informations servant à décrire les produits. Exemple : le poids ou le pays de fabrication ; ce ne sont pas forcément des choix proposés à l’achat. |
| **`product_attributes`** | La valeur d’une caractéristique pour un produit précis. Exemple : le poids de ce pot de miel est de 500 grammes. |
| **`sales_pages`** | Les pages qui présentent un seul produit pour donner envie de le commander. Exemple : une page partageable avec les avantages d’un produit, ses images et son formulaire de commande. |
| **`product_promotions`** | Les réductions appliquées automatiquement aux produits, sans code à saisir. Exemple : une réduction sur un produit pendant une période choisie. |
| **`product_reviews`** | Les notes et commentaires laissés sur les produits, même sans compte acheteur. Exemple : un client écrit « Très bon produit » ; la boutique décide ensuite de publier ou de masquer cet avis. |
| **`visitors`** | Un identifiant pour reconnaître un navigateur dans cette boutique, sans créer de compte acheteur. Exemple : reconnaître le même navigateur lors d’un retour sur le site, sans garantir qu’il s’agit de la même personne. |
| **`visit_sessions`** | Les différentes visites d’un navigateur sur la boutique. Exemple : une visite le matin puis une autre le soir peuvent former deux sessions pour le même visiteur. |
| **`navigation_events`** | Les actions minimales servant aux statistiques globales : page ouverte, produit vu ou ajout au panier. Aucun écran de parcours individuel n’est prévu. |
| **`visitor_preferences`** | Les choix du visiteur concernant la mesure de sa navigation. Exemple : refuser cette mesure tout en continuant à utiliser le panier et à commander. |
| **`carts`** | Les paniers conservés par le site pour les acheteurs invités. Exemple : un visiteur ajoute deux produits avant de renseigner ses coordonnées ; cela ne réserve pas encore le stock. |
| **`cart_items`** | Le contenu détaillé de chaque panier. Exemple : deux t-shirts rouges taille M, avec une éventuelle personnalisation, forment une ligne du panier. |
| **`orders`** | La fiche principale de chaque commande, avec son identité et son état commercial. Exemple : la commande de Karim reste la même commande même si son contenu est modifié avant expédition. |
| **`order_revisions`** | Les copies successives du contenu d’une commande à chaque modification. Exemple : la première version contient une taille M ; une nouvelle version contient une taille L, sans effacer l’ancienne. |
| **`order_items`** | Les produits et quantités d’une version précise de commande, avec les prix et coûts conservés à ce moment-là. Exemple : deux t-shirts à 2 000 DA chacun, même si le prix du catalogue change ensuite. |
| **`order_history`** | Le carnet des appels, rappels, propositions et changements concernant une commande. Exemple : noter un appel sans réponse ou un changement de taille ; le clic « Valider » est audité séparément dans `activity_log`. |
| **`stock_reservations`** | Les quantités mises de côté au clic « Valider » du commerçant après son appel. Exemple : réserver deux t-shirts ; leur sortie physique est enregistrée lors de la remise du colis au transporteur. |
| **`stock_movements`** | Le carnet de tous les changements de stock. Exemple : recevoir dix articles, en réserver deux, les expédier ou constater une perte, en gardant l’explication de chaque changement. |
| **`order_returns`** | Les dossiers des colis qui reviennent à la boutique. Exemple : un client refuse son colis. Au lancement, le retour porte sur tout le colis ; la structure permet une évolution future. |
| **`return_items`** | Le détail de ce qui est attendu et constaté dans un retour. Exemple : sur trois articles attendus, deux sont reçus et un manque ; les articles reçus peuvent être revendables ou abîmés. |
| **`shipping_providers`** | Les personnes ou sociétés qui livrent pour la boutique. Exemple : un livreur interne ou EcoTrack, avec le suivi de l’argent qu’ils doivent reverser. |
| **`customer_shipping_rates`** | Le prix de livraison demandé à l’acheteur. Exemple : le client paie 600 DA pour une livraison dans une zone donnée ; ce prix peut différer du coût payé au transporteur. |
| **`provider_rates`** | Le coût estimé de la livraison pour la boutique selon la zone et le mode choisi. Exemple : estimer le coût d’une livraison à domicile. Les tarifs de retour des comptes société sont versionnés dans carrier_rate_versions de cette boutique (T25). |
| **`free_shipping_rules`** | Les conditions qui rendent automatiquement la livraison gratuite pour le client. Exemple : offrir la livraison lorsque la commande remplit la règle définie par la boutique. |
| **`carrier_geo_mappings`** | Relie les wilayas et communes du SaaS aux noms ou codes utilisés par chaque transporteur. Exemple : traduire une commune choisie sur le site en code reconnu par EcoTrack. |
| **`pickup_points`** | Les bureaux du transporteur où le client peut retirer son colis. Exemple : choisir un stop desk au lieu d’une livraison à domicile. |
| **`shipments`** | Les colis : commande et version expédiée, livreur, frais, suivi interne et dates. On accepte la déclaration de livraison du livreur, sans preuve de réception client. |
| **`shipment_events`** | Les étapes reçues pendant le transport, avec le message original du transporteur. Exemple : « en livraison », puis « livré » ou « refusé ». |
| **`carrier_operations`** | Les demandes à envoyer au transporteur, conservées pour pouvoir les suivre et les reprendre. Exemple : demander la création d’un colis sans créer un deuxième colis si la réponse est incertaine. |
| **`carrier_operation_attempts`** | Le résultat de chaque essai de communication avec le transporteur. Exemple : le premier essai échoue ; une nouvelle tentative est enregistrée séparément sans effacer la précédente. |
| **`collections`** | Le suivi de l’argent lié à un colis : ce qui doit être encaissé et reversé. Exemple : le client a payé le livreur, mais le commerçant attend encore son argent. |
| **`remittance_statements`** | Les documents de suivi d’un règlement avec le livreur ou le transporteur pour cette boutique. Exemple : expliquer le montant reçu en distinguant ventes, frais et indemnisations. |
| **`remittance_lines`** | Le détail d’un reversement pour chaque colis. Exemple : préciser que 4 000 DA du versement concernent le colis A et 6 000 DA le colis B, sans les compter deux fois. |
| **`expenses`** | Les autres dépenses réelles de la boutique, hors frais transporteur et pertes de stock déjà suivis ailleurs. Exemple : publicité, emballages ou frais généraux. |
| **`customer_adjustments`** | Le suivi des remboursements aux acheteurs, avec leurs montants, motifs et états. Exemple : enregistrer un remboursement réellement effectué, sans créer de portefeuille client. |
| **`order_documents`** | Les bons de commande facultatifs correspondant à une version précise de la commande. Exemple : conserver un document indiquant exactement les articles et les prix de cette version. |
| **`activity_log`** | Le carnet de la boutique : qui a fait quoi, quand et sur quel élément. Il garde aussi la validation par clic et les opérations sensibles sur les données, sans seconde table de journal. |
| **`carrier_fees`** | Les frais liés au transport et la personne qui doit les payer. Exemple : des frais de retour à la charge du commerçant, distincts de la livraison payée par l’acheteur. |
| **`carrier_fee_payments`** | Indique comment les frais dus par le commerçant au transporteur sont réglés. Exemple : un frais de retour est déduit d’un reversement ou payé séparément, sans compter une deuxième dépense. |
| **`carrier_receivables`** | Les sommes que le transporteur doit rendre après correction de frais déjà payés. Exemple : 650 DA ont été payés au lieu de 600 DA ; le transporteur doit encore 50 DA au commerçant. |
| **`carrier_receivable_allocations`** | Indique comment le transporteur règle les sommes qu’il doit après une correction de frais. Exemple : les 50 DA dus sont remboursés ou déduits d’un prochain frais. |
| **`collection_entries`** | Les montants réellement encaissés auprès du client et vérifiés, avec leurs éventuelles corrections. Exemple : confirmer que le livreur a reçu 5 000 DA ; cela ne prouve pas encore leur reversement au commerçant. |
| **`carrier_compensations`** | Les dédommagements du transporteur pour un problème comme une perte ou une casse. Exemple : un montant versé au commerçant pour un colis perdu, séparé de l’argent payé par le client. |
| **`invoices`** | Les factures de vente de la boutique et les avoirs qui les corrigent, avec leur contenu historique conservé. Exemple : garder la facture d’origine puis créer un avoir si son montant doit être réduit. |
| **`order_incidents`** | Le dossier d’un problème concernant une ligne de produits expédiée et les limites de sa prise en charge. Exemple : un article cassé pour lequel on examine un remplacement ou un remboursement. |
| **`order_incident_details`** | Les différents problèmes et quantités dans un dossier d’incident. Exemple : sur trois articles, un est cassé, un manque et le troisième est correct ; on ne compte pas deux fois le même article. |
| **`billing_rules`** | Les réglages de facturation dans une seule table : une ligne type 1 compte les numéros ; une ligne type 2 définit une règle validée de production des documents. |
| **`sales_terms_acceptances`** | Les conditions de vente réellement acceptées pour une version précise de commande. Cela ne crée aucun contrat ni PDF d’accord téléphonique. |
| **`billing_obligations`** | Les factures ou avoirs que le système doit produire après un événement prévu par une règle validée. Exemple : garder une facture à émettre dans la liste jusqu’à ce que son émission réussisse. |
| **`exchange_offsets`** | La part d’un avoir utilisée pour payer une nouvelle commande d’échange précise. Exemple : affecter 8 000 DA à un échange coûtant 10 000 DA, avec 2 000 DA de produits restant à payer, sans portefeuille client. |
| **`commercial_corrections`** | Les décisions qui corrigent les montants des ventes, avec la date où elles comptent dans les statistiques. Exemple : enregistrer une réduction après un retour, séparément du retour physique et du remboursement réel. |
| **`commercial_correction_lines`** | Le détail d’une correction commerciale pour chaque ligne de produits concernée. Exemple : retirer 2 000 DA de ventes pour un article et indiquer aussi la correction de son coût dans les résultats. |
| **`users`** | Les comptes du propriétaire et des employés de cette boutique : nom, e-mail, mot de passe protégé, état du compte et droit d’entrer dans l’équipe. Chaque boutique garde ses propres comptes. |
| **`permissions`** | La liste des actions qu’une personne peut être autorisée à faire dans cette boutique : créer un produit, valider une commande ou inviter un employé. |
| **`roles`** | Les groupes d’autorisations de la boutique. Exemple : le rôle de préparateur réunit les actions nécessaires pour préparer les colis. |
| **`role_has_permissions`** | Indique quelles actions sont autorisées pour chaque rôle. |
| **`model_has_roles`** | Indique quel compte possède quel rôle dans cette boutique. |
| **`model_has_permissions`** | Donne une autorisation directement à un compte de cette boutique. |
| **`permission_overrides`** | Les autorisations ou interdictions exceptionnelles accordées à un compte, avec leur motif et leurs dates. |
| **`team_invitations`** | Les invitations permettant à un employé de rejoindre cette boutique avec un rôle précis et un lien secret qui expire. |
| **`contact_verifications`** | Les codes protégés utilisés pour vérifier les contacts des comptes du propriétaire et des employés ; les acheteurs ne reçoivent aucun message. |
| **`carrier_accounts`** | Les connexions de cette boutique aux services de livraison : compte transporteur, adresse API et secrets protégés. |
| **`carrier_rate_versions`** | Les tarifs de retour annoncés par un compte transporteur, avec leur période d’application. Un changement crée une autre version. |
| **`carrier_remittance_batches`** | Les lots de versements annoncés par un compte transporteur, leur justificatif et la part vérifiée pour cette boutique. |
| **`processing_activity_register`** | Le registre qui explique pourquoi la boutique utilise des données, lesquelles, avec qui et comment elle les protège. Les actions réellement faites sont dans activity_log. |

## 2. Un seul diagramme pour toute la boutique

**Types du dessin :** u64 = BIGINT UNSIGNED ; u8 = TINYINT UNSIGNED ; « ? » = nullable. Les types complets, enums et phases restent définis dans le schéma principal.

**Lecture :** PK = clé primaire ; FK = lien SQL vers une table de cette boutique ; UK = unicité ; REF = référence logique vers la BDD centrale. Dans chaque table : identifiants d’abord, clés et liens ensuite, autres champs après. Les trois pivots Spatie conservent leurs clés composites sans inventer id ou uuid. Les 220 liens FK du dessin reprennent toutes les colonnes marquées FK. Les 90 liens POLY sont conditionnels et n’inventent aucune FK SQL. Un trait plein concerne une colonne de clé primaire ; les autres traits sont pointillés. Un parent peut être obligatoire ou facultatif, et plusieurs enfants sont possibles sauf les liens uniques indiqués.

Le dessin contient uniquement les 77 tables locales. Les UUID centraux sont visibles dans les champs REF ; ils ne recopient aucune table centrale et n’autorisent aucune jointure d’identités. Les contraintes composites et les règles de phase restent obligatoires même lorsque le dessin montre chaque colonne séparément : voir §6 et les modules T8–T26 du schéma principal.

Les pivots et causer_id ne ciblent que les comptes shop_user autorisés de cette boutique. subject_id cible un modèle local explicitement autorisé ; les traits « si modèle autorisé » décrivent ce choix sans autoriser automatiquement toutes les tables. Les événements d’attribution de pivots prennent pour sujet un compte ou rôle et décrivent le lien dans des propriétés filtrées. model_id des médias utilise un seul parent local autorisé, y compris produit/variante pour les galeries ; les FK de pièce précise restent distinctes de ce lien. Un inverse peut référencer la pièce de son original sous les contrôles du schéma, sans en déplacer le parent.

```mermaid
erDiagram
    direction LR

shop {
  u64 id PK
  uuid uuid UK
  uuid tenant_uuid "REF central.tenants.uuid"
  u64 logo_media_id FK "?"
  u64 favicon_media_id FK "?"
  tinyint singleton UK
  bigint central_profile_version
  varchar name
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
  u64 id PK
  uuid uuid UK
  u64 shop_id FK
  uuid province_uuid "REF central.geographic_areas.uuid"
  uuid municipality_uuid "REF central.geographic_areas.uuid"
  varchar label
  text address
  varchar postal_code "?"
  decimal_geo latitude "?"
  decimal_geo longitude "?"
  varchar map_url "?"
  varchar phone "?"
  json opening_hours "?"
  boolean is_primary
  boolean visible
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

social_links {
  u64 id PK
  uuid uuid UK
  u64 shop_id FK
  u64 shop_address_id FK "?"
  varchar network
  varchar label "?"
  varchar url
  int position
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

content_pages {
  u64 id PK
  uuid uuid UK
  varchar slug
  varchar type
  varchar title
  json content
  varchar meta_title "?"
  text meta_description "?"
  boolean indexable
  boolean is_published
  datetime published_at "?"
  int version
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

media {
  u64 id PK
  uuid uuid UK
  u64 model_id
  u64 created_by_id FK "?"
  varchar(64) model_type
  varchar(64) collection_name
  varchar(64) disk
  varchar storage_key UK
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
  u64 id PK
  uuid uuid UK
  u64 parent_id FK "?"
  u64 media_id FK "?"
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
  u64 id PK
  uuid uuid UK
  u64 category_id FK "?"
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
  u64 id PK
  uuid uuid UK
  u64 product_id FK
  varchar label
  varchar sku
  varchar barcode "?"
  varchar combination_signature
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
  u64 id PK
  uuid uuid UK
  u64 product_id FK
  varchar name
  u8 display_type
  int position
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

option_values {
  u64 id PK
  uuid uuid UK
  u64 option_id FK
  varchar identity_code
  varchar value
  char(7) color_hex "?"
  int position
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

variant_option_values {
  u64 id PK
  uuid uuid UK
  u64 product_id FK
  u64 variant_id FK
  u64 option_id FK
  u64 value_id FK
  datetime created_at
  datetime updated_at
}

tags {
  u64 id PK
  uuid uuid UK
  varchar name
  varchar slug
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

product_tags {
  u64 id PK
  uuid uuid UK
  u64 product_id FK
  u64 tag_id FK
  datetime created_at
  datetime updated_at
}

attributes {
  u64 id PK
  uuid uuid UK
  varchar name
  varchar group_name "?"
  u8 value_type
  varchar unit "?"
  text explanation "?"
  int position
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

product_attributes {
  u64 id PK
  uuid uuid UK
  u64 product_id FK
  u64 attribute_id FK
  text text_value "?"
  decimal numeric_value "?"
  datetime created_at
  datetime updated_at
}

sales_pages {
  u64 id PK
  uuid uuid UK
  u64 product_id FK
  varchar slug
  varchar title
  json content
  varchar meta_title "?"
  text meta_description "?"
  varchar canonical_url "?"
  boolean indexable
  boolean is_published
  datetime published_at "?"
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

product_promotions {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  varchar token_hash
  datetime first_visited_at
  datetime last_visited_at
  datetime expires_at
  datetime created_at
  datetime updated_at
}

visit_sessions {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  u64 session_id FK
  u64 product_id FK "?"
  u64 variant_id FK "?"
  u64 sales_page_id FK "?"
  u64 content_page_id FK "?"
  u64 cart_id FK "?"
  varchar type
  varchar path
  int quantity "?"
  datetime occurred_at
  datetime received_at
  datetime created_at
}

visitor_preferences {
  u64 id PK
  uuid uuid UK
  u64 visitor_id FK
  boolean allows_analytics
  varchar notice_version
  datetime chosen_at
  datetime created_at
}

carts {
  u64 id PK
  uuid uuid UK
  u64 visitor_id FK
  u8 status
  datetime last_activity_at
  datetime expires_at
  datetime converted_at "?"
  datetime created_at
  datetime updated_at
}

cart_items {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 author_id FK "?"
  u64 pickup_point_id FK "?"
  u64 free_shipping_rule_id FK "?"
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
  decimal exchange_offset_amount
  decimal amount_to_collect
  text customer_note "?"
  varchar sales_terms_version
  json sales_terms_snapshot
  datetime created_at
}

order_items {
  u64 id PK
  uuid uuid UK
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
  datetime created_at
}

order_history {
  u64 id PK
  uuid uuid UK
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

stock_reservations {
  u64 id PK
  uuid uuid UK
  u64 order_item_id FK
  int quantity
  u8 status
  datetime reserved_at
  datetime released_at "?"
  datetime created_at
  datetime updated_at
}

stock_movements {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  u64 user_id FK "?"
  u64 carrier_account_id FK "?"
  u8 type
  varchar name
  varchar phone "?"
  varchar email "?"
  varchar carrier_code "?"
  datetime last_synced_at "?"
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

customer_shipping_rates {
  u64 id PK
  uuid uuid UK
  uuid province_uuid "REF central.geographic_areas.uuid"
  uuid municipality_uuid "? ; REF central.geographic_areas.uuid"
  u8 delivery_mode
  decimal amount
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

provider_rates {
  u64 id PK
  uuid uuid UK
  u64 provider_id FK
  uuid province_uuid "REF central.geographic_areas.uuid"
  uuid municipality_uuid "? ; REF central.geographic_areas.uuid"
  u8 delivery_mode
  u8 service_type
  decimal amount
  u8 source
  datetime retrieved_at
  boolean is_active
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

free_shipping_rules {
  u64 id PK
  uuid uuid UK
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

carrier_geo_mappings {
  u64 id PK
  uuid uuid UK
  u64 provider_id FK
  uuid province_uuid "REF central.geographic_areas.uuid"
  uuid municipality_uuid "? ; REF central.geographic_areas.uuid"
  u8 zone_type
  varchar external_code
  varchar external_name
  varchar external_province_code
  varchar verification_source
  datetime verified_at "?"
  varchar mapping_version
  boolean is_active
  datetime synced_at
  datetime created_at
  datetime updated_at
}

pickup_points {
  u64 id PK
  uuid uuid UK
  u64 provider_id FK
  uuid province_uuid "REF central.geographic_areas.uuid"
  uuid municipality_uuid "? ; REF central.geographic_areas.uuid"
  varchar external_code
  varchar name
  text address
  varchar phone "?"
  varchar map_url "?"
  boolean is_carrier_active
  boolean is_shop_active
  datetime synced_at
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

shipments {
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 shipped_revision_id FK
  u64 provider_id FK
  u64 pickup_point_id FK "?"
  u64 label_media_id FK "?"
  u64 assigned_by_id FK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
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

remittance_lines {
  u64 id PK
  uuid uuid UK
  u64 remittance_statement_id FK
  u64 collection_id FK
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  decimal remitted_amount
  varchar operation_key
  datetime created_at
}

expenses {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 return_id FK "?"
  u64 incident_id FK
  u64 credit_note_id FK "?"
  u64 exchange_order_id FK "?"
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
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  u64 subject_id "?"
  u64 causer_id "?"
  varchar(64) log_name "?"
  text description
  varchar(64) subject_type "?"
  varchar(100) event "?"
  varchar(64) causer_type "?"
  json attribute_changes "?"
  json properties "?"
  varchar(191) operation_key UK "?"
  uuid correlation_id "?"
  u8 origin
  datetime performed_at "?"
  datetime created_at
  datetime updated_at
}

carrier_fees {
  u64 id PK
  uuid uuid UK
  u64 shipment_id FK
  u64 provider_id FK
  u64 return_id FK "?"
  u64 carrier_account_id FK "?"
  u64 source_rate_id FK "?"
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  json rate_snapshot "?"
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

carrier_fee_payments {
  u64 id PK
  uuid uuid UK
  u64 remittance_statement_id FK
  u64 carrier_fee_id FK
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  decimal amount
  u8 mode
  varchar operation_key
  datetime created_at
}

carrier_receivables {
  u64 id PK
  uuid uuid UK
  u64 provider_id FK
  u64 carrier_fee_id FK
  u64 original_fee_payment_id FK "?"
  u64 reversal_of_id FK "?"
  decimal initial_amount
  decimal remaining_amount
  varchar reason
  u8 status
  varchar operation_key
  datetime recognized_at
  datetime settled_at "?"
  datetime created_at
  datetime updated_at
}

carrier_receivable_allocations {
  u64 id PK
  uuid uuid UK
  u64 receivable_id FK
  u64 remittance_statement_id FK "?"
  u64 carrier_fee_id FK "?"
  u64 reversal_of_id FK "?"
  u8 settlement_type
  decimal amount
  varchar external_reference "?"
  varchar operation_key
  datetime performed_at
  datetime created_at
}

collection_entries {
  u64 id PK
  uuid uuid UK
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

carrier_compensations {
  u64 id PK
  uuid uuid UK
  u64 remittance_statement_id FK
  u64 shipment_id FK
  u64 replacement_order_id FK "?"
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 correction_of_id FK "?"
  decimal amount
  varchar reason
  varchar external_reference
  varchar operation_key
  datetime created_at
}

invoices {
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 revision_id FK
  u64 original_invoice_id FK "?"
  u64 sequence_id FK "?"
  u64 media_id FK "?"
  u64 issued_by_id FK "?"
  u64 incident_id FK "?"
  u8 document_type
  u8 sequence_record_type
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
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 shipment_id FK
  u64 shipped_revision_id FK
  u64 order_item_id FK,UK
  u64 return_id FK "?"
  u64 opened_by_id FK "?"
  u64 validated_by_id FK "?"
  int affected_quantity
  decimal eligible_product_amount
  decimal eligible_shipping_amount
  u8 status
  varchar operation_key UK
  text reason
  datetime validated_at "?"
  datetime closed_at "?"
  datetime created_at
  datetime updated_at
}

order_incident_details {
  u64 id PK
  uuid uuid UK
  u64 incident_id FK
  u64 author_id FK "?"
  u8 type
  int quantity
  text reason
  datetime created_at
  datetime updated_at
}

billing_rules {
  u64 id PK
  uuid uuid UK
  u64 validated_by_id FK "?"
  u8 record_type
  u8 document_type "?"
  int fiscal_year "?"
  varchar(32) shop_prefix "?"
  bigint next_number "?"
  u8 sequence_slot
  varchar(100) code "?"
  int version "?"
  u64 seller_profile_version "?"
  varchar trigger_event "?"
  varchar exchange_rule "?"
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
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 revision_id FK
  varchar sales_terms_version
  char(64) terms_hash
  datetime accepted_at
  u8 acceptance_mode
  json sanitized_proof "?"
  varchar operation_key UK
  datetime created_at
}

billing_obligations {
  u64 id PK
  uuid uuid UK
  u64 event_id
  u64 order_id FK
  u64 revision_id FK
  u64 billing_rule_id FK
  u64 original_invoice_id FK "?"
  u64 invoice_id FK "?"
  u8 billing_rule_record_type
  json rule_snapshot
  varchar event_type
  datetime triggered_at
  u8 document_type
  u8 status
  varchar operation_key UK
  int attempts_count
  datetime next_attempt_at "?"
  varchar error_code "?"
  datetime created_at
  datetime updated_at
}

exchange_offsets {
  u64 id PK
  uuid uuid UK
  u64 incident_id FK
  u64 original_order_id FK
  u64 original_credit_note_id FK
  u64 destination_order_id FK
  u64 destination_revision_id FK
  u64 reversal_of_id FK "?"
  decimal amount
  u8 status
  varchar operation_key UK
  datetime performed_at "?"
  datetime created_at
}

commercial_corrections {
  u64 id PK
  uuid uuid UK
  u64 order_id FK
  u64 source_revision_id FK
  u64 incident_id FK "?"
  u64 correction_of_id FK "?"
  u64 actor_id FK "?"
  u8 correction_type
  u8 status
  decimal non_product_revenue_delta
  u8 non_product_kind
  datetime effective_at
  datetime recorded_at
  text reason
  varchar operation_key UK
  datetime created_at
}

commercial_correction_lines {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  uuid central_user_uuid UK "? ; REF central.users.uuid"
  varchar name
  varchar first_name "?"
  varchar email UK
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
  u64 id PK
  uuid uuid UK
  varchar(125) name
  varchar(32) guard_name
  varchar label
  varchar(100) feature_code "?"
  datetime created_at
  datetime updated_at
}

roles {
  u64 id PK
  uuid uuid UK
  varchar(125) name
  varchar(32) guard_name
  varchar label
  boolean is_system
  boolean is_protected
  boolean is_super_admin
  u8 super_admin_slot UK "?"
  u64 permission_version
  datetime created_at
  datetime updated_at
}

role_has_permissions {
  u64 permission_id PK,FK
  u64 role_id PK,FK
}

model_has_roles {
  u64 role_id PK,FK
  varchar(64) model_type PK
  u64 model_id PK
}

model_has_permissions {
  u64 permission_id PK,FK
  varchar(64) model_type PK
  u64 model_id PK
}

permission_overrides {
  u64 id PK
  uuid uuid UK
  u64 user_id FK
  u64 permission_id FK
  u64 assigned_by_id FK
  u8 effect
  u8 status
  datetime started_at
  datetime ended_at "?"
  u8 active_slot "?"
  datetime expires_at "?"
  text reason "?"
  datetime created_at
  datetime updated_at
  datetime deleted_at "?"
}

team_invitations {
  u64 id PK
  uuid uuid UK
  u64 initial_role_id FK
  u64 invited_by_id FK
  varchar email
  varchar token_hash UK
  u64 role_permission_version
  datetime expires_at
  datetime accepted_at "?"
  datetime revoked_at "?"
  datetime created_at
  datetime updated_at
}

contact_verifications {
  u64 id PK
  uuid uuid UK
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
  u64 id PK
  uuid uuid UK
  u64 created_by_id FK
  varchar carrier
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

carrier_rate_versions {
  u64 id PK
  uuid uuid UK
  u64 carrier_account_id FK
  u64 created_by_id FK
  decimal return_rate
  datetime starts_at
  datetime ends_at "?"
  boolean is_active
  u8 source
  datetime created_at
}

carrier_remittance_batches {
  u64 id PK
  uuid uuid UK
  u64 carrier_account_id FK
  u64 proof_media_id FK "?"
  u64 reversal_of_id FK "?"
  u64 validated_by_id FK "?"
  varchar external_reference "?"
  decimal reported_account_net_amount "?"
  decimal computed_shop_net_amount
  decimal verified_net_amount "?"
  u8 status
  datetime received_at "?"
  varchar operation_key UK
  datetime created_at
  datetime updated_at
}

processing_activity_register {
  u64 id PK
  uuid uuid UK
  u64 validated_by_id FK "?"
  varchar code
  int version
  text purpose
  json data_subject_categories
  json data_categories
  json recipients
  text processing_basis
  text controller
  text processors
  json retention_rules
  json security_measures
  u8 status
  text validation_reference "?"
  datetime validated_at "?"
  datetime effective_at "?"
  datetime created_at
}

media |o..o{ shop : "FK logo_media_id"
media |o..o{ shop : "FK favicon_media_id"
shop ||..o{ shop_addresses : "FK shop_id"
shop ||..o{ social_links : "FK shop_id"
shop_addresses |o..o{ social_links : "FK shop_address_id"
users |o..o{ media : "FK created_by_id"
categories |o..o{ categories : "FK parent_id"
media |o..o{ categories : "FK media_id"
categories |o..o{ products : "FK category_id"
products ||..o{ product_variants : "FK product_id"
products ||..o{ product_options : "FK product_id"
product_options ||..o{ option_values : "FK option_id"
products ||..o{ variant_option_values : "FK product_id"
product_variants ||..o{ variant_option_values : "FK variant_id"
product_options ||..o{ variant_option_values : "FK option_id"
option_values ||..o{ variant_option_values : "FK value_id"
products ||..o{ product_tags : "FK product_id"
tags ||..o{ product_tags : "FK tag_id"
products ||..o{ product_attributes : "FK product_id"
attributes ||..o{ product_attributes : "FK attribute_id"
products ||..o{ sales_pages : "FK product_id"
products ||..o{ product_promotions : "FK product_id"
product_variants |o..o{ product_promotions : "FK variant_id"
sales_pages |o..o{ product_promotions : "FK sales_page_id"
products ||..o{ product_reviews : "FK product_id"
visitors |o..o{ product_reviews : "FK visitor_id"
order_items |o..o{ product_reviews : "FK order_item_id"
users |o..o{ product_reviews : "FK moderated_by_id"
visitors ||..o{ visit_sessions : "FK visitor_id"
visit_sessions ||..o{ navigation_events : "FK session_id"
products |o..o{ navigation_events : "FK product_id"
product_variants |o..o{ navigation_events : "FK variant_id"
sales_pages |o..o{ navigation_events : "FK sales_page_id"
content_pages |o..o{ navigation_events : "FK content_page_id"
carts |o..o{ navigation_events : "FK cart_id"
visitors ||..o{ visitor_preferences : "FK visitor_id"
visitors ||..o{ carts : "FK visitor_id"
carts ||..o{ cart_items : "FK cart_id"
product_variants ||..o{ cart_items : "FK variant_id"
products ||..o{ cart_items : "FK product_id"
sales_pages |o..o{ cart_items : "FK sales_page_id"
visitors |o..o{ orders : "FK visitor_id"
carts |o..o{ orders : "FK cart_id"
visit_sessions |o..o{ orders : "FK original_session_id"
sales_pages |o..o{ orders : "FK original_sales_page_id"
order_returns |o..o{ orders : "FK original_return_id"
orders |o..o{ orders : "FK original_order_id"
order_incidents |o..o{ orders : "FK original_incident_id"
order_revisions |o..o{ orders : "FK current_revision_id"
order_revisions |o..o{ orders : "FK confirmed_revision_id"
users |o..o{ orders : "FK confirmation_owner_id"
users |o..o{ orders : "FK operationally_confirmed_by_id"
orders ||..o{ order_revisions : "FK order_id"
users |o..o{ order_revisions : "FK author_id"
pickup_points |o..o{ order_revisions : "FK pickup_point_id"
free_shipping_rules |o..o{ order_revisions : "FK free_shipping_rule_id"
order_revisions ||..o{ order_items : "FK revision_id"
product_variants ||..o{ order_items : "FK variant_id"
products ||..o{ order_items : "FK product_id"
product_promotions |o..o{ order_items : "FK promotion_id"
sales_pages |o..o{ order_items : "FK sales_page_id"
orders ||..o{ order_history : "FK order_id"
order_revisions |o..o{ order_history : "FK previous_revision_id"
order_revisions |o..o{ order_history : "FK next_revision_id"
users |o..o{ order_history : "FK actor_id"
order_items ||..o| stock_reservations : "FK order_item_id"
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
shipping_providers ||..o{ provider_rates : "FK provider_id"
products |o..o{ free_shipping_rules : "FK product_id"
shipping_providers ||..o{ carrier_geo_mappings : "FK provider_id"
shipping_providers ||..o{ pickup_points : "FK provider_id"
orders ||..o| shipments : "FK order_id"
order_revisions ||..o{ shipments : "FK shipped_revision_id"
shipping_providers ||..o{ shipments : "FK provider_id"
pickup_points |o..o{ shipments : "FK pickup_point_id"
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
remittance_statements ||..o{ remittance_lines : "FK remittance_statement_id"
collections ||..o{ remittance_lines : "FK collection_id"
remittance_lines |o..o{ remittance_lines : "FK reversal_of_id"
remittance_lines |o..o{ remittance_lines : "FK correction_of_id"
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
orders |o..o{ customer_adjustments : "FK exchange_order_id"
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
carrier_rate_versions |o..o{ carrier_fees : "FK source_rate_id"
media |o..o{ carrier_fees : "FK proof_media_id"
carrier_fees |o..o{ carrier_fees : "FK reversal_of_id"
carrier_fees |o..o{ carrier_fees : "FK correction_of_id"
remittance_statements ||..o{ carrier_fee_payments : "FK remittance_statement_id"
carrier_fees ||..o{ carrier_fee_payments : "FK carrier_fee_id"
carrier_fee_payments |o..o{ carrier_fee_payments : "FK reversal_of_id"
carrier_fee_payments |o..o{ carrier_fee_payments : "FK correction_of_id"
shipping_providers ||..o{ carrier_receivables : "FK provider_id"
carrier_fees ||..o{ carrier_receivables : "FK carrier_fee_id"
carrier_fee_payments |o..o{ carrier_receivables : "FK original_fee_payment_id"
carrier_receivables |o..o{ carrier_receivables : "FK reversal_of_id"
carrier_receivables ||..o{ carrier_receivable_allocations : "FK receivable_id"
remittance_statements |o..o{ carrier_receivable_allocations : "FK remittance_statement_id"
carrier_fees |o..o{ carrier_receivable_allocations : "FK carrier_fee_id"
carrier_receivable_allocations |o..o{ carrier_receivable_allocations : "FK reversal_of_id"
collections ||..o{ collection_entries : "FK collection_id"
users ||..o{ collection_entries : "FK verified_by_id"
media |o..o{ collection_entries : "FK proof_media_id"
collection_entries |o..o{ collection_entries : "FK reversal_of_id"
collection_entries |o..o{ collection_entries : "FK correction_of_id"
remittance_statements ||..o{ carrier_compensations : "FK remittance_statement_id"
shipments ||..o{ carrier_compensations : "FK shipment_id"
orders |o..o{ carrier_compensations : "FK replacement_order_id"
media |o..o{ carrier_compensations : "FK proof_media_id"
carrier_compensations |o..o{ carrier_compensations : "FK reversal_of_id"
carrier_compensations |o..o{ carrier_compensations : "FK correction_of_id"
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
order_incidents ||..o{ exchange_offsets : "FK incident_id"
orders ||..o{ exchange_offsets : "FK original_order_id"
invoices ||..o{ exchange_offsets : "FK original_credit_note_id"
orders ||..o{ exchange_offsets : "FK destination_order_id"
order_revisions ||..o{ exchange_offsets : "FK destination_revision_id"
exchange_offsets |o..o{ exchange_offsets : "FK reversal_of_id"
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
users ||..o{ permission_overrides : "FK user_id"
permissions ||..o{ permission_overrides : "FK permission_id"
users ||..o{ permission_overrides : "FK assigned_by_id"
roles ||..o{ team_invitations : "FK initial_role_id"
users ||..o{ team_invitations : "FK invited_by_id"
users ||..o{ contact_verifications : "FK user_id"
users ||..o{ carrier_accounts : "FK created_by_id"
carrier_accounts ||..o{ carrier_rate_versions : "FK carrier_account_id"
users ||..o{ carrier_rate_versions : "FK created_by_id"
carrier_accounts ||..o{ carrier_remittance_batches : "FK carrier_account_id"
media |o..o{ carrier_remittance_batches : "FK proof_media_id"
carrier_remittance_batches |o..o{ carrier_remittance_batches : "FK reversal_of_id"
users |o..o{ carrier_remittance_batches : "FK validated_by_id"
users |o..o{ processing_activity_register : "FK validated_by_id"
users ||..o{ model_has_roles : "POLY model_id"
users ||..o{ model_has_permissions : "POLY model_id"
users |o..o{ activity_log : "POLY causer_id"
shop |o..o{ activity_log : "POLY subject_id"
shop_addresses |o..o{ activity_log : "POLY subject_id"
social_links |o..o{ activity_log : "POLY subject_id"
content_pages |o..o{ activity_log : "POLY subject_id"
media |o..o{ activity_log : "POLY subject_id"
categories |o..o{ activity_log : "POLY subject_id"
products |o..o{ activity_log : "POLY subject_id"
product_variants |o..o{ activity_log : "POLY subject_id"
product_options |o..o{ activity_log : "POLY subject_id"
option_values |o..o{ activity_log : "POLY subject_id"
variant_option_values |o..o{ activity_log : "POLY subject_id"
tags |o..o{ activity_log : "POLY subject_id"
product_tags |o..o{ activity_log : "POLY subject_id"
attributes |o..o{ activity_log : "POLY subject_id"
product_attributes |o..o{ activity_log : "POLY subject_id"
sales_pages |o..o{ activity_log : "POLY subject_id"
product_promotions |o..o{ activity_log : "POLY subject_id"
product_reviews |o..o{ activity_log : "POLY subject_id"
visitors |o..o{ activity_log : "POLY subject_id"
visit_sessions |o..o{ activity_log : "POLY subject_id"
navigation_events |o..o{ activity_log : "POLY subject_id"
visitor_preferences |o..o{ activity_log : "POLY subject_id"
carts |o..o{ activity_log : "POLY subject_id"
cart_items |o..o{ activity_log : "POLY subject_id"
orders |o..o{ activity_log : "POLY subject_id"
order_revisions |o..o{ activity_log : "POLY subject_id"
order_items |o..o{ activity_log : "POLY subject_id"
order_history |o..o{ activity_log : "POLY subject_id"
stock_reservations |o..o{ activity_log : "POLY subject_id"
stock_movements |o..o{ activity_log : "POLY subject_id"
order_returns |o..o{ activity_log : "POLY subject_id"
return_items |o..o{ activity_log : "POLY subject_id"
shipping_providers |o..o{ activity_log : "POLY subject_id"
customer_shipping_rates |o..o{ activity_log : "POLY subject_id"
provider_rates |o..o{ activity_log : "POLY subject_id"
free_shipping_rules |o..o{ activity_log : "POLY subject_id"
carrier_geo_mappings |o..o{ activity_log : "POLY subject_id"
pickup_points |o..o{ activity_log : "POLY subject_id"
shipments |o..o{ activity_log : "POLY subject_id"
shipment_events |o..o{ activity_log : "POLY subject_id"
carrier_operations |o..o{ activity_log : "POLY subject_id"
carrier_operation_attempts |o..o{ activity_log : "POLY subject_id"
collections |o..o{ activity_log : "POLY subject_id"
remittance_statements |o..o{ activity_log : "POLY subject_id"
remittance_lines |o..o{ activity_log : "POLY subject_id"
expenses |o..o{ activity_log : "POLY subject_id"
customer_adjustments |o..o{ activity_log : "POLY subject_id"
order_documents |o..o{ activity_log : "POLY subject_id"
carrier_fees |o..o{ activity_log : "POLY subject_id"
carrier_fee_payments |o..o{ activity_log : "POLY subject_id"
carrier_receivables |o..o{ activity_log : "POLY subject_id"
carrier_receivable_allocations |o..o{ activity_log : "POLY subject_id"
collection_entries |o..o{ activity_log : "POLY subject_id"
carrier_compensations |o..o{ activity_log : "POLY subject_id"
invoices |o..o{ activity_log : "POLY subject_id"
order_incidents |o..o{ activity_log : "POLY subject_id"
order_incident_details |o..o{ activity_log : "POLY subject_id"
billing_rules |o..o{ activity_log : "POLY subject_id"
sales_terms_acceptances |o..o{ activity_log : "POLY subject_id"
billing_obligations |o..o{ activity_log : "POLY subject_id"
exchange_offsets |o..o{ activity_log : "POLY subject_id"
commercial_corrections |o..o{ activity_log : "POLY subject_id"
commercial_correction_lines |o..o{ activity_log : "POLY subject_id"
users |o..o{ activity_log : "POLY subject_id"
permissions |o..o{ activity_log : "POLY subject_id"
roles |o..o{ activity_log : "POLY subject_id"
permission_overrides |o..o{ activity_log : "POLY subject_id"
team_invitations |o..o{ activity_log : "POLY subject_id"
contact_verifications |o..o{ activity_log : "POLY subject_id"
carrier_accounts |o..o{ activity_log : "POLY subject_id"
carrier_rate_versions |o..o{ activity_log : "POLY subject_id"
carrier_remittance_batches |o..o{ activity_log : "POLY subject_id"
processing_activity_register |o..o{ activity_log : "POLY subject_id"
products ||..o{ media : "POLY model_id"
product_variants ||..o{ media : "POLY model_id"
shop ||..o{ media : "POLY model_id"
categories ||..o{ media : "POLY model_id"
shipments ||..o{ media : "POLY model_id"
remittance_statements ||..o{ media : "POLY model_id"
expenses ||..o{ media : "POLY model_id"
customer_adjustments ||..o{ media : "POLY model_id"
order_documents ||..o{ media : "POLY model_id"
carrier_fees ||..o{ media : "POLY model_id"
collection_entries ||..o{ media : "POLY model_id"
carrier_compensations ||..o{ media : "POLY model_id"
invoices ||..o{ media : "POLY model_id"
carrier_remittance_batches ||..o{ media : "POLY model_id"
```

## 3. Les références à la BDD centrale

| Champ local | Référence logique | Explication |
|---|---|---|
| `shop.tenant_uuid` | `central.tenants.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `shop_addresses.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `shop_addresses.municipality_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `order_revisions.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `order_revisions.municipality_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `customer_shipping_rates.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `customer_shipping_rates.municipality_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `provider_rates.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `provider_rates.municipality_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `free_shipping_rules.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `carrier_geo_mappings.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `carrier_geo_mappings.municipality_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `pickup_points.province_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `pickup_points.municipality_uuid` | `central.geographic_areas.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |
| `users.central_user_uuid` | `central.users.uuid` | UUID externe, vérifié dans le bon contexte ; aucune FK SQL entre BDD. |

permissions.feature_code et les codes transporteur sont des codes métier, pas des identifiants de comptes. Le préfixe de numérotation et seller_profile_version viennent du contexte central vérifié ; ils servent de repères documentaires sans changer les tables centrales.

## 4. Chaque champ expliqué simplement

Chaque tableau conserve l’ordre du diagramme. Une mention nullable signifie que le champ peut rester vide dans les cas prévus. Les dates, états et justificatifs ont des rôles distincts : une livraison déclarée ne prouve pas un paiement. Les états et contraintes détaillés sont définis dans le schéma principal.

### 1. `shop` — 23 champs

La fiche publique de la boutique : son nom affiché, son logo, ses contacts et sa présentation. Exemple : les informations que les visiteurs voient sur le site de Karim.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `tenant_uuid` | l’identifiant de la boutique. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `logo_media_id` | le fichier utilisé comme logo de la boutique. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `favicon_media_id` | la petite image affichée dans l’onglet du navigateur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `singleton` | un petit verrou technique qui garantit qu’il n’existe qu’une seule ligne de ce type dans la base. Exemple : une seule fiche `shop`. |
| `central_profile_version` | la dernière version du profil central que cette boutique a reçue. Cela permet de voir si elle est à jour. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
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

### 2. `shop_addresses` — 18 champs

Les adresses publiques de la boutique et leur emplacement sur une carte. Exemple : une adresse pour le magasin et une autre pour un point de retrait. Cela n’ajoute pas une caisse de magasin.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `shop_id` | l’identifiant de la fiche de la boutique. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `municipality_uuid` | l’identifiant de la commune. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `label` | un nom court utilisé pour reconnaître facilement l’élément à l’écran. |
| `address` | l’adresse écrite. Exemple : rue, cité ou quartier. |
| `postal_code` | le code postal lorsqu’il est connu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `latitude` | la position nord/sud utilisée pour placer l’adresse sur une carte. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `longitude` | la position est/ouest utilisée pour placer l’adresse sur une carte. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `map_url` | un lien vers la position sur une carte. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `phone` | le numéro de téléphone. Il est gardé comme texte pour ne pas perdre le `0`, le `+213` ou d’autres signes utiles. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `opening_hours` | les heures d’ouverture regroupées par jour. Exemple : samedi 09:00–18:00. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_primary` | indique si cette ligne est la principale parmi plusieurs choix. |
| `visible` | indique si les visiteurs peuvent voir l’élément sur le site. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 3. `social_links` — 12 champs

Les liens vers les pages de la boutique sur les réseaux sociaux. Exemple : son compte Instagram et deux pages Facebook différentes.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `shop_id` | l’identifiant de la fiche de la boutique. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shop_address_id` | l’identifiant de l’adresse de la boutique. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `network` | le réseau social concerné. Exemple : Instagram, Facebook ou TikTok. |
| `label` | un nom court utilisé pour reconnaître facilement l’élément à l’écran. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `url` | le lien web à ouvrir. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `is_active` | indique si l’élément peut encore être utilisé. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 4. `content_pages` — 15 champs

Le contenu des pages d’information du site. Exemple : Karim écrit le texte de « À propos », de « Contact » ou de sa politique de retour.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `slug` | la partie simple de l’adresse web. Exemple : `a-propos` dans `/a-propos`. |
| `type` | type fonctionnel de page, conservé en texte car il est extensible. Exemples : `about`, `contact`, `faq`, `returns` ou un nouveau type ajouté par le template sans migration de BDD. |
| `title` | le titre affiché à l’utilisateur. |
| `content` | le texte ou contenu principal de la page. |
| `meta_title` | le titre prévu pour les moteurs de recherche et le partage web. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `meta_description` | la petite description prévue pour les moteurs de recherche. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `indexable` | indique si les moteurs de recherche sont autorisés à indexer cette page. |
| `is_published` | indique si l’élément est publié et donc prêt à être montré. |
| `published_at` | la date et l’heure liées à **publiee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `version` | le numéro de version de cet élément. Exemple : version 1, puis version 2 après une évolution ; l’ancienne version peut rester conservée pour comprendre l’historique. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 5. `media` — 23 champs

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

### 6. `categories` — 14 champs

Les familles de produits et leurs sous-familles. Exemple : « Vêtements » contient « T-shirts ». Une seule table permet d’organiser les deux niveaux.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `parent_id` | l’élément parent. Exemple : une sous-catégorie « Chaussures » peut avoir « Mode » comme catégorie parent. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `media_id` | l’identifiant du fichier/image/vidéo. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `slug` | la partie simple de l’adresse web. Exemple : `a-propos` dans `/a-propos`. |
| `description` | un texte qui explique l’élément plus en détail. Il peut rester vide si aucune explication supplémentaire n’est nécessaire. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `meta_title` | le titre prévu pour les moteurs de recherche et le partage web. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `meta_description` | la petite description prévue pour les moteurs de recherche. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 7. `products` — 25 champs

La présentation commune d’un produit : son nom, sa description et les informations partagées par ses versions. Exemple : le modèle « T-shirt coton », proposé ensuite en plusieurs tailles et couleurs.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `category_id` | l’identifiant de la catégorie. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
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

### 8. `product_variants` — 25 champs

Les versions précises que l’on peut acheter, avec leur prix et leur stock. Exemple : « T-shirt rouge, taille M ». Un produit sans choix possède aussi une variante standard.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `label` | un nom court utilisé pour reconnaître facilement l’élément à l’écran. |
| `sku` | la référence utilisée pour reconnaître **SKU** sans se baser seulement sur son nom. |
| `barcode` | le code-barres de la variante lorsqu’il existe. |
| `combination_signature` | une signature calculée à partir des options choisies pour empêcher deux variantes représentant exactement la même combinaison. |
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

### 9. `product_options` — 9 champs

Les types de choix proposés pour un produit. Exemple : pour un t-shirt, le client peut choisir une taille et une couleur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `display_type` | la façon d’afficher l’option. Exemple : boutons, liste ou pastilles de couleur. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 10. `option_values` — 10 champs

Les choix disponibles pour chaque option. Exemple : M et L pour la taille ; rouge et bleu pour la couleur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `option_id` | l’option concernée. Exemple : « Taille ». |
| `identity_code` | le code utilisé pour reconnaître **identite** de manière stable dans le programme ou chez un service externe. |
| `value` | la valeur enregistrée. Exemple : `Rouge`, `XL` ou une autre valeur selon la table. |
| `color_hex` | la couleur écrite sous forme de code web. Exemple : `#FF0000` pour rouge. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 11. `variant_option_values` — 8 champs

Indique les choix qui composent chaque variante. Exemple : cette variante correspond à la taille M et à la couleur rouge.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `variant_id` | l’identifiant de la variante du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `option_id` | l’option concernée. Exemple : « Taille ». |
| `value_id` | la valeur choisie pour l’option. Exemple : « 42 » pour l’option Taille. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 12. `tags` — 7 champs

Les petits mots utilisés pour classer ou mettre en avant les produits. Exemple : « Été » ou « Idée cadeau ».

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `slug` | la partie simple de l’adresse web. Exemple : `a-propos` dans `/a-propos`. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 13. `product_tags` — 6 champs

Indique quelles étiquettes sont attachées à chaque produit. Exemple : le même t-shirt peut porter les étiquettes « Été » et « Idée cadeau ».

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `tag_id` | l’identifiant de l’étiquette. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 14. `attributes` — 11 champs

La liste des informations servant à décrire les produits. Exemple : le poids ou le pays de fabrication ; ce ne sont pas forcément des choix proposés à l’achat.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `group_name` | un nom qui permet de ranger plusieurs caractéristiques ensemble. Exemple : « Dimensions » pour longueur, largeur et hauteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `value_type` | code de `AttributeValueTypeEnum` qui indique le type de valeur attendu pour cette caractéristique : `TEXT`, `NUMBER`, `BOOLEAN` ou `DATE`. |
| `unit` | explique ce que le nombre représente. Exemple : dans **« 3 boutiques »**, le nombre est 3 et l’unité est « boutiques ». Pour une fonction seulement oui/non, ce champ peut rester vide. |
| `explanation` | un texte simple qui aide à comprendre la caractéristique. Exemple : expliquer ce que veut dire « matière ». Ce champ peut rester vide. |
| `position` | l’ordre d’affichage. Exemple : 1 apparaît avant 2. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 15. `product_attributes` — 8 champs

La valeur d’une caractéristique pour un produit précis. Exemple : le poids de ce pot de miel est de 500 grammes.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `attribute_id` | l’identifiant de la caractéristique. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `text_value` | la valeur écrite en texte pour cette caractéristique. Exemple : `Coton`. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `numeric_value` | la valeur numérique de la caractéristique quand elle se mesure avec un nombre. Exemple : `500`. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 16. `sales_pages` — 15 champs

Les pages qui présentent un seul produit pour donner envie de le commander. Exemple : une page partageable avec les avantages d’un produit, ses images et son formulaire de commande.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `product_id` | l’identifiant du produit. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `slug` | la partie simple de l’adresse web. Exemple : `a-propos` dans `/a-propos`. |
| `title` | le titre affiché à l’utilisateur. |
| `content` | le texte ou contenu principal de la page. |
| `meta_title` | le titre prévu pour les moteurs de recherche et le partage web. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `meta_description` | la petite description prévue pour les moteurs de recherche. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `canonical_url` | l’adresse web principale que les moteurs de recherche doivent considérer comme la vraie version de cette page. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `indexable` | indique si les moteurs de recherche sont autorisés à indexer cette page. |
| `is_published` | indique si l’élément est publié et donc prêt à être montré. |
| `published_at` | la date et l’heure liées à **publiee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 17. `product_promotions` — 16 champs

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

### 18. `product_reviews` — 15 champs

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

### 19. `visitors` — 8 champs

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

### 20. `visit_sessions` — 14 champs

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

### 21. `navigation_events` — 14 champs

Les actions minimales servant aux statistiques globales : page ouverte, produit vu ou ajout au panier. Aucun écran de parcours individuel n’est prévu.

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
| `type` | code d’événement de navigation extensible, par exemple `product_view`, `add_to_cart` ou `checkout_started`. Il reste textuel car de nouveaux événements analytiques peuvent être ajoutés sans migration. |
| `path` | Le chemin de la page visitée, sans paramètres contenant des données sensibles. |
| `quantity` | le nombre d’éléments concernés. Exemple : `2` signifie deux unités du produit. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `occurred_at` | la date et l’heure où l’événement s’est produit. |
| `received_at` | la date à laquelle le serveur a reçu cet événement ; elle peut être différente de la date où le navigateur l’a produit. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 22. `visitor_preferences` — 7 champs

Les choix du visiteur concernant la mesure de sa navigation. Exemple : refuser cette mesure tout en continuant à utiliser le panier et à commander.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `visitor_id` | l’identifiant du visiteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `allows_analytics` | indique si la mesure d’audience prévue par le site peut être utilisée pour ce visiteur selon la règle retenue. |
| `notice_version` | la version du texte d’information montrée au client. |
| `chosen_at` | la date à laquelle le navigateur a enregistré ce choix de mesure d’audience ; une modification crée un nouveau choix daté. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 23. `carts` — 9 champs

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

### 24. `cart_items` — 11 champs

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

### 25. `orders` — 32 champs

La fiche principale de chaque commande, avec son identité et son état commercial. Exemple : la commande de Karim reste la même commande même si son contenu est modifié avant expédition.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `visitor_id` | l’identifiant du visiteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `cart_id` | l’identifiant du panier. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_session_id` | l’identifiant de la session d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_sales_page_id` | l’identifiant de la page de vente d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_return_id` | l’identifiant du retour d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_order_id` | l’identifiant de la commande d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_incident_id` | l’identifiant de l’incident d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `current_revision_id` | l’identifiant de la version actuelle de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `confirmed_revision_id` | la version précise que le commerçant a validée après son appel. Si une nouvelle proposition est préparée, cette ancienne version validée reste connue jusqu’au prochain clic « Valider ». |
| `confirmation_owner_id` | l’identifiant de la personne responsable de la confirmation. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `operationally_confirmed_by_id` | l’identifiant de la personne qui a validé le contrôle opérationnel. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `number` | le numéro lisible de l’élément. Exemple : numéro d’échéance ou de facture selon la table. |
| `data_policy_version` | la version de la politique d’information sur les données personnelles montrée au client pendant ce checkout. |
| `data_notice_acknowledged_at` | la date où le client a continué le checkout après que l’information sur l’utilisation de ses données lui a été présentée. |
| `notice_text_hash` | une empreinte du texte montré au client, pour pouvoir prouver quelle version a été présentée sans dupliquer inutilement le texte. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `original_incident_quantity` | le nombre d’unités correspondant à **incident origine**. Exemple : `2` signifie deux unités. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `replacement_reason` | explique la raison de **remplacement**. Cela permet de comprendre plus tard pourquoi la décision a été prise. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `order_type` | indique la catégorie de **commande** utilisée pour cette ligne. |
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

### 26. `order_revisions` — 38 champs

Les copies successives du contenu d’une commande à chaque modification. Exemple : la première version contient une taille M ; une nouvelle version contient une taille L, sans effacer l’ancienne.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `author_id` | l’identifiant de la personne qui a créé l’élément. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `pickup_point_id` | l’identifiant du point relais. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
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
| `pickup_point_snapshot` | une copie figée des informations du point relais choisi au moment de l’expédition. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `catalog_subtotal` | le total calculé avec les prix normaux du catalogue avant les changements manuels appliqués à la commande. |
| `applied_subtotal` | le total réellement utilisé après les changements de prix ou remises prévus. |
| `customer_shipping_fee` | le montant de livraison payé par le client. |
| `shipping_discount` | la réduction appliquée aux frais de livraison. |
| `shipping_charge_bearer` | indique qui prend en charge les frais de livraison selon la règle choisie. |
| `merchant_shipping_amount` | la somme d’argent correspondant à **livraison commercant**. Exemple : `1500` représente 1 500 DA au lancement. |
| `order_total` | le montant total de la commande à cette révision. |
| `exchange_offset_amount` | la somme d’argent correspondant à **compensation echange**. Exemple : `1500` représente 1 500 DA au lancement. |
| `amount_to_collect` | le montant que le livreur doit demander au client lors de la livraison. |
| `customer_note` | la note donnée par le client. Exemple : 4 sur 5. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `sales_terms_version` | la version des conditions de vente applicables à cette commande. |
| `sales_terms_snapshot` | une copie figée du texte ou des informations importantes des conditions acceptées. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 27. `order_items` — 23 champs

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
| `created_at` | la date où cette ligne a été créée dans la base. |

### 28. `order_history` — 16 champs

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

### 29. `stock_reservations` — 9 champs

Les quantités mises de côté au clic « Valider » du commerçant après son appel. Exemple : réserver deux t-shirts ; leur sortie physique est enregistrée lors de la remise du colis au transporteur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_item_id` | l’identifiant de la ligne de produit de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `quantity` | le nombre d’éléments concernés. Exemple : `2` signifie deux unités du produit. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `reserved_at` | la date et l’heure liées à **reserve**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `released_at` | la date et l’heure liées à **libere**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 30. `stock_movements` — 28 champs

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

### 31. `order_returns` — 14 champs

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

### 32. `return_items` — 19 champs

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

### 33. `shipping_providers` — 14 champs

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
| `carrier_code` | le code utilisé par le transporteur pour reconnaître une zone ou un service. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `last_synced_at` | la dernière fois où le SaaS a synchronisé ce compte avec le service externe. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_active` | indique si l’élément peut encore être utilisé. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 34. `customer_shipping_rates` — 10 champs

Le prix de livraison demandé à l’acheteur. Exemple : le client paie 600 DA pour une livraison dans une zone donnée ; ce prix peut différer du coût payé au transporteur.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `municipality_uuid` | l’identifiant de la commune. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `delivery_mode` | la façon de livrer choisie. Exemple : domicile ou point relais. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 35. `provider_rates` — 14 champs

Le coût estimé de la livraison pour la boutique selon la zone et le mode choisi. Exemple : estimer le coût d’une livraison à domicile. Les tarifs de retour des comptes société sont versionnés dans carrier_rate_versions de cette boutique (T25).

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `municipality_uuid` | l’identifiant de la commune. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `delivery_mode` | la façon de livrer choisie. Exemple : domicile ou point relais. |
| `service_type` | indique la catégorie de **prestation** utilisée pour cette ligne. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `source` | code de `ProviderRateSourceEnum` : `1 MANUAL` pour une saisie manuelle, `2 API` pour un tarif importé par API. |
| `retrieved_at` | la date et l’heure liées à **releve**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 36. `free_shipping_rules` — 14 champs

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

### 37. `carrier_geo_mappings` — 16 champs

Relie les wilayas et communes du SaaS aux noms ou codes utilisés par chaque transporteur. Exemple : traduire une commune choisie sur le site en code reconnu par EcoTrack.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `municipality_uuid` | l’identifiant de la commune. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `zone_type` | indique la catégorie de **zone** utilisée pour cette ligne. |
| `external_code` | le code utilisé pour reconnaître **externe** de manière stable dans le programme ou chez un service externe. |
| `external_name` | le nom utilisé par le transporteur pour cet élément. |
| `external_province_code` | le code de wilaya attendu par ce transporteur, qui peut être différent du code interne du SaaS. |
| `verification_source` | indique d’où vient **verification** afin de savoir si l’information vient du SaaS, d’un utilisateur ou d’un service externe. |
| `verified_at` | la date où l’information a été vérifiée. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `mapping_version` | la version des règles utilisées pour traduire les statuts du transporteur en statuts internes. |
| `is_active` | indique si cette possibilité est autorisée. `true` = oui, `false` = non. |
| `synced_at` | la date et l’heure liées à **synchronise**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 38. `pickup_points` — 16 champs

Les bureaux du transporteur où le client peut retirer son colis. Exemple : choisir un stop desk au lieu d’une livraison à domicile.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `province_uuid` | l’identifiant de la wilaya. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `municipality_uuid` | l’identifiant de la commune. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `external_code` | le code utilisé pour reconnaître **externe** de manière stable dans le programme ou chez un service externe. |
| `name` | le nom affiché à l’utilisateur. Exemple : « Nombre de boutiques » ou « Livraison EcoTrack ». |
| `address` | l’adresse écrite. Exemple : rue, cité ou quartier. |
| `phone` | le numéro de téléphone. Il est gardé comme texte pour ne pas perdre le `0`, le `+213` ou d’autres signes utiles. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `map_url` | un lien vers la position sur une carte. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `is_carrier_active` | un **oui/non** pour indiquer si **actif transporteur** est vrai ou autorisé. `true` = oui ; `false` = non. |
| `is_shop_active` | un **oui/non** pour indiquer si **active boutique** est vrai ou autorisé. `true` = oui ; `false` = non. |
| `synced_at` | la date et l’heure liées à **synchronise**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |
| `deleted_at` | la date où l’élément a été retiré sans effacer son ancienne ligne. Si ce champ est vide, l’élément n’est pas supprimé. |

### 39. `shipments` — 24 champs

Les colis : commande et version expédiée, livreur, frais, suivi interne et dates. On accepte la déclaration de livraison du livreur, sans preuve de réception client.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipped_revision_id` | l’identifiant de la version réellement expédiée. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `provider_id` | l’identifiant du transporteur ou livreur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `pickup_point_id` | l’identifiant du point relais. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
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

### 40. `shipment_events` — 25 champs

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

### 41. `carrier_operations` — 27 champs

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

### 42. `carrier_operation_attempts` — 13 champs

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

### 43. `collections` — 13 champs

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

### 44. `remittance_statements` — 22 champs

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

### 45. `remittance_lines` — 9 champs

Le détail d’un reversement pour chaque colis. Exemple : préciser que 4 000 DA du versement concernent le colis A et 6 000 DA le colis B, sans les compter deux fois.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `remittance_statement_id` | l’identifiant du bordereau de reversement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `collection_id` | l’identifiant du recouvrement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `remitted_amount` | La part du versement reçue par le commerçant et attribuée à ce colis ; un inverse garde le montant opposé. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 46. `expenses` — 21 champs

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

### 47. `customer_adjustments` — 22 champs

Le suivi des remboursements aux acheteurs, avec leurs montants, motifs et états. Exemple : enregistrer un remboursement réellement effectué, sans créer de portefeuille client.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `return_id` | l’identifiant du retour. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `incident_id` | l’identifiant de l’incident de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `credit_note_id` | l’identifiant de l’avoir. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `exchange_order_id` | l’identifiant de la commande d’échange. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `validated_by_id` | l’identifiant de la personne qui a validé. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `compensated_quantity` | le nombre d’unités correspondant à **compensee**. Exemple : `2` signifie deux unités. |
| `amount_kind` | explique ce que représente le montant. Exemple : frais, remboursement ou correction. |
| `type` | code de `AdjustmentTypeEnum` indiquant la nature de l’ajustement financier appliqué au client. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `performed_at` | la date où l’opération a réellement été faite. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reference` | un numéro ou texte de référence qui aide à reconnaître l’opération. Exemple : numéro d’un reçu ou référence externe. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 48. `order_documents` — 11 champs

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

### 49. `activity_log` — 17 champs

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

### 50. `carrier_fees` — 23 champs

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

### 51. `carrier_fee_payments` — 10 champs

Indique comment les frais dus par le commerçant au transporteur sont réglés. Exemple : un frais de retour est déduit d’un reversement ou payé séparément, sans compter une deuxième dépense.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `remittance_statement_id` | l’identifiant du bordereau de reversement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `carrier_fee_id` | l’identifiant des frais du transporteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `mode` | indique la manière utilisée pour cette opération. Exemple : paiement séparé, déduction ou autre mode prévu. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 52. `carrier_receivables` — 15 champs

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
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `recognized_at` | la date et l’heure liées à **reconnue**. Elle permet de savoir exactement quand cette étape a eu lieu. |
| `settled_at` | la date et l’heure liées à **soldee**. Elle permet de savoir exactement quand cette étape a eu lieu. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |
| `updated_at` | la date de la dernière modification de cette ligne. Exemple : si tu modifies l’élément aujourd’hui, cette date devient celle d’aujourd’hui. |

### 53. `carrier_receivable_allocations` — 12 champs

Indique comment le transporteur règle les sommes qu’il doit après une correction de frais. Exemple : les 50 DA dus sont remboursés ou déduits d’un prochain frais.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `receivable_id` | la somme due par le transporteur que ce règlement vient réduire. |
| `remittance_statement_id` | l’identifiant du bordereau de reversement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `carrier_fee_id` | l’identifiant des frais du transporteur. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `settlement_type` | indique la catégorie de **apurement** utilisée pour cette ligne. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `external_reference` | le numéro donné par un système extérieur. Exemple : référence d’un reversement chez le transporteur. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `performed_at` | la date où l’opération a réellement été faite. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 54. `collection_entries` — 14 champs

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

### 55. `carrier_compensations` — 13 champs

Les dédommagements du transporteur pour un problème comme une perte ou une casse. Exemple : un montant versé au commerçant pour un colis perdu, séparé de l’argent payé par le client.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `remittance_statement_id` | l’identifiant du bordereau de reversement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `shipment_id` | l’identifiant de la livraison. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `replacement_order_id` | l’identifiant de la commande de remplacement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `proof_media_id` | l’identifiant lié à **preuve media**. Il sert à retrouver l’élément correspondant. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `correction_of_id` | l’identifiant de l’ancienne écriture que cette ligne corrige. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `reason` | explique pourquoi l’action ou la décision a été faite. |
| `external_reference` | le numéro donné par un système extérieur. Exemple : référence d’un reversement chez le transporteur. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 56. `invoices` — 28 champs

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
| `sequence_record_type` | Colonne SQL calculée qui impose que sequence_id vise un compteur, jamais une règle de facturation. |
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

### 57. `order_incidents` — 19 champs

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

### 58. `order_incident_details` — 9 champs

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

### 59. `billing_rules` — 23 champs

Les réglages de facturation dans une seule table : une ligne type 1 compte les numéros ; une ligne type 2 définit une règle validée de production des documents.

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
| `exchange_rule` | La règle validée à appliquer aux échanges, sans inventer un portefeuille client. |
| `numbering_scope` | La portée de numérotation de la règle ; elle reste propre à cette boutique. |
| `parameters` | Les paramètres structurés de la règle, interprétés seulement par du code serveur autorisé. |
| `policy_status` | L’état de la règle : brouillon, validée, active ou retirée ; pas l’état d’une facture. |
| `validation_reference` | La référence expliquant sur quoi repose la validation de la règle. |
| `validated_at` | La date de validation de la règle. |
| `effective_at` | La date à laquelle la règle devient applicable. |
| `ends_at` | La fin éventuelle de la période d’application de la règle. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 60. `sales_terms_acceptances` — 11 champs

Les conditions de vente réellement acceptées pour une version précise de commande. Cela ne crée aucun contrat ni PDF d’accord téléphonique.

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

### 61. `billing_obligations` — 20 champs

Les factures ou avoirs que le système doit produire après un événement prévu par une règle validée. Exemple : garder une facture à émettre dans la liste jusqu’à ce que son émission réussisse.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `event_id` | l’identifiant de l’événement. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `order_id` | l’identifiant de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `revision_id` | l’identifiant de la version de la commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `billing_rule_id` | La version de règle locale dans billing_rules, obligatoirement de type 2 RULE ; elle est figée pour cette occurrence. |
| `original_invoice_id` | l’identifiant de la facture d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `invoice_id` | l’identifiant de la facture. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
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

### 62. `exchange_offsets` — 13 champs

La part d’un avoir utilisée pour payer une nouvelle commande d’échange précise. Exemple : affecter 8 000 DA à un échange coûtant 10 000 DA, avec 2 000 DA de produits restant à payer, sans portefeuille client.

| Champ | Explication très simple |
|---|---|
| `id` | le numéro unique qui permet de reconnaître cette ligne dans la base. Deux lignes différentes ne peuvent pas avoir le même `id`. |
| `uuid` | identifiant public UUID v4 unique et indexé ; routes, API, formulaires et exports utilisent cet identifiant, sans exposer la PK numérique. |
| `incident_id` | l’identifiant de l’incident de commande. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `original_order_id` | l’identifiant de la commande d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `original_credit_note_id` | l’identifiant de l’avoir d’origine. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `destination_order_id` | l’identifiant de la nouvelle commande liée. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `destination_revision_id` | l’identifiant de la version de commande de destination. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. |
| `reversal_of_id` | l’identifiant de l’ancienne écriture annulée par une écriture inverse. Il sert à relier cette ligne à la bonne information au lieu de recopier toutes ses données. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `amount` | la somme d’argent de cette ligne, en DZD au lancement. Exemple : `3000` signifie 3 000 DA. |
| `status` | code entier de l’enum propre à cet objet, défini au §3.3 ; les libellés sont traduits à l’affichage et les transitions contrôlées par le service. |
| `operation_key` | une clé unique utilisée pour reconnaître une opération déjà faite. Exemple : si le serveur reçoit deux fois la même demande après une coupure, cette clé aide à éviter de faire l’opération deux fois. |
| `performed_at` | la date où l’opération a réellement été faite. Ce champ peut rester vide quand cette information n’est pas nécessaire ou pas encore connue. |
| `created_at` | la date où cette ligne a été créée dans la base. |

### 63. `commercial_corrections` — 16 champs

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

### 64. `commercial_correction_lines` — 11 champs

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

### 65. `users` — 18 champs

Les comptes du propriétaire et des employés de cette boutique : nom, e-mail, mot de passe protégé, état du compte et droit d’entrer dans l’équipe. Chaque boutique garde ses propres comptes.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `central_user_uuid` | L’identifiant du propriétaire dans la BDD centrale ; vide pour les employés. Ce n’est pas un compte partagé. |
| `name` | Le nom de cet élément. |
| `first_name` | Le prénom de la personne, si renseigné. |
| `email` | L’adresse e-mail du compte ou de la personne invitée. |
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

### 66. `permissions` — 8 champs

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

### 67. `roles` — 12 champs

Les groupes d’autorisations de la boutique. Exemple : le rôle de préparateur réunit les actions nécessaires pour préparer les colis.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `name` | Le nom technique du rôle, par exemple shop-owner. |
| `guard_name` | Le contexte de connexion de ces droits ; ici tenant, pour cette boutique. |
| `label` | Le nom lisible affiché dans les écrans. |
| `is_system` | Indique si ce rôle est fourni par le système. |
| `is_protected` | Indique si les écrans ordinaires ne peuvent pas modifier ou retirer ce rôle. |
| `is_super_admin` | Indique si ce rôle dispose de l’exception de propriétaire dans cette boutique, avec les protections prévues. |
| `super_admin_slot` | Une valeur calculée qui empêche de créer deux rôles de propriétaire dans cette base. |
| `permission_version` | La version des droits de ce rôle ; elle change quand ses autorisations changent. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 68. `role_has_permissions` — 2 champs

Indique quelles actions sont autorisées pour chaque rôle.

| Champ | Explication très simple |
|---|---|
| `permission_id` | Le lien local vers permissions correspondant à permission_id ; les informations ne sont pas recopiées. |
| `role_id` | Le lien local vers roles correspondant à role_id ; les informations ne sont pas recopiées. |

### 69. `model_has_roles` — 3 champs

Indique quel compte possède quel rôle dans cette boutique.

| Champ | Explication très simple |
|---|---|
| `role_id` | Le lien local vers roles correspondant à role_id ; les informations ne sont pas recopiées. |
| `model_type` | Le type du modèle auquel appartient ce lien, sous forme d’alias autorisé. |
| `model_id` | Le numéro local de ce modèle ; son type indique dans quelle table le retrouver. |

### 70. `model_has_permissions` — 3 champs

Donne une autorisation directement à un compte de cette boutique.

| Champ | Explication très simple |
|---|---|
| `permission_id` | Le lien local vers permissions correspondant à permission_id ; les informations ne sont pas recopiées. |
| `model_type` | Le type du modèle auquel appartient ce lien, sous forme d’alias autorisé. |
| `model_id` | Le numéro local de ce modèle ; son type indique dans quelle table le retrouver. |

### 71. `permission_overrides` — 15 champs

Les autorisations ou interdictions exceptionnelles accordées à un compte, avec leur motif et leurs dates.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `user_id` | Le lien local vers users correspondant à user_id ; les informations ne sont pas recopiées. |
| `permission_id` | Le lien local vers permissions correspondant à permission_id ; les informations ne sont pas recopiées. |
| `assigned_by_id` | Le lien local vers users correspondant à assigned_by_id ; les informations ne sont pas recopiées. |
| `effect` | Indique si cette exception autorise ou interdit l’action. |
| `status` | L’état de cette exception : active, révoquée, expirée ou clôturée. |
| `started_at` | La date de début de cette exception. |
| `ended_at` | La date à laquelle cette exception a été terminée. |
| `active_slot` | Une valeur calculée qui permet de garantir une seule exception active pour la même personne et permission. |
| `expires_at` | La date après laquelle cette donnée ou ce jeton n’est plus utilisable. |
| `reason` | L’explication de cette décision. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |
| `deleted_at` | La date d’archivage ; vide tant que cette ligne n’est pas archivée. |

### 72. `team_invitations` — 12 champs

Les invitations permettant à un employé de rejoindre cette boutique avec un rôle précis et un lien secret qui expire.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `initial_role_id` | Le rôle local prévu pour la personne invitée. |
| `invited_by_id` | Le compte local qui a envoyé cette invitation. |
| `email` | L’adresse e-mail du compte ou de la personne invitée. |
| `token_hash` | L’empreinte du secret de l’invitation ; le lien expire et n’est utilisable qu’une fois. |
| `role_permission_version` | La version des droits du rôle au moment de l’invitation ; elle permet de vérifier les changements avant acceptation. |
| `expires_at` | La date après laquelle cette donnée ou ce jeton n’est plus utilisable. |
| `accepted_at` | La date à laquelle l’invitation a été acceptée. |
| `revoked_at` | La date à laquelle l’invitation a été retirée. |
| `created_at` | La date de création de cette ligne. |
| `updated_at` | La date du dernier changement autorisé de cette ligne. |

### 73. `contact_verifications` — 11 champs

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

### 74. `carrier_accounts` — 14 champs

Les connexions de cette boutique aux services de livraison : compte transporteur, adresse API et secrets protégés.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `created_by_id` | Le lien local vers users correspondant à created_by_id ; les informations ne sont pas recopiées. |
| `carrier` | Le service transporteur utilisé par ce compte. |
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

### 75. `carrier_rate_versions` — 10 champs

Les tarifs de retour annoncés par un compte transporteur, avec leur période d’application. Un changement crée une autre version.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `carrier_account_id` | Le lien local vers carrier_accounts correspondant à carrier_account_id ; les informations ne sont pas recopiées. |
| `created_by_id` | Le lien local vers users correspondant à created_by_id ; les informations ne sont pas recopiées. |
| `return_rate` | Le tarif de retour annoncé pour cette période. |
| `starts_at` | Le début de la période d’application de ce tarif. |
| `ends_at` | La fin éventuelle de sa période d’application. |
| `is_active` | Indique si cet élément peut encore être utilisé. |
| `source` | L’origine de ce tarif : déclaration locale ou donnée transporteur contrôlée. |
| `created_at` | La date de création de cette ligne. |

### 76. `carrier_remittance_batches` — 15 champs

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

### 77. `processing_activity_register` — 19 champs

Le registre qui explique pourquoi la boutique utilise des données, lesquelles, avec qui et comment elle les protège. Les actions réellement faites sont dans activity_log.

| Champ | Explication très simple |
|---|---|
| `id` | Le numéro interne de cette ligne, utilisé par la base de données. |
| `uuid` | Son identifiant public unique, utilisé dans les écrans autorisés. |
| `validated_by_id` | Le lien local vers users correspondant à validated_by_id ; les informations ne sont pas recopiées. |
| `code` | Le code stable de ce traitement. |
| `version` | Le numéro de version de ce traitement. |
| `purpose` | La raison pour laquelle la boutique utilise ces données. |
| `data_subject_categories` | Les catégories de personnes concernées, par exemple acheteurs ou employés ; pas leur liste de noms. |
| `data_categories` | Les catégories de données utilisées, par exemple coordonnées ou commande ; pas les valeurs personnelles. |
| `recipients` | Les catégories de destinataires autorisés à recevoir les données. |
| `processing_basis` | La justification prévue pour utiliser ces données, à valider pour ce traitement. |
| `controller` | L’identité du responsable de ce traitement. |
| `processors` | Les intervenants qui traitent les données pour ce responsable. |
| `retention_rules` | Les règles de durée prévues pour ces données ; ce champ ne recrée pas une table de politiques de rétention. |
| `security_measures` | Les mesures prévues pour protéger les données. |
| `status` | L’état de cette version du registre : brouillon, validée, active ou retirée. |
| `validation_reference` | La référence expliquant la validation de ce registre ou de cette règle. |
| `validated_at` | La date à laquelle cette version a été validée. |
| `effective_at` | La date à laquelle cette version devient applicable. |
| `created_at` | La date de création de cette ligne. |

## 5. Ce que cette version garde et retire

La boutique garde le catalogue et ses variantes, les pages et promotions, le panier invité, la personnalisation en texte libre, les appels et rappels internes, le responsable de confirmation, les révisions, le contrôle opérationnel, le stock, les retours et inspections, les incidents/SAV, les remplacements/échanges/remboursements, les APIs transporteur, les frais et reversements, les dépenses, les marges estimées, les bons internes, les factures/avoirs, les conditions réellement acceptées, les statistiques globales, les comptes d’équipe, les droits et l’audit.

Après l’appel, le commerçant clique Valider. orders garde la révision validée et sa date ; activity_log garde qui a cliqué. Validation, audit et réservation du stock forment une seule transaction. Aucun contrat, PDF d’accord ou enregistrement d’appel n’est nécessaire. Le suivi reste interne. La déclaration du livreur ou du transporteur suffit pour le fait logistique livré ; l’encaissement et le versement restent vérifiés séparément.

Fonctions retirées : annulation/clôture commerciale de commande, lien/écran/API de suivi acheteur, preuve de réception client, messages et envoi de documents aux acheteurs, imports de factures externes, constructeur de champs de personnalisation, consultation de parcours individuels, personnalisation avancée du thème au lancement. Les messages nécessaires à l’accès aux comptes du propriétaire et des employés restent disponibles. Les fermetures d’incidents/retours et corrections des documents financiers gardent leur sens propre.

| Ancien modèle | Organisation actuelle |
|---|---|
| shop_members | État d’appartenance et première activation dans users ; mêmes identités locales |
| personal_data_operations | Activités privacy dans activity_log local, avec performed_at et propriétés contrôlées |
| document_sequences | billing_rules type 1 SEQUENCE ; règles type 2 RULE séparées logiquement |
| order_contracts | Révision/date dans orders et événement officiel order.validated dans activity_log |
| document_deliveries | Fonction retirée pour les acheteurs ; aucun remplacement actif |
| theme_customizations | Fonction avancée reportée ; thème standard, logo et couleurs dans shop |

Les invoices restent à 28 champs et les billing_rules à 23. Les détails historiques des factures/avoirs sont dans leur snapshot_json versionné et contrôlé ; les règles de calcul, taxes, plafonds et liens d’origine sont décrits en T17. Les obligations d’émission restent séparées pour conserver les reprises fiables sans agrandir à nouveau invoices. Cette organisation ne crée pas un moteur fiscal universel ni un portefeuille client.

## 6. Contraintes qui complètent le dessin

Une FK simple ne suffit pas à garantir que deux éléments appartiennent à la même commande/révision/prestataire. Toutes les FK composites, UNIQ, checks et contrôles serveur du §6 restent requis ; les pointeurs de révision et acteurs sont résolus dans la BDD locale. Les références morphs sont contrôlées avec une liste d’alias autorisés et des Policies ; aucune FK générique n’existe pour tous leurs parents.

| Relation ou règle | Ce qu’elle empêche |
|---|---|
| orders(current_revision_id,id) et orders(confirmed_revision_id,id) → order_revisions(id,order_id) | Pointer une version d’une autre commande ou expédier une proposition non validée |
| Liens ligne/révision/variante, retour, incident et remède du §6 | Mélanger les articles, versions ou budgets de deux dossiers |
| invoices(sequence_id,document_type,fiscal_year,sequence_record_type) → billing_rules(id,document_type,fiscal_year,record_type) | Utiliser une règle comme compteur ou le compteur du mauvais type/exercice |
| billing_obligations(billing_rule_id,billing_rule_record_type) → billing_rules(id,record_type) | Utiliser un compteur comme règle |
| UNIQUE(document_type,fiscal_year,sequence_slot) sur les compteurs | Créer deux séries du même type/exercice dans cette boutique |
| UNIQUE(code,version) et contrôle des périodes des règles | Réécrire une version validée ou activer deux versions du même code à la même date |
| Comptes actifs, appartenance active, tenant accessible, DENY prioritaire et quota | Redonner l’accès à un compte suspendu/révoqué par un simple rôle |
| Clé de validation stable par commande/révision et verrous de stock | Réserver deux fois au double clic ou lors d’une reprise |
| Écritures signées, références d’origine, plafonds et preuve financière | Compter deux fois un montant ou confondre livraison et paiement |
| Médias et fichiers historiques privés/figés selon leur usage | Remplacer un PDF ou justificatif utilisé comme pièce historique |

Ce diagramme doit évoluer avec la partie boutique du schéma principal. Il n’est ni une migration SQL ni une validation réelle des APIs transporteur.

## 7. Notes et historique utilisés

La même méthode que pour la centrale est appliquée : clarifier les fonctions, réunir les données d’un même rôle quand cela reste lisible, séparer les cycles métier, garder les contraintes SQL locales et les instantanés historiques, puis vérifier champs, relations, parcours et inventaire ensemble. Le nombre de tables est un compromis pour ces fonctions ; il ne prouve pas à lui seul une meilleure vitesse. Les performances seront mesurées sur la future BDD MySQL.

L’analyse couvre les 35 commits disponibles, du premier 7b14ffb au dernier 2660108, et les documents du dépôt à ce dernier état. Les étapes centrales retenues comme méthode sont les UUID publics avec PK/FK locales, les comptes et droits isolés, les regroupements par type, les états et preuves séparés, puis la réduction des gros documents de facturation sans perdre leurs reprises ou leur histoire. Les choix boutique V4.6 remplacent les anciennes notes incompatibles ; ils ne réécrivent pas la centrale.

Références du dépôt :

- [Schéma principal](Schema-BDD-SaaS-Ecommerce-UUID.md) et [diagramme central conservé](Diagramme-BDD-Centrale-Complet.md).
- [Permissions et accès aux comptes](Documentation-Laravel-Spatie-Permissions-Passkeys.md) et [recherche Activity Log](Recherche_complete_Spatie_Laravel_Activity_Log.md).
- [Demandes du jour 4](<les modiff a efectuer le jours 4.txt>), [demandes initiales](<les truc a modifier .txt.txt>), [notes de suivi](<les note pendans le suivie.txt>) et [dernières notes](<last one notes.md>).
- [Notes V2](<note et machin v2.docx>), [améliorations de première version](<les notes et remarque deja apliquer pour ameliorer le premiere version du shema.docx>), [dernières modifications](<les derniere modiff toujour les notes.docx>) et [bugs et remarques](<des bug et des truc encore.docx>).

Ces ressources sont conservées sans modification.

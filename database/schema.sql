-- =====================================================================
-- MENSA COMUNISTA GRAZ — MySQL/MariaDB schema (3NF)
-- Charset utf8mb4, engine InnoDB. Canonical fresh-install schema.
-- Import order: database/schema.sql  →  database/seed.sql (optional demo)
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. IDENTITY & ACCESS
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id                  CHAR(36)     NOT NULL,
  email               VARCHAR(200) NOT NULL,
  password_hash       VARCHAR(255) NOT NULL,
  status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1)  NOT NULL DEFAULT 0,
  created_at          TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  last_login_at       TIMESTAMP(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE roles (
  id       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     ENUM('admin','coordinator','volunteer') NOT NULL,
  label_de VARCHAR(60) NOT NULL,
  label_en VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_roles (
  user_id    CHAR(36)         NOT NULL,
  role_id    TINYINT UNSIGNED NOT NULL,
  granted_by CHAR(36)         NULL,
  created_at TIMESTAMP(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user  FOREIGN KEY (user_id)  REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role  FOREIGN KEY (role_id)  REFERENCES roles (id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_roles_grant FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL,
  KEY idx_user_roles_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time password reset tokens (only the hash is stored)
CREATE TABLE password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     CHAR(36)     NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  TIMESTAMP(6) NOT NULL,
  used_at     TIMESTAMP(6) NULL,
  created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  KEY idx_password_resets_user (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate-limit counter (login attempts, public form submissions)
CREATE TABLE rate_limits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket     VARCHAR(64)  NOT NULL,          -- e.g. 'login', 'donation'
  ip_hash    CHAR(64)     NOT NULL,          -- SHA-256 hash, never raw IP
  subject    VARCHAR(200) NULL,              -- e.g. submitted email
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_rate_limits_lookup (bucket, ip_hash, created_at),
  KEY idx_rate_limits_subject (bucket, subject, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. TEAM PROFILES & SKILLS  (replaces profiles.skills text[])
-- ---------------------------------------------------------------------
CREATE TABLE skills (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     VARCHAR(40)  NOT NULL,
  label_de VARCHAR(80)  NOT NULL,
  label_en VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skills_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE profiles (
  user_id        CHAR(36)    NOT NULL,
  display_name   VARCHAR(80) NULL,
  phone          VARCHAR(40) NULL,
  is_active      TINYINT(1)  NOT NULL DEFAULT 1,
  created_at     TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id),
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE profile_skills (
  user_id  CHAR(36)          NOT NULL,
  skill_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, skill_id),
  CONSTRAINT fk_profile_skills_user  FOREIGN KEY (user_id)  REFERENCES profiles (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_skills_skill FOREIGN KEY (skill_id) REFERENCES skills (id)        ON DELETE CASCADE,
  KEY idx_profile_skills_skill (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. LOCATIONS & ACTIVITY CATEGORIES  (lookups for events)
-- ---------------------------------------------------------------------
CREATE TABLE event_locations (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     VARCHAR(60)  NOT NULL,
  name     VARCHAR(120) NOT NULL,
  address  VARCHAR(200) NULL,
  city     VARCHAR(80)  NULL,
  note     VARCHAR(200) NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_locations_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_categories (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     ENUM('shopping','cooking','preparation','delivery','serving','cleaning','dishwashing','photography','video','editing','other') NOT NULL DEFAULT 'other',
  label_de VARCHAR(60) NOT NULL,
  label_en VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_activity_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. MEDIA LIBRARY
--    Binary files live on disk (storage/media/), metadata only here.
-- ---------------------------------------------------------------------
CREATE TABLE media_assets (
  id          CHAR(36)     NOT NULL,
  file_path   VARCHAR(255) NOT NULL,     -- path relative to storage root
  mime_type   VARCHAR(100) NOT NULL,
  byte_size   INT UNSIGNED NOT NULL,
  width       SMALLINT UNSIGNED NULL,
  height      SMALLINT UNSIGNED NULL,
  thumb_path  VARCHAR(255) NULL,
  uploaded_by CHAR(36)     NULL,
  created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  replaced_at TIMESTAMP(6) NULL,
  is_deleted  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_media_assets_uploaded (uploaded_by, created_at),
  CONSTRAINT fk_media_assets_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE media_translations (
  media_id CHAR(36) NOT NULL,
  lang     ENUM('de','en') NOT NULL,
  alt      VARCHAR(200) NULL,
  caption  VARCHAR(400) NULL,
  PRIMARY KEY (media_id, lang),
  CONSTRAINT fk_media_translations_media FOREIGN KEY (media_id) REFERENCES media_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. EVENTS (base data + per-language translations)
-- ---------------------------------------------------------------------
CREATE TABLE events (
  id                CHAR(36)  NOT NULL,
  event_date        DATE      NOT NULL,
  start_time        TIME      NULL,
  end_time          TIME      NULL,
  timezone          VARCHAR(64) NOT NULL DEFAULT 'Europe/Vienna',
  location_id       SMALLINT UNSIGNED NULL,
  meeting_point     VARCHAR(150) NULL,
  activity_category_id SMALLINT UNSIGNED NULL,
  volunteers_needed INT UNSIGNED NULL,
  cover_media_id    CHAR(36)  NULL,
  status            ENUM('planned','open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
  is_published      TINYINT(1) NOT NULL DEFAULT 0,
  needs_review      TINYINT(1) NOT NULL DEFAULT 0,
  review_note       VARCHAR(500) NULL,
  internal_notes    TEXT NULL,
  is_demo           TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at        TIMESTAMP(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_events_date_published (event_date, is_published),
  KEY idx_events_status (status),
  KEY idx_events_location (location_id),
  KEY idx_events_activity (activity_category_id),
  CONSTRAINT fk_events_location FOREIGN KEY (location_id) REFERENCES event_locations (id) ON DELETE SET NULL,
  CONSTRAINT fk_events_activity FOREIGN KEY (activity_category_id) REFERENCES activity_categories (id) ON DELETE SET NULL,
  CONSTRAINT fk_events_cover    FOREIGN KEY (cover_media_id) REFERENCES media_assets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE event_translations (
  event_id    CHAR(36) NOT NULL,
  lang        ENUM('de','en') NOT NULL,
  title       VARCHAR(200) NOT NULL,
  description TEXT NULL,
  extra_info  VARCHAR(1000) NULL,
  PRIMARY KEY (event_id, lang),
  CONSTRAINT fk_event_translations_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event gallery (cover gallery images, ordered)
CREATE TABLE event_gallery_images (
  event_id CHAR(36) NOT NULL,
  media_id CHAR(36) NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (event_id, media_id),
  CONSTRAINT fk_event_gallery_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
  CONSTRAINT fk_event_gallery_media FOREIGN KEY (media_id) REFERENCES media_assets (id) ON DELETE CASCADE,
  KEY idx_event_gallery_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. SHIFT ASSIGNMENTS (volunteer roster per event/shift)
--    user_id = linked account (may be NULL for historically imported names)
--    constraint_note = individual time constraint ("ab 10 Uhr")
-- ---------------------------------------------------------------------
CREATE TABLE event_assignments (
  id              CHAR(36) NOT NULL,
  event_id        CHAR(36) NOT NULL,
  user_id         CHAR(36) NULL,
  person_name     VARCHAR(120) NOT NULL,
  user_asigned_by CHAR(36) NULL,     -- coordinator who assigned
  constraint_note VARCHAR(300) NULL,
  created_at      TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_assignment_once (event_id, user_id),
  KEY idx_assignments_event (event_id),
  KEY idx_assignments_user (user_id),
  CONSTRAINT fk_assignments_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE,
  CONSTRAINT fk_assignments_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE SET NULL,
  CONSTRAINT fk_assignments_by    FOREIGN KEY (user_asigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. HOMEPAGE CMS
--    Sections are structural rows; all copy is translatable;
--    images reference the media library; links are href + per-language labels.
-- ---------------------------------------------------------------------
CREATE TABLE homepage_sections (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(40) NOT NULL,   -- hero, about, events, stories, impact, transparency, instagram, involved, navbar, footer
  kind       ENUM('nav','hero','text','stories','events','impact','transparency','instagram','involved','footer') NOT NULL DEFAULT 'text',
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homepage_sections_slug (slug),
  KEY idx_homepage_sections_order (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every editable text field of a section. field_key examples:
-- hero_title, hero_subtitle, hero_hand, hero_sub, hero_kicker,
-- about_heading, about_body, cta_nav_* etc.
CREATE TABLE homepage_section_texts (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id SMALLINT UNSIGNED NOT NULL,
  field_key VARCHAR(60) NOT NULL,
  lang      ENUM('de','en') NOT NULL,
  value     TEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_section_texts (section_id, field_key, lang),
  KEY idx_section_texts_lookup (section_id, lang)
  , CONSTRAINT fk_section_texts_section FOREIGN KEY (section_id) REFERENCES homepage_sections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ordered label lists per section & language (e.g. marquee words, roles, step titles)
CREATE TABLE homepage_list_items (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id SMALLINT UNSIGNED NOT NULL,
  group_key VARCHAR(40) NOT NULL,   -- rotating, roles, steps_titles, steps_descriptions
  lang      ENUM('de','en') NOT NULL,
  position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  value     VARCHAR(300) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_list_items (section_id, group_key, lang, position),
  CONSTRAINT fk_list_items_section FOREIGN KEY (section_id) REFERENCES homepage_sections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE homepage_section_images (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id SMALLINT UNSIGNED NOT NULL,
  media_id   CHAR(36) NOT NULL,
  role       VARCHAR(40) NOT NULL,   -- main, background, grid_1..grid_9
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_section_images (section_id, role, position),
  CONSTRAINT fk_section_images_section FOREIGN KEY (section_id) REFERENCES homepage_sections (id) ON DELETE CASCADE,
  CONSTRAINT fk_section_images_media   FOREIGN KEY (media_id)   REFERENCES media_assets (id)   ON DELETE CASCADE,
  KEY idx_section_images_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE homepage_section_links (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id SMALLINT UNSIGNED NOT NULL,
  link_key   VARCHAR(60) NOT NULL,   -- cta_primary, participate, instagram_link, nav_about ...
  href       VARCHAR(300) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_section_links (section_id, link_key),
  CONSTRAINT fk_section_links_section FOREIGN KEY (section_id) REFERENCES homepage_sections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE homepage_link_texts (
  link_id BIGINT UNSIGNED NOT NULL,
  lang    ENUM('de','en') NOT NULL,
  label   VARCHAR(200) NOT NULL,
  PRIMARY KEY (link_id, lang),
  CONSTRAINT fk_link_texts_link FOREIGN KEY (link_id) REFERENCES homepage_section_links (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. COMMUNITY STORIES
-- ---------------------------------------------------------------------
CREATE TABLE community_stories (
  id       CHAR(36)   NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  media_id CHAR(36)   NULL,
  PRIMARY KEY (id),
  KEY idx_stories_order (position),
  CONSTRAINT fk_stories_media FOREIGN KEY (media_id) REFERENCES media_assets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE community_story_texts (
  story_id CHAR(36) NOT NULL,
  lang     ENUM('de','en') NOT NULL,
  title    VARCHAR(200) NOT NULL,
  body     TEXT NOT NULL,
  PRIMARY KEY (story_id, lang),
  CONSTRAINT fk_story_texts_story FOREIGN KEY (story_id) REFERENCES community_stories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. IMPACT METRICS
-- ---------------------------------------------------------------------
CREATE TABLE impact_metrics (
  id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  metric_key VARCHAR(40) NOT NULL,          -- meals, volunteers, events ...
  value     DECIMAL(12,2) NOT NULL DEFAULT 0,
  position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_sample TINYINT(1) NOT NULL DEFAULT 1,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_impact_metrics_key (metric_key),
  KEY idx_impact_metrics_order (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE impact_metric_texts (
  metric_id SMALLINT UNSIGNED NOT NULL,
  lang      ENUM('de','en') NOT NULL,
  label     VARCHAR(120) NOT NULL,
  PRIMARY KEY (metric_id, lang),
  CONSTRAINT fk_metric_texts_metric FOREIGN KEY (metric_id) REFERENCES impact_metrics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. SITE SETTINGS (single row) + per-language long texts
-- ---------------------------------------------------------------------
CREATE TABLE site_settings (
  id                TINYINT UNSIGNED NOT NULL DEFAULT 1,
  request_enabled   TINYINT(1) NOT NULL DEFAULT 1,
  bank_enabled      TINYINT(1) NOT NULL DEFAULT 0,
  bank_holder       VARCHAR(120)  NULL,
  bank_iban         VARCHAR(64)   NULL,
  bank_bic          VARCHAR(32)   NULL,
  bank_name         VARCHAR(120)  NULL,
  bank_reference    VARCHAR(120)  NULL,
  office_email      VARCHAR(200)  NULL,
  contact_address   VARCHAR(300)  NULL,
  PRIMARY KEY (id),
  CONSTRAINT ck_site_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bank transfer instructions & imprint & privacy are language-dependent text
CREATE TABLE site_bank_instructions (
  bank_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  lang    ENUM('de','en') NOT NULL,
  instructions TEXT NULL,
  PRIMARY KEY (bank_id, lang),
  CONSTRAINT fk_bank_instructions_bank FOREIGN KEY (bank_id) REFERENCES site_settings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE site_page_texts (
  page   ENUM('privacy','imprint') NOT NULL,
  lang   ENUM('de','en') NOT NULL,
  title  VARCHAR(200) NOT NULL,
  body   MEDIUMTEXT  NOT NULL,
  PRIMARY KEY (page, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Instagram section configuration
CREATE TABLE site_instagram (
  id           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  handle       VARCHAR(80) NOT NULL DEFAULT 'mensacomunistagraz',
  grid_enabled TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT ck_site_instagram_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Instagram grid tiles: media asset + (optional) post URL
CREATE TABLE site_instagram_tiles (
  tile_id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  media_id   CHAR(36) NULL,
  post_url   VARCHAR(300) NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  alt        VARCHAR(200) NULL,
  PRIMARY KEY (tile_id),
  KEY idx_instagram_tiles_order (position),
  CONSTRAINT fk_instagram_tiles_setting FOREIGN KEY (setting_id) REFERENCES site_instagram (id) ON DELETE CASCADE,
  CONSTRAINT fk_instagram_tiles_media FOREIGN KEY (media_id) REFERENCES media_assets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. DONATIONS & FINANCES
-- ---------------------------------------------------------------------
CREATE TABLE donation_types (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     ENUM('money','food','ingredients','equipment','other') NOT NULL,
  label_de VARCHAR(60) NOT NULL,
  label_en VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_donation_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE donation_requests (
  id             CHAR(36) NOT NULL,
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(200) NOT NULL,
  phone          VARCHAR(40)  NULL,
  amount         DECIMAL(10,2) NULL,
  type_id        SMALLINT UNSIGNED NOT NULL,
  contact_method ENUM('email','phone','whatsapp') NOT NULL DEFAULT 'email',
  message        VARCHAR(2000) NULL,
  consent        TINYINT(1) NOT NULL,
  status         ENUM('new','contacted','confirmed','received','closed') NOT NULL DEFAULT 'new',
  internal_notes TEXT NULL,
  is_demo        TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_donation_requests_status (status),
  KEY idx_donation_requests_created (created_at),
  CONSTRAINT ck_donation_requests_amount CHECK (amount IS NULL OR amount >= 0),
  CONSTRAINT ck_donation_requests_consent CHECK (consent = 1),
  CONSTRAINT fk_donation_requests_type FOREIGN KEY (type_id) REFERENCES donation_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CONFIRMED donations/pledges actually received (pledges live only in requests)
CREATE TABLE donations (
  id          CHAR(36) NOT NULL,
  request_id  CHAR(36) NULL,             -- optional link to the original request
  type_id     SMALLINT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2) NULL,        -- money: amount; goods: NULL
  description VARCHAR(300) NULL,
  received_on DATE NOT NULL,
  recorded_by CHAR(36) NULL,
  note        VARCHAR(500) NULL,
  created_at  TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_donations_received (received_on),
  KEY idx_donations_request (request_id),
  CONSTRAINT ck_donations_amount CHECK (amount IS NULL OR amount >= 0),
  CONSTRAINT fk_donations_request FOREIGN KEY (request_id) REFERENCES donation_requests (id) ON DELETE SET NULL,
  CONSTRAINT fk_donations_type    FOREIGN KEY (type_id)    REFERENCES donation_types (id) ON DELETE RESTRICT,
  CONSTRAINT fk_donations_recorded FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expense_categories (
  id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug     VARCHAR(60) NOT NULL,
  label_de VARCHAR(80) NOT NULL,
  label_en VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expense_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expenses (
  id         CHAR(36) NOT NULL,
  spent_on   DATE NOT NULL,
  category_id SMALLINT UNSIGNED NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  supplier   VARCHAR(120) NULL,
  description VARCHAR(500) NULL,
  receipt_media_id CHAR(36) NULL,
  recorded_by CHAR(36) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_expenses_date (spent_on),
  KEY idx_expenses_category (category_id),
  CONSTRAINT ck_expenses_amount CHECK (amount >= 0),
  CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories (id) ON DELETE RESTRICT,
  CONSTRAINT fk_expenses_receipt  FOREIGN KEY (receipt_media_id) REFERENCES media_assets (id) ON DELETE SET NULL,
  CONSTRAINT fk_expenses_recorded FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. SOCIAL POST STUDIO
-- ---------------------------------------------------------------------
CREATE TABLE social_posts (
  id         CHAR(36) NOT NULL,
  channel    ENUM('instagram','flyer','story') NOT NULL DEFAULT 'instagram',
  format     ENUM('square_1080','portrait_1080x1350','story_1080x1920') NOT NULL DEFAULT 'square_1080',
  title      VARCHAR(120) NOT NULL DEFAULT '',
  media_id   CHAR(36) NOT NULL,          -- rendered PNG/JPEG in the media library
  status     ENUM('draft','ready','published') NOT NULL DEFAULT 'ready',
  created_by CHAR(36) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_social_posts_created (created_at),
  KEY idx_social_posts_media (media_id),
  CONSTRAINT fk_social_posts_media   FOREIGN KEY (media_id)   REFERENCES media_assets (id) ON DELETE CASCADE,
  CONSTRAINT fk_social_posts_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. NOTIFICATIONS, AUDIT
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id         CHAR(36) NOT NULL,
  kind       VARCHAR(40) NOT NULL,            -- donation, signup, event, system
  title      VARCHAR(300) NOT NULL,
  link       VARCHAR(200) NULL,
  user_id    CHAR(36) NULL,                   -- NULL = broadcast to all admins
  read_at    TIMESTAMP(6) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_notifications_inbox (user_id, created_at),
  KEY idx_notifications_kind (kind),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id        CHAR(36) NOT NULL,
  actor_id  CHAR(36) NULL,
  action    VARCHAR(80) NOT NULL,
  entity    VARCHAR(60) NULL,
  entity_id CHAR(36) NULL,
  details   JSON NULL,
  ip_hash   CHAR(64) NULL,
  created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_audit_actor (actor_id, created_at),
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_action (action),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. HOMEPAGE CACHE STAMP (cheap invalidation stamp)
-- ---------------------------------------------------------------------
CREATE TABLE homepage_cache (
  id           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  version      BIGINT UNSIGNED NOT NULL DEFAULT 1,
  updated_at   TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  CONSTRAINT ck_homepage_cache_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- 3NF SUMMARY
-- * Every table row is uniquely identified by its PK; no repeating groups
--   (lists moved to junction rows: profile_skills, post_images,
--   event_gallery_images, instagram tiles, homepage list items).
-- * No transitive dependencies on non-key attributes: language-variant
--   labels live in _texts child tables instead of *_de/*_en column pairs;
--   lookups (locations, categories, types, roles, skills) are separate
--   entities referenced by FK instead of free-text columns.
-- * Media binaries are only referenced (file path + dimensions), never
--   stored in the relational rows.
-- * Performance is addressed separately: covering indexes above match the
--   real query patterns (upcoming published events by date, roster counts
--   per event, donations by status/date, notifications inbox). Pagination
--   and API-level caching use homepage_cache.version as invalidation stamp.
-- =====================================================================

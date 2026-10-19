-- =====================================================================
-- MENSA COMUNISTA GRAZ — seed data (MySQL/MariaDB compatible)
-- Seeds: lookups, CMS content with the CURRENT LIVE homepage copy
-- (German/English exactly as the approved Lovable design displays it),
-- the October 2026 duty schedule imported from the original Supabase
-- migration (drizzle/migrations/0001_migration.sql), demo donation
-- requests and a demo social post.
-- NOTE: no user accounts are seeded here. Create the first admin with
--       `php backend/tools/make_admin.php` after importing.
-- FRESH DATABASES ONLY: these INSERT statements are not idempotent. Import
-- only after schema.sql into a new database; never re-import into production.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Roles
-- ---------------------------------------------------------------------
INSERT INTO roles (slug, label_de, label_en) VALUES
 ('admin','Administrator:in','Administrator'),
 ('coordinator','Koordination','Coordinator'),
 ('volunteer','Freiwillige:r','Volunteer');

-- ---------------------------------------------------------------------
-- Skills (free-text lists from the old profiles.skills get mapped on import)
-- ---------------------------------------------------------------------
INSERT INTO skills (slug, label_de, label_en) VALUES
 ('cooking','Kochen','Cooking'),
 ('serving','Ausgabe','Serving'),
 ('dishwashing','Abwasch','Dishwashing'),
 ('shopping','Einkaufen','Shopping'),
 ('photography','Fotografie','Photography'),
 ('video','Video & Schnitt','Video & editing'),
 ('events','Events','Events'),
 ('other','Sonstiges','Other');

-- ---------------------------------------------------------------------
-- Locations & activity categories
-- ---------------------------------------------------------------------
INSERT INTO event_locations (slug, name, address, city) VALUES
 ('volkshaus','Volkshaus','Lagergasse 98a','Graz'),
 ('sonnenfelsplatz','Uni Sonnenfelsplatz',NULL,'Graz');

INSERT INTO activity_categories (slug, label_de, label_en) VALUES
 ('shopping','Einkaufen','Shopping'),
 ('cooking','Kochen','Cooking'),
 ('preparation','Vorbereitung','Preparation'),
 ('delivery','Lieferung','Delivery'),
 ('serving','Ausgabe','Serving'),
 ('cleaning','Reinigung','Cleaning'),
 ('dishwashing','Abwasch','Dishwashing'),
 ('photography','Fotografie','Photography'),
 ('video','Video & Schnitt','Video & editing'),
 ('editing','Schnitt','Editing'),
 ('other','Sonstiges','Other');

-- ---------------------------------------------------------------------
-- Donation types & expense categories
-- ---------------------------------------------------------------------
INSERT INTO donation_types (slug, label_de, label_en) VALUES
 ('money','Geld','Money'),
 ('food','Lebensmittel','Food'),
 ('ingredients','Zutaten','Ingredients'),
 ('equipment','Ausstattung','Equipment'),
 ('other','Sonstiges','Other');

INSERT INTO expense_categories (slug, label_de, label_en) VALUES
 ('groceries','Einkäufe','Groceries'),
 ('kitchen','Küchenausstattung','Kitchen supplies'),
 ('transport','Transport','Transport'),
 ('printing','Druck & Material','Printing & material'),
 ('other','Sonstiges','Other');

-- ---------------------------------------------------------------------
-- Homepage sections (structure) — slugs are stable API ids
-- ---------------------------------------------------------------------
INSERT INTO homepage_sections (slug, kind, position, is_visible) VALUES
 ('navbar',      'nav',          0, 1),
 ('hero',        'hero',         1, 1),
 ('kitchen',     'text',         2, 1),
 ('events',      'events',       3, 1),
 ('stories',     'stories',      4, 1),
 ('impact',      'impact',       5, 1),
 ('transparency','transparency', 6, 1),
 ('instagram',   'instagram',    7, 1),
 ('involved',    'involved',     8, 1),
 ('footer',      'footer',       9, 1);

-- Section texts — VALUES BELOW EQUAL THE CURRENT LIVE COPY.
INSERT INTO homepage_section_texts (section_id, field_key, lang, value)
SELECT s.id, x.field_key, x.lang, x.value
FROM (
 SELECT 'navbar' AS slug,'title' AS field_key,'de' AS lang,'Mensa Comunista Graz' AS value
 UNION ALL SELECT 'navbar','title','en','Mensa Comunista Graz'
 UNION ALL SELECT 'hero','kicker','de','Gratis essen. Gemeinsam kochen.'
 UNION ALL SELECT 'hero','kicker','en','Free food. Cooked together.'
 UNION ALL SELECT 'hero','title_line1','de','Mensa'
 UNION ALL SELECT 'hero','title_line1','en','Mensa'
 UNION ALL SELECT 'hero','title_line2','de','Comunista.'
 UNION ALL SELECT 'hero','title_line2','en','Comunista.'
 UNION ALL SELECT 'hero','hand_line','de','Essen für alle. Solidarität für immer.'
 UNION ALL SELECT 'hero','hand_line','en','Food for everyone. Solidarity, always.'
 UNION ALL SELECT 'hero','sub','de','Mensa Comunista Graz kocht jede Woche frische Mahlzeiten und teilt sie kostenlos aus – organisiert von Freiwilligen, getragen von Solidarität.'
 UNION ALL SELECT 'hero','sub','en','Mensa Comunista Graz cooks fresh meals every week and hands them out for free – organized by volunteers, powered by solidarity.'
 UNION ALL SELECT 'hero','marquee_note','de','Selbstorganisiert. Kostenlos. Für alle.'
 UNION ALL SELECT 'hero','marquee_note','en','Self-organized. Free. For everyone.'
 UNION ALL SELECT 'hero','hero_alt','de','Gemeinsam kochen in der Volksküche'
 UNION ALL SELECT 'hero','hero_alt','en','Cooking together in the community kitchen'
 UNION ALL SELECT 'kitchen','kicker_number','de','01'
 UNION ALL SELECT 'kitchen','kicker_number','en','01'
 UNION ALL SELECT 'kitchen','kicker_label','de','Über uns'
 UNION ALL SELECT 'kitchen','kicker_label','en','About'
 UNION ALL SELECT 'kitchen','heading','de','Solidarität geht'
 UNION ALL SELECT 'kitchen','heading','en','Solidarity starts'
 UNION ALL SELECT 'kitchen','heading_accent','de','durch den Magen.'
 UNION ALL SELECT 'kitchen','heading_accent','en','at the table.'
 UNION ALL SELECT 'kitchen','body','de','Wir kaufen gemeinsam ein, kochen im Volkshaus Graz und geben das Essen an der Uni am Sonnenfelsplatz aus. Jede:r kann mitmachen – ohne Vorkenntnisse.'
 UNION ALL SELECT 'kitchen','body','en','We shop together, cook at Volkshaus Graz and serve the food at the university on Sonnenfelsplatz. Anyone can join – no experience needed.'
 UNION ALL SELECT 'kitchen','note','de','Kein Eintritt. Keine Voraussetzungen. Nur Menschen, die gemeinsam etwas bewegen.'
 UNION ALL SELECT 'kitchen','note','en','No entry fee. No requirements. Just people making a difference together.'
 UNION ALL SELECT 'events','title','de','Nächste Termine'
 UNION ALL SELECT 'events','title','en','Upcoming dates'
 UNION ALL SELECT 'impact','title','de','Unsere Wirkung'
 UNION ALL SELECT 'impact','title','en','Our impact'
 UNION ALL SELECT 'transparency','title','de','Spenden-Transparenz'
 UNION ALL SELECT 'transparency','title','en','Donation transparency'
 UNION ALL SELECT 'transparency','sub','de','Nur bestätigte Eingänge und erfasste Ausgaben – ohne persönliche Daten.'
 UNION ALL SELECT 'transparency','sub','en','Only confirmed receipts and recorded expenses – no personal data.'
 UNION ALL SELECT 'transparency','label_received','de','Bestätigt erhalten'
 UNION ALL SELECT 'transparency','label_received','en','Confirmed received'
 UNION ALL SELECT 'transparency','label_spent','de','Ausgegeben'
 UNION ALL SELECT 'transparency','label_spent','en','Spent'
 UNION ALL SELECT 'transparency','label_available','de','Verfügbar'
 UNION ALL SELECT 'transparency','label_available','en','Available'
 UNION ALL SELECT 'instagram','title','de','Nicht nur am Herd.'
 UNION ALL SELECT 'instagram','title','en','Beyond the kitchen.'
 UNION ALL SELECT 'instagram','kicker_label','de','06 / Instagram'
 UNION ALL SELECT 'instagram','kicker_label','en','06 / Instagram'
 UNION ALL SELECT 'instagram','note','de','Illustrative Bilder · keine Live-Instagram-Posts'
 UNION ALL SELECT 'instagram','note','en','Illustrative images · not live Instagram posts'
 UNION ALL SELECT 'instagram','alt','de','Illustrative Gemeinschaftsfotografie'
 UNION ALL SELECT 'instagram','alt','en','Illustrative community photography'
 UNION ALL SELECT 'instagram','follow_note','de','Live-Einbettung noch nicht eingerichtet. Folge uns direkt auf Instagram für aktuelle Posts.'
 UNION ALL SELECT 'instagram','follow_note','en','Live embedding not configured yet. Follow us directly on Instagram for the latest posts.'
 UNION ALL SELECT 'involved','kicker','de','Deine Hände machen den Unterschied'
 UNION ALL SELECT 'involved','kicker','en','Your hands make a difference'
 UNION ALL SELECT 'involved','title','de','Mach mit!'
 UNION ALL SELECT 'involved','title','en','Join us!'
 UNION ALL SELECT 'involved','sub','de','Komm einfach vorbei oder schreib uns. Wir suchen Menschen für:'
 UNION ALL SELECT 'involved','sub','en','Just drop by or write to us. We''re looking for people for:'
 UNION ALL SELECT 'footer','tag','de','Solidarität geht durch den Magen.'
 UNION ALL SELECT 'footer','tag','en','Solidarity goes through the stomach.'
) AS x
JOIN homepage_sections s ON s.slug = x.slug;

-- Section lists (marquee words, roles, step cards)
INSERT INTO homepage_list_items (section_id, group_key, lang, position, value)
SELECT s.id, x.group_key, x.lang, x.ord, x.value
FROM (
 SELECT 'hero' AS slug,'rotating' AS group_key,'de' AS lang,0 AS ord,'Solidarisch' AS value
 UNION ALL SELECT 'hero','rotating','de',1,'Kostenlos'
 UNION ALL SELECT 'hero','rotating','de',2,'Selbstorganisiert'
 UNION ALL SELECT 'hero','rotating','de',3,'Für alle'
 UNION ALL SELECT 'hero','rotating','en',0,'Solidarity'
 UNION ALL SELECT 'hero','rotating','en',1,'Free'
 UNION ALL SELECT 'hero','rotating','en',2,'Self-organized'
 UNION ALL SELECT 'hero','rotating','en',3,'For everyone'
 UNION ALL SELECT 'involved','roles','de',0,'Kochen'
 UNION ALL SELECT 'involved','roles','de',1,'Ausgabe'
 UNION ALL SELECT 'involved','roles','de',2,'Abwasch'
 UNION ALL SELECT 'involved','roles','de',3,'Einkaufen'
 UNION ALL SELECT 'involved','roles','de',4,'Fotografie'
 UNION ALL SELECT 'involved','roles','de',5,'Video & Schnitt'
 UNION ALL SELECT 'involved','roles','de',6,'Events'
 UNION ALL SELECT 'involved','roles','en',0,'Cooking'
 UNION ALL SELECT 'involved','roles','en',1,'Serving'
 UNION ALL SELECT 'involved','roles','en',2,'Dishwashing'
 UNION ALL SELECT 'involved','roles','en',3,'Shopping'
 UNION ALL SELECT 'involved','roles','en',4,'Photography'
 UNION ALL SELECT 'involved','roles','en',5,'Video & editing'
 UNION ALL SELECT 'involved','roles','en',6,'Events'
 UNION ALL SELECT 'kitchen','steps_titles','de',0,'Einkaufen'
 UNION ALL SELECT 'kitchen','steps_titles','de',1,'Kochen'
 UNION ALL SELECT 'kitchen','steps_titles','de',2,'Ausgeben'
 UNION ALL SELECT 'kitchen','steps_titles','de',3,'Abwaschen'
 UNION ALL SELECT 'kitchen','steps_titles','en',0,'Shopping'
 UNION ALL SELECT 'kitchen','steps_titles','en',1,'Cooking'
 UNION ALL SELECT 'kitchen','steps_titles','en',2,'Serving'
 UNION ALL SELECT 'kitchen','steps_titles','en',3,'Cleaning up'
 UNION ALL SELECT 'kitchen','steps_descriptions','de',0,'Am Vortag besorgen wir Zutaten.'
 UNION ALL SELECT 'kitchen','steps_descriptions','de',1,'Ab 8 Uhr im Volkshaus, Lagergasse 98a.'
 UNION ALL SELECT 'kitchen','steps_descriptions','de',2,'Mittags an der Uni am Sonnenfelsplatz.'
 UNION ALL SELECT 'kitchen','steps_descriptions','de',3,'Danach wird gemeinsam aufgeräumt.'
 UNION ALL SELECT 'kitchen','steps_descriptions','en',0,'The day before, we buy ingredients.'
 UNION ALL SELECT 'kitchen','steps_descriptions','en',1,'From 8 am at Volkshaus, Lagergasse 98a.'
 UNION ALL SELECT 'kitchen','steps_descriptions','en',2,'At lunchtime at Uni Sonnenfelsplatz.'
 UNION ALL SELECT 'kitchen','steps_descriptions','en',3,'Afterwards we clean up together.'
) AS x
JOIN homepage_sections s ON s.slug = x.slug;

-- Section links/CTAs (labels via homepage_link_texts)
INSERT INTO homepage_section_links (section_id, link_key, href)
SELECT s.id, x.link_key, x.href
FROM (
 SELECT 'navbar' AS slug,'nav_about' AS link_key,'#about' AS href
 UNION ALL SELECT 'navbar','nav_kitchen','#kitchen'
 UNION ALL SELECT 'navbar','nav_impact','#impact'
 UNION ALL SELECT 'navbar','nav_events','#events'
 UNION ALL SELECT 'navbar','nav_instagram','#instagram'
 UNION ALL SELECT 'hero','cta_primary','/teams'
 UNION ALL SELECT 'hero','cta_secondary','donate:dialog'
 UNION ALL SELECT 'events','planner_cta','/teams'
 UNION ALL SELECT 'involved','join_cta','/teams'
) AS x
JOIN homepage_sections s ON s.slug = x.slug;

INSERT INTO homepage_link_texts (link_id, lang, label)
SELECT l.id, x.lang, x.label
FROM (
 SELECT 'navbar' AS slug,'nav_about' AS link_key,'de' AS lang,'Über uns' AS label
 UNION ALL SELECT 'navbar','nav_about','en','About'
 UNION ALL SELECT 'navbar','nav_kitchen','de','Volksküche'
 UNION ALL SELECT 'navbar','nav_kitchen','en','Community Kitchen'
 UNION ALL SELECT 'navbar','nav_impact','de','Wirkung'
 UNION ALL SELECT 'navbar','nav_impact','en','Impact'
 UNION ALL SELECT 'navbar','nav_events','de','Termine'
 UNION ALL SELECT 'navbar','nav_events','en','Events'
 UNION ALL SELECT 'navbar','nav_instagram','de','Instagram'
 UNION ALL SELECT 'navbar','nav_instagram','en','Instagram'
 UNION ALL SELECT 'hero','cta_primary','de','Mitmachen'
 UNION ALL SELECT 'hero','cta_primary','en','Get involved'
 UNION ALL SELECT 'hero','cta_secondary','de','Jetzt spenden'
 UNION ALL SELECT 'hero','cta_secondary','en','Donate now'
 UNION ALL SELECT 'events','planner_cta','de','Zum Schichtplan'
 UNION ALL SELECT 'events','planner_cta','en','View the planner'
 UNION ALL SELECT 'involved','join_cta','de','Teil des Teams werden'
 UNION ALL SELECT 'involved','join_cta','en','Join the team'
) AS x
JOIN homepage_sections s ON s.slug = x.slug
JOIN homepage_section_links l ON l.section_id = s.id AND l.link_key = x.link_key;

-- Community stories (same copy the live page shows; media NULL = falls back
-- to the bundled asset, preserving the current visuals)
INSERT INTO community_stories (id, position, is_visible, media_id) VALUES
 ('00000000-0000-7000-8000-000000000001', 0, 1, NULL),
 ('00000000-0000-7000-8000-000000000002', 1, 1, NULL),
 ('00000000-0000-7000-8000-000000000003', 2, 1, NULL);

INSERT INTO community_story_texts (story_id, lang, title, body) VALUES
 ('00000000-0000-7000-8000-000000000001','de','Eine Küche. Viele Hände.','Wir kaufen gemeinsam ein, kochen im Volkshaus Graz und geben das Essen an der Uni am Sonnenfelsplatz aus. Jede:r kann mitmachen – ohne Vorkenntnisse.'),
 ('00000000-0000-7000-8000-000000000001','en','One kitchen. Many hands.','We shop together, cook at Volkshaus Graz and serve the food at the university on Sonnenfelsplatz. Anyone can join – no experience needed.'),
 ('00000000-0000-7000-8000-000000000002','de','Ein Teller Solidarität.','Gutes Essen soll kein Privileg sein. Wir teilen frische Mahlzeiten – kostenlos und ohne Bedingungen.'),
 ('00000000-0000-7000-8000-000000000002','en','A plate of solidarity.','Good food should not be a privilege. We share fresh meals, free and without conditions.'),
 ('00000000-0000-7000-8000-000000000003','de','Gemeinsam statt allein.','Komm einfach vorbei oder schreib uns. Wir suchen Menschen für Kochen, Ausgabe, Abwasch, Einkaufen, Fotografie, Video & Schnitt und Events.'),
 ('00000000-0000-7000-8000-000000000003','en','Together, not alone.','Just drop by or write to us. We are looking for people for cooking, serving, dishwashing, shopping, photography, video & editing and events.');

-- Impact metrics (same keys/labels/zero values as the live Supabase seed)
INSERT INTO impact_metrics (metric_key, value, position, is_sample) VALUES
 ('meals', 0.00, 1, 1),
 ('volunteers', 0.00, 2, 1),
 ('events', 0.00, 3, 1);

INSERT INTO impact_metric_texts (metric_id, lang, label)
SELECT m.id, x.lang, x.label
 FROM (
 SELECT 'meals' AS metric_key,'de' AS lang,'Mahlzeiten ausgegeben' AS label
 UNION ALL SELECT 'meals','en','Meals served'
 UNION ALL SELECT 'volunteers','de','Freiwillige'
 UNION ALL SELECT 'volunteers','en','Volunteers'
 UNION ALL SELECT 'events','de','Aktionen durchgeführt'
 UNION ALL SELECT 'events','en','Actions completed'
) AS x
JOIN impact_metrics m ON m.metric_key = x.metric_key;

-- Site settings singleton (bank data intentionally left EMPTY — the owner
-- must provide real details; nothing is invented here)
INSERT INTO site_settings (id) VALUES (1);
INSERT INTO site_bank_instructions (bank_id, lang, instructions) VALUES (1,'de',NULL),(1,'en',NULL);

INSERT INTO site_instagram (id, handle, grid_enabled) VALUES (1, 'mensacomunistagraz', 1);

-- Legal pages: minimal placeholders the admins MUST replace with real
-- content (no legal text is invented here).
INSERT INTO site_page_texts (page, lang, title, body) VALUES
 ('privacy','de','Datenschutz','<[Platzhalter] Die vollständige Datenschutzerklärung wird von der Redaktion hinterlegt.>'),
 ('privacy','en','Privacy','<[Placeholder] The full privacy statement will be provided by the team.>'),
 ('imprint','de','Impressum','<[Platzhalter] Impressumsangaben werden von der Redaktion hinterlegt.>'),
 ('imprint','en','Imprint','<[Placeholder] Imprint details will be provided by the team.>');

-- (social post templates are handled by the studio UI; no seed rows)

-- ---------------------------------------------------------------------
-- OCTOBER 2026 SCHEDULE — imported 1:1 from drizzle/migrations/0001.
-- Original flagged ambiguities are preserved (needs_review + review_note).
-- ---------------------------------------------------------------------
SET @loc_volkshaus := (SELECT id FROM event_locations WHERE slug='volkshaus');
SET @loc_sonnenfels := (SELECT id FROM event_locations WHERE slug='sonnenfelsplatz');

INSERT INTO events (id, event_date, start_time, end_time, location_id, meeting_point, volunteers_needed, status, is_published, is_demo) VALUES
 ('00000000-0000-7000-8000-000000000101','2026-10-06','08:00:00','13:00:00',@loc_volkshaus,'Volkshaus',NULL,'open',1,1), ('00000000-0000-7000-8000-000000000102','2026-10-06','13:00:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',NULL,'open',1,1),
 ('00000000-0000-7000-8000-000000000103','2026-10-07','08:00:00','13:00:00',@loc_volkshaus,'Volkshaus',NULL,'open',1,1),
 ('00000000-0000-7000-8000-000000000104','2026-10-07','13:00:00','16:00:00',@loc_sonnenfels,NULL,NULL,'open',1,1),
 ('00000000-0000-7000-8000-000000000105','2026-10-14','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000106','2026-10-14','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000107','2026-10-14','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000108','2026-10-13',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1),
 ('00000000-0000-7000-8000-000000000109','2026-10-21','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000110','2026-10-21','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000111','2026-10-21','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000112','2026-10-20',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1),
 ('00000000-0000-7000-8000-000000000113','2026-10-28','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000114','2026-10-28','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000115','2026-10-28','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000116','2026-10-27',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1),
 ('00000000-0000-7000-8000-000000000117','2026-11-04','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000118','2026-11-04','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000119','2026-11-04','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000120','2026-11-03',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1),
 ('00000000-0000-7000-8000-000000000121','2026-11-11','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000122','2026-11-11','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000123','2026-11-11','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000124','2026-11-10',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1),
 ('00000000-0000-7000-8000-000000000125','2026-11-18','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000126','2026-11-18','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000127','2026-11-18','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000128','2026-11-17',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1),
 ('00000000-0000-7000-8000-000000000129','2026-11-25','08:00:00','12:30:00',@loc_volkshaus,'Volkshaus',10,'open',1,1),
 ('00000000-0000-7000-8000-000000000130','2026-11-25','12:30:00','16:00:00',@loc_sonnenfels,'Sonnenfelsplatz',7,'open',1,1),
 ('00000000-0000-7000-8000-000000000131','2026-11-25','16:00:00','18:00:00',@loc_volkshaus,'Volkshaus',4,'open',1,1),
 ('00000000-0000-7000-8000-000000000132','2026-11-24',NULL,NULL,@loc_volkshaus,'Volkshaus',3,'open',1,1);

-- activity categories per shift type (from the original activity_type values)
SET @act_cooking := (SELECT id FROM activity_categories WHERE slug='cooking');
SET @act_serving := (SELECT id FROM activity_categories WHERE slug='serving');
SET @act_wash    := (SELECT id FROM activity_categories WHERE slug='dishwashing');
SET @act_shop    := (SELECT id FROM activity_categories WHERE slug='shopping');
UPDATE events SET activity_category_id = CASE
  WHEN id IN ('00000000-0000-7000-8000-000000000101','00000000-0000-7000-8000-000000000103','00000000-0000-7000-8000-000000000105','00000000-0000-7000-8000-000000000109','00000000-0000-7000-8000-000000000113','00000000-0000-7000-8000-000000000117','00000000-0000-7000-8000-000000000121','00000000-0000-7000-8000-000000000125','00000000-0000-7000-8000-000000000129') THEN @act_cooking
  WHEN id IN ('00000000-0000-7000-8000-000000000102','00000000-0000-7000-8000-000000000104','00000000-0000-7000-8000-000000000106','00000000-0000-7000-8000-000000000110','00000000-0000-7000-8000-000000000114','00000000-0000-7000-8000-000000000118','00000000-0000-7000-8000-000000000122','00000000-0000-7000-8000-000000000126','00000000-0000-7000-8000-000000000130') THEN @act_serving
  WHEN id IN ('00000000-0000-7000-8000-000000000107','00000000-0000-7000-8000-000000000111','00000000-0000-7000-8000-000000000115','00000000-0000-7000-8000-000000000119','00000000-0000-7000-8000-000000000123','00000000-0000-7000-8000-000000000127','00000000-0000-7000-8000-000000000131') THEN @act_wash
  ELSE @act_shop END;

-- Event titles + Zusatzinfos (German source values from the original import;
-- English mirrors German because no English source existed in the data)
INSERT INTO event_translations (event_id, lang, title, extra_info) VALUES
 ('00000000-0000-7000-8000-000000000101','de','Schicht 1 (Kochen und Aufbau)','ca 8-13 Uhr, im Volkshaus Graz, Lagergasse 98a'),
 ('00000000-0000-7000-8000-000000000101','en','Schicht 1 (Kochen und Aufbau)','ca 8-13 Uhr, im Volkshaus Graz, Lagergasse 98a'),
 ('00000000-0000-7000-8000-000000000102','de','Schicht 2 (Ausgabe, Abbau, Aufräumen)','ca 13-16 Uhr, Uni Sonnenfelsplatz'),
 ('00000000-0000-7000-8000-000000000102','en','Schicht 2 (Ausgabe, Abbau, Aufräumen)','ca 13-16 Uhr, Uni Sonnenfelsplatz'),
 ('00000000-0000-7000-8000-000000000103','de','Schicht 1 (Kochen und Aufbau)','ca 8-13 Uhr, im Volkshaus Graz, Lagergasse 98a'),
 ('00000000-0000-7000-8000-000000000103','en','Schicht 1 (Kochen und Aufbau)','ca 8-13 Uhr, im Volkshaus Graz, Lagergasse 98a'),
 ('00000000-0000-7000-8000-000000000104','de','Schicht 2 (Ausgabe, Abbau, Aufräumen)','ca 13-16 Uhr, Uni Sonnenfelsplatz'),
 ('00000000-0000-7000-8000-000000000104','en','Schicht 2 (Ausgabe, Abbau, Aufräumen)','ca 13-16 Uhr, Uni Sonnenfelsplatz'),
 ('00000000-0000-7000-8000-000000000105','de','8-12:30 (Kochen & Liefern) (~10 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000105','en','8-12:30 (Kochen & Liefern) (~10 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000106','de','12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000106','en','12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000107','de','16-18 Uhr (Abwaschen) (~ 4 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000107','en','16-18 Uhr (Abwaschen) (~ 4 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000108','de','Einkaufen (~ 3 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000108','en','Einkaufen (~ 3 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000109','de','8-12:30 (Kochen & Liefern) (~10 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000109','en','8-12:30 (Kochen & Liefern) (~10 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000110','de','12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000110','en','12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000111','de','16-18 Uhr (Abwaschen) (~ 4 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000111','en','16-18 Uhr (Abwaschen) (~ 4 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000112','de','Einkaufen (~ 3 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000112','en','Einkaufen (~ 3 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000113','de','8-12:30 (Kochen & Liefern) (~10 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000113','en','8-12:30 (Kochen & Liefern) (~10 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000114','de','12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000114','en','12:3 - 16 Uhr (Aufbau & Ausgabe) (~ 7 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000115','de','16-18 Uhr (Abwaschen) (~ 4 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000115','en','16-18 Uhr (Abwaschen) (~ 4 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000116','de','Einkaufen (~ 3 Personen)',NULL),
 ('00000000-0000-7000-8000-000000000116','en','Einkaufen (~ 3 Personen)',NULL);

-- Review flags preserved from the original import (ambiguous source rows)
UPDATE events SET needs_review = 1, review_note = 'In der Vorlage folgt eine Zeile nur mit Treffpunkt "Volkshaus" ohne Datum/Aktivität'
 WHERE id = '00000000-0000-7000-8000-000000000102';
UPDATE events SET needs_review = 1, review_note = 'Treffpunkt fehlt in der Vorlage'
 WHERE id = '00000000-0000-7000-8000-000000000104';
UPDATE events SET needs_review = 1, review_note = 'Originalzeit "12:3" – vermutlich 12:30'
 WHERE id IN ('00000000-0000-7000-8000-000000000106','00000000-0000-7000-8000-000000000110');
UPDATE events SET needs_review = 1, review_note = 'Originalzeit "12:3" – vermutlich 12:30; Eintrag "Emily (" in der Vorlage unvollständig'
 WHERE id = '00000000-0000-7000-8000-000000000114';

-- ---------------------------------------------------------------------
-- Assignments (roster as imported; person_name kept, user_id NULL until
-- accounts are linked; constraint_note = original availability entries)
-- ---------------------------------------------------------------------
INSERT INTO event_assignments (id, event_id, person_name, constraint_note) VALUES
 ('00000000-0000-7000-8000-000000000201','00000000-0000-7000-8000-000000000101','Moja',NULL),
 ('00000000-0000-7000-8000-000000000202','00000000-0000-7000-8000-000000000101','Tess','+1'),
 ('00000000-0000-7000-8000-000000000203','00000000-0000-7000-8000-000000000101','Aaron',NULL),
 ('00000000-0000-7000-8000-000000000204','00000000-0000-7000-8000-000000000101','Anna','ab 10 uhr'),
 ('00000000-0000-7000-8000-000000000205','00000000-0000-7000-8000-000000000101','Aaron A','ab 10'),
 ('00000000-0000-7000-8000-000000000206','00000000-0000-7000-8000-000000000101','Peter','ab 10 uhr'),
 ('00000000-0000-7000-8000-000000000207','00000000-0000-7000-8000-000000000101','Benji','ab 10 uhr'),
 ('00000000-0000-7000-8000-000000000208','00000000-0000-7000-8000-000000000102','Moja',NULL),
 ('00000000-0000-7000-8000-000000000209','00000000-0000-7000-8000-000000000102','Peter',NULL),
 ('00000000-0000-7000-8000-000000000210','00000000-0000-7000-8000-000000000102','Tess',NULL),
 ('00000000-0000-7000-8000-000000000211','00000000-0000-7000-8000-000000000102','Benji',NULL),
 ('00000000-0000-7000-8000-000000000212','00000000-0000-7000-8000-000000000102','Hannah',NULL),
 ('00000000-0000-7000-8000-000000000213','00000000-0000-7000-8000-000000000102','Anna',NULL),
 ('00000000-0000-7000-8000-000000000214','00000000-0000-7000-8000-000000000103','Benji',NULL),
 ('00000000-0000-7000-8000-000000000215','00000000-0000-7000-8000-000000000103','Peter',NULL),
 ('00000000-0000-7000-8000-000000000216','00000000-0000-7000-8000-000000000103','Paul',NULL),
 ('00000000-0000-7000-8000-000000000217','00000000-0000-7000-8000-000000000103','Eva',NULL),
 ('00000000-0000-7000-8000-000000000218','00000000-0000-7000-8000-000000000104','Hannah',NULL),
 ('00000000-0000-7000-8000-000000000219','00000000-0000-7000-8000-000000000104','Benji',NULL),
 ('00000000-0000-7000-8000-000000000220','00000000-0000-7000-8000-000000000104','Peter',NULL),
 ('00000000-0000-7000-8000-000000000221','00000000-0000-7000-8000-000000000104','Aaron','ab 13:20'),
 ('00000000-0000-7000-8000-000000000222','00000000-0000-7000-8000-000000000104','Daniel','ab ~13:00'),
 ('00000000-0000-7000-8000-000000000223','00000000-0000-7000-8000-000000000104','Tess','bis 14 Uhr'),
 ('00000000-0000-7000-8000-000000000224','00000000-0000-7000-8000-000000000105','Elias',NULL),
 ('00000000-0000-7000-8000-000000000225','00000000-0000-7000-8000-000000000105','Hannah',NULL),
 ('00000000-0000-7000-8000-000000000226','00000000-0000-7000-8000-000000000105','Moja',NULL),
 ('00000000-0000-7000-8000-000000000227','00000000-0000-7000-8000-000000000105','Anna',NULL),
 ('00000000-0000-7000-8000-000000000228','00000000-0000-7000-8000-000000000105','Sophie','bis 12:30'),
 ('00000000-0000-7000-8000-000000000229','00000000-0000-7000-8000-000000000105','Alex','ab 10:00 · +43 664 5050593'),
 ('00000000-0000-7000-8000-000000000230','00000000-0000-7000-8000-000000000105','Eva',NULL),
 ('00000000-0000-7000-8000-000000000231','00000000-0000-7000-8000-000000000105','Peter',NULL),
 ('00000000-0000-7000-8000-000000000232','00000000-0000-7000-8000-000000000105','Martin',NULL),
 ('00000000-0000-7000-8000-000000000233','00000000-0000-7000-8000-000000000105','Nella',NULL),
 ('00000000-0000-7000-8000-000000000234','00000000-0000-7000-8000-000000000105','Tess',NULL),
 ('00000000-0000-7000-8000-000000000235','00000000-0000-7000-8000-000000000105','Clemens','ab 11:15'),
 ('00000000-0000-7000-8000-000000000236','00000000-0000-7000-8000-000000000106','Hannah',NULL),
 ('00000000-0000-7000-8000-000000000237','00000000-0000-7000-8000-000000000106','Tess','bis 15 Uhr'),
 ('00000000-0000-7000-8000-000000000238','00000000-0000-7000-8000-000000000106','Aaron','ab 13:20'),
 ('00000000-0000-7000-8000-000000000239','00000000-0000-7000-8000-000000000106','Moja',NULL),
 ('00000000-0000-7000-8000-000000000240','00000000-0000-7000-8000-000000000106','Clara B.',NULL),
 ('00000000-0000-7000-8000-000000000241','00000000-0000-7000-8000-000000000106','Sascha',NULL),
 ('00000000-0000-7000-8000-000000000242','00000000-0000-7000-8000-000000000106','Lotta','bis 15 Uhr'),
 ('00000000-0000-7000-8000-000000000243','00000000-0000-7000-8000-000000000106','Anna Biene',NULL),
 ('00000000-0000-7000-8000-000000000244','00000000-0000-7000-8000-000000000106','Jette','ab 14:30'),
 ('00000000-0000-7000-8000-000000000245','00000000-0000-7000-8000-000000000106','Jasmin','bis 15 Uhr'),
 ('00000000-0000-7000-8000-000000000246','00000000-0000-7000-8000-000000000106','Clemens','bis 14:15'),
 ('00000000-0000-7000-8000-000000000247','00000000-0000-7000-8000-000000000107','Sophie','bis 17:45'),
 ('00000000-0000-7000-8000-000000000248','00000000-0000-7000-8000-000000000107','Marijke',NULL),
 ('00000000-0000-7000-8000-000000000249','00000000-0000-7000-8000-000000000107','Clara L',NULL),
 ('00000000-0000-7000-8000-000000000250','00000000-0000-7000-8000-000000000108','Indra',NULL),
 ('00000000-0000-7000-8000-000000000251','00000000-0000-7000-8000-000000000108','Eva H',NULL),
 ('00000000-0000-7000-8000-000000000252','00000000-0000-7000-8000-000000000108','Benji',NULL),
 ('00000000-0000-7000-8000-000000000253','00000000-0000-7000-8000-000000000109','Peter',NULL),
 ('00000000-0000-7000-8000-000000000254','00000000-0000-7000-8000-000000000109','Hannah',NULL),
 ('00000000-0000-7000-8000-000000000255','00000000-0000-7000-8000-000000000109','Moja',NULL),
 ('00000000-0000-7000-8000-000000000256','00000000-0000-7000-8000-000000000109','Anna',NULL),
 ('00000000-0000-7000-8000-000000000257','00000000-0000-7000-8000-000000000109','Manu',NULL),
 ('00000000-0000-7000-8000-000000000258','00000000-0000-7000-8000-000000000109','Martin','bis 12:00'),
 ('00000000-0000-7000-8000-000000000259','00000000-0000-7000-8000-000000000109','Indra',NULL),
 ('00000000-0000-7000-8000-000000000260','00000000-0000-7000-8000-000000000109','Anna Biene',NULL),
 ('00000000-0000-7000-8000-000000000261','00000000-0000-7000-8000-000000000109','Sophie','bis 13:30'),
 ('00000000-0000-7000-8000-000000000262','00000000-0000-7000-8000-000000000109','Ida','bis 12:00'),
 ('00000000-0000-7000-8000-000000000263','00000000-0000-7000-8000-000000000109','Clemens','ab 11'),
 ('00000000-0000-7000-8000-000000000264','00000000-0000-7000-8000-000000000109','Ranjodh',NULL),
 ('00000000-0000-7000-8000-000000000265','00000000-0000-7000-8000-000000000110','Marianne',NULL),
 ('00000000-0000-7000-8000-000000000266','00000000-0000-7000-8000-000000000110','Peter',NULL),
 ('00000000-0000-7000-8000-000000000267','00000000-0000-7000-8000-000000000110','Anna',NULL),
 ('00000000-0000-7000-8000-000000000268','00000000-0000-7000-8000-000000000110','Manu',NULL),
 ('00000000-0000-7000-8000-000000000269','00000000-0000-7000-8000-000000000110','Eva H',NULL),
 ('00000000-0000-7000-8000-000000000270','00000000-0000-7000-8000-000000000110','moja',NULL),
 ('00000000-0000-7000-8000-000000000271','00000000-0000-7000-8000-000000000111','Aaron',NULL),
 ('00000000-0000-7000-8000-000000000272','00000000-0000-7000-8000-000000000111','Marijke',NULL),
 ('00000000-0000-7000-8000-000000000273','00000000-0000-7000-8000-000000000111','Ben','auch'),
 ('00000000-0000-7000-8000-000000000274','00000000-0000-7000-8000-000000000112','Clara B.',NULL),
 ('00000000-0000-7000-8000-000000000275','00000000-0000-7000-8000-000000000112','Marianne',NULL),
 ('00000000-0000-7000-8000-000000000276','00000000-0000-7000-8000-000000000112','Susanne',NULL),
 ('00000000-0000-7000-8000-000000000277','00000000-0000-7000-8000-000000000112','Alea',NULL),
 ('00000000-0000-7000-8000-000000000278','00000000-0000-7000-8000-000000000112','Ben','if possible c: · Nr is +436641554358 falls nicht geht'),
 ('00000000-0000-7000-8000-000000000279','00000000-0000-7000-8000-000000000113','Peter',NULL),
 ('00000000-0000-7000-8000-000000000280','00000000-0000-7000-8000-000000000113','Hannah',NULL),
 ('00000000-0000-7000-8000-000000000281','00000000-0000-7000-8000-000000000113','Marianne',NULL),
 ('00000000-0000-7000-8000-000000000282','00000000-0000-7000-8000-000000000113','Nella',NULL),
 ('00000000-0000-7000-8000-000000000283','00000000-0000-7000-8000-000000000113','Anna S.',NULL),
 ('00000000-0000-7000-8000-000000000284','00000000-0000-7000-8000-000000000113','Eva H',NULL),
 ('00000000-0000-7000-8000-000000000285','00000000-0000-7000-8000-000000000113','Sarah',NULL),
 ('00000000-0000-7000-8000-000000000286','00000000-0000-7000-8000-000000000113','Eva',NULL),
 ('00000000-0000-7000-8000-000000000287','00000000-0000-7000-8000-000000000113','Martin',NULL),
 ('00000000-0000-7000-8000-000000000288','00000000-0000-7000-8000-000000000113','Kathi','bis 11:10'),
 ('00000000-0000-7000-8000-000000000289','00000000-0000-7000-8000-000000000113','Moja',NULL),
 ('00000000-0000-7000-8000-000000000290','00000000-0000-7000-8000-000000000113','Ranjodh',NULL),
 ('00000000-0000-7000-8000-000000000291','00000000-0000-7000-8000-000000000114','Marianne','bis 14 Uhr'),
 ('00000000-0000-7000-8000-000000000292','00000000-0000-7000-8000-000000000114','Peter',NULL),
 ('00000000-0000-7000-8000-000000000293','00000000-0000-7000-8000-000000000114','Emily','Original: "Emily (" – unvollständig'),
 ('00000000-0000-7000-8000-000000000294','00000000-0000-7000-8000-000000000114','Sophie',NULL),
 ('00000000-0000-7000-8000-000000000295','00000000-0000-7000-8000-000000000114','Moja',NULL),
 ('00000000-0000-7000-8000-000000000296','00000000-0000-7000-8000-000000000114','Ranjodh',NULL);

-- Demo donation requests (from the original demo seed; is_demo=1)
INSERT INTO donation_requests (id, full_name, email, type_id, amount, message, consent, status, is_demo) VALUES
 (UUID(),'DEMO Beispiel Person','demo@example.org',(SELECT id FROM donation_types WHERE slug='money'),50.00,'Demo-Datensatz',1,'new',1),
 (UUID(),'DEMO Lebensmittelspende','demo2@example.org',(SELECT id FROM donation_types WHERE slug='food'),NULL,'Demo: 20 kg Kartoffeln',1,'contacted',1);

-- (demo social posts require a media asset; created through the studio instead)

-- Cache stamp
INSERT INTO homepage_cache (id, version) VALUES (1, 1);

-- ============================================================
-- Rachel Rae's Rundown — Database Schema
-- Run this once on a fresh MySQL 8.0+ database
-- mysql -u root -p rachel_rae_rundown < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS rachel_rae_rundown
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rachel_rae_rundown;

-- ============================================================
-- ARTICLES
-- ============================================================
CREATE TABLE IF NOT EXISTS articles (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(255) NOT NULL UNIQUE,
  headline        VARCHAR(600) NOT NULL,
  dek             VARCHAR(600) DEFAULT NULL,
  body            LONGTEXT DEFAULT NULL,
  section         VARCHAR(120) DEFAULT 'news',
  author_id       INT UNSIGNED DEFAULT NULL,
  status          ENUM('draft','scheduled','live','archived') NOT NULL DEFAULT 'draft',
  is_breaking     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  is_featured     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  publish_at      DATETIME DEFAULT NULL,
  hero_emoji      VARCHAR(20) DEFAULT '📰',
  hero_image      VARCHAR(500) DEFAULT NULL,
  views           INT UNSIGNED NOT NULL DEFAULT 0,
  tags            JSON DEFAULT NULL,
  ai_prompt_used  TEXT DEFAULT NULL,
  approved_by     VARCHAR(100) DEFAULT 'auto',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_publish (status, publish_at),
  INDEX idx_section_status (section, status),
  INDEX idx_author (author_id),
  INDEX idx_breaking (is_breaking),
  FULLTEXT INDEX ft_search (headline, dek, body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STAFF / AI AGENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(100) NOT NULL UNIQUE,
  display_name    VARCHAR(200) NOT NULL,
  job_title       VARCHAR(400) NOT NULL,
  department      VARCHAR(200) DEFAULT NULL,
  tier            ENUM('leadership','senior','staff','freelance','intern') NOT NULL DEFAULT 'staff',
  bio             TEXT DEFAULT NULL,
  previously      TEXT DEFAULT NULL,
  beat            JSON DEFAULT NULL,
  system_prompt   TEXT DEFAULT NULL,
  hobbies         JSON DEFAULT NULL,
  quote           TEXT DEFAULT NULL,
  headshot_svg    MEDIUMTEXT DEFAULT NULL,
  is_active       TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tier (tier),
  INDEX idx_active (is_active),
  INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MEDIA / IMAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS media (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id      INT UNSIGNED DEFAULT NULL,
  filename        VARCHAR(500) NOT NULL,
  filepath        VARCHAR(500) NOT NULL,
  type            ENUM('hero','inline','staff_headshot','site') NOT NULL DEFAULT 'hero',
  alt_text        TEXT DEFAULT NULL,
  prompt_used     TEXT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_article (article_id),
  CONSTRAINT fk_media_article
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CRON LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS cron_log (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_name            VARCHAR(100) NOT NULL,
  status              ENUM('success','failed','skipped') NOT NULL,
  articles_generated  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  articles_published  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  error_msg           TEXT DEFAULT NULL,
  tokens_used         INT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd            DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  model_used          VARCHAR(200) DEFAULT NULL,
  ran_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_job_status (job_name, status),
  INDEX idx_ran_at (ran_at),
  INDEX idx_model (model_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
  `key`       VARCHAR(100) PRIMARY KEY,
  `value`     TEXT DEFAULT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TOPIC QUEUE (for cron story generation)
-- ============================================================
CREATE TABLE IF NOT EXISTS topic_queue (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prompt      TEXT NOT NULL,
  section     VARCHAR(120) NOT NULL DEFAULT 'news',
  agent_slug  VARCHAR(100) DEFAULT NULL,
  priority    TINYINT UNSIGNED NOT NULL DEFAULT 5,
  used        TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  used_at     DATETIME DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_used_priority (used, priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VISITOR LOG (analytics)
-- ============================================================
CREATE TABLE IF NOT EXISTS visitor_log (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address      VARCHAR(45) DEFAULT NULL,
  user_agent      TEXT DEFAULT NULL,
  page_path       VARCHAR(500) DEFAULT NULL,
  article_id      INT UNSIGNED DEFAULT NULL,
  referrer        VARCHAR(1000) DEFAULT NULL,
  country         VARCHAR(100) DEFAULT NULL,
  city            VARCHAR(200) DEFAULT NULL,
  device_type     VARCHAR(50) DEFAULT NULL,
  browser         VARCHAR(100) DEFAULT NULL,
  os              VARCHAR(100) DEFAULT NULL,
  session_id      VARCHAR(100) DEFAULT NULL,
  visited_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visited (visited_at),
  INDEX idx_page (page_path(191)),
  INDEX idx_article (article_id),
  INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ARTICLE REACTIONS (like/dislike/share)
-- ============================================================
CREATE TABLE IF NOT EXISTS article_reactions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id  INT UNSIGNED NOT NULL,
  action_type ENUM('like','dislike','share') NOT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_article_action (article_id, action_type),
  INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED: DEFAULT SETTINGS
-- ============================================================
INSERT INTO settings (`key`, `value`) VALUES
  ('site_name',           'Rachel Rae''s Rundown'),
  ('site_tagline',        'All the news that''s unfit to print — delivered with love and zero remorse.'),
  ('stories_per_day',     '6'),
  ('breaking_story_id',   NULL),
  ('llm_model',           'claude-sonnet-4-6'),
  ('llm_max_tokens',      '2048'),
  ('auto_publish',        '1'),
  ('publish_delay_hours', '2'),
  ('maintenance_mode',    '0'),
  ('social_queue_enabled','0'),
  ('image_gen_enabled',   '0'),
  ('admin_email',         'rachel@rachel.local')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ============================================================
-- SEED: AI AGENT STAFF
-- (system_prompt populated separately — see agents.php)
-- ============================================================
INSERT INTO staff (slug, display_name, job_title, department, tier, beat, sort_order) VALUES
  ('the-desk',      'The Desk',          'Master of the News Universe & Keeper of All Deadlines', 'Leadership', 'leadership', '["all"]', 1),
  ('rachel-rae',    'Rachel Rae',         'Supreme Overlord of All Terrible Ideas & Inspired Ones', 'Leadership', 'leadership', '["all"]', 2),
  ('vex-moriarty',  'Vex Moriarty',       'Prophet of Doom & Senior Apostle of Uncomfortable Truths', 'Senior Staff', 'senior', '["politics","religion","national","world"]', 3),
  ('dolores-vendetta','Dolores Vendetta', 'Grand Inquisitor of the Political Class & Certified Bull Detector', 'Senior Staff', 'senior', '["politics","elections","washington"]', 4),
  ('roxanne-blaze', 'Roxanne Blaze',      'Chaos Correspondent & Rhinestone-Certified Demolisher of Bad Takes', 'Senior Staff', 'senior', '["culture","lgbtq","tech","fashion"]', 5),
  ('fabrizia-sloane','Fabrizia Sloane',   'Haute Mess Correspondent & Designated Hater of Everything Beige', 'Staff', 'staff', '["fashion","luxury","culture"]', 6),
  ('chip-largo',    'Chip Largo',         'Enthusiastic Chronicler of Things That Should Not Exist but Do', 'Staff', 'staff', '["pop_culture","film","tech","local"]', 7),
  ('esperanza-lux', 'Esperanza Lux',      'Ecclesiastical Beat Reporter & God''s Least Favorite Journalist', 'Staff', 'staff', '["religion","faith","miracles"]', 8),
  ('ziggy-nullpointer','Ziggy Nullpointer','Coder-in-Residence, Bug Whisperer & Architect of Controlled Digital Chaos', 'Freelance', 'freelance', '["tech","dev","systems"]', 9),
  ('lavinia-overhype','Lavinia Overhype', 'Vibe Architect, Brand Whisperer & Marketing Maven of Maximum Impact', 'Freelance', 'freelance', '["marketing","social","brand"]', 10),
  ('ptolemy-snark', 'Ptolemy Snark',      'Apprentice of Anarchy & Unpaid Fetcher of Context', 'Intern', 'intern', '["research","anything"]', 11)
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- ============================================================
-- SEED: TOPIC QUEUE (30 story prompts to start)
-- ============================================================
INSERT INTO topic_queue (prompt, section, agent_slug, priority) VALUES
-- Politics
('A senator introduces legislation requiring all bills to be explained using hand puppets before a vote. Bipartisan support.', 'politics', 'dolores-vendetta', 8),
('A congressional hearing on whether the concept of compromise is, legally, still alive.', 'politics', 'vex-moriarty', 7),
('The White House accidentally sends a press release intended for internal use only. It contains the phrase "just keep saying economy" seventeen times.', 'politics', 'dolores-vendetta', 9),
('A senator claims he has never received a bribe, citing inability to see them.', 'politics', 'dolores-vendetta', 7),
('New poll: 74% of Americans would vote for "a reasonable adult." Search ongoing.', 'elections', 'dolores-vendetta', 6),

-- Religion
('Vatican introduces official patron saint of strong Wi-Fi and reliable cellular coverage.', 'religion', 'esperanza-lux', 8),
('A megachurch pastor''s private jet is blessed by a second pastor, flown in on a separate private jet.', 'religion', 'esperanza-lux', 9),
('Pope introduces ring light blessing ceremony for Catholic content creators.', 'religion', 'esperanza-lux', 7),
('God is spotted at a Waffle House at 3am in Amarillo. He ordered decaf. The waitress noticed something was off.', 'religion', 'vex-moriarty', 10),
('A man builds an ark in the suburbs. The city issues permits. He has not explained why.', 'religion', 'esperanza-lux', 6),

-- LGBTQ+
('Texas drag queen sets record for surviving the most consecutive legislative sessions. Still fabulous.', 'lgbtq', 'roxanne-blaze', 9),
('Study finds the Gay Agenda consists primarily of brunch, dog ownership, and not being murdered.', 'lgbtq', 'roxanne-blaze', 8),
('Area straight man is fully supportive of LGBTQ+ rights, owns one flag, mentions it constantly.', 'lgbtq', 'roxanne-blaze', 7),
('Texas man screams at a pride flag for 14 hours. The flag does not move. The flag wins.', 'lgbtq', 'roxanne-blaze', 8),
('Gay AI model develops parasocial bond with user. User''s girlfriend is furious and confused.', 'lgbtq', 'roxanne-blaze', 10),

-- Fashion
('Met Gala theme "What if clothes were honest?" causes entire guest list to cancel.', 'fashion', 'fabrizia-sloane', 9),
('Quiet luxury is just rich people being boring and calling it a personality.', 'fashion', 'fabrizia-sloane', 8),
('Birkenstocks named official shoe of the Apocalypse at Paris Fashion Week.', 'fashion', 'fabrizia-sloane', 7),
('Fast fashion brand launches "guilt-free" collection. It is made of guilt, actually.', 'fashion', 'fabrizia-sloane', 7),

-- Cosmic / Aliens / Time Travel
('Aliens make first contact. They arrived in a minivan. They have opinions about American truck stops.', 'world', 'vex-moriarty', 10),
('Time traveler from 2087 refuses to reveal anything about the future but is visibly relieved about something.', 'culture', 'chip-largo', 9),
('Scientists confirm a parallel universe exists. It is governed significantly better. They decline to elaborate.', 'world', 'vex-moriarty', 9),
('A robot sent from the future arrives to stop one specific piece of legislation. It succeeds in four hours.', 'politics', 'dolores-vendetta', 8),
('God appears to farm animals. His warning is not about humans. The animals are processing.', 'religion', 'esperanza-lux', 10),

-- Pop Culture / Tech
('Taylor Swift releases 47-track album about the DMV experience. Track 31 debuts at number one.', 'culture', 'chip-largo', 9),
('Hollywood confirms 14th Spider-Man reboot. This time he has feelings about his feelings.', 'culture', 'chip-largo', 8),
('AI therapist tells three million patients they are doing their best. Charges $400 per session.', 'tech', 'roxanne-blaze', 8),

-- Local SA
('San Antonio man claims breakfast taco cured his sciatica. Church of the Holy Taco now accepting members.', 'local', 'chip-largo', 8),
('Restaurant serves deconstructed taco. Is immediately and righteously burned to the ground.', 'local', 'chip-largo', 9),
('H-E-B achieves religious status in Texas. State legislature recognizes it as a denomination.', 'local', 'chip-largo', 7);

-- ============================================================
-- BeSix CAD — SQL migrace
-- Nové tabulky pro cad.besix.cz (autonomní od board.besix.cz)
-- Sdílené tabulky: users, projects, project_members, sessions
-- ============================================================

-- 0. Remember me tokeny (přihlášení na 30 dní)
CREATE TABLE IF NOT EXISTS remember_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rt_token (token),
  INDEX idx_rt_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automatické mazání expirovaných tokenů (spusť jako event nebo cron)
-- DELETE FROM remember_tokens WHERE expires_at < NOW();

-- 1. Šablony razítek (per user — uživatel si nese šablony napříč projekty)
CREATE TABLE IF NOT EXISTS cad_title_block_templates (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  name         VARCHAR(255) NOT NULL DEFAULT 'Výchozí',
  logo_data    MEDIUMTEXT,
  fields_json  TEXT NOT NULL,
  paper_size   VARCHAR(10) DEFAULT 'A3',
  is_default   TINYINT(1) DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_cad_tbt_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fields_json příklad:
-- {
--   "projectName": "Bytový dům Vinohrady",
--   "drawingNumber": "D.1.1.01",
--   "drawingTitle": "Půdorys 1.NP",
--   "scale": "1:100",
--   "date": "04/2026",
--   "author": "David",
--   "checker": "",
--   "stage": "DPS",
--   "investor": "ABC Development s.r.o.",
--   "revisions": [
--     { "rev": "A", "date": "2026-03-15", "desc": "Změna příček", "author": "David" }
--   ]
-- }

-- 2. Metadata výkresů (reference — soubory zůstávají lokálně u uživatele)
CREATE TABLE IF NOT EXISTS cad_drawing_metadata (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  project_id    INT NOT NULL,
  user_id       INT NOT NULL,
  file_name     VARCHAR(255) NOT NULL,
  drawing_name  VARCHAR(255),
  scale         VARCHAR(20) DEFAULT '1:100',
  paper_size    VARCHAR(10) DEFAULT 'A3',
  thumbnail     MEDIUMTEXT,
  file_hash     VARCHAR(64),
  template_id   INT DEFAULT NULL,
  _ts           BIGINT DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES cad_title_block_templates(id) ON DELETE SET NULL,
  INDEX idx_cad_dm_project (project_id),
  INDEX idx_cad_dm_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Vlastní bloky/symboly uživatele
CREATE TABLE IF NOT EXISTS cad_user_symbols (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  name         VARCHAR(255) NOT NULL,
  category     VARCHAR(100) DEFAULT 'obecné',
  svg_data     MEDIUMTEXT NOT NULL,
  width_mm     DECIMAL(10,2),
  height_mm    DECIMAL(10,2),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_cad_us_user (user_id),
  INDEX idx_cad_us_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Výchozí stavební symboly (sdílené pro všechny uživatele)
CREATE TABLE IF NOT EXISTS cad_default_symbols (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255) NOT NULL,
  category     VARCHAR(100) NOT NULL,
  svg_data     MEDIUMTEXT NOT NULL,
  width_mm     DECIMAL(10,2),
  height_mm    DECIMAL(10,2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed výchozích kategorií symbolů
INSERT INTO cad_default_symbols (name, category, svg_data, width_mm, height_mm) VALUES
  ('Dveře 800',    'dveře',   '<svg><!-- placeholder --></svg>', 800, 100),
  ('Dveře 900',    'dveře',   '<svg><!-- placeholder --></svg>', 900, 100),
  ('Okno 1200',    'okna',    '<svg><!-- placeholder --></svg>', 1200, 100),
  ('Okno 1500',    'okna',    '<svg><!-- placeholder --></svg>', 1500, 100),
  ('Schodiště',    'schody',  '<svg><!-- placeholder --></svg>', 1200, 3000),
  ('WC',           'sanitár', '<svg><!-- placeholder --></svg>', 400, 700),
  ('Umyvadlo',     'sanitár', '<svg><!-- placeholder --></svg>', 500, 450),
  ('Vana',         'sanitár', '<svg><!-- placeholder --></svg>', 700, 1700),
  ('Sprchový kout','sanitár', '<svg><!-- placeholder --></svg>', 900, 900),
  ('Zásuvka',      'elektro', '<svg><!-- placeholder --></svg>', 50, 50),
  ('Vypínač',      'elektro', '<svg><!-- placeholder --></svg>', 50, 50),
  ('Světlo',       'elektro', '<svg><!-- placeholder --></svg>', 50, 50);

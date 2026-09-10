-- Ajaraamat — mitme pere tugi (multi-tenant)
-- Import this file into your MySQL database (e.g. via phpMyAdmin -> Import)
-- for a FRESH installation. If you already have data from the single-family
-- version, use migrate_multitenant.php instead — do NOT run this file on
-- top of existing data, it will conflict.

CREATE TABLE IF NOT EXISTS families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    is_demo TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Additional parent logins for the same family (second parent etc.). The
-- primary account stays on the families row; these are extra email+password
-- pairs that resolve to the same family_id.
CREATE TABLE IF NOT EXISTS family_logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS children (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    public_token VARCHAR(40) NOT NULL UNIQUE,
    daily_goal_min INT DEFAULT NULL,
    weekly_goal_min INT DEFAULT NULL,
    screen_reward_cap_min INT DEFAULT NULL,
    reading_ratio DECIMAL(3,2) DEFAULT NULL,   -- per-child override for READING_RATIO; NULL = use the site default
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    status ENUM('lugemata','loeb','loetud') NOT NULL DEFAULT 'lugemata',
    started_date DATE DEFAULT NULL,
    finished_date DATE DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    total_pages INT DEFAULT NULL,
    current_page INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    entry_date DATE NOT NULL,
    raamat INT NOT NULL DEFAULT 0,
    book_id INT DEFAULT NULL,
    raamat_comment VARCHAR(255) DEFAULT NULL,
    kind VARCHAR(16) DEFAULT NULL,
    ekraan INT NOT NULL DEFAULT 0,
    ekraan_comment VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    title VARCHAR(120) NOT NULL,
    goal_type ENUM('books','minutes') NOT NULL DEFAULT 'books',
    goal_value INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

-- Lapse enda lisatud kanded, mis ootavad vanema kinnitust. Kinnitamisel
-- tekitatakse päris `entries` rida ja see rida kustutatakse; tagasilükkamisel
-- lihtsalt kustutatakse. Statistika neid ei arvesta enne kinnitamist.
CREATE TABLE IF NOT EXISTS pending_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    entry_date DATE NOT NULL,
    type ENUM('raamat','ekraan') NOT NULL,
    minutes INT NOT NULL,
    book_id INT DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    current_page INT DEFAULT NULL,
    source VARCHAR(16) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
);

-- Saavutatud verstapostid. Iga (child_id, kind, threshold) salvestatakse üks
-- kord, esimesel korral kui piir ületati; `achieved_on` on siis fikseeritud.
-- kind: books | pages | hours | days | streak | challenge
-- challenge puhul on threshold väljakutse id ja label selle pealkiri.
CREATE TABLE IF NOT EXISTS milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    child_id INT NOT NULL,
    kind VARCHAR(20) NOT NULL,
    threshold INT NOT NULL,
    label VARCHAR(160) DEFAULT NULL,
    emoji VARCHAR(16) DEFAULT NULL,
    achieved_on DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_milestone (child_id, kind, threshold),
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
);

-- Parooli lähtestamise ühekordsed tokenid (vanema kontod).
-- `token_hash` on väärtus, mida reset.php päringus otsib; `token` (toores)
-- on ainult selleks, et admin.php saaks käsitsi jagatava lingi taastada,
-- kui e-kiri kohale ei jõua. Rida kustutatakse kohe, kui tokenit kasutati;
-- igal perel saab korraga olla ainult üks aktiivne rida (UNIQUE võti).
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_password_resets_family (family_id),
    KEY idx_password_resets_token_hash (token_hash),
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

CREATE INDEX idx_entries_child_date ON entries(child_id, entry_date);
CREATE INDEX idx_entries_book ON entries(book_id);
CREATE INDEX idx_books_child ON books(child_id);

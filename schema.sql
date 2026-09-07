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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS children (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    public_token VARCHAR(40) NOT NULL UNIQUE,
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
    ekraan INT NOT NULL DEFAULT 0,
    ekraan_comment VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
);

CREATE INDEX idx_entries_child_date ON entries(child_id, entry_date);
CREATE INDEX idx_entries_book ON entries(book_id);
CREATE INDEX idx_books_child ON books(child_id);

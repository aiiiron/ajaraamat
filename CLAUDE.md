# Ajaraamat

Self-hosted **reading- & screen-time tracker** for multiple families. A parent
logs each child's reading minutes and screen minutes; the app shows how much
reading time is currently "owed" (ratio configurable, default 1:1). Multi-tenant:
one install serves many families, each with one or more children.

UI language is **Estonian**. Keep user-facing strings Estonian; code comments are
mixed ET/EN — match the file you're editing.

## Stack

- **PHP 7.4+ / 8.x**, plain procedural — no framework, no Composer.
- **MySQL / MariaDB** via PDO (`db.php` → `get_db()`).
- No build step. Files are served as-is by the host.

## Layout

- `config.php` — DB credentials, `ADMIN_PASSWORD`, `READING_RATIO`,
  `GOOGLE_BOOKS_API_KEY`, `configure_session()`. **Gitignored**; the real file
  lives only on the server. `config.example.php` is the committed template.
- `db.php`, `functions.php` — DB connection + all query helpers (~500 lines).
- `auth.php` / `admin_auth.php` — session guards for parent / site-owner areas.
- `schema.sql` — fresh install. Tables: `families`, `children`, `books`, `entries`.
- `migrate_*.php` — one-shot browser-run upgrade scripts; deleted from the server
  after use, kept in the repo for the record.
- Public pages: `index.php` (marketing), `register.php`, `login.php`.
- Parent area (login required): `paren.php` (dashboard), `history.php`,
  `books.php` + `add_book.php` / `edit_book.php`, `children.php`,
  `add.php` / `edit_day.php`, `export.php` (CSV).
- Site-owner area: `admin_login.php` → `admin.php` (approve/reject new families).
- Token-based child view (no login): `child.php`, `child_books.php`,
  `reading_timer.php` — reached via `?token=` (per-child `random_bytes(16)` hex).

## Security invariants (don't regress these)

- Every query that takes a `?child=` / `?book=` param must verify ownership
  against the logged-in `family_id` first — see `child_belongs_to_family()`.
- All SQL goes through PDO prepared statements.
- Passwords: `password_hash()` (bcrypt). New family accounts start `pending` and
  need admin approval.
- Child public tokens are secret-but-shareable — treat like passwords.

## Deploy

Per `README.md`: pushing `main` to GitHub is meant to FTP-deploy to the host via
a GitHub Actions workflow (secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`,
`FTP_SERVER_DIR`). `config.php` and `.git*` are never uploaded. The workflow file
under `.github/workflows/` is not present yet — create it before relying on
auto-deploy.

## Local testing

`php -S localhost:8000` from this dir works, but needs a reachable MySQL and a
local `config.php`. There is no fixture/seed script.

<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// =========================================================
// Formatting
// =========================================================

/**
 * Turn a minute count into "1 päev 6 h 46 min". Days and hours appear only
 * when non-zero; minutes always appear unless a bigger unit already does and
 * the remainder is zero (so 120 -> "2 h", 1006 -> "16 h 46 min", 43 -> "43 min").
 * Each unit keeps a non-breaking space so a wrap only ever falls between units.
 */
function format_duration(int $minutes): string {
    $minutes = max(0, $minutes);
    $days  = intdiv($minutes, 1440);
    $hours = intdiv($minutes % 1440, 60);
    $mins  = $minutes % 60;

    $parts = [];
    if ($days > 0)  { $parts[] = $days . "\u{00A0}" . ($days === 1 ? 'päev' : 'päeva'); }
    if ($hours > 0) { $parts[] = $hours . "\u{00A0}h"; }
    if ($mins > 0 || !$parts) { $parts[] = $mins . "\u{00A0}min"; }

    return implode(' ', $parts);
}

// =========================================================
// Icons — inline Lucide (https://lucide.dev, ISC/MIT). 24x24, currentColor
// so an icon takes the colour of its surrounding text.
// =========================================================

function icon(string $name, string $class = 'ic'): string {
    static $paths = [
        'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'refresh'    => '<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>',
        'pencil'     => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
        'check'      => '<path d="M20 6 9 17l-5-5"/>',
        'flame'      => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5"/>',
        'timer'      => '<line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/>',
        'search'     => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'scale'      => '<path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>',
        'plus'       => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

// =========================================================
// CSRF (session-authenticated forms)
// =========================================================

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Hidden field to drop inside every session-authenticated <form method="post">. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Call once before handling a POST on a session-authenticated page. No-op on GET. */
function require_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('Vigane või aegunud vorm. Mine tagasi, värskenda lehte ja proovi uuesti.');
    }
}

// =========================================================
// Families & children
// =========================================================

function generate_token(): string {
    return bin2hex(random_bytes(16));
}

function get_children(int $familyId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM children WHERE family_id = :fid ORDER BY created_at ASC, id ASC");
    $stmt->execute([':fid' => $familyId]);
    return $stmt->fetchAll();
}

function get_child(int $childId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = :id");
    $stmt->execute([':id' => $childId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_child_by_token(string $token): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM children WHERE public_token = :t");
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** True if $childId genuinely belongs to $familyId — call before trusting any ?child= param. */
function child_belongs_to_family(int $childId, int $familyId): bool {
    $child = get_child($childId);
    return $child !== null && (int) $child['family_id'] === $familyId;
}

function create_child(int $familyId, string $name): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO children (family_id, name, public_token) VALUES (:fid, :name, :token)");
    $stmt->execute([':fid' => $familyId, ':name' => $name, ':token' => generate_token()]);
    return (int) $pdo->lastInsertId();
}

function rename_child(int $childId, string $name): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE children SET name = :name WHERE id = :id");
    $stmt->execute([':name' => $name, ':id' => $childId]);
}

/** Sets a child's reading goals; pass null for "no goal". */
function set_child_goal(int $childId, ?int $daily, ?int $weekly): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE children SET daily_goal_min = :d, weekly_goal_min = :w WHERE id = :id");
    $stmt->execute([':d' => $daily, ':w' => $weekly, ':id' => $childId]);
}

/** Reading-goal progress card with a conic-gradient ring. Renders nothing if no goal is set. */
function render_goal_card(int $todayMin, int $weekMin, int $dailyGoal, int $weeklyGoal): void {
    $hasDaily = $dailyGoal > 0;
    $hasWeekly = $weeklyGoal > 0;
    if (!$hasDaily && !$hasWeekly) {
        return;
    }

    if ($hasDaily) {
        $cur = $todayMin; $goal = $dailyGoal; $label = 'Tänane lugemiseesmärk';
    } else {
        $cur = $weekMin; $goal = $weeklyGoal; $label = 'Selle nädala lugemiseesmärk';
    }
    $pct = $goal > 0 ? min(100, (int) round($cur / $goal * 100)) : 0;
    $done = $cur >= $goal;
    ?>
    <div class="goal-card<?= $done ? ' done' : '' ?>">
        <div class="goal-ring" style="--pct: <?= $pct ?>">
            <span class="goal-ring-num"><?= $done ? icon('check') : $pct . '%' ?></span>
        </div>
        <div class="goal-body">
            <div class="goal-label"><?= $done ? 'Eesmärk täidetud! 🎉' : htmlspecialchars($label) ?></div>
            <div class="goal-value"><?= format_duration($cur) ?> / <?= format_duration($goal) ?></div>
            <?php if ($hasDaily && $hasWeekly): ?>
                <div class="goal-sub">Sel nädalal <?= format_duration($weekMin) ?> / <?= format_duration($weeklyGoal) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function delete_child(int $childId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("DELETE FROM children WHERE id = :id");
    $stmt->execute([':id' => $childId]);
}

/**
 * Resolves which child the current family-area page should show, from a
 * ?child= GET param, falling back to the family's first child. Verifies
 * ownership. Returns null if the family has no children at all yet.
 */
function resolve_current_child(int $familyId): ?array {
    $children = get_children($familyId);
    if (empty($children)) return null;

    $requested = (int) ($_GET['child'] ?? 0);
    foreach ($children as $c) {
        if ((int) $c['id'] === $requested) return $c;
    }
    return $children[0];
}

/** Tab strip for switching between a family's children, on every family-area page. */
function render_child_switcher(array $children, int $activeChildId, string $baseUrl): void {
    if (count($children) < 2) return;
    ?>
    <nav class="tabs child-switcher">
        <?php foreach ($children as $c): ?>
            <a href="<?= $baseUrl ?>?child=<?= $c['id'] ?>" class="tab <?= (int) $c['id'] === $activeChildId ? 'active' : '' ?>"><?= htmlspecialchars($c['name']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

// =========================================================
// Admin (site owner) — family approval
// =========================================================

function get_families_by_status(string $status): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM families WHERE status = :s ORDER BY created_at ASC");
    $stmt->execute([':s' => $status]);
    return $stmt->fetchAll();
}

function get_all_families(): array {
    $pdo = get_db();
    return $pdo->query("SELECT f.*, (SELECT COUNT(*) FROM children c WHERE c.family_id = f.id) as child_count
        FROM families f ORDER BY created_at DESC")->fetchAll();
}

function set_family_status(int $familyId, string $status): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE families SET status = :s WHERE id = :id");
    $stmt->execute([':s' => $status, ':id' => $familyId]);
}

// =========================================================
// Reading / screen entries (per child)
// =========================================================

function get_totals(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(raamat),0) as raamat, COALESCE(SUM(ekraan),0) as ekraan FROM entries WHERE child_id = :cid");
    $stmt->execute([':cid' => $childId]);
    $row = $stmt->fetch();
    $raamat = (int) $row['raamat'];
    $ekraan = (int) $row['ekraan'];
    $owed = round($ekraan * READING_RATIO) - $raamat; // positive = lugemist võlgu (reading owed)
    return ['raamat' => $raamat, 'ekraan' => $ekraan, 'owed' => $owed];
}

function get_today_totals(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(raamat),0) as raamat, COALESCE(SUM(ekraan),0) as ekraan FROM entries WHERE child_id = :cid AND entry_date = CURDATE()");
    $stmt->execute([':cid' => $childId]);
    $row = $stmt->fetch();
    return ['raamat' => (int) $row['raamat'], 'ekraan' => (int) $row['ekraan']];
}

/**
 * Reading vs screen minutes for today / this ISO week (Mon–Sun) / this
 * calendar month / all time, in one query. All values are plain ints.
 */
function get_stats_matrix(int $childId): array {
    $pdo = get_db();
    $sql = "SELECT
        COALESCE(SUM(CASE WHEN entry_date = CURDATE() THEN raamat END), 0) AS today_raamat,
        COALESCE(SUM(CASE WHEN entry_date = CURDATE() THEN ekraan END), 0) AS today_ekraan,
        COALESCE(SUM(CASE WHEN YEARWEEK(entry_date, 3) = YEARWEEK(CURDATE(), 3) THEN raamat END), 0) AS week_raamat,
        COALESCE(SUM(CASE WHEN YEARWEEK(entry_date, 3) = YEARWEEK(CURDATE(), 3) THEN ekraan END), 0) AS week_ekraan,
        COALESCE(SUM(CASE WHEN entry_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND entry_date <= CURDATE() THEN raamat END), 0) AS month_raamat,
        COALESCE(SUM(CASE WHEN entry_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND entry_date <= CURDATE() THEN ekraan END), 0) AS month_ekraan,
        COALESCE(SUM(raamat), 0) AS all_raamat,
        COALESCE(SUM(ekraan), 0) AS all_ekraan
      FROM entries WHERE child_id = :cid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cid' => $childId]);
    $row = $stmt->fetch() ?: [];
    foreach (['today_raamat', 'today_ekraan', 'week_raamat', 'week_ekraan',
              'month_raamat', 'month_ekraan', 'all_raamat', 'all_ekraan'] as $k) {
        $row[$k] = (int) ($row[$k] ?? 0);
    }
    return $row;
}

/** Four period tiles (today / week / month / all time), each with a reading
 *  and a screen figure. Keeps the playful card look of the old stat grid. */
function render_stats_tiles(array $s): void {
    $tiles = [
        ['Täna',      $s['today_raamat'], $s['today_ekraan'], false],
        ['See nädal',  $s['week_raamat'],  $s['week_ekraan'],  false],
        ['See kuu',    $s['month_raamat'], $s['month_ekraan'], false],
        ['Kokku',      $s['all_raamat'],   $s['all_ekraan'],   true],
    ];
    ?>
    <div class="period-grid">
        <?php foreach ($tiles as [$label, $raamat, $ekraan, $isTotal]): ?>
            <div class="period-tile<?= $isTotal ? ' pt-total' : '' ?>">
                <div class="pt-label"><?= htmlspecialchars($label) ?></div>
                <div class="pt-row pt-reading"><span class="pt-k">📖</span><span class="pt-t">Raamat</span><span class="pt-v"><?= format_duration($raamat) ?></span></div>
                <div class="pt-row pt-screen"><span class="pt-k">📱</span><span class="pt-t">Ekraan</span><span class="pt-v"><?= format_duration($ekraan) ?></span></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function count_distinct_dates(int $childId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT entry_date) FROM entries WHERE child_id = :cid");
    $stmt->execute([':cid' => $childId]);
    return (int) $stmt->fetchColumn();
}

/** Returns the entries belonging to a page of dates (newest dates first), for pagination. */
function get_entries_page(int $childId, int $page, int $perPage = 10): array {
    $pdo = get_db();
    $offset = max(0, ($page - 1) * $perPage);

    $dateStmt = $pdo->prepare("SELECT DISTINCT entry_date FROM entries WHERE child_id = :cid ORDER BY entry_date DESC LIMIT :lim OFFSET :off");
    $dateStmt->bindValue(':cid', $childId, PDO::PARAM_INT);
    $dateStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $dateStmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $dateStmt->execute();
    $dates = $dateStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) return [];

    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $stmt = $pdo->prepare("SELECT e.*, b.title as book_title FROM entries e
        LEFT JOIN books b ON b.id = e.book_id
        WHERE e.child_id = ? AND e.entry_date IN ($placeholders) ORDER BY e.entry_date DESC, e.id ASC");
    $stmt->execute(array_merge([$childId], $dates));
    return $stmt->fetchAll();
}

/** Renders Previous/Next + page-number pagination controls. $extraParams is appended to both links (e.g. ['child' => 3]). */
function render_pagination(int $page, int $totalPages, string $baseUrl = 'history.php', array $extraParams = []): void {
    if ($totalPages <= 1) return;
    $extra = '';
    foreach ($extraParams as $k => $v) {
        $extra .= '&' . urlencode($k) . '=' . urlencode((string) $v);
    }
    ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= $baseUrl ?>?page=<?= $page - 1 ?><?= $extra ?>" class="page-link">‹ Eelmised</a>
        <?php else: ?>
            <span class="page-link disabled">‹ Eelmised</span>
        <?php endif; ?>

        <span class="page-status">Leht <?= $page ?> / <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrl ?>?page=<?= $page + 1 ?><?= $extra ?>" class="page-link">Järgmised ›</a>
        <?php else: ?>
            <span class="page-link disabled">Järgmised ›</span>
        <?php endif; ?>
    </nav>
    <?php
}

// =========================================================
// Books ("Raamatud") — per child
// =========================================================

function get_books(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT b.*, COALESCE((SELECT SUM(e.raamat) FROM entries e WHERE e.book_id = b.id), 0) as total_minutes
        FROM books b WHERE b.child_id = :cid ORDER BY
        FIELD(b.status, 'loeb', 'lugemata', 'loetud'),
        COALESCE(b.finished_date, b.started_date) DESC,
        b.id DESC");
    $stmt->execute([':cid' => $childId]);
    return $stmt->fetchAll();
}

/** Fetches a book AND verifies it belongs to $childId — pass the current family's child. */
function get_book_for_child(int $bookId, int $childId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id AND child_id = :cid");
    $stmt->execute([':id' => $bookId, ':cid' => $childId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Quickly creates a book on the fly (from the "+ Uus raamat" option when logging an entry). */
function create_book(int $childId, string $title, ?string $author = null, string $status = 'loeb'): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO books (child_id, title, author, status) VALUES (:cid, :title, :author, :status)");
    $stmt->execute([':cid' => $childId, ':title' => $title, ':author' => $author, ':status' => $status]);
    return (int) $pdo->lastInsertId();
}

/** If a book is still sitting in the backlog ("lugemata") and a reading session gets logged
 * against it, move it to "loeb" — otherwise the backlog never reflects reality. Never
 * touches a book that's already "loeb" or "loetud". */
function mark_book_started(int $bookId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE books SET status = 'loeb', started_date = COALESCE(started_date, CURDATE()) WHERE id = :id AND status = 'lugemata'");
    $stmt->execute([':id' => $bookId]);
}

function count_finished_books(int $childId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE child_id = :cid AND status = 'loetud'");
    $stmt->execute([':cid' => $childId]);
    return (int) $stmt->fetchColumn();
}

/** Advance a book's current page (never rewind), e.g. from a logged reading session. */
function update_book_page(int $bookId, int $childId, int $page): void {
    if ($bookId <= 0 || $page <= 0) {
        return;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE books SET current_page = :p
        WHERE id = :id AND child_id = :cid AND (current_page IS NULL OR current_page < :p2)");
    $stmt->execute([':p' => $page, ':id' => $bookId, ':cid' => $childId, ':p2' => $page]);
}

/** Books / pages / reading time / active days for one child in a calendar year. */
function get_year_summary(int $childId, int $year): array {
    $pdo = get_db();
    $start = sprintf('%04d-01-01', $year);
    $end   = sprintf('%04d-12-31', $year);

    $e = $pdo->prepare("SELECT
        COALESCE(SUM(raamat), 0) AS reading_minutes,
        COALESCE(SUM(ekraan), 0) AS screen_minutes,
        COUNT(DISTINCT CASE WHEN raamat > 0 THEN entry_date END) AS reading_days
      FROM entries WHERE child_id = :cid AND entry_date BETWEEN :s AND :e");
    $e->execute([':cid' => $childId, ':s' => $start, ':e' => $end]);
    $row = $e->fetch() ?: [];

    $b = $pdo->prepare("SELECT
        COUNT(*) AS books_read,
        COALESCE(SUM(total_pages), 0) AS pages_read
      FROM books WHERE child_id = :cid AND status = 'loetud'
        AND finished_date BETWEEN :s AND :e");
    $b->execute([':cid' => $childId, ':s' => $start, ':e' => $end]);
    $brow = $b->fetch() ?: [];

    return [
        'year'            => $year,
        'books_read'      => (int) ($brow['books_read'] ?? 0),
        'pages_read'      => (int) ($brow['pages_read'] ?? 0),
        'reading_minutes' => (int) ($row['reading_minutes'] ?? 0),
        'screen_minutes'  => (int) ($row['screen_minutes'] ?? 0),
        'reading_days'    => (int) ($row['reading_days'] ?? 0),
    ];
}

/** [minYear, maxYear] covering a child's data; always includes the current year. */
function get_reading_year_range(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT MIN(YEAR(entry_date)) AS mn, MAX(YEAR(entry_date)) AS mx
        FROM entries WHERE child_id = :cid");
    $stmt->execute([':cid' => $childId]);
    $r = $stmt->fetch() ?: [];
    $cur = (int) date('Y');
    $mn = !empty($r['mn']) ? (int) $r['mn'] : $cur;
    $mx = !empty($r['mx']) ? max((int) $r['mx'], $cur) : $cur;
    return [min($mn, $cur), $mx];
}

/** 2x2 grid of headline year numbers. */
function render_year_summary(array $s): void {
    $nf = fn($n) => number_format($n, 0, ',', "\u{202F}");
    ?>
    <div class="ys-grid">
        <div class="ys-stat"><span class="ys-n"><?= $nf($s['books_read']) ?></span><span class="ys-l">Raamatut loetud</span></div>
        <div class="ys-stat"><span class="ys-n"><?= $nf($s['pages_read']) ?></span><span class="ys-l">Lehekülge</span></div>
        <div class="ys-stat"><span class="ys-n"><?= format_duration($s['reading_minutes']) ?></span><span class="ys-l">Loetud aega</span></div>
        <div class="ys-stat"><span class="ys-n"><?= $nf($s['reading_days']) ?></span><span class="ys-l">Lugemispäeva</span></div>
    </div>
    <?php
}

/** Year navigation arrows for the year-summary card. $baseUrl already carries child/token. */
function render_year_nav(int $year, int $minYear, int $maxYear, string $baseUrl): void {
    ?>
    <div class="ys-year">
        <?php if ($year > $minYear): ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>&year=<?= $year - 1 ?>" aria-label="Eelmine aasta">‹</a>
        <?php else: ?><span class="disabled">‹</span><?php endif; ?>
        <span><?= $year ?></span>
        <?php if ($year < $maxYear): ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>&year=<?= $year + 1 ?>" aria-label="Järgmine aasta">›</a>
        <?php else: ?><span class="disabled">›</span><?php endif; ?>
    </div>
    <?php
}

/** Books with the most minutes actually logged against them (via linked entries). */
function get_top_books(int $childId, int $limit = 5): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT b.title, SUM(e.raamat) as minutes
        FROM entries e JOIN books b ON b.id = e.book_id
        WHERE e.child_id = :cid AND e.raamat > 0
        GROUP BY b.id, b.title ORDER BY minutes DESC LIMIT :lim");
    $stmt->bindValue(':cid', $childId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Renders the book list, grouped by status. $editable adds a "Muuda" link. */
function render_books_table(array $books, bool $editable = false): void {
    if (empty($books)) {
        echo '<p class="empty">Raamatuid pole veel lisatud.</p>';
        return;
    }

    // get_books() already orders loeb -> lugemata -> loetud.
    $sections = [
        'loeb'     => ['Loeb praegu', 'bl-reading'],
        'lugemata' => ['Lugemata',    'bl-unread'],
        'loetud'   => ['Loetud',      'bl-done'],
    ];
    $groups = [];
    foreach ($books as $b) {
        $groups[$b['status']][] = $b;
    }
    ?>
    <div class="books-list">
        <?php foreach ($sections as $status => [$heading, $cls]):
            if (empty($groups[$status])) { continue; } ?>
            <div class="bl-group">
                <p class="bl-group-head <?= $cls ?>"><?= $heading ?><span class="bl-count"><?= count($groups[$status]) ?></span></p>
                <?php foreach ($groups[$status] as $b):
                    $mins = isset($b['total_minutes']) ? (int) $b['total_minutes'] : 0;
                    $totalPages = (int) ($b['total_pages'] ?? 0);
                    $curPage = (int) ($b['current_page'] ?? 0);
                    $showBar = $totalPages > 0 && $status === 'loeb';
                    $pct = $showBar ? min(100, max(0, (int) round($curPage / $totalPages * 100))) : 0;
                    $showPages = $totalPages > 0 && $status !== 'loeb';
                    $hasMeta = $mins > 0 || !empty($b['finished_date']) || $showPages; ?>
                    <div class="bl-item <?= $cls ?>">
                        <div class="bl-main">
                            <span class="bl-title"><?= htmlspecialchars($b['title']) ?></span>
                            <?php if (!empty($b['author'])): ?>
                                <span class="bl-author"><?= htmlspecialchars($b['author']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($showBar): ?>
                            <div class="bl-progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="bl-progress-fill" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="bl-progress-label">lk <?= number_format($curPage, 0, ',', "\u{202F}") ?> / <?= number_format($totalPages, 0, ',', "\u{202F}") ?> · <?= $pct ?>%</div>
                        <?php endif; ?>
                        <?php if ($hasMeta): ?>
                            <div class="bl-meta">
                                <?php if ($mins > 0): ?><span class="bl-mins">📖 <?= format_duration($mins) ?></span><?php endif; ?>
                                <?php if ($showPages): ?><span class="bl-pages">📄 <?= number_format($totalPages, 0, ',', "\u{202F}") ?> lk</span><?php endif; ?>
                                <?php if (!empty($b['finished_date'])): ?><span class="bl-date">✓ <?= htmlspecialchars(date('d.M.Y', strtotime($b['finished_date']))) ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($editable): ?>
                            <a href="edit_book.php?id=<?= $b['id'] ?>" class="bl-edit" aria-label="Muuda" title="Muuda"><?= icon("pencil") ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/** Short label suffix for a book <option>, e.g. " (lugemata)". Empty for "loeb" (the common case). */
function book_status_suffix(string $status): string {
    if ($status === 'loetud') return ' (loetud)';
    if ($status === 'lugemata') return ' (lugemata)';
    return '';
}

function get_book_milestone(int $count): ?int {
    $milestones = [5, 10, 20, 30, 50, 75, 100, 150, 200];
    $reached = null;
    foreach ($milestones as $m) {
        if ($count >= $m) $reached = $m;
    }
    return ($reached !== null && $count === $reached) ? $reached : null;
}

// =========================================================
// Stats — trend, streak, heatmap, top activities (per child)
// =========================================================

/** Minutes per day for the last $days days (including days with nothing logged). */
function get_daily_totals_range(int $childId, int $days = 30): array {
    $pdo = get_db();
    $start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $stmt = $pdo->prepare("SELECT entry_date, SUM(raamat) as raamat, SUM(ekraan) as ekraan FROM entries WHERE child_id = :cid AND entry_date >= :start GROUP BY entry_date");
    $stmt->execute([':cid' => $childId, ':start' => $start]);
    $byDate = [];
    foreach ($stmt as $row) {
        $byDate[$row['entry_date']] = ['raamat' => (int) $row['raamat'], 'ekraan' => (int) $row['ekraan']];
    }
    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $result[] = [
            'date' => $d,
            'raamat' => $byDate[$d]['raamat'] ?? 0,
            'ekraan' => $byDate[$d]['ekraan'] ?? 0,
        ];
    }
    return $result;
}

function get_current_streak(int $childId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT entry_date, SUM(raamat) as raamat, SUM(ekraan) as ekraan FROM entries WHERE child_id = :cid GROUP BY entry_date ORDER BY entry_date DESC");
    $stmt->execute([':cid' => $childId]);
    $rows = $stmt->fetchAll();
    $streak = 0;
    foreach ($rows as $row) {
        $raamat = (int) $row['raamat'];
        $ekraan = (int) $row['ekraan'];
        if (round($ekraan * READING_RATIO) - $raamat <= 0) {
            $streak++;
        } else {
            break;
        }
    }
    return $streak;
}

/** Most common comments for one activity type, e.g. most-watched/most-read. */
function get_top_comments(int $childId, string $column, int $limit = 5): array {
    $pdo = get_db();
    $col = $column === 'raamat' ? 'raamat_comment' : 'ekraan_comment';
    $sumCol = $column === 'raamat' ? 'raamat' : 'ekraan';
    $stmt = $pdo->prepare("SELECT $col as comment, SUM($sumCol) as minutes, COUNT(*) as sessions
        FROM entries WHERE child_id = :cid AND $col IS NOT NULL AND $col != ''
        GROUP BY $col ORDER BY minutes DESC LIMIT :lim");
    $stmt->bindValue(':cid', $childId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Searches book titles and screen comments for a keyword, within one child's entries (no pagination). */
function search_entries(int $childId, string $q): array {
    $pdo = get_db();
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("SELECT e.*, b.title as book_title FROM entries e
        LEFT JOIN books b ON b.id = e.book_id
        WHERE e.child_id = :cid AND (e.raamat_comment LIKE :q1 OR e.ekraan_comment LIKE :q2 OR b.title LIKE :q3)
        ORDER BY e.entry_date DESC, e.id ASC LIMIT 200");
    $stmt->execute([':cid' => $childId, ':q1' => $like, ':q2' => $like, ':q3' => $like]);
    return $stmt->fetchAll();
}

/** Slim horizontal bar showing reading vs. screen minutes side by side. */
function render_balance_bar(int $raamat, int $ekraan): void {
    $total = $raamat + $ekraan;
    $raamatPct = $total > 0 ? round($raamat / $total * 100) : 50;
    $ekraanPct = 100 - $raamatPct;
    ?>
    <div class="balance-bar">
        <div class="balance-bar-fill raamat" style="width: <?= $raamatPct ?>%"></div>
        <div class="balance-bar-fill ekraan" style="width: <?= $ekraanPct ?>%"></div>
    </div>
    <div class="balance-bar-legend">
        <span><span class="dot dot-reading"></span> Raamat <?= $raamatPct ?>%</span>
        <span><span class="dot dot-screen"></span> Ekraan <?= $ekraanPct ?>%</span>
    </div>
    <?php
}

/** 30-day heatmap: one square per day, colored by whether that day was balanced. */
function render_heatmap(array $dailyTotals): void {
    ?>
    <div class="heatmap">
        <?php foreach ($dailyTotals as $d):
            $hasData = $d['raamat'] > 0 || $d['ekraan'] > 0;
            $owed = round($d['ekraan'] * READING_RATIO) - $d['raamat'];
            if (!$hasData) {
                $class = 'hm-empty';
                $state = 'Kandeid pole';
            } elseif ($owed > 0) {
                $class = 'hm-debt';
                $state = 'Võlgu';
            } elseif ($owed < 0) {
                $class = 'hm-bonus';
                $state = 'Boonuses';
            } else {
                $class = 'hm-good';
                $state = 'Tasakaalus';
            }
            $label = date('d.m', strtotime($d['date'])) . ': ' . $d['raamat'] . ' min raamat, ' . $d['ekraan'] . ' min ekraan — ' . $state;
        ?>
            <div class="hm-cell <?= $class ?>" title="<?= htmlspecialchars($label) ?>"></div>
        <?php endforeach; ?>
    </div>
    <div class="heatmap-legend">
        <span><span class="hm-cell hm-good" style="display:inline-block;"></span> Tasakaalus</span>
        <span><span class="hm-cell hm-bonus" style="display:inline-block;"></span> Boonuses</span>
        <span><span class="hm-cell hm-debt" style="display:inline-block;"></span> Võlgu</span>
        <span><span class="hm-cell hm-empty" style="display:inline-block;"></span> Kandeid pole</span>
    </div>
    <?php
}

/**
 * Renders the entries as an HTML table grouped by date, with a merged
 * date cell and a color band per date group — matching the spreadsheet.
 * Each session becomes ONE ROW PER ACTIVITY (book, screen) instead of
 * two mostly-empty numeric columns. $editable adds a single "Muuda" edit
 * icon next to the date; $childId is required whenever $editable is true,
 * so the edit link knows which child's data to go back to.
 */
function render_entries_table(array $entries, bool $editable = false, int $childId = 0): void {
    if (empty($entries)) {
        echo '<p class="empty">Kandeid pole veel. Lisa esimene kanne ülalt.</p>';
        return;
    }

    $activities = [];
    foreach ($entries as $e) {
        if ((int) $e['raamat'] > 0) {
            $bookTitle = $e['book_title'] ?? null;
            $note = $e['raamat_comment'] ?? null;
            // Legacy entries (from before books were linked) stored the book
            // name itself in raamat_comment — show it as the label if there's
            // no linked book.
            $label = $bookTitle ?: $note;
            $subNote = ($bookTitle && $note) ? $note : null;
            $activities[] = ['date' => $e['entry_date'], 'type' => 'raamat', 'minutes' => (int) $e['raamat'], 'label' => $label, 'sub' => $subNote, 'id' => $e['id']];
        }
        if ((int) $e['ekraan'] > 0) {
            $activities[] = ['date' => $e['entry_date'], 'type' => 'ekraan', 'minutes' => (int) $e['ekraan'], 'label' => $e['ekraan_comment'], 'sub' => null, 'id' => $e['id']];
        }
    }

    $groups = [];
    foreach ($activities as $a) {
        $groups[$a['date']][] = $a;
    }

    $colors = ['blue', 'green', 'peach', 'purple'];
    $colorIndex = 0;
    ?>
    <div class="entries-list">
        <?php foreach ($groups as $date => $rows):
            $colorClass = 'day-' . $colors[$colorIndex % count($colors)];
            $colorIndex++;
            ?>
            <div class="day-group <?= $colorClass ?>">
                <div class="day-head">
                    <span class="day-date"><?= htmlspecialchars(date('d.M', strtotime($date))) ?></span>
                    <?php if ($editable): ?>
                        <a href="edit_day.php?date=<?= urlencode($date) ?>&child=<?= $childId ?>" class="btn-edit-day" aria-label="Muuda seda päeva" title="Muuda seda päeva"><?= icon("pencil") ?> Muuda</a>
                    <?php endif; ?>
                </div>
                <div class="day-rows">
                    <?php foreach ($rows as $a): ?>
                        <div class="entry-row">
                            <?php if ($a['type'] === 'raamat'): ?>
                                <span class="tag tag-reading">📖 <?= $a['minutes'] ?> min</span>
                            <?php else: ?>
                                <span class="tag tag-screen">📱 <?= $a['minutes'] ?> min</span>
                            <?php endif; ?>
                            <div class="entry-label">
                                <?= $a['label'] ? htmlspecialchars($a['label']) : '–' ?>
                                <?php if ($a['sub']): ?><span class="entry-sub"><?= htmlspecialchars($a['sub']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

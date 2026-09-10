<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// =========================================================
// Never cache the HTML documents themselves
// =========================================================
// The CSS, JS and icons are versioned with ?v= and cache fine. But a stale
// cached *page* is a real problem on iOS: when a Screen Time passcode is set,
// Safari's cache can't be cleared at all, so an old child.php / paren.php would
// stick forever. no-store tells every cache (Safari and the Hostinger CDN) to
// re-fetch the markup each time; the versioned assets it references still cache.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Poem-memorisation entries (entries.kind = 'luuletus') count as normal reading
// minutes everywhere, but give this multiplier's worth of credit against the
// screen-time debt. 2.0 => 15 min learnt by heart erases 30 min of screen debt.
if (!defined('POEM_BONUS_MULT')) {
    define('POEM_BONUS_MULT', 2.0);
}

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
// Kategooria-ikoonid — inline Microsoft Fluent Emoji (flat),
// MIT (https://github.com/microsoft/fluentui-emoji). Värvilised,
// et "lugemine" / "ekraan" oleksid lapsesõbralikud ja näeksid
// igas seadmes ühtemoodi välja. Mõõt tuleb .emo klassist (1em),
// nii et kutse pärib ümbritseva teksti suuruse (.pt-k, .tag jne).
// =========================================================

function emoji_svg(string $name): string {
    static $svg = [
        'books'  => '<svg class="emo" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false"><path d="M17.0454 27.2857H30V13C30 11.8954 29.1045 11 28 11H17.0454V27.2857Z" fill="#00A6ED"/><path d="M15.6818 27.9642H30V29.3214H15.6818V27.9642Z" fill="#D3D3D3"/><path d="M16.0227 11C15.4579 11 15 11.4557 15 12.0179V28.6429H15.6818C15.6818 28.2681 15.9871 27.9643 16.3636 27.9643H17.0455V11H16.0227Z" fill="#0074BA"/><path d="M16.0227 27.2858C15.4579 27.2858 15 27.7415 15 28.3036V28.9822C15 29.5443 15.4579 30.0001 16.0227 30.0001H28.9773C29.4226 30.0001 29.8014 29.7168 29.9418 29.3215H16.3636C15.9871 29.3215 15.6818 29.0177 15.6818 28.6429C15.6818 28.2681 15.9871 27.9643 16.3636 27.9643H30V27.2858H16.0227Z" fill="#0074BA"/><path d="M10.0454 23.2857H23V9C23 7.89543 22.1045 7 21 7H10.0454V23.2857Z" fill="#CA0B4A"/><path d="M8.68182 23.9642H23V25.3214H8.68182V23.9642Z" fill="#D3D3D3"/><path d="M9.02273 7C8.45789 7 8 7.45571 8 8.01786V24.6429H8.68182C8.68182 24.2681 8.98708 23.9643 9.36364 23.9643H10.0455V7H9.02273Z" fill="#990838"/><path d="M9.02273 23.2858C8.45789 23.2858 8 23.7415 8 24.3036V24.9822C8 25.5443 8.45789 26.0001 9.02273 26.0001H21.9773C22.4226 26.0001 22.8014 25.7168 22.9418 25.3215H9.36364C8.98708 25.3215 8.68182 25.0177 8.68182 24.6429C8.68182 24.2681 8.98708 23.9643 9.36364 23.9643H23V23.2858H9.02273Z" fill="#990838"/><path d="M4.04541 20.2857H17V6C17 4.89543 16.1045 4 15 4H4.04541V20.2857Z" fill="#86D72F"/><path d="M2.68182 20.9642H17V22.3214H2.68182V20.9642Z" fill="#D3D3D3"/><path d="M3.02273 4C2.45789 4 2 4.45571 2 5.01786V21.6429H2.68182C2.68182 21.2681 2.98708 20.9643 3.36364 20.9643H4.04545V4H3.02273Z" fill="#44911B"/><path d="M3.02273 20.2858C2.45789 20.2858 2 20.7415 2 21.3036V21.9822C2 22.5443 2.45789 23.0001 3.02273 23.0001H15.9773C16.4226 23.0001 16.8014 22.7168 16.9418 22.3215H3.36364C2.98708 22.3215 2.68182 22.0177 2.68182 21.6429C2.68182 21.2681 2.98708 20.9643 3.36364 20.9643H17V20.2858H3.02273Z" fill="#008463"/></svg>',
        'screen' => '<svg class="emo" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false"><path d="M10.3535 3.06063C10.1582 2.86537 10.1582 2.54879 10.3535 2.35352C10.5487 2.15826 10.8653 2.15826 11.0606 2.35352L14.9497 6.24261L14.2426 6.94972L10.3535 3.06063Z" fill="#636363"/><path d="M18.889 2.35348C19.0842 2.15822 19.4008 2.15822 19.5961 2.35348C19.7914 2.54874 19.7914 2.86532 19.5961 3.06058L15.707 6.94967L14.9999 6.24257L18.889 2.35348Z" fill="#636363"/><path d="M11.0002 23.5L8.00024 23L6.46544 28.7279C6.31248 29.2987 6.65125 29.8855 7.22209 30.0385C7.73725 30.1765 8.2754 29.9141 8.48393 29.4232L11.0002 23.5Z" fill="#636363"/><path d="M21.0002 23.5L24.0002 23L25.535 28.7279C25.688 29.2987 25.3492 29.8855 24.7784 30.0385C24.2632 30.1765 23.7251 29.9141 23.5165 29.4232L21.0002 23.5Z" fill="#636363"/><path d="M3 9C3 7.34315 4.34315 6 6 6H26C27.6569 6 29 7.34315 29 9V22C29 23.6569 27.6569 25 26 25H6C4.34315 25 3 23.6569 3 22V9Z" fill="#9B9B9B"/><circle cx="25.75" cy="13.25" r="1.25" fill="#CA0B4A"/><circle cx="25.75" cy="9.25" r="1.25" fill="#636363"/><path d="M6.5 11.5C6.5 10.3954 7.39543 9.5 8.5 9.5H20.5C21.6046 9.5 22.5 10.3954 22.5 11.5V19.5C22.5 20.6046 21.6046 21.5 20.5 21.5H8.5C7.39543 21.5 6.5 20.6046 6.5 19.5V11.5Z" fill="#83CBFF"/><path d="M21 10C21.5523 10 22 10.4477 22 11V20C22 20.5523 21.5523 21 21 21H8C7.44772 21 7 20.5523 7 20V11C7 10.4477 7.44772 10 8 10H21ZM8 9C6.89543 9 6 9.89543 6 11V20C6 21.1046 6.89543 22 8 22H21C22.1046 22 23 21.1046 23 20V11C23 9.89543 22.1046 9 21 9H8Z" fill="#321B41"/></svg>',
    ];
    return $svg[$name] ?? '';
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

/** Sets a child's reading goals + earned-screen-time cap; pass null to clear one. */
function set_child_goal(int $childId, ?int $daily, ?int $weekly, ?int $rewardCap, ?float $ratio = null): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE children SET daily_goal_min = :d, weekly_goal_min = :w, screen_reward_cap_min = :r, reading_ratio = :ratio WHERE id = :id");
    $stmt->execute([':d' => $daily, ':w' => $weekly, ':r' => $rewardCap, ':ratio' => $ratio, ':id' => $childId]);
}

/** "Earned screen time" card. Opt-in: only renders when a cap is set and the
 *  child is actually in credit (owed < 0 = read more than watched). */
function render_reward_card(int $owed, int $cap): void {
    if ($cap <= 0) {
        return;
    }
    $earned = max(0, -$owed);
    if ($earned <= 0) {
        return;
    }
    $shown = min($earned, $cap);
    ?>
    <div class="reward-card">
        <div class="reward-icon"><?= emoji_svg('screen') ?></div>
        <div class="reward-body">
            <div class="reward-label">Teenitud ekraaniaeg</div>
            <div class="reward-value"><?= format_duration($shown) ?></div>
            <?php if ($earned > $cap): ?>
                <div class="reward-sub">Rohkem on veel varuks 👍</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
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

    $requested = (int) ($_GET['child'] ?? $_POST['child'] ?? 0);
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

function get_family(int $familyId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM families WHERE id = :id");
    $stmt->execute([':id' => $familyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_family_by_email(string $email): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM families WHERE email = :e");
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// =========================================================
// Parent logins — the primary account (families row) plus any
// co-parent logins (family_logins) that resolve to the same family.
// =========================================================

/** Verify email+password against the primary account and any co-parent login.
 *  Returns [family_id, kind ('family'|'login'), login_id, email, status,
 *  is_demo] or null. */
function authenticate_parent(string $email, string $password): ?array {
    $email = trim($email);
    if ($email === '') return null;
    $pdo = get_db();

    $stmt = $pdo->prepare("SELECT id, email, password_hash, status, is_demo FROM families WHERE email = :e");
    $stmt->execute([':e' => $email]);
    if ($f = $stmt->fetch()) {
        if (!password_verify($password, $f['password_hash'])) return null;
        return ['family_id' => (int) $f['id'], 'kind' => 'family', 'login_id' => (int) $f['id'],
                'email' => $f['email'], 'status' => $f['status'], 'is_demo' => (int) $f['is_demo']];
    }

    $stmt = $pdo->prepare("SELECT l.id, l.email, l.password_hash, l.family_id, f.status, f.is_demo
        FROM family_logins l JOIN families f ON f.id = l.family_id WHERE l.email = :e");
    $stmt->execute([':e' => $email]);
    if ($l = $stmt->fetch()) {
        if (!password_verify($password, $l['password_hash'])) return null;
        return ['family_id' => (int) $l['family_id'], 'kind' => 'login', 'login_id' => (int) $l['id'],
                'email' => $l['email'], 'status' => $l['status'], 'is_demo' => (int) $l['is_demo']];
    }
    return null;
}

/** Is this email already in use as any parent login (primary or co-parent)? */
function parent_email_exists(string $email): bool {
    $pdo = get_db();
    $email = trim($email);
    $a = $pdo->prepare("SELECT 1 FROM families WHERE email = :e");
    $a->execute([':e' => $email]);
    if ($a->fetchColumn()) return true;
    $b = $pdo->prepare("SELECT 1 FROM family_logins WHERE email = :e");
    $b->execute([':e' => $email]);
    return (bool) $b->fetchColumn();
}

function get_family_logins(int $familyId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM family_logins WHERE family_id = :fid ORDER BY created_at ASC, id ASC");
    $stmt->execute([':fid' => $familyId]);
    return $stmt->fetchAll();
}

function add_family_login(int $familyId, string $email, string $name, string $password): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO family_logins (family_id, email, name, password_hash) VALUES (:fid, :e, :n, :h)");
    $stmt->execute([
        ':fid' => $familyId,
        ':e'   => trim($email),
        ':n'   => ($n = trim($name)) !== '' ? mb_substr($n, 0, 100) : null,
        ':h'   => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function delete_family_login(int $loginId, int $familyId): void {
    $pdo = get_db();
    $pdo->prepare("DELETE FROM family_logins WHERE id = :id AND family_id = :fid")
        ->execute([':id' => $loginId, ':fid' => $familyId]);
}

/** Change the password of whichever record the current session logged in as. */
function change_current_password(string $current, string $new): bool {
    $kind = $_SESSION['login_kind'] ?? 'family';
    $loginId = (int) ($_SESSION['login_id'] ?? 0);
    $familyId = (int) ($_SESSION['family_id'] ?? 0);
    $pdo = get_db();
    if ($kind === 'login') {
        $stmt = $pdo->prepare("SELECT password_hash FROM family_logins WHERE id = :id AND family_id = :fid");
        $stmt->execute([':id' => $loginId, ':fid' => $familyId]);
        $hash = $stmt->fetchColumn();
        if ($hash === false || !password_verify($current, (string) $hash)) return false;
        $pdo->prepare("UPDATE family_logins SET password_hash = :h WHERE id = :id")
            ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $loginId]);
        return true;
    }
    $stmt = $pdo->prepare("SELECT password_hash FROM families WHERE id = :id");
    $stmt->execute([':id' => $familyId]);
    $hash = $stmt->fetchColumn();
    if ($hash === false || !password_verify($current, (string) $hash)) return false;
    $pdo->prepare("UPDATE families SET password_hash = :h WHERE id = :id")
        ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $familyId]);
    return true;
}

function current_login_email(): string {
    return (string) ($_SESSION['login_email'] ?? '');
}

function is_demo_session(): bool {
    return !empty($_SESSION['is_demo']);
}

/**
 * True for the site owner. Qualifies in either of two ways:
 *  - a legacy `admin_login.php` session (`$_SESSION['is_admin']`), or
 *  - being logged in as the family account whose e-mail matches
 *    `ADMIN_EMAIL` in config.php (case-insensitive) — so the owner reaches
 *    admin.php straight from their normal login, with no second password.
 * If `ADMIN_EMAIL` is unset/empty, only the legacy path applies.
 */
function is_admin(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // The demo account is never the owner, even if a stale admin flag from an
    // earlier admin.php session lingers in the same browser.
    if (!empty($_SESSION['is_demo'])) {
        return false;
    }
    if (!empty($_SESSION['is_admin'])) {
        return true;
    }
    $adminEmail = defined('ADMIN_EMAIL') ? trim((string) ADMIN_EMAIL) : '';
    if ($adminEmail === '' || empty($_SESSION['family_id'])) {
        return false;
    }
    $family = get_family((int) $_SESSION['family_id']);
    return $family !== null
        && strcasecmp(trim((string) $family['email']), $adminEmail) === 0;
}

// =========================================================
// Password reset (parent accounts)
// =========================================================
//
// Flow: forgot.php creates a token for an *approved* family, tries to email
// the reset link via mail(), and — because shared-hosting mail() is
// unreliable — the same link is always shown to the site owner in admin.php
// to hand over manually. reset.php verifies the token and sets a new hash.
//
// The `password_resets` row keeps both `token_hash` (the value reset.php
// looks up) and the raw `token` (only so admin.php can rebuild a working
// link for the manual fallback). The row is deleted the moment the token is
// used, and a family can only ever have one active reset row (UNIQUE key).

// How long a reset link stays valid.
define('PASSWORD_RESET_TTL', 60 * 60 * 24); // 24 tundi

/** Absolute site origin for the current request, e.g. "https://ajaraamat.ee". */
function base_url(): string {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host  = $_SERVER['HTTP_HOST'] ?? 'ajaraamat.ee';
    return ($https ? 'https' : 'http') . '://' . $host;
}

/**
 * Issue a fresh single-use reset token for a family, replacing any earlier
 * one. Returns the RAW token (never stored anywhere it can be read back for
 * verification) to drop into the reset link.
 */
function create_password_reset(int $familyId): string {
    $pdo = get_db();
    // Drop this family's previous token and sweep anything already expired.
    $pdo->prepare("DELETE FROM password_resets WHERE family_id = :fid OR expires_at < NOW()")
        ->execute([':fid' => $familyId]);

    $raw = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        "INSERT INTO password_resets (family_id, token_hash, token, expires_at)
         VALUES (:fid, :h, :t, :exp)"
    );
    $stmt->execute([
        ':fid' => $familyId,
        ':h'   => hash('sha256', $raw),
        ':t'   => $raw,
        ':exp' => date('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL),
    ]);
    return $raw;
}

/** The reset row for a still-valid raw token, or null. Does not consume it. */
function find_valid_password_reset(string $rawToken): ?array {
    if ($rawToken === '') {
        return null;
    }
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare(
            "SELECT * FROM password_resets
             WHERE token_hash = :h AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([':h' => hash('sha256', $rawToken)]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        // Table not migrated yet — treat as no valid token.
        return null;
    }
}

/** Set the family's new bcrypt hash and burn the reset token. */
function complete_password_reset(int $resetId, int $familyId, string $newPassword): void {
    $pdo = get_db();
    $pdo->prepare("UPDATE families SET password_hash = :h WHERE id = :id")
        ->execute([':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $familyId]);
    $pdo->prepare("DELETE FROM password_resets WHERE id = :id")
        ->execute([':id' => $resetId]);
}

/**
 * Active (unexpired) reset requests with the family e-mail — for admin.php.
 * Returns [] if the `password_resets` table hasn't been created yet
 * (migrate_password_resets.php), so the admin panel never 500s on a
 * deploy that landed before the migration ran.
 */
function get_active_password_resets(): array {
    try {
        $pdo = get_db();
        return $pdo->query(
            "SELECT pr.*, f.email
             FROM password_resets pr
             JOIN families f ON f.id = pr.family_id
             WHERE pr.expires_at > NOW()
             ORDER BY pr.created_at DESC"
        )->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Best-effort: e-mail the reset link. Shared-hosting mail() often silently
 * fails or lands in spam, so callers must ALSO surface the link in admin.php.
 * Returns mail()'s own boolean result.
 */
function send_password_reset_email(string $toEmail, string $link): bool {
    $host = $_SERVER['HTTP_HOST'] ?? 'ajaraamat.ee';
    $subject = 'Ajaraamat — parooli lähtestamine';
    $body =
        "Keegi (loodetavasti sina) palus Ajaraamatus parooli lähtestamist.\n\n" .
        "Ava see link 24 tunni jooksul ja vali uus parool:\n" .
        $link . "\n\n" .
        "Kui sa ei palunud parooli lähtestamist, jäta see kiri tähelepanuta — " .
        "sinu parool ei muutu.\n";
    $headers = implode("\r\n", [
        'From: Ajaraamat <no-reply@' . $host . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ]);
    return @mail(
        $toEmail,
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        $body,
        $headers
    );
}

// =========================================================
// Reading / screen entries (per child)
// =========================================================

/** Reading-minutes owed per screen minute for this child. A per-child
 *  children.reading_ratio overrides the site-wide READING_RATIO default. */
function child_reading_ratio(int $childId): float {
    static $cache = [];
    if (!array_key_exists($childId, $cache)) {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT reading_ratio FROM children WHERE id = :id");
        $stmt->execute([':id' => $childId]);
        $r = $stmt->fetchColumn();
        $cache[$childId] = ($r !== false && $r !== null && (float) $r > 0) ? (float) $r : (float) READING_RATIO;
    }
    return $cache[$childId];
}

function get_totals(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(raamat),0) as raamat,
        COALESCE(SUM(ekraan),0) as ekraan,
        COALESCE(SUM(CASE WHEN kind = 'luuletus' THEN raamat ELSE 0 END),0) as poem
      FROM entries WHERE child_id = :cid");
    $stmt->execute([':cid' => $childId]);
    $row = $stmt->fetch();
    $raamat = (int) $row['raamat'];
    $ekraan = (int) $row['ekraan'];
    $bonus  = (int) round($row['poem'] * (POEM_BONUS_MULT - 1)); // luuletuse lisakrediit
    $owed = round($ekraan * child_reading_ratio($childId)) - $raamat - $bonus; // positive = lugemist võlgu
    return ['raamat' => $raamat, 'ekraan' => $ekraan, 'owed' => $owed, 'poem' => (int) $row['poem']];
}

function get_today_totals(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(raamat),0) as raamat, COALESCE(SUM(ekraan),0) as ekraan FROM entries WHERE child_id = :cid AND entry_date = CURDATE()");
    $stmt->execute([':cid' => $childId]);
    $row = $stmt->fetch();
    return ['raamat' => (int) $row['raamat'], 'ekraan' => (int) $row['ekraan']];
}

// =========================================================
// Child self-logging — entries the child adds, held for parent approval.
// Kept in a separate table so nothing counts them until approved.
// =========================================================

function add_pending_entry(int $childId, string $date, string $type, int $minutes, ?int $bookId, ?string $note, ?int $currentPage, ?string $source = null): void {
    if (!in_array($type, ['raamat', 'ekraan'], true)) return;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $minutes = max(1, min(600, $minutes));
    $note = trim((string) $note);
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO pending_entries (child_id, entry_date, type, minutes, book_id, note, current_page, source)
        VALUES (:cid, :d, :t, :m, :bid, :note, :page, :src)");
    $stmt->execute([
        ':cid'  => $childId,
        ':d'    => $date,
        ':t'    => $type,
        ':m'    => $minutes,
        ':bid'  => $type === 'raamat' && $bookId > 0 ? $bookId : null,
        ':note' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ':page' => $type === 'raamat' && $currentPage > 0 ? $currentPage : null,
        ':src'  => $source !== null ? mb_substr($source, 0, 16) : null,
    ]);
}

function get_pending_entries(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT p.*, b.title AS book_title
        FROM pending_entries p LEFT JOIN books b ON b.id = p.book_id
        WHERE p.child_id = :cid ORDER BY p.entry_date DESC, p.id ASC");
    $stmt->execute([':cid' => $childId]);
    return $stmt->fetchAll();
}

function count_pending_entries(int $childId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pending_entries WHERE child_id = :cid");
    $stmt->execute([':cid' => $childId]);
    return (int) $stmt->fetchColumn();
}

/** Turn one pending entry into a real entry, then drop it from the queue. */
/** Approve a pending entry exactly as the child submitted it. */
function approve_pending_entry(int $id, int $childId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM pending_entries WHERE id = :id AND child_id = :cid");
    $stmt->execute([':id' => $id, ':cid' => $childId]);
    $p = $stmt->fetch();
    if (!$p) return;

    approve_pending_entry_with_edits(
        $id, $childId, (string) $p['entry_date'], (string) $p['type'], (int) $p['minutes'],
        $p['book_id'] ? (int) $p['book_id'] : null, $p['note'],
        $p['current_page'] ? (int) $p['current_page'] : null
    );
}

/** Approve a pending entry, but with a parent's corrections applied first —
 *  saves re-typing it as a fresh entry for a small fix (wrong minutes, wrong
 *  book, ...). Used by edit_pending.php. */
function approve_pending_entry_with_edits(int $id, int $childId, string $date, string $type, int $minutes, ?int $bookId, ?string $note, ?int $currentPage): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id FROM pending_entries WHERE id = :id AND child_id = :cid");
    $stmt->execute([':id' => $id, ':cid' => $childId]);
    if (!$stmt->fetch()) return;

    $isRaamat = $type === 'raamat';
    $minutes = max(1, min(600, $minutes));
    $note = trim((string) $note);
    $note = $note !== '' ? mb_substr($note, 0, 255) : null;

    $realBookId = null;
    if ($isRaamat && $bookId) {
        $chosen = get_book_for_child($bookId, $childId);
        if ($chosen) {
            $realBookId = (int) $chosen['id'];
            mark_book_started($realBookId);
        }
    }
    $ins = $pdo->prepare("INSERT INTO entries (child_id, entry_date, raamat, book_id, raamat_comment, ekraan, ekraan_comment)
        VALUES (:cid, :d, :raamat, :bid, :rc, :ekraan, :ec)");
    $ins->execute([
        ':cid'    => $childId,
        ':d'      => $date,
        ':raamat' => $isRaamat ? $minutes : 0,
        ':bid'    => $realBookId,
        ':rc'     => $isRaamat ? $note : null,
        ':ekraan' => $isRaamat ? 0 : $minutes,
        ':ec'     => !$isRaamat ? $note : null,
    ]);
    if ($isRaamat && $realBookId && $currentPage !== null && $currentPage > 0) {
        update_book_page($realBookId, $childId, $currentPage);
    }
    $pdo->prepare("DELETE FROM pending_entries WHERE id = :id AND child_id = :cid")
        ->execute([':id' => $id, ':cid' => $childId]);
}

function get_pending_entry_for_child(int $id, int $childId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT p.*, b.title AS book_title
        FROM pending_entries p LEFT JOIN books b ON b.id = p.book_id
        WHERE p.id = :id AND p.child_id = :cid");
    $stmt->execute([':id' => $id, ':cid' => $childId]);
    return $stmt->fetch() ?: null;
}

function reject_pending_entry(int $id, int $childId): void {
    $pdo = get_db();
    $pdo->prepare("DELETE FROM pending_entries WHERE id = :id AND child_id = :cid")
        ->execute([':id' => $id, ':cid' => $childId]);
}

/** The pending list. $parent adds Kinnita / Lükka tagasi controls; otherwise
 *  it is a plain read-only "waiting" list for the child's own view. */
function render_pending_queue(array $rows, int $childId, bool $parent = true): void {
    ?>
    <div class="pending-list">
        <?php foreach ($rows as $p):
            $isRaamat = $p['type'] === 'raamat';
            $label = $p['book_title'] ?: ($p['note'] ?: ($isRaamat ? 'Raamat' : 'Ekraan'));
        ?>
        <div class="pending-row">
            <div class="pending-main">
                <span class="tag <?= $isRaamat ? 'tag-reading' : 'tag-screen' ?>"><?= emoji_svg($isRaamat ? 'books' : 'screen') ?> <?= (int) $p['minutes'] ?> min</span>
                <div class="pending-label"><?= htmlspecialchars($label) ?><span class="pending-meta"><?= htmlspecialchars(date('d.m', strtotime($p['entry_date']))) ?><?= $p['source'] === 'taimer' ? ' · taimer' : '' ?></span></div>
            </div>
            <?php if ($parent): ?>
            <div class="pending-actions">
                <a class="pending-edit" href="edit_pending.php?id=<?= (int) $p['id'] ?>&child=<?= $childId ?>" aria-label="Muuda" title="Muuda enne kinnitamist"><?= icon('pencil') ?></a>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="child" value="<?= $childId ?>">
                    <input type="hidden" name="action" value="pending_approve">
                    <input type="hidden" name="pending_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn-approve"><?= icon('check') ?> Kinnita</button>
                </form>
                <form method="post" onsubmit="return confirm('Lükata see kanne tagasi?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="child" value="<?= $childId ?>">
                    <input type="hidden" name="action" value="pending_reject">
                    <input type="hidden" name="pending_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn-reject">Lükka tagasi</button>
                </form>
            </div>
            <?php else: ?>
            <span class="pending-wait">⏳ ootel</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
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
                <div class="pt-row pt-reading"><span class="pt-k"><?= emoji_svg('books') ?></span><span class="pt-t">Raamat</span><span class="pt-v"><?= format_duration($raamat) ?></span></div>
                <div class="pt-row pt-screen"><span class="pt-k"><?= emoji_svg('screen') ?></span><span class="pt-t">Ekraan</span><span class="pt-v"><?= format_duration($ekraan) ?></span></div>
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

/** Advance a book's current page (never rewind), e.g. from a logged reading session.
 *  When the page reaches the last one, the book is marked finished automatically. */
function update_book_page(int $bookId, int $childId, int $page): void {
    if ($bookId <= 0 || $page <= 0) {
        return;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE books SET current_page = :p
        WHERE id = :id AND child_id = :cid AND (current_page IS NULL OR current_page < :p2)");
    $stmt->execute([':p' => $page, ':id' => $bookId, ':cid' => $childId, ':p2' => $page]);

    // Reached (or passed) the final page → finish the book automatically, so it
    // counts towards reading challenges and drops out of the "still reading" list.
    $done = $pdo->prepare("UPDATE books
        SET status = 'loetud', finished_date = COALESCE(finished_date, CURDATE())
        WHERE id = :id AND child_id = :cid AND status <> 'loetud'
          AND total_pages IS NOT NULL AND total_pages > 0
          AND current_page >= total_pages");
    $done->execute([':id' => $bookId, ':cid' => $childId]);
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

    // Pages read = whole books finished this year + progress in books still being
    // read (only counted for the current year, where "in progress" is meaningful).
    $curYear = $year === (int) date('Y') ? 1 : 0;
    $b = $pdo->prepare("SELECT
        COUNT(CASE WHEN status = 'loetud' AND finished_date BETWEEN :s AND :e THEN 1 END) AS books_read,
        COALESCE(SUM(CASE WHEN status = 'loetud' AND finished_date BETWEEN :s2 AND :e2 THEN total_pages END), 0)
          + COALESCE(SUM(CASE WHEN :cur = 1 AND status = 'loeb' THEN current_page END), 0) AS pages_read
      FROM books WHERE child_id = :cid");
    $b->execute([':cid' => $childId, ':s' => $start, ':e' => $end, ':s2' => $start, ':e2' => $end, ':cur' => $curYear]);
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

/** Books finished within a calendar year, oldest first — for the printable
 *  year certificate. */
function get_books_finished_in_year(int $childId, int $year): array {
    $pdo = get_db();
    $start = sprintf('%04d-01-01', $year);
    $end   = sprintf('%04d-12-31', $year);
    $stmt = $pdo->prepare("SELECT title, author, total_pages, finished_date FROM books
        WHERE child_id = :cid AND status = 'loetud' AND finished_date BETWEEN :s AND :e
        ORDER BY finished_date ASC");
    $stmt->execute([':cid' => $childId, ':s' => $start, ':e' => $end]);
    return $stmt->fetchAll();
}

/** Milestones reached within a calendar year (excludes custom ones — those
 *  aren't tied to a specific ladder and would clutter the certificate less
 *  meaningfully than the automatic ones). */
function get_milestones_in_year(int $childId, int $year): array {
    $rows = array_filter(get_milestones($childId), function ($m) use ($year) {
        return (int) substr((string) $m['achieved_on'], 0, 4) === $year && $m['kind'] !== 'custom';
    });
    return array_values($rows);
}

// =========================================================
// Reading challenges (per child)
// =========================================================

function create_challenge(int $childId, string $title, string $type, int $value, string $start, string $end): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO challenges (child_id, title, goal_type, goal_value, start_date, end_date)
        VALUES (:cid, :t, :gt, :gv, :s, :e)");
    $stmt->execute([
        ':cid' => $childId,
        ':t'   => mb_substr($title, 0, 120),
        ':gt'  => $type === 'minutes' ? 'minutes' : 'books',
        ':gv'  => max(1, $value),
        ':s'   => $start,
        ':e'   => $end,
    ]);
}

function delete_challenge(int $id, int $childId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("DELETE FROM challenges WHERE id = :id AND child_id = :cid");
    $stmt->execute([':id' => $id, ':cid' => $childId]);
}

function update_challenge(int $id, int $childId, string $title, string $type, int $value, string $start, string $end): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE challenges SET title = :t, goal_type = :gt, goal_value = :gv, start_date = :s, end_date = :e
        WHERE id = :id AND child_id = :cid");
    $stmt->execute([
        ':t'   => mb_substr($title, 0, 120),
        ':gt'  => $type === 'minutes' ? 'minutes' : 'books',
        ':gv'  => max(1, $value),
        ':s'   => $start,
        ':e'   => $end,
        ':id'  => $id,
        ':cid' => $childId,
    ]);
}

/** A child's challenges: active first, then upcoming, then past (newest end first). */
function get_challenges(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE child_id = :cid
        ORDER BY
          (CURDATE() BETWEEN start_date AND end_date) DESC,
          (start_date > CURDATE()) DESC,
          end_date DESC");
    $stmt->execute([':cid' => $childId]);
    return $stmt->fetchAll();
}

/** Books finished / reading minutes logged inside the challenge window. */
function get_challenge_progress(array $ch): int {
    $pdo = get_db();
    if (($ch['goal_type'] ?? 'books') === 'minutes') {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(raamat), 0) FROM entries
            WHERE child_id = :cid AND entry_date BETWEEN :s AND :e");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM books
            WHERE child_id = :cid AND status = 'loetud' AND finished_date BETWEEN :s AND :e");
    }
    $stmt->execute([':cid' => (int) $ch['child_id'], ':s' => $ch['start_date'], ':e' => $ch['end_date']]);
    return (int) $stmt->fetchColumn();
}

/** Pages read towards a challenge: finished books' pages inside the window, plus
 *  the current page of books still being read (while the window is live). */
function get_challenge_pages(array $ch): int {
    $pdo = get_db();
    $today = date('Y-m-d');
    $live = ($today >= $ch['start_date'] && $today <= $ch['end_date']) ? 1 : 0;
    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN status = 'loetud' AND finished_date BETWEEN :s AND :e THEN total_pages END), 0)
        + COALESCE(SUM(CASE WHEN :live = 1 AND status = 'loeb' THEN current_page END), 0) AS pages
      FROM books WHERE child_id = :cid");
    $stmt->execute([':cid' => (int) $ch['child_id'], ':s' => $ch['start_date'], ':e' => $ch['end_date'], ':live' => $live]);
    return (int) $stmt->fetchColumn();
}

/** One challenge with a progress bar (and a dot row for small book goals). */
function render_challenge_card(array $ch, int $progress, bool $editable): void {
    $goal = max(1, (int) $ch['goal_value']);
    $type = ($ch['goal_type'] ?? 'books') === 'minutes' ? 'minutes' : 'books';
    $pct  = min(100, (int) round($progress / $goal * 100));
    $done = $progress >= $goal;
    $today = date('Y-m-d');

    if ($today < $ch['start_date']) {
        $status = 'Algab ' . date('d.m', strtotime($ch['start_date']));
        $statusCls = 'chal-upcoming';
    } elseif ($today > $ch['end_date']) {
        $status = 'Lõppenud';
        $statusCls = 'chal-ended';
    } else {
        $daysLeft = (int) ceil((strtotime($ch['end_date']) - strtotime($today)) / 86400);
        $status = $daysLeft . ' ' . ($daysLeft === 1 ? 'päev' : 'päeva') . ' jäänud';
        $statusCls = 'chal-active';
    }

    $progText = $type === 'minutes'
        ? format_duration($progress) . ' / ' . format_duration($goal)
        : $progress . ' / ' . $goal . ' raamatut';
    ?>
    <div class="chal<?= $done ? ' chal-done' : '' ?>">
        <div class="chal-head">
            <span class="chal-title"><?= htmlspecialchars($ch['title']) ?></span>
            <span class="chal-status <?= $statusCls ?>"><?= $done ? 'Valmis! 🎉' : htmlspecialchars($status) ?></span>
        </div>
        <div class="chal-dates"><?= date('d.m', strtotime($ch['start_date'])) ?> – <?= date('d.m.Y', strtotime($ch['end_date'])) ?></div>
        <div class="chal-bar"><div class="chal-bar-fill" style="width: <?= $pct ?>%"></div></div>
        <div class="chal-prog"><?= htmlspecialchars($progText) ?> · <?= $pct ?>%</div>
        <?php $chalPages = get_challenge_pages($ch); ?>
        <?php if ($chalPages > 0): ?>
            <div class="chal-pages"><?= emoji_svg('books') ?> <?= $chalPages ?> lk loetud</div>
        <?php endif; ?>
        <?php if ($type === 'books' && $goal <= 24): ?>
            <div class="chal-dots">
                <?php for ($i = 0; $i < $goal; $i++): ?>
                    <span class="chal-dot<?= $i < $progress ? ' filled' : '' ?>"></span>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php if ($editable): ?>
            <div class="chal-actions">
                <a class="link-muted chal-edit-btn" href="edit_challenge.php?id=<?= (int) $ch['id'] ?>&child=<?= (int) $ch['child_id'] ?>"><?= icon('pencil') ?> Muuda</a>
                <form method="post" class="chal-del" onsubmit="return confirm('Kustuta väljakutse?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="challenge_del">
                    <input type="hidden" name="challenge_id" value="<?= (int) $ch['id'] ?>">
                    <button type="submit" class="link-muted chal-del-btn">Kustuta</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// =========================================================
// Weekly recap
// =========================================================

/** [mondayDate, sundayDate] for a week; 0 = current week, 1 = last week, ... */
function get_week_bounds(int $weeksAgo): array {
    $weeksAgo = max(0, $weeksAgo);
    $monday = date('Y-m-d', strtotime("monday this week -{$weeksAgo} week"));
    $sunday = date('Y-m-d', strtotime("{$monday} +6 days"));
    return [$monday, $sunday];
}

function et_weekday(string $date): string {
    $names = [1 => 'esmaspäev', 2 => 'teisipäev', 3 => 'kolmapäev', 4 => 'neljapäev',
              5 => 'reede', 6 => 'laupäev', 7 => 'pühapäev'];
    return $names[(int) date('N', strtotime($date))] ?? '';
}

/** Reading/screen totals, active days, best day, top book and per-day reading
 *  for one child across a date range. */
function get_week_recap(int $childId, string $start, string $end): array {
    $pdo = get_db();
    $p = [':c' => $childId, ':s' => $start, ':e' => $end];

    $t = $pdo->prepare("SELECT COALESCE(SUM(raamat),0) AS raamat, COALESCE(SUM(ekraan),0) AS ekraan,
        COUNT(DISTINCT CASE WHEN raamat > 0 THEN entry_date END) AS reading_days
      FROM entries WHERE child_id = :c AND entry_date BETWEEN :s AND :e");
    $t->execute($p);
    $row = $t->fetch() ?: [];

    $bd = $pdo->prepare("SELECT entry_date, SUM(raamat) AS m FROM entries
      WHERE child_id = :c AND entry_date BETWEEN :s AND :e AND raamat > 0
      GROUP BY entry_date ORDER BY m DESC, entry_date DESC LIMIT 1");
    $bd->execute($p);
    $best = $bd->fetch() ?: null;

    $tb = $pdo->prepare("SELECT bk.title, SUM(en.raamat) AS m
      FROM entries en JOIN books bk ON bk.id = en.book_id
      WHERE en.child_id = :c AND en.entry_date BETWEEN :s AND :e AND en.raamat > 0
      GROUP BY bk.id, bk.title ORDER BY m DESC LIMIT 1");
    $tb->execute($p);
    $topBook = $tb->fetch() ?: null;

    $pd = $pdo->prepare("SELECT entry_date, SUM(raamat) AS m FROM entries
      WHERE child_id = :c AND entry_date BETWEEN :s AND :e GROUP BY entry_date");
    $pd->execute($p);
    $perDay = array_map('intval', $pd->fetchAll(PDO::FETCH_KEY_PAIR));

    return [
        'raamat'       => (int) ($row['raamat'] ?? 0),
        'ekraan'       => (int) ($row['ekraan'] ?? 0),
        'reading_days' => (int) ($row['reading_days'] ?? 0),
        'best_day'     => $best ? ['date' => $best['entry_date'], 'min' => (int) $best['m']] : null,
        'top_book'     => $topBook ? ['title' => $topBook['title'], 'min' => (int) $topBook['m']] : null,
        'per_day'      => $perDay,
    ];
}

/** Small "▲ 20 min rohkem" delta line. $inverse: for screen time, less is better. */
function render_wk_delta(int $now, int $prev, bool $inverse = false): string {
    $d = $now - $prev;
    if ($d === 0) {
        return '<div class="wk-d wk-flat">sama kui eelmisel nädalal</div>';
    }
    $up = $d > 0;
    $good = $inverse ? !$up : $up;
    return '<div class="wk-d ' . ($good ? 'wk-good' : 'wk-bad') . '">'
        . ($up ? '▲' : '▼') . ' ' . format_duration(abs($d)) . ($up ? ' rohkem' : ' vähem') . '</div>';
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
                    $showBar = $totalPages > 0 && $status !== 'loetud';
                    $pct = $showBar ? min(100, max(0, (int) round($curPage / $totalPages * 100))) : 0;
                    $showPages = $totalPages > 0 && $status === 'loetud';
                    $hasMeta = $mins > 0 || !empty($b['finished_date']) || $showPages; ?>
                    <div class="bl-item <?= $cls ?>">
                        <div class="bl-main">
                            <span class="bl-title"><?= htmlspecialchars($b['title']) ?><?php if ($showBar): ?> <span class="bl-pct"><?= $pct ?>%</span><?php endif; ?></span>
                            <?php if (!empty($b['author'])): ?>
                                <span class="bl-author"><?= htmlspecialchars($b['author']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($showBar): ?>
                            <div class="bl-progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="bl-progress-fill" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="bl-progress-label">lk <?= number_format($curPage, 0, ',', "\u{202F}") ?> / <?= number_format($totalPages, 0, ',', "\u{202F}") ?></div>
                        <?php endif; ?>
                        <?php if ($hasMeta): ?>
                            <div class="bl-meta">
                                <?php if ($mins > 0): ?><span class="bl-mins"><?= emoji_svg('books') ?> <?= format_duration($mins) ?></span><?php endif; ?>
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

// =========================================================
// Milestones / achievements (persisted, per child)
// =========================================================

/** Threshold ladders per automatic milestone kind. These are the "predefined
 *  variables" — every value here becomes a milestone the moment the child
 *  reaches it. Edit a list to add / remove / retune tiers. */
function milestone_ladders(): array {
    return [
        'books'  => [1, 5, 10, 20, 30, 50, 75, 100, 150, 200],           // finished books
        'pages'  => [500, 1000, 2500, 5000, 10000, 25000, 50000, 100000], // pages read
        'hours'  => [10, 25, 50, 100, 250, 500, 1000],                    // hours read
        'days'   => [10, 25, 50, 100, 200, 365, 500],                     // days with reading logged
        'streak' => [7, 14, 30, 60, 100, 200],                            // consecutive balanced days
    ];
}

/** Human heading per automatic milestone kind. */
function milestone_kind_names(): array {
    return [
        'books'  => 'Loetud raamatud',
        'pages'  => 'Loetud leheküljed',
        'hours'  => 'Loetud tunnid',
        'days'   => 'Lugemispäevad',
        'streak' => 'Tasakaalu seeria',
    ];
}

/** [emoji, Estonian phrase] for an automatic milestone tier (no stored row). */
function milestone_phrase(string $kind, int $n): array {
    switch ($kind) {
        case 'books':  return ['📚', $n . ($n === 1 ? ' raamat loetud' : ' raamatut loetud')];
        case 'pages':  return ['📖', number_format($n, 0, '', ' ') . ($n === 1 ? ' lehekülg loetud' : ' lehekülge loetud')];
        case 'hours':  return ['⏱️', $n . ($n === 1 ? ' tund loetud' : ' tundi loetud')];
        case 'days':   return ['📅', $n . ($n === 1 ? ' lugemispäev' : ' lugemispäeva')];
        case 'streak': return ['🔥', $n . ($n === 1 ? ' päev järjest tasakaalus' : ' päeva järjest tasakaalus')];
    }
    return ['⭐', 'Saavutus'];
}

/** Current running totals a child is measured against for automatic milestones. */
function milestone_standings(int $childId): array {
    $pdo = get_db();

    $bp = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN status = 'loetud' THEN total_pages END), 0)
      + COALESCE(SUM(CASE WHEN status = 'loeb'   THEN current_page END), 0) AS pages
      FROM books WHERE child_id = :cid");
    $bp->execute([':cid' => $childId]);
    $pages = (int) $bp->fetchColumn();

    $en = $pdo->prepare("SELECT COALESCE(SUM(raamat), 0) AS mins,
        COUNT(DISTINCT CASE WHEN raamat > 0 THEN entry_date END) AS days
      FROM entries WHERE child_id = :cid");
    $en->execute([':cid' => $childId]);
    $enr = $en->fetch() ?: ['mins' => 0, 'days' => 0];

    return [
        'books'  => count_finished_books($childId),
        'pages'  => $pages,
        'hours'  => intdiv((int) $enr['mins'], 60),
        'days'   => (int) $enr['days'],
        'streak' => get_current_streak($childId),
    ];
}

/** Detect any newly-reached milestones for a child and store them (first hit
 *  keeps the date). Cheap enough for dashboard / books / milestones page loads. */
function record_milestones(int $childId): void {
    $pdo = get_db();
    $standings = milestone_standings($childId);

    // Real finish dates for the books ladder, so old achievements keep their date.
    $fd = $pdo->prepare("SELECT finished_date FROM books
        WHERE child_id = :cid AND status = 'loetud' AND finished_date IS NOT NULL
        ORDER BY finished_date ASC, id ASC");
    $fd->execute([':cid' => $childId]);
    $bookDates = $fd->fetchAll(PDO::FETCH_COLUMN);

    $ins = $pdo->prepare("INSERT IGNORE INTO milestones (child_id, kind, threshold, achieved_on, label)
        VALUES (:cid, :k, :t, :d, :l)");

    foreach (milestone_ladders() as $kind => $steps) {
        foreach ($steps as $step) {
            if ($standings[$kind] < $step) break;
            $date = ($kind === 'books' && isset($bookDates[$step - 1]))
                ? $bookDates[$step - 1]
                : date('Y-m-d');
            $ins->execute([':cid' => $childId, ':k' => $kind, ':t' => $step, ':d' => $date, ':l' => null]);
        }
    }

    // One milestone per completed challenge (threshold = challenge id).
    foreach (get_challenges($childId) as $ch) {
        if (get_challenge_progress($ch) >= max(1, (int) $ch['goal_value'])) {
            $ins->execute([
                ':cid' => $childId, ':k' => 'challenge', ':t' => (int) $ch['id'],
                ':d' => date('Y-m-d'), ':l' => mb_substr((string) $ch['title'], 0, 150),
            ]);
        }
    }
}

/** All stored milestones for a child, newest achievement first. */
function get_milestones(int $childId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM milestones WHERE child_id = :cid
        ORDER BY achieved_on DESC, id DESC");
    $stmt->execute([':cid' => $childId]);
    return $stmt->fetchAll();
}

/** [emoji, Estonian phrase] for one milestone row. A stored emoji / label
 *  (parent override, and the only source for custom milestones) wins. */
function milestone_text(array $m): array {
    $n = (int) $m['threshold'];
    if (($m['kind'] ?? '') === 'challenge') {
        $def = ['🏆', 'Väljakutse täidetud: ' . ((string) ($m['label'] ?? 'väljakutse'))];
    } elseif (($m['kind'] ?? '') === 'custom') {
        $def = ['⭐', 'Saavutus'];
    } else {
        $def = milestone_phrase((string) $m['kind'], $n);
    }
    $emoji = trim((string) ($m['emoji'] ?? ''));
    $label = trim((string) ($m['label'] ?? ''));
    $icon = $emoji !== '' ? $emoji : $def[0];
    // 'challenge' already folds its label into $def[1]; every other kind lets a
    // stored label replace the generated phrase outright.
    $text = ($label !== '' && $m['kind'] !== 'challenge') ? $label : $def[1];
    return [$icon, $text];
}

/** True for parent-created milestones (freely editable / deletable). */
function milestone_is_custom(array $m): bool {
    return ($m['kind'] ?? '') === 'custom';
}

/** Parent adds a free-form achievement. threshold is a per-child sequence so the
 *  (child_id, kind, threshold) unique key still holds for custom rows. */
function add_custom_milestone(int $childId, string $label, string $emoji, string $date): void {
    $label = trim($label);
    if ($label === '') return;
    $pdo = get_db();
    $seq = $pdo->prepare("SELECT COALESCE(MAX(threshold), 0) + 1 FROM milestones WHERE child_id = :cid AND kind = 'custom'");
    $seq->execute([':cid' => $childId]);
    $t = (int) $seq->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO milestones (child_id, kind, threshold, label, emoji, achieved_on)
        VALUES (:cid, 'custom', :t, :l, :e, :d)");
    $ins->execute([
        ':cid' => $childId,
        ':t'   => $t,
        ':l'   => mb_substr($label, 0, 150),
        ':e'   => ($e = trim($emoji)) !== '' ? mb_substr($e, 0, 12) : null,
        ':d'   => $date,
    ]);
}

/** Edit a milestone's emoji + date, and (only when $label is not null — i.e. a
 *  custom row) its label. Passing null leaves the stored label untouched, so an
 *  auto row's generated text and a challenge row's title copy are preserved. */
function update_milestone(int $id, int $childId, ?string $label, string $emoji, string $date): void {
    $pdo = get_db();
    $emojiVal = ($e = trim($emoji)) !== '' ? mb_substr($e, 0, 12) : null;
    if ($label === null) {
        $stmt = $pdo->prepare("UPDATE milestones SET emoji = :e, achieved_on = :d
            WHERE id = :id AND child_id = :cid");
        $stmt->execute([':e' => $emojiVal, ':d' => $date, ':id' => $id, ':cid' => $childId]);
        return;
    }
    $stmt = $pdo->prepare("UPDATE milestones SET label = :l, emoji = :e, achieved_on = :d
        WHERE id = :id AND child_id = :cid");
    $stmt->execute([
        ':l'   => ($l = trim($label)) !== '' ? mb_substr($l, 0, 150) : null,
        ':e'   => $emojiVal,
        ':d'   => $date,
        ':id'  => $id,
        ':cid' => $childId,
    ]);
}

/** Delete a milestone. Only custom rows — an auto row would just reappear on the
 *  next scan, so the edit page hides delete for those. */
function delete_milestone(int $id, int $childId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("DELETE FROM milestones WHERE id = :id AND child_id = :cid AND kind = 'custom'");
    $stmt->execute([':id' => $id, ':cid' => $childId]);
}

/** Gold banner for a milestone reached today or yesterday. Persists for the whole
 *  page view (no auto-dismiss) — unlike the transient save toast. */
function render_milestone_banner(int $childId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM milestones
        WHERE child_id = :cid AND achieved_on >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ORDER BY achieved_on DESC, id DESC LIMIT 1");
    $stmt->execute([':cid' => $childId]);
    $m = $stmt->fetch();
    if (!$m) return;
    [$icon, $text] = milestone_text($m);
    echo '<div class="achievement-banner">🎉 ' . htmlspecialchars($icon . ' Saavutus: ' . $text . '!') . '</div>';
}

/** Full achievement list for the Saavutused page. When $editable (parent view)
 *  each row links to edit_milestone.php. */
function render_milestones_list(int $childId, bool $editable = false): void {
    $rows = get_milestones($childId);
    if (empty($rows)) {
        echo '<p class="empty">Saavutusi pole veel. Loe raamatuid ja täida väljakutseid!</p>';
        return;
    }
    echo '<ul class="ms-list">';
    foreach ($rows as $m) {
        [$icon, $text] = milestone_text($m);
        echo '<li class="ms-row">'
           . '<span class="ms-icon">' . htmlspecialchars($icon) . '</span>'
           . '<span class="ms-text">' . htmlspecialchars($text) . '</span>'
           . '<span class="ms-date">' . htmlspecialchars(date('d.m.Y', strtotime($m['achieved_on']))) . '</span>';
        if ($editable) {
            echo '<a class="ms-edit" href="edit_milestone.php?id=' . (int) $m['id'] . '&child=' . $childId . '" aria-label="Muuda" title="Muuda">' . icon('pencil') . '</a>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

/** The full catalogue of automatic milestone tiers (milestone_ladders), grouped
 *  by kind, each row showing the date it was reached or "current / target". */
function render_milestone_catalog(int $childId): void {
    $standings = milestone_standings($childId);

    $earned = [];
    foreach (get_milestones($childId) as $m) {
        if (in_array($m['kind'], ['challenge', 'custom'], true)) continue;
        $earned[$m['kind'] . ':' . (int) $m['threshold']] = $m['achieved_on'];
    }

    $names   = milestone_kind_names();
    $ladders = milestone_ladders();

    foreach ($ladders as $kind => $steps) {
        $cur = (int) ($standings[$kind] ?? 0);
        $doneCount = 0;
        foreach ($steps as $step) {
            if (isset($earned["$kind:$step"])) $doneCount++;
        }
        echo '<details class="mc-group">'
           . '<summary class="mc-title"><span class="mc-name">' . htmlspecialchars($names[$kind] ?? $kind) . '</span>'
           . '<span class="mc-meta">' . $doneCount . '/' . count($steps)
           . ' · <span class="mc-now">' . number_format($cur, 0, '', ' ') . '</span></span></summary>'
           . '<ul class="ms-list">';
        foreach ($steps as $step) {
            [$icon, $text] = milestone_phrase($kind, $step);
            $key = $kind . ':' . $step;
            $done = isset($earned[$key]);
            $right = $done
                ? date('d.m.Y', strtotime($earned[$key]))
                : number_format(min($cur, $step), 0, '', ' ') . ' / ' . number_format($step, 0, '', ' ');
            echo '<li class="ms-row' . ($done ? '' : ' mc-locked') . '">'
               . '<span class="ms-icon">' . htmlspecialchars($done ? $icon : '🔒') . '</span>'
               . '<span class="ms-text">' . htmlspecialchars($text) . '</span>'
               . '<span class="ms-date">' . htmlspecialchars($right) . '</span>'
               . '</li>';
        }
        echo '</ul></details>';
    }
}

// =========================================================
// Stats — trend, streak, heatmap, top activities (per child)
// =========================================================

/** Minutes per day for the last $days days (including days with nothing logged). */
function get_daily_totals_range(int $childId, int $days = 30): array {
    $pdo = get_db();
    $start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $stmt = $pdo->prepare("SELECT entry_date,
        SUM(raamat) as raamat,
        SUM(ekraan) as ekraan,
        SUM(CASE WHEN kind = 'luuletus' THEN raamat ELSE 0 END) as poem
      FROM entries WHERE child_id = :cid AND entry_date >= :start GROUP BY entry_date");
    $stmt->execute([':cid' => $childId, ':start' => $start]);
    $byDate = [];
    foreach ($stmt as $row) {
        $byDate[$row['entry_date']] = ['raamat' => (int) $row['raamat'], 'ekraan' => (int) $row['ekraan'], 'poem' => (int) $row['poem']];
    }
    $result = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $result[] = [
            'date' => $d,
            'raamat' => $byDate[$d]['raamat'] ?? 0,
            'ekraan' => $byDate[$d]['ekraan'] ?? 0,
            'poem' => $byDate[$d]['poem'] ?? 0,
        ];
    }
    return $result;
}

function get_current_streak(int $childId): int {
    $pdo = get_db();
    $ratio = child_reading_ratio($childId);
    $stmt = $pdo->prepare("SELECT entry_date,
        SUM(raamat) as raamat,
        SUM(ekraan) as ekraan,
        SUM(CASE WHEN kind = 'luuletus' THEN raamat ELSE 0 END) as poem
      FROM entries WHERE child_id = :cid GROUP BY entry_date ORDER BY entry_date DESC");
    $stmt->execute([':cid' => $childId]);
    $rows = $stmt->fetchAll();
    $streak = 0;
    foreach ($rows as $row) {
        $raamat = (int) $row['raamat'];
        $ekraan = (int) $row['ekraan'];
        $bonus  = (int) round($row['poem'] * (POEM_BONUS_MULT - 1));
        if (round($ekraan * $ratio) - $raamat - $bonus <= 0) {
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

// =========================================================
// Deleted entries (trash) — a deleted entry moves here instead of vanishing,
// so a parent can undo a misclick from the Pere page.
// =========================================================

/** Move one entry to the trash. Verifies the entry belongs to $childId first. */
function soft_delete_entry(int $entryId, int $childId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM entries WHERE id = :id AND child_id = :cid");
    $stmt->execute([':id' => $entryId, ':cid' => $childId]);
    $e = $stmt->fetch();
    if (!$e) return false;

    $ins = $pdo->prepare("INSERT INTO deleted_entries
        (child_id, entry_date, raamat, book_id, raamat_comment, kind, ekraan, ekraan_comment, original_created_at)
        VALUES (:cid, :d, :r, :bid, :rc, :k, :e, :ec, :created)");
    $ins->execute([
        ':cid' => $childId, ':d' => $e['entry_date'], ':r' => $e['raamat'], ':bid' => $e['book_id'],
        ':rc' => $e['raamat_comment'], ':k' => $e['kind'], ':e' => $e['ekraan'], ':ec' => $e['ekraan_comment'],
        ':created' => $e['created_at'],
    ]);
    $pdo->prepare("DELETE FROM entries WHERE id = :id")->execute([':id' => $entryId]);
    return true;
}

/** Every deleted entry across a family's children, newest deletion first. */
function get_deleted_entries_for_family(int $familyId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT d.*, c.name AS child_name, b.title AS book_title
        FROM deleted_entries d
        JOIN children c ON c.id = d.child_id
        LEFT JOIN books b ON b.id = d.book_id
        WHERE c.family_id = :fid
        ORDER BY d.deleted_at DESC, d.id DESC");
    $stmt->execute([':fid' => $familyId]);
    return $stmt->fetchAll();
}

/** Move a deleted entry back into entries. Verifies it belongs to the family. */
function restore_deleted_entry(int $id, int $familyId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT d.* FROM deleted_entries d
        JOIN children c ON c.id = d.child_id
        WHERE d.id = :id AND c.family_id = :fid");
    $stmt->execute([':id' => $id, ':fid' => $familyId]);
    $e = $stmt->fetch();
    if (!$e) return false;

    $ins = $pdo->prepare("INSERT INTO entries
        (child_id, entry_date, raamat, book_id, raamat_comment, kind, ekraan, ekraan_comment, created_at)
        VALUES (:cid, :d, :r, :bid, :rc, :k, :e, :ec, :created)");
    $ins->execute([
        ':cid' => $e['child_id'], ':d' => $e['entry_date'], ':r' => $e['raamat'], ':bid' => $e['book_id'],
        ':rc' => $e['raamat_comment'], ':k' => $e['kind'], ':e' => $e['ekraan'], ':ec' => $e['ekraan_comment'],
        ':created' => $e['original_created_at'] ?: date('Y-m-d H:i:s'),
    ]);
    $pdo->prepare("DELETE FROM deleted_entries WHERE id = :id")->execute([':id' => $id]);
    return true;
}

/** Permanently remove one trashed entry (no way back after this). */
function purge_deleted_entry(int $id, int $familyId): void {
    $pdo = get_db();
    $pdo->prepare("DELETE d FROM deleted_entries d
        JOIN children c ON c.id = d.child_id
        WHERE d.id = :id AND c.family_id = :fid")
        ->execute([':id' => $id, ':fid' => $familyId]);
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
function render_heatmap(array $dailyTotals, float $ratio = READING_RATIO): void {
    ?>
    <div class="heatmap">
        <?php foreach ($dailyTotals as $d):
            $hasData = $d['raamat'] > 0 || $d['ekraan'] > 0;
            $bonus = (int) round(($d['poem'] ?? 0) * (POEM_BONUS_MULT - 1));
            $owed = round($d['ekraan'] * $ratio) - $d['raamat'] - $bonus;
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
            $isPoem = ($e['kind'] ?? '') === 'luuletus';
            $bookTitle = $e['book_title'] ?? null;
            $note = $e['raamat_comment'] ?? null;
            // Legacy entries (from before books were linked) stored the book
            // name itself in raamat_comment — show it as the label if there's
            // no linked book.
            $label = $bookTitle ?: $note ?: ($isPoem ? 'Luuletus' : null);
            $subNote = ($bookTitle && $note) ? $note : null;
            $activities[] = ['date' => $e['entry_date'], 'type' => $isPoem ? 'luuletus' : 'raamat', 'minutes' => (int) $e['raamat'], 'label' => $label, 'sub' => $subNote, 'id' => $e['id']];
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
                                <span class="tag tag-reading"><?= emoji_svg('books') ?> <?= $a['minutes'] ?> min</span>
                            <?php elseif ($a['type'] === 'luuletus'): ?>
                                <span class="tag tag-reading"><?= emoji_svg('books') ?> <?= $a['minutes'] ?> min · 2×</span>
                            <?php else: ?>
                                <span class="tag tag-screen"><?= emoji_svg('screen') ?> <?= $a['minutes'] ?> min</span>
                            <?php endif; ?>
                            <div class="entry-label">
                                <?= $a['label'] ? htmlspecialchars($a['label']) : '–' ?><?php if ($a['type'] === 'luuletus'): ?> <span class="poem-star" title="Luuletus pähe õpitud — 2× boonus">★</span><?php endif; ?>
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

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
function set_child_goal(int $childId, ?int $daily, ?int $weekly, ?int $rewardCap): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE children SET daily_goal_min = :d, weekly_goal_min = :w, screen_reward_cap_min = :r WHERE id = :id");
    $stmt->execute([':d' => $daily, ':w' => $weekly, ':r' => $rewardCap, ':id' => $childId]);
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
    $owed = round($ekraan * READING_RATIO) - $raamat - $bonus; // positive = lugemist võlgu (reading owed)
    return ['raamat' => $raamat, 'ekraan' => $ekraan, 'owed' => $owed, 'poem' => (int) $row['poem']];
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
        <?php if ($type === 'books' && $goal <= 24): ?>
            <div class="chal-dots">
                <?php for ($i = 0; $i < $goal; $i++): ?>
                    <span class="chal-dot<?= $i < $progress ? ' filled' : '' ?>"></span>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php if ($editable): ?>
            <form method="post" class="chal-del" onsubmit="return confirm('Kustuta väljakutse?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="challenge_del">
                <input type="hidden" name="challenge_id" value="<?= (int) $ch['id'] ?>">
                <button type="submit" class="link-muted chal-del-btn">Kustuta</button>
            </form>
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
        if (round($ekraan * READING_RATIO) - $raamat - $bonus <= 0) {
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
            $bonus = (int) round(($d['poem'] ?? 0) * (POEM_BONUS_MULT - 1));
            $owed = round($d['ekraan'] * READING_RATIO) - $d['raamat'] - $bonus;
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

<?php
require_once __DIR__ . '/functions.php';

$token = $_GET['token'] ?? '';
$child = $token !== '' ? get_child_by_token($token) : null;

if (!$child) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="et"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ei leitud — Ajaraamat</title><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>"></head>
    <body><div class="wrap narrow"><div class="card" style="text-align:center;margin-top:60px;">
    <h2>Seda linki ei leitud</h2>
    <p style="margin-top:8px;color:var(--text-muted);">Palu vanemal link uuesti jagada.</p>
    </div></div></body></html>
    <?php
    exit;
}

$childId = (int) $child['id'];

// Save a finished session as a normal entry.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $minutes = (int) ($_POST['minutes'] ?? 0);

    $book = get_book_for_child($bookId, $childId);
    $minutes = max(1, min(600, $minutes));

    if ($book) {
        $pdo = get_db();
        $stmt = $pdo->prepare("INSERT INTO entries (child_id, entry_date, raamat, book_id, raamat_comment) VALUES (:cid, CURDATE(), :min, :bid, :note)");
        $stmt->execute([
            ':cid' => $childId,
            ':min' => $minutes,
            ':bid' => $bookId,
            ':note' => 'Lisatud taimeriga',
        ]);
        mark_book_started($bookId);
    }
    header('Location: child.php?token=' . urlencode($token) . '&saved=1');
    exit;
}

$books = get_books($childId);
$books = array_filter($books, fn($b) => $b['status'] !== 'loetud') ?: $books; // prefer books still being read, but show everything if none are
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Lugemistaimer — Ajaraamat</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="manifest" href="manifest.php?token=<?= urlencode($token) ?>">
<meta name="theme-color" content="#8B5CF6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Ajaraamat">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="wrap narrow">
    <header class="topbar">
        <a href="child.php?token=<?= htmlspecialchars($token) ?>" class="link-muted">← Tagasi</a>
    </header>

    <?php if (empty($books)): ?>
        <div class="card" style="text-align:center;">
            <h2>Raamatuid pole veel</h2>
            <p style="margin-top:8px;color:var(--text-muted);">Palu vanemal esmalt "Raamatud" alt üks raamat lisada.</p>
        </div>
    <?php else: ?>

    <!-- Step 1: pick a book and mode -->
    <div class="card" id="setup-view">
        <h2>⏱ Alusta lugemist</h2>

        <div class="field-stack">
            <label for="book_id">Raamat</label>
            <select id="book_id">
                <?php foreach ($books as $b): ?>
                    <option value="<?= $b['id'] ?>" data-title="<?= htmlspecialchars($b['title'], ENT_QUOTES) ?>"><?= htmlspecialchars($b['title']) ?><?= book_status_suffix($b['status']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Taimeri tüüp</label>
            <div class="toggle-group">
                <label class="toggle-btn mode-radio">
                    <input type="radio" name="mode" value="up" checked style="display:none">
                    ⏱ Loen üles
                </label>
                <label class="toggle-btn mode-radio">
                    <input type="radio" name="mode" value="down" style="display:none">
                    ⏳ Loen maha
                </label>
            </div>

            <div id="target-picker" style="display:none;">
                <label for="target_minutes">Mitu minutit?</label>
                <input type="number" id="target_minutes" min="1" max="180" value="30" inputmode="numeric">
                <div class="quick-add-row">
                <button type="button" class="quick-add-btn" onclick="setTarget(15)">15 min</button>
                <button type="button" class="quick-add-btn" onclick="setTarget(30)">30 min</button>
                <button type="button" class="quick-add-btn" onclick="setTarget(45)">45 min</button>
                <button type="button" class="quick-add-btn" onclick="setTarget(60)">60 min</button>
            </div>
        </div>
        </div>

        <button type="button" class="btn btn-add full-width" style="margin-top:16px;" onclick="startTimer()">Alusta taimerit</button>
    </div>

    <!-- Step 2: running timer -->
    <div class="card timer-card" id="timer-view" style="display:none;text-align:center;">
        <p class="timer-book" id="timer-book-label"></p>
        <div class="timer-display" id="timer-display">00:00</div>
        <p class="timer-status" id="timer-status"></p>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="button" class="btn btn-add" style="flex:1;" id="pause-btn" onclick="togglePause()">⏸ Paus</button>
            <button type="button" class="btn btn-ghost-solid" style="flex:1;" onclick="finishTimer()">✔ Lõpeta</button>
        </div>
        <button type="button" class="link-muted" style="margin-top:16px;background:none;border:none;cursor:pointer;font-size:14px;" onclick="cancelTimer()">Tühista, ära salvesta</button>
    </div>

    <!-- Step 3: finished, confirm save -->
    <div class="card" id="finish-view" style="display:none;text-align:center;">
        <h2>Tubli lugemine! 🎉</h2>
        <p class="finish-minutes" id="finish-minutes-label"></p>
        <form method="post" id="save-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="book_id" id="save-book-id">
            <input type="hidden" name="minutes" id="save-minutes">
            <button type="submit" class="btn btn-add full-width" style="margin-top:12px;">Salvesta</button>
        </form>
        <button type="button" class="link-muted" style="margin-top:12px;background:none;border:none;cursor:pointer;font-size:14px;" onclick="discardFinish()">Ei, ära salvesta</button>
    </div>

    <?php endif; ?>
</div>

<script>
var STORAGE_KEY = 'loevsvaata_timer_<?= htmlspecialchars($token) ?>';
var state = null;
var tickHandle = null;

function loadState() {
    try {
        var raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
}
function saveState() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) {}
}
function clearState() {
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
}

document.querySelectorAll('.mode-radio').forEach(function(label) {
    var input = label.querySelector('input');
    function sync() {
        document.querySelectorAll('.mode-radio').forEach(function(l) { l.classList.remove('active'); });
        if (input.checked) label.classList.add('active');
        document.getElementById('target-picker').style.display = (input.checked && input.value === 'down') ? 'block' : 'none';
    }
    input.addEventListener('change', sync);
    sync();
});

function setTarget(min) {
    document.getElementById('target_minutes').value = min;
}

function startTimer() {
    var bookSelect = document.getElementById('book_id');
    var mode = document.querySelector('input[name="mode"]:checked').value;
    var targetMinutes = parseInt(document.getElementById('target_minutes').value, 10) || 30;

    state = {
        bookId: bookSelect.value,
        bookTitle: bookSelect.options[bookSelect.selectedIndex].dataset.title,
        mode: mode,
        targetSeconds: targetMinutes * 60,
        accumulatedSeconds: 0,
        runningSince: Date.now(),
        chimed: false
    };
    saveState();
    showTimerView();
}

function elapsedSeconds() {
    var extra = state.runningSince ? Math.floor((Date.now() - state.runningSince) / 1000) : 0;
    return state.accumulatedSeconds + extra;
}

function formatTime(totalSeconds) {
    var sign = totalSeconds < 0 ? '-' : '';
    totalSeconds = Math.abs(totalSeconds);
    var m = Math.floor(totalSeconds / 60);
    var s = totalSeconds % 60;
    return sign + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

function beep() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        [0, 400, 800].forEach(function(delay) {
            setTimeout(function() {
                var o = ctx.createOscillator();
                var g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.value = 880;
                g.gain.setValueAtTime(0.25, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                o.start();
                o.stop(ctx.currentTime + 0.35);
            }, delay);
        });
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate([200, 100, 200, 100, 200]);
}

function tick() {
    var elapsed = elapsedSeconds();
    var display = document.getElementById('timer-display');
    var statusEl = document.getElementById('timer-status');

    if (state.mode === 'up') {
        display.textContent = formatTime(elapsed);
        statusEl.textContent = '';
    } else {
        var remaining = state.targetSeconds - elapsed;
        display.textContent = formatTime(remaining);
        if (remaining <= 0) {
            display.classList.add('timer-overtime');
            statusEl.textContent = remaining === 0 ? 'Aeg on täis! 🎉' : 'Aeg on täis — jätkad lisaajaga';
            if (!state.chimed) {
                state.chimed = true;
                saveState();
                beep();
            }
        } else {
            display.classList.remove('timer-overtime');
            statusEl.textContent = '';
        }
    }
}

function startTicking() {
    tick();
    tickHandle = setInterval(tick, 1000);
}
function stopTicking() {
    if (tickHandle) clearInterval(tickHandle);
    tickHandle = null;
}

function togglePause() {
    var btn = document.getElementById('pause-btn');
    if (state.runningSince) {
        state.accumulatedSeconds = elapsedSeconds();
        state.runningSince = null;
        stopTicking();
        btn.textContent = '▶ Jätka';
        document.getElementById('timer-status').textContent = 'Peatatud';
    } else {
        state.runningSince = Date.now();
        startTicking();
        btn.textContent = '⏸ Paus';
    }
    saveState();
}

function finishTimer() {
    if (state.runningSince) {
        state.accumulatedSeconds = elapsedSeconds();
        state.runningSince = null;
    }
    stopTicking();
    var minutes = Math.max(1, Math.round(state.accumulatedSeconds / 60));

    document.getElementById('timer-view').style.display = 'none';
    document.getElementById('finish-view').style.display = 'block';
    document.getElementById('finish-minutes-label').textContent = state.bookTitle + ' — ' + minutes + ' min';
    document.getElementById('save-book-id').value = state.bookId;
    document.getElementById('save-minutes').value = minutes;
}

function discardFinish() {
    clearState();
    window.location.href = 'child.php?token=<?= htmlspecialchars($token) ?>';
}

function cancelTimer() {
    if (!confirm('Kustutada see taimer ilma salvestamata?')) return;
    stopTicking();
    clearState();
    window.location.href = 'child.php?token=<?= htmlspecialchars($token) ?>';
}

function showTimerView() {
    document.getElementById('setup-view').style.display = 'none';
    document.getElementById('finish-view').style.display = 'none';
    document.getElementById('timer-view').style.display = 'block';
    document.getElementById('timer-book-label').textContent = state.bookTitle;
    document.getElementById('pause-btn').textContent = state.runningSince ? '⏸ Paus' : '▶ Jätka';
    startTicking();
}

// Restore an in-progress timer if the page was reloaded.
var restored = loadState();
if (restored) {
    state = restored;
    showTimerView();
}
</script>
</body>
</html>

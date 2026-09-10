<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$familyId = current_family_id();
$childId = (int) ($_GET['child'] ?? 0);
if (!child_belongs_to_family($childId, $familyId)) {
    header('Location: books.php');
    exit;
}
$child = get_child($childId);

[$minYear, $maxYear] = get_reading_year_range($childId);
$year = (int) ($_GET['year'] ?? date('Y'));
$year = max($minYear, min($maxYear, $year));

$summary = get_year_summary($childId, $year);
$books = get_books_finished_in_year($childId, $year);
$milestones = get_milestones_in_year($childId, $year);
$nf = fn($n) => number_format((int) $n, 0, ',', "\u{202F}");

// The certificate is a single printed page, always — never spill to a second.
// As the book list grows, switch to two columns and progressively smaller
// type instead of letting it overflow; past a point, cap the list outright.
$bookCount = count($books);
$moreBooks = 0;
if ($bookCount > 60) {
    $moreBooks = $bookCount - 60;
    $books = array_slice($books, 0, 60);
}
$certDensity = $bookCount > 40 ? 'cert-dense' : ($bookCount > 12 ? 'cert-compact' : '');
$canNoWatermark = has_feature($familyId, 'certificate_no_watermark');
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Lugemistunnistus · Ajaraamat</title>
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . "/style.css") ?>">
<style>
  .cert-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
  .cert-page {
    background: #FFFFFF; color: #392F4D; border-radius: 22px;
    padding: 40px 32px; position: relative;
    border: 3px solid #EADCFB;
    box-shadow: 0 20px 46px -24px rgba(139,92,246,0.4);
    display: flex; flex-direction: column;
  }
  .cert-body { flex: 1 1 auto; }
  .cert-mark { text-align: center; }
  .cert-mark img { width: 64px; height: 64px; border-radius: 16px; }
  .cert-kicker {
    text-align: center; font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 13px;
    letter-spacing: 0.12em; text-transform: uppercase; color: #8B5CF6; margin-top: 14px;
  }
  .cert-name {
    text-align: center; font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 30px;
    color: #392F4D; margin-top: 6px;
  }
  .cert-year {
    text-align: center; font-family: 'Baloo 2', sans-serif; font-weight: 700; font-size: 15px;
    color: #7B7096; margin-top: 2px;
  }
  .cert-stats {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
    margin: 26px 0; text-align: center;
  }
  .cert-stat-n { font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 22px; color: #392F4D; }
  .cert-stat-l { font-size: 11px; font-weight: 700; color: #7B7096; text-transform: uppercase; letter-spacing: 0.03em; margin-top: 2px; }
  .cert-section-title {
    font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 13px; color: #7B7096;
    text-transform: uppercase; letter-spacing: 0.05em; margin: 22px 0 8px;
  }
  .cert-books { list-style: none; margin: 0; padding: 0; column-count: 1; column-gap: 28px; }
  .cert-books li { padding: 5px 0; font-size: 14px; border-bottom: 1px solid #F0E6F5; break-inside: avoid; }
  .cert-books li:last-child { border-bottom: none; }
  .cert-books .a { color: #7B7096; font-size: 12px; }
  .cert-books-more { font-size: 12px; color: #7B7096; margin-top: 6px; }
  .cert-milestones { display: flex; flex-wrap: wrap; gap: 8px; }

  /* Density tiers — a long book list gets two columns and smaller type
     instead of pushing the certificate onto a second page. */
  .cert-compact .cert-page { padding: 30px 30px; }
  .cert-compact .cert-mark img { width: 48px; height: 48px; }
  .cert-compact .cert-name { font-size: 24px; margin-top: 4px; }
  .cert-compact .cert-kicker { margin-top: 10px; }
  .cert-compact .cert-stats { margin: 16px 0; }
  .cert-compact .cert-stat-n { font-size: 19px; }
  .cert-compact .cert-section-title { margin: 14px 0 6px; }
  .cert-compact .cert-books { column-count: 2; }
  .cert-compact .cert-books li { font-size: 12.5px; padding: 3px 0; }
  .cert-compact .cert-footer { margin-top: 18px; }

  .cert-dense .cert-page { padding: 24px 28px; }
  .cert-dense .cert-mark img { width: 40px; height: 40px; }
  .cert-dense .cert-kicker { margin-top: 8px; font-size: 11px; }
  .cert-dense .cert-name { font-size: 21px; margin-top: 3px; }
  .cert-dense .cert-year { font-size: 13px; }
  .cert-dense .cert-stats { margin: 12px 0; gap: 6px; }
  .cert-dense .cert-stat-n { font-size: 16px; }
  .cert-dense .cert-stat-l { font-size: 9.5px; }
  .cert-dense .cert-section-title { margin: 10px 0 4px; font-size: 11px; }
  .cert-dense .cert-books { column-count: 2; }
  .cert-dense .cert-books li { font-size: 10.5px; padding: 2px 0; }
  .cert-dense .cert-books .a { font-size: 9px; }
  .cert-dense .cert-milestones { gap: 5px; }
  .cert-dense .cert-ms { font-size: 10px; padding: 4px 9px; }
  .cert-dense .cert-footer { margin-top: 12px; font-size: 10.5px; }
  .cert-ms { display: flex; align-items: center; gap: 6px; background: #FBF5FF; border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; }
  .cert-footer { margin-top: 34px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 12px; color: #7B7096; }
  .cert-sign { border-top: 1.5px solid #D9C6F5; padding-top: 4px; min-width: 180px; text-align: center; }
  .cert-empty { color: #7B7096; font-size: 13px; }
  @page { size: A4; margin: 14mm 16mm; }
  @media print {
    html, body { background: #fff !important; }
    .no-print { display: none !important; }
    .wrap { max-width: none; padding: 0; }
    .cert-page { box-shadow: none; min-height: calc(297mm - 28mm); }
    .cert-stats, .cert-mark, .cert-kicker, .cert-name, .cert-year,
    .cert-section-title, .cert-footer { break-inside: avoid; }
    .cert-books li, .cert-ms { break-inside: avoid; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="wrap">
    <div class="cert-toolbar no-print">
        <a href="books.php?child=<?= $childId ?>" class="link-muted"><?= icon("arrow-left") ?> Tagasi</a>
        <div style="display:flex;gap:10px;align-items:center;">
            <?php if ($year > $minYear): ?><a href="?child=<?= $childId ?>&year=<?= $year - 1 ?>" class="icon-btn" aria-label="Eelmine aasta">‹</a><?php endif; ?>
            <strong><?= $year ?></strong>
            <?php if ($year < $maxYear): ?><a href="?child=<?= $childId ?>&year=<?= $year + 1 ?>" class="icon-btn" aria-label="Järgmine aasta">›</a><?php endif; ?>
            <button type="button" class="btn btn-add" onclick="printCertificate()" style="flex:none;">⬇️ Lae alla PDF</button>
        </div>
    </div>
    <p class="child-link-note no-print" style="text-align:right;margin:-10px 0 14px;">Vali avanevas aknas sihtkohaks „Salvesta PDF-ina" (Save as PDF) ja luba „Taustapildid"/„Background graphics".</p>

    <div class="cert-page <?= $certDensity ?>">
      <div class="cert-body">
        <div class="cert-mark"><img src="logo-mark.png?v=2" alt=""></div>
        <p class="cert-kicker">Lugemistunnistus</p>
        <p class="cert-name"><?= htmlspecialchars($child['name']) ?></p>
        <p class="cert-year"><?= $year ?>. aasta lugemine</p>

        <div class="cert-stats">
            <div><div class="cert-stat-n"><?= $nf($summary['books_read']) ?></div><div class="cert-stat-l">Raamatut</div></div>
            <div><div class="cert-stat-n"><?= $nf($summary['pages_read']) ?></div><div class="cert-stat-l">Lehekülge</div></div>
            <div><div class="cert-stat-n"><?= htmlspecialchars(format_duration($summary['reading_minutes'])) ?></div><div class="cert-stat-l">Lugemisaega</div></div>
            <div><div class="cert-stat-n"><?= $nf($summary['reading_days']) ?></div><div class="cert-stat-l">Lugemispäeva</div></div>
        </div>

        <p class="cert-section-title">Loetud raamatud</p>
        <?php if (empty($books)): ?>
            <p class="cert-empty">Sel aastal pole veel raamatuid lõpetatud.</p>
        <?php else: ?>
            <ul class="cert-books">
                <?php foreach ($books as $b): ?>
                    <li><?= htmlspecialchars($b['title']) ?><?php if ($b['author']): ?> <span class="a">, <?= htmlspecialchars($b['author']) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($moreBooks > 0): ?>
                <p class="cert-books-more">… ja veel <?= $moreBooks ?> raamatut.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($milestones)): ?>
            <p class="cert-section-title">Saavutused</p>
            <div class="cert-milestones">
                <?php foreach ($milestones as $m): [$icon, $text] = milestone_text($m); ?>
                    <span class="cert-ms"><?= htmlspecialchars($icon) ?> <?= htmlspecialchars($text) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>

        <div class="cert-footer">
            <span>Väljastatud <?= htmlspecialchars(date('d.m.Y')) ?> · Ajaraamat<?= $canNoWatermark ? '' : '<br><span style="opacity:.7;">Loodud tasuta Ajaraamatu kontoga. Pere+ eemaldab selle märke</span>' ?></span>
            <span class="cert-sign">Vanema allkiri</span>
        </div>
    </div>
</div>
<script>
// Wait for the Baloo 2 / Nunito webfonts to finish loading before printing —
// otherwise a fast click can snapshot the page in the generic fallback font.
function printCertificate() {
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { window.print(); });
    } else {
        window.print();
    }
}
</script>
</body>
</html>

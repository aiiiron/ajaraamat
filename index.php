<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php'; // emoji_svg() turunduslehe mock-ridade jaoks
configure_session();
// If a parent is already logged in (e.g. reopening a home-screen shortcut
// that points here), skip the marketing page and go straight to the dashboard.
if (!empty($_SESSION['family_id'])) {
    header('Location: paren.php');
    exit;
}

// Pere+ funktsioonide loend tuleb otse admin.php hallatavast tabelist, nii
// et see leht ei jää kunagi hinnastuse muudatustest maha.
$featureFlags = get_feature_flags();
$freeFlags = array_values(array_filter($featureFlags, fn($f) => !$f['is_premium']));
$premiumFlags = array_values(array_filter($featureFlags, fn($f) => (bool) $f['is_premium']));
$adminEmail = defined('ADMIN_EMAIL') ? trim((string) ADMIN_EMAIL) : '';
?>
<!DOCTYPE html>
<html lang="et">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Ajaraamat — tasakaal lugemise ja ekraaniaja vahel</title>
<meta name="description" content="Ajaraamat aitab peredel jälgida lapse lugemis- ja ekraaniaega, seada väljakutseid, koguda saavutusi ja hoida kõik lapsed ühes kohas — nii et üks tegevus ei kao teise varju.">
<link rel="icon" href="favicon.ico?v=2" sizes="any">
<link rel="icon" href="icon-192.png?v=2" type="image/png">
<link rel="apple-touch-icon" href="apple-touch-icon.png?v=2">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#8B5CF6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Ajaraamat">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --bg-grad-start: #FFE8D6;
    --bg-grad-mid: #FFDCEC;
    --bg-grad-end: #EDE0FA;
    --paper-raised: #FFFFFF;
    --ink: #392F4D;
    --ink-soft: #7B7096;
    --reading: #F0578F;
    --reading-tint: #FFE5EF;
    --screen: #5B7FE8;
    --screen-tint: #E8EEFF;
    --gradient-primary: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%);
    --line: #F0E6F5;
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body {
    margin: 0;
    background: linear-gradient(160deg, var(--bg-grad-start) 0%, var(--bg-grad-mid) 45%, var(--bg-grad-end) 100%);
    color: var(--ink);
    font-family: 'Nunito', -apple-system, sans-serif;
    line-height: 1.6;
  }
  h1, h2, h3 { font-family: 'Baloo 2', sans-serif; color: var(--ink); font-weight: 700; margin: 0; }
  p { margin: 0; color: var(--ink-soft); }
  a { color: inherit; }
  .wrap { max-width: 1080px; margin: 0 auto; padding: 0 24px; }

  header.top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28px 24px;
    max-width: 1080px;
    margin: 0 auto;
  }
  .logo { font-family: 'Baloo 2', sans-serif; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
  nav.top-links { display: flex; gap: 20px; font-size: 15px; align-items: center; }
  nav.top-links a { text-decoration: none; color: var(--ink-soft); font-weight: 600; }
  nav.top-links a:hover { color: var(--ink); }
  nav.top-links a.cta-link { color: #fff; background: var(--gradient-primary); padding: 10px 20px; border-radius: 14px; font-weight: 700; box-shadow: 0 10px 22px -10px rgba(139,92,246,0.55); }

  .hero {
    max-width: 1080px;
    margin: 40px auto 80px;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 56px;
    align-items: center;
  }
  .hero h1 { font-size: 44px; line-height: 1.2; }
  .hero h1 .em { color: var(--reading); }
  .hero h1 .in { color: var(--screen); }
  .hero p.lede { font-size: 18px; margin-top: 20px; max-width: 46ch; }
  .hero-ctas { margin-top: 32px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
  .btn { display: inline-block; padding: 15px 26px; border-radius: 16px; font-family: 'Baloo 2', sans-serif; font-size: 15px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; }
  .btn-primary { background: var(--gradient-primary); color: #fff; box-shadow: 0 12px 26px -10px rgba(139,92,246,0.55); }
  .btn-primary:hover { filter: brightness(1.05); }
  .btn-ghost { background: #fff; color: var(--ink); border: none; box-shadow: 0 8px 20px -14px rgba(61,51,88,0.35); }
  .btn-ghost:hover { box-shadow: 0 10px 24px -12px rgba(61,51,88,0.45); }

  .mock-wrap { position: relative; }
  .mock-card { background: var(--paper-raised); border: none; border-radius: 22px; box-shadow: 0 24px 60px -20px rgba(139,92,246,0.35); padding: 20px; transform: rotate(-2.2deg); }
  .mock-card .mock-head { font-family: 'Baloo 2', sans-serif; font-size: 13px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
  .mock-balance { border-radius: 14px; background: #F7F4FC; padding: 14px 16px; margin-bottom: 14px; position: relative; overflow: hidden; }
  .mock-balance::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--reading), var(--screen)); }
  .mock-balance .l { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-soft); font-weight: 700; }
  .mock-balance .v { font-family: 'Baloo 2', sans-serif; font-size: 19px; font-weight: 700; margin-top: 4px; }
  .mock-bar { height: 8px; border-radius: 99px; overflow: hidden; display: flex; margin-top: 10px; background: var(--line); }
  .mock-bar span:first-child { background: var(--reading); width: 58%; }
  .mock-bar span:last-child { background: var(--screen); width: 42%; }
  .mock-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
  .mock-stat { background: #F7F4FC; border-radius: 12px; padding: 10px 12px; }
  .mock-stat .l { font-size: 10px; color: var(--ink-soft); font-weight: 700; }
  .mock-stat .v { font-family: 'Baloo 2', sans-serif; font-size: 15px; font-weight: 700; }
  .mock-rows { display: flex; flex-direction: column; gap: 6px; }
  .mock-row { display: flex; align-items: center; gap: 8px; font-size: 12px; background: #F7F4FC; border-radius: 10px; padding: 8px 10px; }
  .mock-pill { font-family: 'Baloo 2', sans-serif; font-size: 11px; font-weight: 700; padding: 3px 9px 3px 7px; border-radius: 99px; white-space: nowrap; }
  .mock-pill.em { background: var(--reading-tint); color: #C93E68; }
  .mock-pill.in { background: var(--screen-tint); color: #4A5FB5; }
  .mock-pill .emo { width: 1.25em; height: 1.25em; vertical-align: -0.25em; display: inline-block; }
  .streak-chip { position: absolute; top: -14px; right: 18px; background: linear-gradient(90deg, #FFC98A, #FFA8CB); color: #93450A; font-family: 'Baloo 2', sans-serif; font-size: 13px; font-weight: 700; padding: 8px 14px; border-radius: 99px; box-shadow: 0 8px 20px -8px rgba(147,69,10,0.3); transform: rotate(3deg); }

  section { padding: 60px 0; }
  section + section { padding-top: 0; }
  .section-head { max-width: 56ch; margin-bottom: 40px; }
  .section-kicker { display: inline-block; font-family: 'Baloo 2', sans-serif; font-size: 12px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: var(--reading); margin-bottom: 8px; }
  .section-head h2 { font-size: 28px; line-height: 1.3; }
  .section-head p { margin-top: 12px; font-size: 16px; }

  .features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .feature { background: var(--paper-raised); border: none; border-radius: 20px; padding: 26px; box-shadow: 0 14px 34px -20px rgba(61,51,88,0.3); }
  .feature .icon { width: 44px; height: 44px; border-radius: 50%; background: var(--reading-tint); display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 14px; }
  .feature:nth-child(2n) .icon { background: var(--screen-tint); }
  .feature h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
  .feature p { font-size: 14.5px; }

  .screens { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .screen-card { background: var(--paper-raised); border: none; border-radius: 20px; padding: 18px; box-shadow: 0 16px 40px -24px rgba(61,51,88,0.35); }
  .screen-card h4 { font-family: 'Baloo 2', sans-serif; font-size: 14px; font-weight: 700; margin-bottom: 12px; color: var(--ink-soft); }
  .screen-inner { background: #F7F4FC; border-radius: 14px; padding: 16px; }

  /* Tasuta vs. Pere+ */
  .plans { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: stretch; }
  .plan-card { background: var(--paper-raised); border-radius: 22px; padding: 30px 28px; box-shadow: 0 16px 40px -24px rgba(61,51,88,0.35); display: flex; flex-direction: column; }
  .plan-card.highlight { background: linear-gradient(160deg, #FBF6FF 0%, #FFF3F9 100%); border: 1.5px solid #EADCFB; box-shadow: 0 20px 46px -20px rgba(139,92,246,0.4); }
  .plan-name { font-family: 'Baloo 2', sans-serif; font-size: 13px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: var(--ink-soft); }
  .plan-card.highlight .plan-name { display: inline-flex; align-items: center; gap: 6px; background: var(--gradient-primary); color: #fff; padding: 4px 12px; border-radius: 999px; }
  .plan-price { font-family: 'Baloo 2', sans-serif; font-size: 22px; font-weight: 800; margin-top: 14px; }
  .plan-sub { font-size: 13.5px; margin-top: 4px; }
  .plan-list { list-style: none; margin: 22px 0 0; padding: 0; display: flex; flex-direction: column; gap: 11px; flex: 1; }
  .plan-list li { display: flex; align-items: flex-start; gap: 9px; font-size: 14.5px; color: var(--ink); }
  .plan-list li::before { content: '✓'; flex: none; width: 20px; height: 20px; border-radius: 50%; background: var(--screen-tint); color: #4A5FB5; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin-top: 1px; }
  .plan-card.highlight .plan-list li::before { content: '★'; background: var(--gradient-primary); color: #fff; }
  .plan-card .btn { margin-top: 26px; text-align: center; }

  .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
  .step .num { font-family: 'Baloo 2', sans-serif; font-size: 34px; color: var(--screen); font-weight: 800; }
  .step h3 { font-size: 17px; margin: 10px 0 8px; }
  .step p { font-size: 15px; }

  .cta-band { background: var(--ink); color: #fff; border-radius: 24px; padding: 56px 48px; text-align: center; }
  .cta-band h2 { color: #fff; font-size: 30px; margin-bottom: 14px; }
  .cta-band p { color: #C9C4DE; font-size: 16px; max-width: 46ch; margin: 0 auto 28px; }
  .cta-band .btn-primary { background: var(--gradient-primary); }
  .form-note { font-size: 13px; color: #9C96B8; margin-top: 16px; }

  footer { padding: 40px 0 60px; text-align: center; }
  footer p { font-size: 14px; color: var(--ink-soft); }
  footer a { color: var(--ink-soft); text-decoration: underline; }

  @media (max-width: 860px) {
    .hero { grid-template-columns: 1fr; }
    .hero h1 { font-size: 32px; }
    .features { grid-template-columns: 1fr; }
    .screens { grid-template-columns: 1fr; }
    .plans { grid-template-columns: 1fr; }
    .steps { grid-template-columns: 1fr; }
    nav.top-links a:not(.cta-link) { display: none; }
    .cta-band { padding: 40px 24px; }
  }

  :focus-visible { outline: 2px solid var(--screen); outline-offset: 2px; }
</style>
</head>
<body>

<header class="top">
  <div class="logo"><img src="logo-mark.png?v=2" alt="" width="46" height="46" style="border-radius:10px;"> Ajaraamat</div>
  <nav class="top-links">
    <a href="#omadused">Omadused</a>
    <a href="#pereplus">Pere+</a>
    <a href="#vaade">Vaata lähemalt</a>
    <a href="login.php">Logi sisse</a>
    <a href="register.php" class="cta-link">Registreeru</a>
  </nav>
</header>

<div class="hero">
  <div>
    <h1>Loe raamatut.<br>Vaata ekraani.<br><span class="em">Pea</span> <span class="in">tasakaalu</span>.</h1>
    <p class="lede">Ajaraamat on lihtne perele mõeldud rakendus, mis jälgib lapse lugemis- ja ekraaniaega — koos väljakutsete, saavutuste ja aastase lugemistunnistusega —, nii et üks ei kao teise varju, ilma pideva vaidluseta selle üle, kes on mida ja kui palju teinud.</p>
    <div class="hero-ctas">
      <a href="register.php" class="btn btn-primary">Registreeri oma pere</a>
      <a href="demo_parent.php" class="btn btn-ghost">👀 Proovi demot</a>
      <a href="#vaade" class="btn btn-ghost">Vaata, kuidas see töötab</a>
    </div>
    <p style="margin-top:14px;"><a href="demo_child.php">👦 Või proovi lapse vaadet →</a></p>
  </div>
  <div class="mock-wrap">
    <div class="streak-chip">🔥 6 päeva järjest</div>
    <div class="mock-card">
      <div class="mock-head"><img src="logo-mark.png?v=2" alt="" width="24" height="24" style="border-radius:6px;"> Ajaraamat</div>
      <div class="mock-balance">
        <div class="l">Lugemise tasakaal</div>
        <div class="v">42 min lugemise boonust</div>
        <div class="mock-bar"><span></span><span></span></div>
      </div>
      <div class="mock-grid">
        <div class="mock-stat"><div class="l">Raamat kokku</div><div class="v">340 min</div></div>
        <div class="mock-stat"><div class="l">Ekraan kokku</div><div class="v">298 min</div></div>
      </div>
      <div class="mock-rows">
        <div class="mock-row"><span class="mock-pill em"><?= emoji_svg('books') ?> 30 min</span> Karlsson katuselt</div>
        <div class="mock-row"><span class="mock-pill in"><?= emoji_svg('screen') ?> 20 min</span> Youtube</div>
        <div class="mock-row"><span class="mock-pill em"><?= emoji_svg('books') ?> 25 min</span> Pipi Pikksukk</div>
      </div>
    </div>
  </div>
</div>

<section id="omadused">
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Iga päev</span>
      <h2>Kõik, mida perel igapäevaselt vaja on</h2>
      <p>Ei mingeid keerulisi seadeid ega müügijutte — lihtsalt selge ülevaade sellest, kuidas laps oma aega veedab.</p>
    </div>
    <div class="features">
      <div class="feature">
        <span class="icon">⚖️</span>
        <h3>Tasakaalu ülevaade ja preemiad</h3>
        <p>Näe ühe pilguga, kas lugemine ja ekraaniaeg on tasakaalus, kui palju on kummastki "võlgu" või boonuseks kogunenud — ja mitu minutit ekraaniaega on selle eest teenitud.</p>
      </div>
      <div class="feature">
        <span class="icon">📚</span>
        <h3>Raamatute nimekiri</h3>
        <p>Pea arvet, mis raamatuid on täpselt loetud — pealkirjad, autorid, leheküljed ja lugemise seis (pooleli, loetud, riiulis).</p>
      </div>
      <div class="feature">
        <span class="icon">🔗</span>
        <h3>Lapsele oma link ja taimer</h3>
        <p>Laps näeb oma tasakaalu ilma sisselogimiseta — lihtsalt tema enda link — ja saab lugemise käivitada ühe nupuvajutusega taimeriga.</p>
      </div>
      <div class="feature">
        <span class="icon">🌙</span>
        <h3>Automaatne tume režiim</h3>
        <p>Rakendus järgib seadme teemat ise — mugav vaadata ka õhtusel lugemisajal, ilma eraldi lülitita.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Motivatsioon</span>
      <h2>Väikesed võidud, mis lugemist edasi kannavad</h2>
      <p>Väljakutsed, saavutused ja aastakokkuvõtted — et lugemine tunduks lapsele lõbus, mitte kohustus.</p>
    </div>
    <div class="features">
      <div class="feature">
        <span class="icon">🏆</span>
        <h3>Lugemisväljakutsed</h3>
        <p>Sea eesmärk raamatute või minutite kaupa, vali periood ja jälgi koos lapsega, kuidas edenemine liigub.</p>
      </div>
      <div class="feature">
        <span class="icon">⭐</span>
        <h3>Saavutused ja lugemisstreak</h3>
        <p>Automaatsed verstapostid tähistavad iga vahva hetke — pluss järjestikuste tasakaalus päevade streak, mis hoiab harjumuse elus.</p>
      </div>
      <div class="feature">
        <span class="icon">📅</span>
        <h3>Iganädalane kokkuvõte</h3>
        <p>Üks vaade, mis näitab nädala tipphetki ja seda, kas nädala eesmärk sai täidetud.</p>
      </div>
      <div class="feature">
        <span class="icon">🎓</span>
        <h3>Aastane lugemistunnistus</h3>
        <p>Aasta lõpus saab iga lapse jaoks luua ilusa, väljaprinditava (PDF) lugemistunnistuse koos loetud raamatute ja saavutustega.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Kogu pere</span>
      <h2>Kasvab koos perega</h2>
      <p>Ükskõik, kas peres on üks laps või mitu, ja kas lugemist jälgib üks vanem või kaks.</p>
    </div>
    <div class="features">
      <div class="feature">
        <span class="icon">👨‍👩‍👧‍👦</span>
        <h3>Mitu last, üks ülevaade</h3>
        <p>Lisa nii palju lapsi kui vaja — igaühel oma tasakaal ja raamatunimekiri — ning vaata kõiki peres korraga ühelt lehelt.</p>
      </div>
      <div class="feature">
        <span class="icon">✋</span>
        <h3>Laps lisab, vanem kinnitab</h3>
        <p>Vanema loal saab laps ise oma lugemis- või ekraanikanded lisada — need lähevad vanemale ülevaatamiseks ja kinnitamiseks.</p>
      </div>
      <div class="feature">
        <span class="icon">👥</span>
        <h3>Teine vanem samas peres</h3>
        <p>Lisa teisele vanemale oma sisselogimine samale perele, nii et mõlemad näevad sama tasakaalu ja saavad kandeid hallata.</p>
      </div>
      <div class="feature">
        <span class="icon">🗑️</span>
        <h3>Kustutatud kanded taastatavad</h3>
        <p>Eksisti juhtub — kustutatud kanded lähevad prügikasti, kust need saab kuni taastamiseni tagasi tuua.</p>
      </div>
      <div class="feature">
        <span class="icon">⬇️</span>
        <h3>CSV eksport</h3>
        <p>Vaja andmeid mujal analüüsida? Laadi kõik kanded alla CSV-failina.</p>
      </div>
    </div>
  </div>
</section>

<section id="pereplus">
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Hinnastus</span>
      <h2>Tasuta ja Pere+</h2>
      <p>Ajaraamati põhifunktsioonid on ja jäävad tasuta. Pere+ lisab mugavused peredele, kel on rohkem lapsi või kes tahavad rohkem seadistusvõimalusi.</p>
    </div>
    <div class="plans">
      <div class="plan-card">
        <span class="plan-name">Tasuta</span>
        <div class="plan-price">0 €</div>
        <p class="plan-sub">Kõigile uutele peredele vaikimisi.</p>
        <ul class="plan-list">
          <li>Lugemis- ja ekraaniaja jälgimine ning tasakaalu ülevaade</li>
          <li>Raamatute nimekiri ja sisseehitatud lugemistaimer</li>
          <li>Lapse enda link, ilma sisselogimiseta</li>
          <li>Iganädalane kokkuvõte, automaatsed saavutused ja streak</li>
          <li>Üks laps peres, üks aktiivne väljakutse korraga</li>
          <?php foreach ($freeFlags as $flag): ?>
            <li><?= htmlspecialchars($flag['label']) ?></li>
          <?php endforeach; ?>
        </ul>
        <a href="register.php" class="btn btn-ghost">Registreeru tasuta</a>
      </div>
      <div class="plan-card highlight">
        <span class="plan-name">🌟 Pere+</span>
        <div class="plan-price">Küsi pakkumist</div>
        <p class="plan-sub">Praegu liitumine käsitsi — kirjuta meile, ja aktiveerime selle sinu perele.</p>
        <ul class="plan-list">
          <?php foreach ($premiumFlags as $flag): ?>
            <li><?= htmlspecialchars($flag['label']) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($adminEmail !== ''): ?>
          <a href="mailto:<?= htmlspecialchars($adminEmail) ?>?subject=<?= rawurlencode('Pere+ liitumine') ?>" class="btn btn-primary">Küsi Pere+ ligipääsu</a>
        <?php else: ?>
          <a href="register.php" class="btn btn-primary">Registreeru ja küsi hiljem</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section id="vaade">
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Kaks vaadet</span>
      <h2>Kaks vaadet, üks tasakaal</h2>
      <p>Vanem näeb tervikpilti ja saab kandeid hallata. Laps näeb oma enda tasakaalu — lihtsalt ja selgelt.</p>
    </div>
    <div class="screens">
      <div class="screen-card">
        <h4>Vanema töölaud</h4>
        <div class="screen-inner">
          <div class="mock-balance" style="margin-bottom:10px;">
            <div class="l">Lugemise tasakaal</div>
            <div class="v">7 min lugemist võlgu</div>
          </div>
          <div class="mock-rows">
            <div class="mock-row"><span class="mock-pill em"><?= emoji_svg('books') ?> 60 min</span> Meister</div>
            <div class="mock-row"><span class="mock-pill in"><?= emoji_svg('screen') ?> 77 min</span> Youtube</div>
          </div>
        </div>
      </div>
      <div class="screen-card">
        <h4>Lapse vaade (ilma sisselogimiseta)</h4>
        <div class="screen-inner">
          <div class="mock-balance" style="margin-bottom:10px;">
            <div class="l">Sinu tasakaal</div>
            <div class="v">Tasakaalus!</div>
          </div>
          <div class="mock-rows">
            <div class="mock-row"><span class="mock-pill em"><?= emoji_svg('books') ?> 45 min</span> Sipsik</div>
            <div class="mock-row">🏆 Väljakutse: 8/10 raamatut</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="alusta">
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Alustamine</span>
      <h2>Kuidas alustada</h2>
      <p>Konto loomine võtab paar minutit — ei mingit tehnilist seadistust.</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="num">1</div>
        <h3>Registreeri konto</h3>
        <p>Sisesta oma e-post, parool ja lapse nimi.</p>
      </div>
      <div class="step">
        <div class="num">2</div>
        <h3>Oota kinnitust</h3>
        <p>Vaatame üle ja kinnitame sinu konto käsitsi paari päeva jooksul.</p>
      </div>
      <div class="step">
        <div class="num">3</div>
        <h3>Hakka kandeid lisama</h3>
        <p>Logi sisse ja alusta — esimene raamat, esimene ekraaniaeg.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="cta-band">
      <h2>Valmis alustama?</h2>
      <p>Registreeri oma pere ja hakka lugemis- ja ekraaniaega jälgima juba täna.</p>
      <a href="register.php" class="btn btn-primary">Registreeru tasuta</a>
      <p class="form-note">Uued kontod kinnitatakse käsitsi, et hoida rakendus turvaline ja töökindel kõigile peredele.</p>
    </div>
  </div>
</section>

<footer>
  <p>Ajaraamat — tehtud ühe Eesti pere poolt, teiste Eesti perede jaoks. <a href="admin_login.php">Admin</a></p>
</footer>

</body>
</html>

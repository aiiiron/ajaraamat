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
<title>Ajaraamat · tasakaal lugemise ja ekraaniaja vahel</title>
<meta name="description" content="Ajaraamat aitab peredel jälgida lapse lugemis- ja ekraaniaega, seada väljakutseid, koguda saavutusi ja hoida kõik lapsed ühes kohas, nii et üks tegevus ei kao teise varju.">
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
  .mock-card { position: relative; z-index: 1; background: var(--paper-raised); border: none; border-radius: 22px; box-shadow: 0 24px 60px -20px rgba(139,92,246,0.35); padding: 20px; transform: rotate(-2.2deg); }
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
  .streak-chip { position: absolute; z-index: 2; top: -14px; right: 18px; background: linear-gradient(90deg, #FFC98A, #FFA8CB); color: #93450A; font-family: 'Baloo 2', sans-serif; font-size: 13px; font-weight: 700; padding: 8px 14px; border-radius: 99px; box-shadow: 0 8px 20px -8px rgba(147,69,10,0.3); transform: rotate(3deg); }

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

  /* Motivatsioon: badges strung along a dashed path, alternating height */
  .path-features { position: relative; display: flex; gap: 20px; padding-top: 6px; }
  .path-features::before { content: ''; position: absolute; top: 48px; left: 76px; right: 76px; border-top: 3px dashed #F0C7DE; z-index: 0; }
  .path-item { position: relative; z-index: 1; flex: 1; text-align: center; display: flex; flex-direction: column; align-items: center; }
  .path-item:nth-child(2), .path-item:nth-child(4) { transform: translateY(-20px); }
  .path-badge { width: 76px; height: 76px; border-radius: 50%; background: var(--gradient-primary); display: flex; align-items: center; justify-content: center; font-size: 30px; box-shadow: 0 16px 30px -12px rgba(139,92,246,0.5); border: 5px solid #FFF6FA; margin-bottom: 16px; }
  .path-item:nth-child(2n) .path-badge { background: linear-gradient(135deg, #5B7FE8 0%, #8B5CF6 100%); }
  .path-item h3 { font-size: 16px; margin-bottom: 6px; }
  .path-item p { font-size: 13.5px; }

  /* Kogu pere: one panel, icon rows */
  .feature-panel { background: var(--paper-raised); border-radius: 22px; box-shadow: 0 16px 40px -24px rgba(61,51,88,0.35); overflow: hidden; }
  .feature-row { display: flex; align-items: flex-start; gap: 18px; padding: 24px 28px; border-bottom: 1px solid var(--line); }
  .feature-row:last-child { border-bottom: none; }
  .row-icon { flex: none; width: 46px; height: 46px; border-radius: 14px; background: var(--reading-tint); display: flex; align-items: center; justify-content: center; font-size: 21px; }
  .feature-row:nth-child(2n) .row-icon { background: var(--screen-tint); }
  .feature-row h3 { font-size: 16px; margin-bottom: 4px; }
  .feature-row p { font-size: 14.5px; }

  /* Vaata lähemalt: tabbed tour with one big phone preview */
  .tour { display: grid; grid-template-columns: 300px 1fr; gap: 32px; align-items: start; }
  .tour-tabs { display: flex; flex-direction: column; gap: 6px; }
  .tour-tab { display: block; width: 100%; text-align: left; background: transparent; border: none; border-radius: 14px; padding: 13px 16px; cursor: pointer; font-family: 'Nunito', sans-serif; }
  .tour-tab strong { display: block; font-family: 'Baloo 2', sans-serif; font-size: 14.5px; color: var(--ink); }
  .tour-tab span { display: block; font-size: 12.5px; color: var(--ink-soft); margin-top: 2px; }
  .tour-tab.active { background: #fff; box-shadow: 0 10px 26px -16px rgba(61,51,88,0.4); }
  .tour-tab.active strong { color: var(--reading); }
  .tour-tab:hover:not(.active) { background: rgba(255,255,255,0.6); }
  .tour-preview { display: flex; justify-content: center; position: sticky; top: 24px; }
  .tour-frame { position: relative; width: 300px; max-width: 100%; border-radius: 32px; background: #fff; padding: 30px 10px 10px; box-shadow: 0 30px 60px -24px rgba(61,51,88,0.45); border: 1px solid #F0E6F5; }
  .tour-notch { position: absolute; top: 12px; left: 50%; transform: translateX(-50%); width: 70px; height: 16px; background: #392F4D; border-radius: 999px; }
  .tour-frame img { width: 100%; height: 520px; object-fit: cover; object-position: top center; border-radius: 18px; display: block; }
  .tour-frame img[hidden] { display: none; }
  .tour-cta { text-align: center; margin-top: 32px; }

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

  /* Alustamine: numbered nodes joined by a chevron connector */
  .steps { display: flex; align-items: flex-start; gap: 0; }
  .step { flex: 1; text-align: center; padding: 0 16px; position: relative; }
  .step .num { display: flex; align-items: center; justify-content: center; width: 54px; height: 54px; margin: 0 auto 16px; border-radius: 50%; background: var(--gradient-primary); color: #fff; font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 22px; box-shadow: 0 14px 28px -12px rgba(139,92,246,0.55); }
  .step:not(:last-child)::after { content: '›'; position: absolute; top: 4px; right: -10px; font-size: 34px; font-weight: 800; color: #D9C6F5; font-family: 'Baloo 2', sans-serif; }
  .step h3 { font-size: 17px; margin: 0 0 8px; }
  .step p { font-size: 15px; }

  .cta-band { background: var(--ink); color: #fff; border-radius: 24px; padding: 56px 48px; display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 30px; align-items: center; }
  .cta-band h2 { color: #fff; font-size: 30px; margin-bottom: 14px; text-align: left; }
  .cta-band p { color: #C9C4DE; font-size: 16px; max-width: 44ch; margin: 0 0 26px; text-align: left; }
  .cta-band .btn-primary { background: var(--gradient-primary); }
  .form-note { font-size: 13px; color: #9C96B8; margin-top: 26px; }
  .cta-visual { position: relative; height: 170px; }
  .cta-chip { position: absolute; background: #fff; color: var(--ink); font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 13.5px; padding: 10px 16px; border-radius: 14px; box-shadow: 0 16px 32px -14px rgba(0,0,0,0.5); white-space: nowrap; }

  footer { padding: 40px 0 60px; text-align: center; }
  footer p { font-size: 14px; color: var(--ink-soft); }
  footer a { color: var(--ink-soft); text-decoration: underline; }

  @media (max-width: 860px) {
    .hero { grid-template-columns: 1fr; }
    .hero h1 { font-size: 32px; }
    .features { grid-template-columns: 1fr; }
    .path-features { flex-direction: column; gap: 28px; }
    .path-features::before { display: none; }
    .path-item, .path-item:nth-child(2), .path-item:nth-child(4) { transform: none; }
    .feature-row { padding: 18px; gap: 14px; }
    .tour { grid-template-columns: 1fr; }
    .tour-tabs { flex-direction: row; flex-wrap: wrap; gap: 8px; justify-content: center; }
    .tour-tab { width: auto; text-align: center; padding: 9px 14px; }
    .tour-tab span { display: none; }
    .tour-preview { position: static; margin-top: 8px; }
    .plans { grid-template-columns: 1fr; }
    .steps { flex-direction: column; gap: 30px; }
    .step { padding: 0; }
    .step:not(:last-child)::after { content: '⌄'; top: auto; bottom: -22px; right: auto; left: 50%; transform: translateX(-50%); }
    .cta-band { grid-template-columns: 1fr; padding: 40px 24px; text-align: center; }
    .cta-band h2, .cta-band p { text-align: center; margin-left: auto; margin-right: auto; }
    .cta-visual { display: none; }
    nav.top-links a:not(.cta-link) { display: none; }
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
    <p class="lede">Ajaraamat on lihtne perele mõeldud rakendus, mis jälgib lapse lugemis- ja ekraaniaega, seab väljakutseid, kogub saavutusi ja loob aastase lugemistunnistuse, nii et üks tegevus ei kao teise varju, ilma pideva vaidluseta selle üle, kes on mida ja kui palju teinud.</p>
    <div class="hero-ctas">
      <a href="register.php" class="btn btn-primary">Registreeri oma pere</a>
      <a href="#vaade" class="btn btn-ghost">Vaata, kuidas see töötab</a>
    </div>
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
      <p>Ei mingeid keerulisi seadeid ega müügijutte, lihtsalt selge ülevaade sellest, kuidas laps oma aega veedab.</p>
    </div>
    <div class="features">
      <div class="feature">
        <span class="icon">⚖️</span>
        <h3>Tasakaalu ülevaade ja preemiad</h3>
        <p>Näe ühe pilguga, kas lugemine ja ekraaniaeg on tasakaalus, kui palju on kummastki "võlgu" või boonuseks kogunenud, ja mitu minutit ekraaniaega on selle eest teenitud.</p>
      </div>
      <div class="feature">
        <span class="icon">📚</span>
        <h3>Raamatute nimekiri</h3>
        <p>Pea arvet, mis raamatuid on täpselt loetud: pealkirjad, autorid, leheküljed ja lugemise seis (pooleli, loetud, riiulis).</p>
      </div>
      <div class="feature">
        <span class="icon">🔗</span>
        <h3>Lapsele oma link ja taimer</h3>
        <p>Laps näeb oma tasakaalu ilma sisselogimiseta, lihtsalt tema enda lingiga, ja saab lugemise käivitada ühe nupuvajutusega taimeriga.</p>
      </div>
      <div class="feature">
        <span class="icon">🌙</span>
        <h3>Automaatne tume režiim</h3>
        <p>Rakendus järgib seadme teemat ise, mugav vaadata ka õhtusel lugemisajal, ilma eraldi lülitita.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Motivatsioon</span>
      <h2>Väikesed võidud, mis lugemist edasi kannavad</h2>
      <p>Väljakutsed, saavutused ja aastakokkuvõtted, et lugemine tunduks lapsele lõbus, mitte kohustus.</p>
    </div>
    <div class="path-features">
      <div class="path-item">
        <span class="path-badge">🏆</span>
        <h3>Lugemisväljakutsed</h3>
        <p>Sea eesmärk raamatute või minutite kaupa, vali periood ja jälgi koos lapsega, kuidas edenemine liigub.</p>
      </div>
      <div class="path-item">
        <span class="path-badge">⭐</span>
        <h3>Saavutused ja lugemisstreak</h3>
        <p>Automaatsed verstapostid tähistavad iga vahva hetke, pluss järjestikuste tasakaalus päevade streak, mis hoiab harjumuse elus.</p>
      </div>
      <div class="path-item">
        <span class="path-badge">📅</span>
        <h3>Iganädalane kokkuvõte</h3>
        <p>Üks vaade, mis näitab nädala tipphetki ja seda, kas nädala eesmärk sai täidetud.</p>
      </div>
      <div class="path-item">
        <span class="path-badge">🎓</span>
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
    <div class="feature-panel">
      <div class="feature-row">
        <span class="row-icon">👨‍👩‍👧‍👦</span>
        <div><h3>Mitu last, üks ülevaade</h3><p>Lisa nii palju lapsi kui vaja, igaühel oma tasakaal ja raamatunimekiri, ning vaata kõiki peres korraga ühelt lehelt.</p></div>
      </div>
      <div class="feature-row">
        <span class="row-icon">✋</span>
        <div><h3>Laps lisab, vanem kinnitab</h3><p>Vanema loal saab laps ise oma lugemis- või ekraanikanded lisada. Need lähevad vanemale ülevaatamiseks ja kinnitamiseks.</p></div>
      </div>
      <div class="feature-row">
        <span class="row-icon">👥</span>
        <div><h3>Teine vanem samas peres</h3><p>Lisa teisele vanemale oma sisselogimine samale perele, nii et mõlemad näevad sama tasakaalu ja saavad kandeid hallata.</p></div>
      </div>
      <div class="feature-row">
        <span class="row-icon">🗑️</span>
        <div><h3>Kustutatud kanded taastatavad</h3><p>Eksisti juhtub. Kustutatud kanded lähevad prügikasti, kust need saab kuni taastamiseni tagasi tuua.</p></div>
      </div>
      <div class="feature-row">
        <span class="row-icon">⬇️</span>
        <div><h3>CSV eksport</h3><p>Vaja andmeid mujal analüüsida? Laadi kõik kanded alla CSV-failina.</p></div>
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
        <p class="plan-sub">Praegu liitumine käsitsi, kirjuta meile ja aktiveerime selle sinu perele.</p>
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
      <span class="section-kicker">Vaata lähemalt</span>
      <h2>Päris vaated otse rakendusest</h2>
      <p>Ehtsad kuvatõmmised meie avalikust demo perest. Vali funktsioon vasakult ja vaata, milline see välja näeb.</p>
    </div>
    <div class="tour">
      <div class="tour-tabs">
        <button type="button" class="tour-tab active" data-i="0"><strong>Vanema töölaud</strong><span>Kinnitusootel kanded, tasakaal, eesmärk</span></button>
        <button type="button" class="tour-tab" data-i="1"><strong>Pere ülevaade</strong><span>Kõik lapsed ühel lehel</span></button>
        <button type="button" class="tour-tab" data-i="2"><strong>Raamatud ja väljakutsed</strong><span>Aasta kokkuvõte, väljakutse, raamaturiiul</span></button>
        <button type="button" class="tour-tab" data-i="3"><strong>Saavutused</strong><span>Automaatsed ja oma lisatud verstapostid</span></button>
        <button type="button" class="tour-tab" data-i="4"><strong>Iganädalane kokkuvõte</strong><span>Nädala tipphetked ja eesmärgi täitmine</span></button>
        <button type="button" class="tour-tab" data-i="5"><strong>Aastane lugemistunnistus</strong><span>Ilus, allalaaditav PDF</span></button>
        <button type="button" class="tour-tab" data-i="6"><strong>Lapse vaade</strong><span>Ilma sisselogimiseta, oma lingiga</span></button>
        <button type="button" class="tour-tab" data-i="7"><strong>Lapse enda saavutused</strong><span>Motiveeriv, lapsele mõistetav</span></button>
      </div>
      <div class="tour-preview">
        <div class="tour-frame">
          <span class="tour-notch"></span>
          <img src="screens/01_dashboard.webp" width="640" height="1000" alt="Vanema töölaud" data-i="0">
          <img src="screens/02_overview.webp" width="640" height="820" alt="Pere ülevaade" data-i="1" hidden>
          <img src="screens/03_books.webp" width="640" height="1200" alt="Raamatud ja väljakutsed" data-i="2" hidden>
          <img src="screens/04_milestones.webp" width="640" height="1033" alt="Saavutused" data-i="3" hidden>
          <img src="screens/05_week.webp" width="640" height="867" alt="Iganädalane kokkuvõte" data-i="4" hidden>
          <img src="screens/06_certificate.webp" width="640" height="1133" alt="Aastane lugemistunnistus" data-i="5" hidden>
          <img src="screens/07_child.webp" width="640" height="1143" alt="Lapse vaade" data-i="6" hidden>
          <img src="screens/08_child_milestones.webp" width="640" height="762" alt="Lapse enda saavutused" data-i="7" hidden>
        </div>
      </div>
    </div>
    <p class="tour-cta"><a href="demo_parent.php" class="btn btn-ghost">👀 Proovi ise, vanema vaade</a> <a href="demo_child.php" class="btn btn-ghost">👦 Proovi ise, lapse vaade</a></p>
  </div>
</section>

<section id="alusta">
  <div class="wrap">
    <div class="section-head">
      <span class="section-kicker">Alustamine</span>
      <h2>Kuidas alustada</h2>
      <p>Konto loomine võtab paar minutit. Tehnilist seadistust pole vaja.</p>
    </div>
    <div class="steps">
      <div class="step">
        <span class="num">1</span>
        <h3>Registreeri konto</h3>
        <p>Sisesta oma e-post, parool ja lapse nimi.</p>
      </div>
      <div class="step">
        <span class="num">2</span>
        <h3>Oota kinnitust</h3>
        <p>Vaatame üle ja kinnitame sinu konto käsitsi paari päeva jooksul.</p>
      </div>
      <div class="step">
        <span class="num">3</span>
        <h3>Hakka kandeid lisama</h3>
        <p>Logi sisse ja alusta: esimene raamat, esimene ekraaniaeg.</p>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="cta-band">
      <div>
        <h2>Valmis alustama?</h2>
        <p>Registreeri oma pere ja hakka lugemis- ja ekraaniaega jälgima juba täna.</p>
        <a href="register.php" class="btn btn-primary">Registreeru tasuta</a>
        <p class="form-note">Uued kontod kinnitatakse käsitsi, et hoida rakendus turvaline ja töökindel kõigile peredele.</p>
      </div>
      <div class="cta-visual">
        <span class="cta-chip" style="top:4px;left:2px;transform:rotate(-7deg);">🔥 12 päeva järjest</span>
        <span class="cta-chip" style="top:66px;right:-4px;transform:rotate(5deg);">⭐ 18 saavutust</span>
        <span class="cta-chip" style="bottom:2px;left:34px;transform:rotate(-3deg);">📚 240 raamatut</span>
      </div>
    </div>
  </div>
</section>

<footer>
  <p>Ajaraamat on tehtud ühe Eesti pere poolt, teiste Eesti perede jaoks.</p>
</footer>

<script>
(function () {
  var tabs = document.querySelectorAll('.tour-tab');
  var imgs = document.querySelectorAll('.tour-frame img');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var i = tab.getAttribute('data-i');
      tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
      imgs.forEach(function (img) { img.hidden = (img.getAttribute('data-i') !== i); });
    });
  });
})();
</script>
</body>
</html>

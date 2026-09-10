<?php
// ==========================================================
// MUUDA NEID VÄÄRTUSI oma hostimiskeskkonna jaoks
// EDIT THESE VALUES for your hosting environment
// ==========================================================

// Andmebaasi ühenduse andmed (leiad oma hostingu haldusliidesest)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Sinu (saidi omaniku) parool uute perekontode kinnitamiseks.
// See ei ole ühegi pere parool — igal perel on oma e-post ja parool.
define('ADMIN_PASSWORD', 'changeme');

// Valikuline: sinu enda pere konto e-post (sama, millega logid sisse
// login.php kaudu). Kui see on täidetud, pääsed admin paneeli (admin.php)
// otse oma tavalisest sisselogimisest — eraldi ADMIN_PASSWORD parooli pole
// vaja. Jäta tühjaks (''), et kasutada ainult ADMIN_PASSWORD-i.
define('ADMIN_EMAIL', '');

// Vaikereegel: mitu minutit lugemist ("Raamat") on vaja iga ekraaniminuti kohta.
// 1.0 = 1:1. Iga lapse profiilis (Lapsed) saab selle üle kirjutada väljaga
// "Lugemise ja ekraani suhe" (children.reading_ratio); tühi = see vaikeväärtus.
define('READING_RATIO', 1.0);

// Luuletuse pähe õppimine (kanne kind = 'luuletus'): loeb tavalise
// lugemisajana kõikjal, aga annab selle kordaja jagu krediiti ekraanivõla
// vastu. 2.0 => 15 min pähe õpitut kustutab 30 min ekraanivõlga.
// (Kui siin defineerimata, kasutab functions.php vaikeväärtust 2.0.)
define('POEM_BONUS_MULT', 2.0);

// Salajane võti, mis lubab seed_demo.php käivitada ilma sisse logimata —
// selle vajab öine cron-töö, mis avaliku demo pere andmed iga öö taastab.
// Vali midagi juhuslikku ja pikka; sama väärtus läheb cron-käsu ?key= külge.
// Saidi omanik (ADMIN_EMAIL) saab seda ka ilma võtmeta käivitada.
define('DEMO_SEED_KEY', 'changeme-long-random-string');

// Demo-konto sisselogimised. NB: demo_parent.php ja demo_child.php ei logi
// enam automaatselt sisse — need on tavalised parool-vormid, mis kontrollivad
// just neid väärtusi. Nii pole demo enam kõigile avalik: link üksi ei anna
// ligipääsu, ilma õige parooli teadmiseta. Muuda mõlemad reaalses config.php-s;
// need näidisväärtused ei jõua kunagi GitHubi.
define('DEMO_PARENT_EMAIL', 'changeme-parent@example.com');
define('DEMO_PARENT_PASSWORD', 'changeme-parent-password');
define('DEMO_CHILD_EMAIL', 'changeme-child@example.com');
define('DEMO_CHILD_PASSWORD', 'changeme-child-password');

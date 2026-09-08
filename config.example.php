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

// Reegel: mitu minutit lugemist ("Raamat") on vaja iga ekraaniminuti kohta.
// 1.0 = 1:1 reegel. Kehtib kõigile peredele ühtemoodi.
define('READING_RATIO', 1.0);

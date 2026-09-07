# Ajaraamat — lugemis- ja ekraaniaja jälgija (mitme pere jaoks)

Iseseisvalt hostitav rakendus, mis jälgib laste ekraani- ja lugemisaega ning
näitab automaatselt, kui palju lugemist on hetkel "võlgu". See versioon
toetab **mitut peret** ja **mitut last pere kohta** — üks paigaldus, mida
saad pakkuda kõigile.

## Kuidas see töötab
- `index.php` on avalik tutvustusleht (kodulehekülg) igale külastajale.
- Vanemad **registreerivad konto** (e-post + parool) — konto jääb ootele,
  kuni sina (saidi omanik) selle **admin paneelis kinnitad**.
- Kinnitatud vanem logib sisse ja saab lisada ühe või mitu last.
- Igal lapsel on oma **eraldi, salajane link** (nt `child.php?token=...`),
  mida vanem saab jagada lapse enda seadmesse — ilma sisselogimiseta,
  ilma et laps näeks teiste perede andmeid.
- Sina, saidi omanikuna, hoiad kõike ühe hostingu ja andmebaasi peal.

## Mida vajad hostingult
- PHP (7.4+ või 8.x)
- MySQL/MariaDB andmebaas

## Automaatne juurutamine (GitHub Actions)

See kaust on git repositoorium, mille push automaatselt serverisse üles
laeb — pärast esmakordset seadistust ei pea sa enam FTP kaudu käsitsi
faile üles laadima.

**Esmakordne seadistus:**

1. `config.php` on `.gitignore`-s ja **ei kunagi** repositooriumisse ei
   jõua ega serverile pealesõidus üle kirjutata — see jääb alati sinu
   serveril, oma tegelike andmebaasi andmetega. Kui server on juba
   püsti (nagu praegu), pole midagi teha; uue serveri puhul kopeeri
   `config.example.php` → `config.php` serveril käsitsi ja täida.
2. Lisa oma GitHub repo seadetes (**Settings → Secrets and variables →
   Actions → New repository secret**) need neli saladust:
   - `FTP_SERVER` — nt `voolaid.eu`
   - `FTP_USERNAME` — sinu FTP kasutajanimi
   - `FTP_PASSWORD` — sinu FTP parool
   - `FTP_SERVER_DIR` — kaust serveril, kuhu rakendus juurutatakse,
     nt `/loevaata/` (lõpeta kaldkriipsuga)
3. Ühenda see kaust oma GitHub repositooriumiga ja tee esimene push:
   ```
   git remote add origin https://github.com/KASUTAJANIMI/REPO.git
   git branch -M main
   git push -u origin main
   ```

Sellest hetkest saadik: iga kord kui teed `git push` `main` harusse,
laeb GitHub Actions automaatselt kõik failid (peale `config.php` ja
`.git*`-failide) FTP kaudu serverisse. Vaata juurutuse käiku oma
GitHub repo "Actions" vahekaardilt.

## Seadistamine (uus, tühi paigaldus)

1. **Loo andmebaas** oma hostingu haldusliideses.
2. **Impordi `schema.sql`** phpMyAdmini "Import" vahekaardil. See loob
   `families`, `children`, `entries` ja `books` tabelid.
3. **Muuda `config.php`**:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — andmebaasi ühendus.
   - `ADMIN_PASSWORD` — SINU parool uute perede kinnitamiseks
     (`admin_login.php` kaudu). See ei ole ühegi pere parool.
   - `READING_RATIO` — jäta `1.0`, kui reegel on 1:1.
4. **Lae kõik failid FTP kaudu üles.**
5. Ava sait brauseris — see näitab tutvustuslehte. Perekonnad saavad end
   ise `register.php` kaudu üles anda; sina kinnitad nad
   `admin_login.php` → `admin.php` kaudu.

## Kui sul on juba varasem (ühe pere) paigaldus

Kui sinu andmebaasis on juba `entries`/`books` tabelid vana struktuuriga
(ilma `child_id` veeruta), **ÄRA** impordi `schema.sql` uuesti — see
tekitaks konflikte. Kasuta selle asemel:

1. Lae üles ka `migrate_multitenant.php`.
2. Ava see brauseris (nt `https://sinudomeen.ee/migrate_multitenant.php`).
3. Täida vorm: sinu e-post, parool, lapse nimi. See loob sinu konto
   (automaatselt kinnitatud, kuna oled saidi omanik), loob esimese lapse,
   ja seob kõik senised kanded/raamatud selle lapsega.
4. **Kustuta `migrate_multitenant.php` server pealt ära** pärast kasutamist.
5. Logi sisse `login.php` kaudu oma uue e-posti/parooliga.

## Raamatute staatused

Raamatul on nüüd kolm võimalikku staatust: **Lugemata** (backlog — lisatud,
aga veel alustamata), **Loeb praegu**, ja **Loetud**. Uus raamat läheb
vaikimisi "Lugemata" alla, nii et vanem saab ette valmistada nimekirja
raamatutest, mida laps järgmiseks lugeda võiks.

Kui laps valib kande lisamisel (või lugemistaimeris) "Lugemata" raamatu,
liigub see raamat automaatselt "Loeb praegu" alla — pole vaja käsitsi
staatust muuta.

Kui sul on juba andmeid vanast versioonist (ainult "Loeb praegu"/"Loetud"):
1. Lae üles `migrate_add_unread_status.php`.
2. Ava see brauseris. See lisab "lugemata" valiku; olemasolevad raamatud
   jäävad muutumatuks.
3. **Kustuta `migrate_add_unread_status.php` server pealt ära.**

## Raamatute sidumine kannetega

Lugemiskanded on nüüd seotud otse raamatunimekirjaga (`books` tabeliga),
mitte enam vaba tekstiga. Kande lisamisel/muutmisel valid olemasoleva
raamatu rippmenüüst, või lisad "+ Uus raamat…" valikuga kohe uue. Nii ei
teki enam lahknevusi nagu "Karlsson katuselt" vs "Karlsson Katuselt", ja
raamatute nimekirjas näed iga raamatu juures, mitu minutit seda on
tegelikult loetud.

Kui sul on juba andmeid vanast (sidumata) versioonist:
1. Lae üles `migrate_link_books.php`.
2. Ava see brauseris — see lisab `entries.book_id` veeru ning proovib
   automaatselt siduda olemasolevaid kandeid raamatutega, mille pealkiri
   täpselt (suur/väiketähtedest hoolimata) kattub kande vana
   kommentaariga. Kande, mida ei õnnestunud automaatselt siduda, jäävad
   lihtsalt tavatekstina nähtavaks — saad need hiljem "Kõik kanded" lehel
   käsitsi raamatuga siduda, kui soovid.
3. **Kustuta `migrate_link_books.php` server pealt ära** pärast kasutamist.

## Admin paneel

`admin_login.php` — sisesta `config.php`-s määratud `ADMIN_PASSWORD`.
Sealt näed:
- Ootel registreerimisi — Kinnita/Lükka tagasi.
- Kõiki peresid ja nende laste arvu.

Ainult sina näed admin paneeli. Vanemad ei näe ega puutu sellega kokku.

## Perede ja laste haldamine

Sisse loginud vanem näeb:
- **Töölaud** (`paren.php`) — valitud lapse tasakaal, trend, kuu ülevaade,
  viimased kanded. Kui peres on mitu last, on ülal sakiriba nende vahel
  liikumiseks.
- **Kõik kanded** (`history.php`) — kõik kanded, otsing, lehekülgedeks
  jagatud.
- **Raamatud** (`books.php`) — lapse raamatunimekiri.
- **Lapsed** (`children.php`) — uue lapse lisamine, olemasoleva lapse
  eraldi lingi kopeerimine, lapse kustutamine.

## Lugemistaimer

Lapse vaates on nupp "⏱ Alusta lugemist" (`reading_timer.php`), mis:
- lasel valida raamatu (senise raamatunimekirja seast),
- pakub kahte režiimi: **loe üles** (stopper) või **loe maha** (nt 30 min,
  loeb tagasi ja mängib heli, kui aeg täis saab),
- lubab taimerit pausile panna ja jätkata,
- salvestab lõpetamisel automaatselt kande selle raamatu ja lapse jaoks
  (kommentaariks "Lisatud taimeriga", nii et vanem näeb, kust see tuli).

Taimer töötab ilma sisselogimiseta (sama token, mis lapse muul vaatel),
ja säilitab pooleliolek isegi kui laps kogemata lehte värskendab
(salvestatakse ajutiselt brauseri `localStorage`-sse, mitte serverisse,
kuni lõpetamiseni).

## Lapse vaade (ilma sisselogimiseta)

Iga lapse jaoks luuakse automaatselt salajane link kujul
`child.php?token=PIKK_JUHUSLIK_STRING`. Seda linki näed ja saad
kopeerida `children.php` lehelt. Jaga seda ainult selle lapse enda
seadmesse — igaühel on erinev, äraarvamatu token, nii et pered ei näe
üksteise andmeid.

`child_books.php?token=...` näitab sama lapse raamatunimekirja.

## Turvamärkus

- Iga pere andmed on eraldatud `child_id`/`family_id` kaudu — iga
  päring kontrollib omandiõigust enne andmete näitamist.
- Paroolid on salvestatud `password_hash()`-iga (bcrypt), mitte
  puhtal kujul.
- Lapse avalikud lingid ei ole indekseeritavad ega arvatavad (pikk
  juhuslik token), aga käitu nendega siiski nagu tundliku infoga —
  ära jaga neid avalikult väljaspool oma peret.
- Uued kontod nõuavad käsitsi kinnitust (`ADMIN_PASSWORD`), et vältida
  suvaliste kontode teket.
- Parooli taastamist praegu ei ole (nõuaks e-kirjade saatmise
  seadistust serveris) — kui vanem unustab parooli, pead sa selle
  andmebaasis käsitsi lähtestama või konto kustutama ja uuesti
  registreerima laskma.

## Failid
- `config.php` — andmebaasi andmed, admini parool
- `schema.sql` — uus paigaldus
- `migrate_multitenant.php` — üleminek vanalt ühe-pere struktuurilt
- `migrate_link_books.php` — üleminek raamatute sidumisele kannetega
- `db.php`, `auth.php`, `admin_auth.php`, `functions.php` — abifailid
- `index.php` — avalik tutvustusleht
- `register.php`, `login.php`, `logout.php` — pere konto
- `admin_login.php`, `admin_logout.php`, `admin.php` — saidi omaniku paneel
- `paren.php` — vanema töölaud (valitud laps)
- `children.php` — laste haldamine, avalike linkide vaatamine
- `add.php`, `edit_day.php` — kannete lisamine/muutmine
- `history.php` — kõikide kannete vaatamine, otsing
- `books.php`, `add_book.php`, `edit_book.php` — raamatute haldamine
- `child.php`, `child_books.php` — avalik lapse vaade (token põhine)
- `reading_timer.php` — lapse lugemistaimer (avalik, token põhine)
- `migrate_add_unread_status.php` — üleminek "Lugemata" staatusele
- `export.php` — CSV eksport (valitud laps)
- `style.css` — kujundus
- `favicon.ico`, `icon-192.png`, `icon-512.png`, `apple-touch-icon.png`,
  `manifest.json` — rakenduse ikoon

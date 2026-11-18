# Custom Maintenance Mode

**Contributors:** Szurofka Márton
**Requires at least:** 5.8
**Tested up to:** 6.4
**Requires PHP:** 7.4
**Stable tag:** 1.3.2
**License:** GPLv2 or later

## Description

A Custom Maintenance Mode egy egyedi, kifejezetten automatizált telepítésekhez és CI/CD folyamatokhoz optimalizált karbantartás mód bővítmény. Aktiváláskor azonnal, emberi beavatkozás nélkül beállítja az alapértelmezett értékeket, betölti a fallback SVG logót, és lezárja a külső végpontokat.

A bővítmény beépített GitHub frissítővel (Plugin Update Checker) rendelkezik, így a jövőbeli verziók a WordPress admin felületén keresztül egy kattintással telepíthetők.

**Figyelem:** Ez a Bővítmény kifejzetten a Széchenyi István Egyetem wordpress oldalainak lett készítve, más oldalak üzemeltetéséhez nem ajánlott!

### Fő funkciók:

- **Kulcsrakész aktiválás:** Telepítéskor automatikusan inicializálja az egyetemi SVG logót és a magyar/angol alapértelmezett szövegeket.
- **Intelligens kétnyelvűség:** A böngésző `HTTP_ACCEPT_LANGUAGE` fejléce alapján automatikusan magyar vagy angol nyelven jelenik meg a tartalom.
- **Szigorú kétnyelvű mód:** Lehetőség van a HU és EN szövegek egymás alatti, kényszerített megjelenítésére.
- **URL-specifikus blokkolás:** A karbantartás mód korlátozható csak bizonyos URL útvonalakra (pl. `/jelentkezes`). Ilyenkor automatikusan megjelenik egy "Vissza a kezdőlapra" gomb.
- **Kiskapuk (Bypass) támogatása:** Az adminisztrátorok, a bejelentkezési képernyők (`wp-login.php`), és a `?cmm_bypass` paraméterrel érkező fejlesztők számára az oldal zavartalanul működik (24 órás süti alapon).
- **Teljes API védelem:** Karbantartás alatt az XML-RPC és a nem adminisztrátorok felől érkező REST API hívások automatikusan `503 Service Temporarily Unavailable` státuszkódot adnak vissza.
- **Beépített frissítő (OTA):** Közvetlen frissítési lehetőség publikus GitHub repóból.
- **Teljesítmény optimalizált:** A frontend sablon inline CSS-t használ, és nem hívja meg a sablonok fejléceit (`wp_head`), így minimalizálva a szerver terhelését.

## Frequently Asked Questions

### Hogyan működik a nyelvfelismerés?

A bővítmény a látogató böngészőjének nyelvét vizsgálja. Ha a nyelv `hu` (magyar), akkor a magyar nyelvű szövegeket és láblécet jeleníti meg. Minden más nyelv esetén (pl. `en`, `de`, `sk`) az angol tartalom töltődik be.

### Hogyan tudok fejlesztőként belépni az oldalra karbantartás alatt?

Három módon lehetséges:

1. Ha be vagy jelentkezve, és a jogosultságod `manage_options` (Adminisztrátor).
2. Közvetlenül a `wp-login.php` vagy `wp-admin` URL-eken keresztül (ezek sosem blokkoltak).
3. A frontend böngészéséhez használd a `https://domain.hu/?cmm_bypass` varázslinket. Ez letesz egy sütit, amely 24 óráig engedélyezi a szabad böngészést a karbantartás alatt lévő oldalon.

### Mi történik, ha elrontom a beállításokat?

Az admin felületen, a "Módosítások mentése" gomb mellett található egy "Alapértelmezett értékek visszaállítása" gomb. Ez azonnal visszaállítja az aktiváláskori biztonságos állapotot, beleértve az SVG logót is.

### Hogyan kereshetek új frissítéseket?

A bővítmény a háttérben automatikusan ellenőrzi a GitHub kiadásokat (Releases). Ha azonnal szeretnéd ellenőrizni, a **Beállítások -> Karbantartás** oldalon kattints a "Frissítések keresése" gombra.

### Mit jelent a „GitHub API error ... HTTP status code: 403" a frissítés keresésekor?

Az anonim GitHub API-limit (60 kérés óránként IP-címenként) elfogyott, ami több, közös kimenő IP mögötti oldalnál gyakori. Várj a limit visszaállására (legfeljebb egy óra), vagy add meg a `wp-config.php`-ban a `CMM_GITHUB_TOKEN` konstanst egy GitHub-tokennel (a publikus repó olvasásához jogosultság nélküli token elég), ekkor a limit 5000 kérés/óra. A tokent sosem szabad a repóba tenni.

## Devtracker-jelentő (1.2.0)

Naponta egyszer, oldalanként összesített számokat küld a devtracker backendnek: hány tartalmat módosított a csapat az oldalon aznap (oldal, bejegyzés, sablon, menü, egyéb), és hány frissítés (plugin, téma, mag) történt. A plugin telepítésekor azonnal bejelenti az oldalt is.

**Bekötés.** A backend címe és kulcsa csak a `wp-config.php`-ból (vagy a `cmm_dt_url` / `cmm_dt_key` opcióból) jöhet, a repóba sosem kerül:

```php
define( 'DEVTRACKER_URL', '...' ); // https:// (fejlesztéshez http://localhost)
define( 'DEVTRACKER_KEY', '...' );
```

Ha bármelyik hiányzik, a funkció teljesen inaktív (nincs horog, nincs cron, nincs opció), a plugin úgy működik, mint az 1.1.17. Az engedélyezett felhasználónevek listáját a backend adja a válaszban (`cmm_dt_wp_users`), a `DEVTRACKER_USERS` konstans (vesszővel elválasztva) felülírhatja. Üres lista esetén semmit nem számol.

**Klón és staging.** Az oldal azonosítója egy véletlen UUID, mellé a gépnév (a `home_url()` gépneve, kisbetűs, port nélkül) is mentődik. Ha az adatbázist átmásolják egy másik gépnévre, a másolat új azonosítót kap, és nem örökli az eredeti el nem küldött napjait. Ismert korlát: ha egy oldal `wp-config.php`-ban a `WP_HOME`-ot a kérés gépneve alapján állítja, vagy ugyanazt az adatbázist két gépnév alatt egyszerre használják, az azonosító gépnévváltásonként újra cserélődik.

**Adatvédelem.** A jelentés számokat és az oldal nyilvános adatait (azonosító, cím, név, verziók) tartalmazza. Nem kerül bele felhasználónév, e-mail, tartalom, IP. Az egyetlen kivétel az 1.4.0 óta a közzétett, elavult **oldalak** listája (azonosító, cím, útvonal, módosítási nap): ez a webhely nyilvános szerkezete, nem a tartalma (a döntés a projekt gazdájáé, 2026-11-18).

**Hogyan számol.**

- *Tartalom:* a nap során módosított **különböző** tartalmak száma (ugyanannak az oldalnak a tíz mentése 1). Csak az engedélyezett felhasználó (mentéskor bejelentkezett `user_login`) módosítása számít; cron, WP-CLI és rendszerfolyamat nem. Kizárt: revízió, automatikus mentés, `auto-draft`, média, és minden `show_ui = false` típus (a lista a `cmm_dt_excluded_post_types` szűrővel módosítható). A sablon-típusok (`elementor_library`, `wp_template`, `wp_template_part`) a `show_ui`-kizárás alól mentesek, a `wp_navigation` (blokkos menü) a `menu` vödörbe számít. A napváltás `Europe/Budapest` szerint van.
- *Frissítés:* nem horogra, hanem **verzió-különbségre** épül: a plugin elmenti a telepített verziókat, és ha egy verzió megnőtt, az +1. Így mindegy, ki indította a frissítést (MainWP, WP-CLI, automatikus, kézi), és nincs kettős számolás. Ismert, elfogadott pontatlanság: ha ugyanaz az elem két ellenőrzés között két frissítést kap, az 1-nek számít. Az első futás csak alapállapotot ment, új elemet nem számol frissítésnek, visszalépést (kisebb verzió) sem.
- *Küldés:* napi cron (`cmm_dt_daily`), a lezárt napokat küldi. Hiba esetén 15 perc, 1 óra, 6 óra múlva újrapróbál, utána a következő napi futásra vár; a függő napokat nem veszíti el (legfeljebb 60 napot tart meg). Az adminban (legfeljebb 6 óránként) pótolja a kimaradt küldést. A Beállítások → Karbantartás oldalon egy csak olvasható „Tracker” blokk mutatja az állapotot, és van „Küldés most” gomb.

## Oldal-frissesség és karbantartási állapot (1.3.0)

A devtracker-jelentő két új dolgot hordoz. Ugyanúgy csak bekötött (`DEVTRACKER_URL` + `DEVTRACKER_KEY`) oldalon él: bekötés nélkül nincs horog, cron, új opció és admin blokk.

**Mit küld.** A jelentés (`schema` marad 1, a mezők additívak) minden küldésben (napi, bejelentkezés, „Küldés most", pótló küldés) két új legfelső szintű mezőt kap:

- `maintenance` (logikai): a karbantartás mód aktuális állapota. Önjavítás: az elveszett jelzést, vagy az adatbázisból szerkesztett állapotot a következő jelentés rendbe teszi.
- `maintenance_changed_at` (UTC ISO 8601): az utolsó, a plugin által észlelt váltás ideje. Ha még nem volt váltás, a mező kimarad.
- `freshness`: `last_content_change` (a legutóbbi **oldal**-módosítás napja `Europe/Budapest` szerint, **bárki** módosítását számítva, üres halmaznál `null`), `published` (a közzétett oldalak száma) és `stale` (közülük hány nem módosult a küszöb ideje óta). Ha a pillanatkép nem számolható, a mező **kimarad** (a „nincs adat" nem 0).

**Mit NEM küld.** Nincs benne felhasználónév, e-mail, szerző, tartalom, részlet, IP, és semmilyen vázlat, privát, jelszóval védett vagy ütemezett oldal, illetve más bejegyzéstípus (bejegyzés, sablon, menü, egyéni típus). A kulcs és a backend címe a hibaszövegekbe és az admin felületre sem kerül. Az egyetlen kivétel a **közzétett, elavult oldalak listája** (1.4.0 óta, lásd az „Elavult oldalak a jelentésben" fejezetet).

**A mérési halmaz (1.3.1 óta csak az oldal).** `publish` állapotú `page`. Az „elavult" jelzést az oldalak tartalma indokolja (régi dátumok, megszűnt információ): egy bejegyzés nem arra való, hogy módosuljon, a sablon szerkezet, nem tartalom, ezért ezek az 1.3.0-ban tévesen frissnek vagy elavultnak mutatták az oldalt. A halmaz a `cmm_dt_freshness_post_types` szűrővel egy oldalnál bővíthető (pl. `post`-tal); a jelentés és az admin lista ugyanazt a függvényt használja. Nem számít bele alapból: bejegyzés, sablon (`wp_template`, `wp_template_part`, `elementor_library`), egyéni típus (pl. kurzus: a tömeges mentése bármit frissnek mutatna), menü, média, revízió, automatikus mentés, vázlat, privát tartalom. Ha a szűrővel az `elementor_library` bekerül, az **Elementor „kit"** (az oldal globális beállításai) akkor is kizárt. Hibás, nullás (`0000-00-00`) módosítási dátumú sornál a közzétételi dátum (`post_date_gmt`) számít; ha az is hiányzik, a sor a `published`-ben benne van, de a `last_content_change` és a `stale` számításból kimarad. A napi `days[].content` számlálás (az engedélyezett felhasználók munkája) **nem változott**: továbbra is mind az öt vödröt számolja.

**A küszöb.** A tracker adja a jelentés válaszának `config.stale_days` mezőjében, a plugin 30-365 közé vágja (a határon kívüli értéket a határra), a `cmm_dt_stale_days` opcióba menti, és a következő jelentés ezzel számol. Amíg a tracker nem küldött értéket, az alapérték 90 (`CMM_Devtracker_Freshness::DEFAULT_STALE_DAYS`). Ha egy későbbi válaszból hiányzik vagy érvénytelen a mező, a korábban mentett érték marad.

**Elavult oldalak lista.** A Beállítások → Karbantartás oldal „Tracker" blokkja alatt, csak `manage_options` jogosultsággal: ugyanaz a halmaz és küszöb, a legrégebbi elöl, legfeljebb 200 sor (az összes darabszám felette, pl. „14 elavult a 40 közzétett oldalból"), cím, utolsó módosítás napja, szerkesztés link.

**A ping.** Ha a `cmm_settings['is_active']` **értéke változik** (admin mentés, „Alapértelmezett értékek visszaállítása", bármilyen más `update_option`), a plugin a kérés végén egyszer `POST {DEVTRACKER_URL}/api/v1/wp/status` jelzést küld (5 s időkorlát, átirányítás nélkül, `{"schema":1,"site_uuid":...,"maintenance":...,"changed_at":...}`). Más beállítás mentése nem küld semmit. A ping nem blokkolja a mentést: FastCGI-n a válasz lezárása után megy, egyébként (pl. `mod_php`) egyszeri cron-eseményre bízza, így az admin sosem vár a hálózatra. Minden nem 2xx választ és hibát csendben eltűr, nincs újrapróba és admin hibaüzenet, mert a következő napi jelentés `maintenance` mezője úgyis helyreállít. Az eredmény rövid szövege a `cmm_dt_ping` opcióba kerül (a Tracker blokkban látszik). Amíg az oldal sosem jelentett sikeresen, nem megy ping (a tracker még nem ismeri): ilyenkor a bejelentkezési jelentés `maintenance` mezője viszi az állapotot. A ping nem függ a karbantartás módtól. Az új oldalak karbantartásban indulnak: az aktiválás `is_active = 1`-et ír, ez nem számít váltásnak.

## Kiadás jelzése (1.3.2)

A devtracker az új oldalak életútját követi: mennyi munkaidő ment egy új oldal felépítésére, és mennyi az után, hogy kiadták. Ehhez tudnia kell, mikor lett kész az oldal. A karbantartás mód kikapcsolása ezt önmagában nem mondja meg (tesztelés közben is kikapcsolják), ezért a plugin **rákérdez**. Csak bekötött (`DEVTRACKER_URL` + `DEVTRACKER_KEY`) oldalon él, egyébként nincs kérdés és nincs új opció.

**Mikor kérdez.** Amikor a Beállítások → Karbantartás oldalon a karbantartás módot **bekapcsoltról kikapcsoltra** váltják, és mindhárom feltétel teljesül: a jelentő be van kötve; az oldalt még nem jelezték kiadottnak (`cmm_dt_released`); az oldal **karbantartásban született**, vagyis a plugin első aktiválásakor (friss telepítés, bekötött konfigurációval) a karbantartás be volt kapcsolva, és ezt a `cmm_dt_born_in_maintenance` opcióba jegyezte (csak aktiváláskor áll be). Az 1.2.x-ről frissített, már éles oldalakon ez az opció nincs beállítva, ott soha nincs kérdés. Ugyanígy nincs kérdés, ha a pluginnal egy már létező beállítású oldalt aktiválnak újra, és a karbantartás **bekapcsolásánál** sem.

**A kérdés.** A „Karbantartás mód bekapcsolva” jelölőnégyzet alatt jelenik meg, amint kikapcsolják: „Elkészült az oldal (kiadás)” vagy „Csak átmenetileg kapcsolom ki”. Választás nélkül a mentés nem megy át (JS). A szerver külön ellenőriz: kikapcsolás kérdés nélkül (WP-CLI, adatbázis-szerkesztés, JS nélküli mentés) **nem minősül kiadásnak**, és nem is blokkolja a mentést. Csak az érvényes nonce-szal, `manage_options` joggal érkező admin-mentés „kiadás” válasza számít.

**Mi történik.** *Kiadás:* a plugin elmenti a `cmm_dt_released` opciót (UTC ISO 8601), a karbantartás-váltás pingje (`POST /api/v1/wp/status`) `"released": true` mezőt is visz (csak `maintenance: false` mellett, és csak az éppen kiadó váltásnál), a napi jelentésekben és a bejelentkezésben pedig `"released": true` és `"released_at"` szerepel. Innentől nem kérdez többet. *Átmeneti:* a ping `released` nélkül megy, a jelentés nem hordoz kiadást, és a következő kikapcsolásnál újra kérdez. A `released` soha nem megy `false` értékkel: a hiány azt jelenti, hogy nem jelezték, nem azt, hogy visszavonták. Ha a devtracker nem érhető el, a kiadás a következő napi jelentéssel utólag is eljut. A Tracker blokk egy csak olvasható sorban mutatja az állapotot. A minták: `sample-report.json`, `sample-status.json`.

Ismert korlát: ha egy oldalt a bekötés (`DEVTRACKER_*`) **előtt** aktiváltak, az opció nem jön létre, ott nincs kérdés (a csapatvezető a trackerben kézzel is megjelölheti az oldalt kiadottnak).

## Elavult oldalak a jelentésben (1.4.0)

A devtracker Oldal-frissesség lapján a csapatvezető lenyithatja egy webhely sorát, és látja a felülvizsgálandó oldalakat hivatkozásokkal. Ehhez a `freshness` mező egy új, opcionális listát kap (a `schema` marad 1):

```json
"freshness": {
  "last_content_change": "2026-03-01",
  "published": 40,
  "stale": 14,
  "stale_pages": [
    { "id": 12, "title": "Kapcsolat", "path": "/kapcsolat/", "modified": "2026-02-10" },
    { "id": 30, "title": "", "path": "/?page_id=30", "modified": "2026-01-04" }
  ]
}
```

- **Mely oldalak:** a `stale`-t adó halmaz és küszöb (közzétett `page`, a `stale_days`-nél régebben módosult), a legrégebbi elöl, **legfeljebb 200**. Ha több az elavult oldal, a `stale` az **összes** darabszám marad, a lista csak az első 200. A lista és a `stale` ugyanazzal a küszöbidővel számolódik (egy `report()` hívásban).
- **Jelszóval védett oldal** (`post_password` nem üres) **kimarad a listából**, de a `stale` számába beleszámít, ahogy eddig is (a címe a jelszó mögötti tartalom része lehet). Ezért a `stale` ilyenkor nagyobb lehet a lista hosszánál. Vázlat, privát és ütemezett oldal eleve nem közzétett, így sem a listában, sem a `stale`-ben nincs.
- **`id`**: a bejegyzés azonosítója (egész szám). **`title`**: egyszerű szöveg, legfeljebb 120 karakter, HTML és entitások nélkül (a `the_title` szűrőn átment cím címkéi eltávolítva, entitásai feloldva, majd a címkék még egyszer eltávolítva), lehet üres. **`path`**: az oldal címe a gyökértől, `/`-rel kezdve (a permalink útvonala és ha van, lekérdezése, pl. `?page_id=30`), **séma és gépnév nélkül**; alkönyvtáras telepítésnél az alkönyvtár is benne van. Ha a permalink üres, vagy az útvonal szóközt, idézőjelet, `<`, `>`, `\` jelet tartalmaz, a `/?page_id=<id>` alakra esik vissza. **`modified`**: az utolsó módosítás napja `YYYY-MM-DD`, `Europe/Budapest` szerint.
- **Hiány és üres lista:** ha a pillanatkép nem számolható, a `freshness` kimarad, és a `stale_pages` sincs. Ha a pillanatkép megvan, de a lista nem számolható (adatbázis-hiba), csak a `stale_pages` marad ki. Ha nincs elavult oldal, a lista **üres** (`[]`): a tracker ilyenkor törli a régit. A kulcs hiánya azt jelenti, hogy a plugin nem tud listát küldeni, a tracker a meglévőt békén hagyja.
- **Mikor és mennyibe kerül:** a lista a **küldéskor** számolódik (nem horgokból), egyetlen lekérdezéssel a `posts` táblán (azonosító, cím, módosítási idő, nincs tartalom a PHP-ban); a permalink és a cím oldalanként, legfeljebb a 200 elemre.
- **Adatvédelem:** a jelentésbe ezen a listán túl továbbra sem kerül oldaltartalom, részlet, szerző, felhasználónév, e-mail, IP, vázlat, privát, jelszóval védett vagy ütemezett oldal, és más bejegyzéstípus.
- A helyi „Elavult oldalak" lista a WordPress adminban változatlan (ugyanaz a halmaz, a jelszóval védett oldalakkal együtt). A minta: `sample-report.json`.

## Changelog

### 1.4.0

- Új: a devtracker-jelentés `freshness.stale_pages` listát hordoz: a közzétett, elavult oldalak (legfeljebb 200, a legrégebbi elöl) azonosítója, egyszerű szöveges címe, gyökértől számított útvonala és módosítási napja. Jelszóval védett oldal nincs a listában (a `stale`-ben benne lehet). Nincs elavult oldal: üres lista; a lista hiánya: a régi plugin / nem számolható.
- Adatvédelem: az eddigi "a jelentés nem tartalmaz címet, URL-t, azonosítót" szabály erre az egy listára módosul (a projekt gazdájának döntése, 2026-11-18). Oldaltartalom, szerző, felhasználónév, e-mail, IP és más típus nem kerülhet bele.
- Nem változott: a napi `days` számolás, a ping, a kiadás-jelzés, a karbantartás és a klón-észlelés.

### 1.3.2

- Új: kiadás jelzése. Új, karbantartásban született oldalon a karbantartás kikapcsolásakor a plugin rákérdez, hogy elkészült-e az oldal. „Kiadás” esetén a ping `released: true` mezőt visz, és a jelentések `released` és `released_at` mezőt hordoznak. Átmeneti kikapcsolásnál nincs jelzés, és újra kérdez. Kérdés nélküli kikapcsolás (WP-CLI, adatbázis, JS nélkül) nem kiadás és nem blokkol. Konfiguráció nélkül, 1.2.x-ről frissített éles oldalon és bekapcsoláskor nincs kérdés.

### 1.3.1

- Fix: az oldal-frissesség (`freshness`) mérési halmaza csak a közzétett oldal (`page`). Az 1.3.0 a bejegyzéseket és a sablonokat is számolta, ami az oldalt tévesen frissnek vagy elavultnak mutatta. A `cmm_dt_freshness_post_types` szűrő marad (egy oldalnál bővíthető). Az „Elavult oldalak" lista és a feliratok is „oldal"-ra álltak át, a „Típus" oszlop elmarad.
- Nem változott: a napi tartalom-számlálás (az engedélyezett felhasználók munkája) továbbra is mind az öt vödröt számolja. A `schema` és a mezők nevei változatlanok; a következő napi jelentés felülírja az 1.3.0-ban számolt értéket.

### 1.3.0

- Új: a devtracker-jelentés oldal-frissesség pillanatképet hordoz (`freshness`: utolsó tartalom-módosítás napja, közzétett és elavult tartalmak száma), a küszöböt (`stale_days`, 30-365, alap 90) a tracker adja. A konkrét elavult oldalak csak a WordPress adminban látszanak (Beállítások → Karbantartás, „Elavult oldalak").
- Új: a jelentés hordozza a karbantartás mód aktuális állapotát (`maintenance`, `maintenance_changed_at`), a mód váltásáról pedig a plugin azonnali, csendes jelzést (`POST /api/v1/wp/status`) küld a kérés végén.
- Konfiguráció nélkül (`DEVTRACKER_URL` + `DEVTRACKER_KEY`) minden új rész inaktív.

### 1.2.1

- Fix: a devtracker-jelentő kezeli a klónozott és a staging oldalakat. Az azonosító mellé a gépnév is mentődik (`cmm_dt_site_host`); ha az óránkénti ellenőrzésen, a napi cron elején vagy küldés előtt a gépnév eltér, az oldal új azonosítót kap, az örökölt függő napok és a mai azonosítóhalmazok törlődnek, a verzió-pillanatkép marad, és gyors bejelentkezés indul. Így az eredeti és a másolat nem ír felül egymás számait. Gépnév-váltás (staging -> éles, a `www` eltűnése) szándékosan új oldal a trackerben. Az 1.2.1 előtt már klónozott oldalon a mostani gépnév lesz az alap, ott a `cmm_dt_site_uuid` opciót kézzel kell törölni.
- Fix: a „Frissítések keresése" 403-mal bukhatott, ha az anonim GitHub API-limit (60 kérés/óra/IP) elfogyott. Opcionális `CMM_GITHUB_TOKEN` konstans a `wp-config.php`-ban: ha meg van adva, a frissítésellenőrzés tokennel megy (5000 kérés/óra). Token nélkül nincs változás.

### 1.2.0

- Új: devtracker-jelentő (napi összesített tartalom- és frissítésszámok, oldal automatikus bejelentése). Konfiguráció nélkül inaktív.

### 1.1.18

- Fix: SZEducate Hub->Kliens hívások (`X-SZEducate-Auth` fejléc) átengedése karbantartás alatt

### 1.1.17

- Kuma JSON gateway

### 1.1.16

- UpTimeKuma gateway

### 1.1.15

- Patch for bugs

### 1.1.14

- RestAPI fixation

### 1.1.13

- GateWay for Szeducate

### 1.1.12

- Redirection to url

### 1.1.11

- Role management added to enable other roles to bypass

### 1.1.10

- Premade button setups

### 1.1.9

- Admin core update for API bypass

### 1.1.8

- Still wasn't good enough (it's 1:47am now...)

### 1.1.7

- Wildcard Gateway

### 1.1.6

- better Gateway

### 1.1.5

- Gateway for MSDLWP plugin

### 1.1.4

- Customer support email update

### 1.1.3

- Some small fixes

### 1.1.2

- Admin felület vizuális lapfülekre (Tabs) bontása (Beállítások / Tartalom).
- "Frissítések keresése" manuális trigger gomb hozzáadása az admin felülethez.
- Továbbfejlesztett fallback logika a magyar/angol gombfeliratok kezelésére.

### 1.1.1

- Plugin Update Checker (PUC) integrálva a GitHub-alapú automatikus frissítésekhez.
- SVG kód valós idejű admin előnézetének integrálása.
- Frontend CSS optimalizálása, kényszerített középre igazítás a logók és gombok esetében.

### 1.1.0

- Kezdeti stabil kiadás.
- Biztonsági funkciók: REST API és XML-RPC végpontok lezárása (503 status).
- SZE arculati SVG logó és egyedi CSS hardcode integráció.

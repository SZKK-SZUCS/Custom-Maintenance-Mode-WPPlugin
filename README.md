# Custom Maintenance Mode

**Contributors:** Szurofka Márton
**Requires at least:** 5.8
**Tested up to:** 6.4
**Requires PHP:** 7.4
**Stable tag:** 1.1.5
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

## Changelog

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

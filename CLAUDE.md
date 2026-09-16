# CLAUDE.md — custom-maintenance-mode

WordPress plugin: egyedi karbantartás-mód a Széchenyi István Egyetem (SZE)
oldalaihoz. Lásd a megosztott `wp-plugin-conventions` skillt.

A `class-cmm-frontend.php`-ban egy bejövő `HTTP_X_MSDL_API_KEY` fejlécet
vizsgáló bypass-logika van — ez nem hardcodeolt secret.

## Parancsok
- `composer install` — phpcs (WordPress Coding Standards) + phpstan dev-függőségek
- `composer lint` — phpcs (jelenleg ~43 hiba, túlnyomórészt CRLF/tabs-vs-space/komment-formázás)
- `composer lint:fix` — phpcbf, a fenti auto-javítható részét lefixálja (nagy diffet generál, futtasd tudatosan)
- `composer stan` — phpstan level 5. A jelenlegi 18 hiba `phpstan-baseline.neon`-ban van (nem blokkol, csak ÚJ hiba jelez) — köztük van két érdemi jelölt, amit érdemes lenne egyszer megnézni: `class-cmm-frontend.php:64` egy `||` feltétel ami mindig false, és a `templates/maintenance-view.php`-ban több változó ($active_lang, $text_hu/$text_en stb.) amit phpstan nem lát definiálva (extract()-tal kaphatják értéküket).
- `composer stan:baseline` — újragenerálja a baseline-t, ha tudatosan elfogadsz új hibákat

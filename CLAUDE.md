# CLAUDE.md — custom-maintenance-mode

WordPress plugin: egyedi karbantartás-mód a Széchenyi István Egyetem (SZE)
oldalaihoz. Lásd a megosztott `wp-plugin-conventions` skillt.

A `class-cmm-frontend.php`-ban egy bejövő `HTTP_X_MSDL_API_KEY` fejlécet
vizsgáló bypass-logika van — ez nem hardcodeolt secret.

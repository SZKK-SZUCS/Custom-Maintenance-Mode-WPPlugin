<?php
/**
 * Plugin Name: SZE - Custom Maintenance Mode
 * Plugin URI: https://github.com/SZKK-SZUCS/Custom-Maintenance-Mode-WPPlugin
 * Description: Egyedi karbantartás mód plugin a telepítés idejére, valamint a későbbi karbantartási időszakokra.
 * Version: 1.1.8
 * Author: Műszaki Fejlesztési és Üzemeltetési Igazgatóság
 * Author URI: https://www.uni.sze.hu/
 * Text Domain: custom-maintenance-mode
 */

// Készítette: Szurofka Márton - MFÜI
// Közvetlen hozzáférés tiltása
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Plugin Update Checker integráció
require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

global $cmm_update_checker; // Globálissá tesszük, hogy az admin osztály elérje
$cmm_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/SZKK-SZUCS/Custom-Maintenance-Mode-WPPlugin', // SAJÁT URL!
    __FILE__,
    'custom-maintenance-mode'
);
$cmm_update_checker->setBranch('main');

/**
 * Plugin aktiválásakor lefutó metódus.
 * Létrehozza az alapértelmezett beállításokat az adatbázisban, 
 * így az automatikus telepítés után a plugin azonnal funkcionális.
 */
function cmm_activate_plugin() {
    $default_options = array(
        'is_active'          => 1,
        'force_bilingual'    => 0,
        'logo_attachment_id' => '',
        'logo_svg'           => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 309.58 80.83"><defs><style>.cls-1{fill:#1c2442;}.cls-2{fill:#00adcb;}</style></defs><g id="Layer_2" data-name="Layer 2"><g id="Layer_1-2" data-name="Layer 1"><path class="cls-1" d="M129.41,76.34a4.52,4.52,0,0,1-9,0V70h1.23v6.34a3.29,3.29,0,1,0,6.58,0V70h1.23Z"/><polygon class="cls-1" points="139.02 80.77 132.56 72.13 132.56 80.77 131.33 80.77 131.33 70 132.61 70 139.07 78.66 139.07 70 140.28 70 140.28 80.77 139.02 80.77"/><rect class="cls-1" x="142.13" y="70" width="1.23" height="10.76"/><polygon class="cls-1" points="149.11 80.77 144.74 70 146.06 70 149.77 79.34 153.49 70 154.78 70 150.38 80.77 149.11 80.77"/><polygon class="cls-1" points="157.35 71.19 157.35 74.72 162.77 74.72 162.77 75.91 157.35 75.91 157.35 79.6 163.62 79.6 163.62 80.77 156.12 80.77 156.12 70 163.42 70 163.42 71.19 157.35 71.19"/><path class="cls-1" d="M169.45,76.18c2,0,3.06-.86,3.06-2.53s-1.1-2.46-3.06-2.46h-2.86v5Zm.73,1.16c-.24,0-.48,0-.73,0h-2.86v3.4h-1.23V70h4.09c2.69,0,4.25,1.33,4.25,3.62a3.37,3.37,0,0,1-2.42,3.49l2.52,3.66h-1.4Z"/><path class="cls-1" d="M182.64,71l-.52,1.15a5.52,5.52,0,0,0-3-.91c-1.38,0-2.29.53-2.29,1.43,0,2.77,6.06,1.33,6,5.23,0,1.8-1.59,3-3.89,3a6.12,6.12,0,0,1-4.12-1.64l.55-1.11A5.35,5.35,0,0,0,179,79.58c1.55,0,2.52-.63,2.52-1.66,0-2.83-6-1.32-6-5.18,0-1.69,1.49-2.78,3.72-2.78a6.34,6.34,0,0,1,3.41,1"/><rect class="cls-1" x="184.83" y="70" width="1.23" height="10.76"/><polygon class="cls-1" points="192.45 71.19 192.45 80.77 191.22 80.77 191.22 71.19 187.72 71.19 187.72 70 195.97 70 195.97 71.19 192.45 71.19"/><polygon class="cls-1" points="202.66 80.77 201.45 80.77 201.45 77.12 197.28 70 198.56 70 202.05 75.62 205.49 70 206.75 70 202.66 77.08 202.66 80.77"/><path class="cls-1" d="M212.62,75.39a4.39,4.39,0,1,0,4.4-4.25,4.34,4.34,0,0,0-4.4,4.25m10,0A5.64,5.64,0,1,1,217,70a5.51,5.51,0,0,1,5.63,5.42"/><polygon class="cls-1" points="225.7 71.19 225.7 74.97 230.73 74.97 230.73 76.15 225.7 76.15 225.7 80.77 224.47 80.77 224.47 70 231.31 70 231.31 71.19 225.7 71.19"/><path class="cls-1" d="M245.66,75.51v3.9a6.62,6.62,0,0,1-4,1.42A5.43,5.43,0,1,1,241.72,70a6.09,6.09,0,0,1,4,1.48l-.71.92a5,5,0,0,0-3.26-1.24,4.27,4.27,0,1,0,0,8.53,5.14,5.14,0,0,0,2.83-.91V75.51Z"/><polygon class="cls-1" points="251.9 80.77 250.69 80.77 250.69 77.12 246.52 70 247.8 70 251.29 75.62 254.73 70 255.99 70 251.9 77.08 251.9 80.77"/><path class="cls-1" d="M262.57,68.7h-1l1.4-2.17,1,.52Zm-2.26,0h-1l1.4-2.17,1,.52Zm-3.38,6.69a4.38,4.38,0,1,0,4.4-4.25,4.34,4.34,0,0,0-4.4,4.25m10,0A5.64,5.64,0,1,1,261.33,70,5.51,5.51,0,0,1,267,75.39"/><path class="cls-1" d="M272.87,76.18c1.95,0,3.06-.86,3.06-2.53s-1.11-2.46-3.06-2.46H270v5Zm.72,1.16c-.23,0-.48,0-.72,0H270v3.4h-1.23V70h4.09c2.69,0,4.24,1.33,4.24,3.62a3.37,3.37,0,0,1-2.41,3.49l2.52,3.66h-1.4Z"/><path class="cls-1" d="M129,12c-1.79,0-3,.65-3,1.93,0,4.35,12.59,1.92,12.55,10.43,0,4.62-4,7.12-9.24,7.12a16.57,16.57,0,0,1-10.43-3.75L121,23.39a14,14,0,0,0,8.37,3.54c2.2,0,3.51-.81,3.51-2.26,0-4.45-12.58-1.86-12.58-10.23,0-4.28,3.67-7,9.14-7a16.9,16.9,0,0,1,9,2.56l-2.06,4.42A16.56,16.56,0,0,0,129,12"/><polygon class="cls-1" points="160.87 7.7 160.87 11.27 147.67 26.86 161.07 26.86 161.04 31.32 140.48 31.32 140.48 27.71 153.71 12.15 140.92 12.15 140.92 7.7 160.87 7.7"/><path class="cls-1" d="M179.3,1.82,175,5.5h-4.12L174.47,0Zm3.2,5.87v4.43H169.85v5.13h11.4v4.42h-11.4V26.9h13v4.42H164.51V7.69Z"/><path class="cls-1" d="M197.49,12.15a7.11,7.11,0,0,0-7.19,7.26,7.14,7.14,0,0,0,7.19,7.29,9.1,9.1,0,0,0,6.14-2.77l3.14,3.41a13.85,13.85,0,0,1-9.55,4.21,12,12,0,0,1-12.39-12.08c0-6.81,5.44-11.94,12.59-11.94a13.44,13.44,0,0,1,9.32,3.91l-3.11,3.78a8.54,8.54,0,0,0-6.14-3.07"/><polygon class="cls-1" points="214.9 7.7 214.9 17.55 225.6 17.55 225.6 7.7 230.93 7.7 230.93 31.32 225.6 31.32 225.6 22 214.9 22 214.9 31.32 209.57 31.32 209.57 7.7 214.9 7.7"/><polygon class="cls-1" points="252.36 7.7 252.36 12.12 239.7 12.12 239.7 17.25 251.11 17.25 251.11 21.67 239.7 21.67 239.7 26.9 252.73 26.9 252.73 31.32 234.37 31.32 234.37 7.7 252.36 7.7"/><polygon class="cls-1" points="260.6 7.7 272.07 22.88 272.07 7.7 277.13 7.7 277.13 31.32 272.27 31.32 260.83 16.17 260.83 31.32 255.74 31.32 255.74 7.7 260.6 7.7"/><polygon class="cls-1" points="293.32 23.69 293.32 31.32 287.99 31.32 287.99 23.83 279.25 7.7 284.61 7.7 290.65 18.29 296.56 7.7 301.92 7.7 293.32 23.69"/><rect class="cls-1" x="304" y="7.7" width="5.33" height="23.62"/><polygon class="cls-1" points="138.4 37.79 138.4 42.21 125.75 42.21 125.75 47.34 137.16 47.34 137.16 51.77 125.75 51.77 125.75 57 138.78 57 138.78 61.42 120.42 61.42 120.42 37.79 138.4 37.79"/><path class="cls-1" d="M157.34,49.5H162v9.08a17.43,17.43,0,0,1-9.58,3.07c-7.16,0-12.59-5.19-12.59-12s5.53-12,12.89-12a14.88,14.88,0,0,1,9.48,3.44l-3,3.81a9.88,9.88,0,0,0-6.48-2.63,7.38,7.38,0,1,0,0,14.75,10.74,10.74,0,0,0,4.63-1.29Z"/><polygon class="cls-1" points="177.42 53.79 177.42 61.42 172.09 61.42 172.09 53.92 163.35 37.79 168.71 37.79 174.75 48.39 180.66 37.79 186.03 37.79 177.42 53.79"/><polygon class="cls-1" points="206.14 37.79 206.14 42.21 193.48 42.21 193.48 47.34 204.89 47.34 204.89 51.77 193.48 51.77 193.48 57 206.51 57 206.51 61.42 188.15 61.42 188.15 37.79 206.14 37.79"/><polygon class="cls-1" points="228.25 37.79 228.25 42.31 221.02 42.31 221.02 61.42 215.69 61.42 215.69 42.31 208.5 42.31 208.5 37.79 228.25 37.79"/><polygon class="cls-1" points="248.7 37.79 248.7 42.21 236.04 42.21 236.04 47.34 247.45 47.34 247.45 51.77 236.04 51.77 236.04 57 249.07 57 249.07 61.42 230.71 61.42 230.71 37.79 248.7 37.79"/><polygon class="cls-1" points="257.44 37.79 264.32 52.24 271.18 37.79 277.12 37.79 277.12 61.42 272.32 61.42 272.32 45.49 266.05 59.02 262.57 59.02 256.29 45.49 256.29 61.42 251.47 61.42 251.47 37.79 257.44 37.79"/><rect class="cls-1" y="76.44" width="64.54" height="4.32"/><rect class="cls-1" y="59.23" width="64.54" height="4.3"/><rect class="cls-2" x="17.21" y="67.84" width="47.33" height="4.3"/><path class="cls-1" d="M17.21,50.63v4.3A47.34,47.34,0,0,0,64.55,7.6H60.24a43,43,0,0,1-43,43"/><path class="cls-1" d="M17.21,42v4.3A38.72,38.72,0,0,0,55.94,7.6H51.63A34.42,34.42,0,0,1,17.21,42"/><rect class="cls-1" x="73.15" y="76.44" width="17.21" height="4.32"/><rect class="cls-1" x="73.15" y="67.84" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="59.23" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="50.63" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="42.02" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="33.42" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="24.81" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="16.21" width="17.21" height="4.3"/><rect class="cls-1" x="73.15" y="7.6" width="17.21" height="4.3"/><rect class="cls-1" x="292.39" y="76.46" width="17.2" height="4.3"/></g></g></svg>', // Ezt írd át a ZIP készítése előtt!
        'main_text'          => '<h1>Karbantartás alatt</h1><p>Az oldal jelenleg fejlesztés alatt áll. Kérjük, látogasson vissza később!</p>',
        'main_text_en'       => '<h1>Under Maintenance</h1><p>The site is currently under development. Please check back later!</p>',
        'specific_urls'      => '',
        'links'              => array()
    );
    
    add_option( 'cmm_settings', $default_options );
}
register_activation_hook( __FILE__, 'cmm_activate_plugin' );

// Fő fájl betöltése és inicializálás
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cmm-core.php';

add_action( 'plugins_loaded', 'cmm_init_plugin' );
function cmm_init_plugin() {
    $core = new CMM_Core();
    $core->init();
}
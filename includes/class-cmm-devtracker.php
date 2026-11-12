<?php
/**
 * Devtracker-jelentő: naponta egyszer, oldalanként összesített számokat küld a devtracker backendnek.
 *
 * Két dolgot számol: hány különböző tartalmat módosított az engedélyezett csapat aznap,
 * és hány frissítés (plugin / téma / mag) történt aznap.
 *
 * Konfiguráció nélkül (DEVTRACKER_URL + DEVTRACKER_KEY, vagy cmm_dt_url + cmm_dt_key opció)
 * az egész funkció inaktív: nem regisztrál horgot, nem ütemez eseményt, nem ír opciót.
 *
 * @package custom-maintenance-mode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Devtracker-jelentő.
 */
class CMM_Devtracker {

	const SCHEMA            = 1;
	const TIMEZONE          = 'Europe/Budapest';
	const ENDPOINT_PATH     = '/api/v1/wp/report';
	const MAX_PENDING_DAYS  = 60;
	const MAX_RESPONSE_SIZE = 65536;
	const MAX_USERS         = 200;
	const HTTP_TIMEOUT      = 10;
	const CATCHUP_MIN_AGE   = 72000; // 20 óra.
	const CATCHUP_INTERVAL  = 21600; // 6 óra.
	const LOCK_TTL          = 120;
	const RETRY_DELAYS      = array( 900, 3600, 21600 ); // 15 perc, 1 óra, 6 óra.

	const BUCKETS      = array( 'page', 'post', 'template', 'menu', 'other' );
	const UPDATE_TYPES = array( 'plugin', 'theme', 'core' );

	const CRON_DAILY    = 'cmm_dt_daily';
	const CRON_REGISTER = 'cmm_dt_register';
	const CRON_RETRY    = 'cmm_dt_retry';

	const OPT_URL       = 'cmm_dt_url';
	const OPT_KEY       = 'cmm_dt_key';
	const OPT_SITE_UUID = 'cmm_dt_site_uuid';
	const OPT_USERS     = 'cmm_dt_wp_users';
	const OPT_VERSIONS  = 'cmm_dt_versions';
	const OPT_STATE     = 'cmm_dt_state';
	const OPT_STATUS    = 'cmm_dt_status';

	const TRANSIENT_VCHECK  = 'cmm_dt_vcheck';
	const TRANSIENT_CATCHUP = 'cmm_dt_catchup';
	const TRANSIENT_LOCK    = 'cmm_dt_lock';

	/**
	 * Horgok regisztrálása. Konfiguráció nélkül semmit nem csinál.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! self::is_configured() ) {
			return;
		}

		// Tartalom: mindkét horog ugyanazt hívja, a duplikációt az azonosítóhalmaz szűri ki.
		add_action( 'wp_after_insert_post', array( $this, 'on_after_insert_post' ), 10, 1 );
		add_action( 'elementor/document/after_save', array( $this, 'on_elementor_after_save' ), 10, 1 );
		add_action( 'wp_update_nav_menu', array( $this, 'on_update_nav_menu' ), 10, 1 );

		// Frissítések: verzió-különbség alapján, a horog csak a gyors észlelést szolgálja.
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 10, 0 );
		add_action( 'init', array( $this, 'on_init' ) );

		// Küldés.
		add_action( self::CRON_DAILY, array( $this, 'cron_daily' ) );
		add_action( self::CRON_REGISTER, array( $this, 'cron_register' ) );
		add_action( self::CRON_RETRY, array( $this, 'cron_retry' ) );
		add_action( 'admin_init', array( $this, 'maybe_catch_up' ) );
		add_action( 'admin_post_cmm_dt_send_now', array( $this, 'handle_send_now' ) );
	}

	/**
	 * Aktiváláskor: bejelentkezés ütemezése, ha van konfiguráció.
	 *
	 * @return void
	 */
	public static function on_activation() {
		if ( ! self::is_configured() ) {
			return;
		}
		self::ensure_daily_scheduled();
		self::schedule_registration();
	}

	/**
	 * Kikapcsoláskor: az ütemezett események törlése (opciót nem érint).
	 *
	 * @return void
	 */
	public static function on_deactivation() {
		wp_clear_scheduled_hook( self::CRON_DAILY );
		wp_clear_scheduled_hook( self::CRON_REGISTER );
		wp_clear_scheduled_hook( self::CRON_RETRY );
	}

	// ------------------------------------------------------------------
	// Konfiguráció.
	// ------------------------------------------------------------------

	/**
	 * A backend alap-URL-je. Csak https, fejlesztéshez http://localhost. Érvénytelen esetén üres.
	 *
	 * @return string
	 */
	public static function get_url() {
		$url = '';
		if ( defined( 'DEVTRACKER_URL' ) && is_string( constant( 'DEVTRACKER_URL' ) ) ) {
			$url = trim( constant( 'DEVTRACKER_URL' ) );
		}
		if ( '' === $url ) {
			$url = trim( (string) get_option( self::OPT_URL, '' ) );
		}
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme = strtolower( $parts['scheme'] );
		$host   = strtolower( $parts['host'] );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && 'localhost' === $host ) ) {
			return '';
		}
		return untrailingslashit( $url );
	}

	/**
	 * A backend kulcsa. Sehová nem íródik ki (se logba, se admin felületre).
	 *
	 * @return string
	 */
	private static function get_key() {
		$key = '';
		if ( defined( 'DEVTRACKER_KEY' ) && is_string( constant( 'DEVTRACKER_KEY' ) ) ) {
			$key = trim( constant( 'DEVTRACKER_KEY' ) );
		}
		if ( '' === $key ) {
			$key = trim( (string) get_option( self::OPT_KEY, '' ) );
		}
		return $key;
	}

	/**
	 * Be van-e kötve (mindkét érték megvan és az URL érvényes).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_url() && '' !== self::get_key();
	}

	/**
	 * Az oldal stabil azonosítója (véletlen UUID). Az első szükséges pillanatban jön létre, és soha nem íródik felül.
	 *
	 * @return string
	 */
	public static function get_site_uuid() {
		$uuid = get_option( self::OPT_SITE_UUID, '' );
		if ( is_string( $uuid ) && '' !== $uuid ) {
			return $uuid;
		}
		$uuid = wp_generate_uuid4();
		// Az add_option nem írja felül a már létezőt (párhuzamos kérésnél sem), ezért nem update_option.
		if ( ! add_option( self::OPT_SITE_UUID, $uuid, '', false ) ) {
			$existing = get_option( self::OPT_SITE_UUID, '' );
			if ( is_string( $existing ) && '' !== $existing ) {
				return $existing;
			}
		}
		return $uuid;
	}

	/**
	 * Az engedélyezett felhasználónevek (kisbetűs lista). A DEVTRACKER_USERS konstans felülírja az opciót.
	 *
	 * @return string[]
	 */
	private static function get_allowed_users() {
		if ( defined( 'DEVTRACKER_USERS' ) && is_string( constant( 'DEVTRACKER_USERS' ) ) && '' !== trim( constant( 'DEVTRACKER_USERS' ) ) ) {
			return self::sanitize_users( explode( ',', constant( 'DEVTRACKER_USERS' ) ) );
		}
		$list = get_option( self::OPT_USERS, array() );
		return self::sanitize_users( is_array( $list ) ? $list : array() );
	}

	/**
	 * Felhasználónév-lista tisztítása: sanitize_user, kisbetű, üres elemek nélkül, legfeljebb 200 elem.
	 *
	 * @param array $items Nyers lista.
	 * @return string[]
	 */
	private static function sanitize_users( array $items ) {
		$clean = array();
		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$login = strtolower( trim( sanitize_user( $item ) ) );
			if ( '' !== $login ) {
				$clean[ $login ] = $login;
			}
		}
		return array_slice( array_values( $clean ), 0, self::MAX_USERS );
	}

	// ------------------------------------------------------------------
	// Idő és tárolás.
	// ------------------------------------------------------------------

	/**
	 * A mai nap (Y-m-d) Budapesti idő szerint, a WP-oldal saját időzónájától függetlenül.
	 *
	 * @return string
	 */
	private static function today() {
		return (string) wp_date( 'Y-m-d', time(), new DateTimeZone( self::TIMEZONE ) );
	}

	/**
	 * Egy nap üres szerkezete.
	 *
	 * @return array
	 */
	private static function empty_day() {
		return array(
			'ids'     => array(),
			'content' => array_fill_keys( self::BUCKETS, 0 ),
			'updates' => array_fill_keys( self::UPDATE_TYPES, 0 ),
		);
	}

	/**
	 * Az állapot betöltése és rendbetétele: a lezárt napokról az azonosítók eldobása (csak a számok maradnak),
	 * legfeljebb 60 függő nap megtartása.
	 *
	 * @param string $today A mai nap (Y-m-d).
	 * @return array array( 'days' => array( 'Y-m-d' => nap ) ).
	 */
	private static function load_state( $today ) {
		$raw  = get_option( self::OPT_STATE, array() );
		$days = ( is_array( $raw ) && isset( $raw['days'] ) && is_array( $raw['days'] ) ) ? $raw['days'] : array();

		$clean = array();
		foreach ( $days as $date => $day ) {
			$date = (string) $date;
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! is_array( $day ) ) {
				continue;
			}
			$closed = $date < $today; // ISO dátum: a szöveges összehasonlítás időrendi.
			$out    = self::empty_day();
			foreach ( self::BUCKETS as $bucket ) {
				$ids = ( isset( $day['ids'][ $bucket ] ) && is_array( $day['ids'][ $bucket ] ) ) ? $day['ids'][ $bucket ] : array();
				$cnt = isset( $day['content'][ $bucket ] ) ? max( 0, (int) $day['content'][ $bucket ] ) : 0;
				if ( $closed ) {
					$out['content'][ $bucket ] = max( $cnt, count( $ids ) );
				} else {
					$out['ids'][ $bucket ]     = $ids;
					$out['content'][ $bucket ] = $cnt;
				}
			}
			foreach ( self::UPDATE_TYPES as $type ) {
				$out['updates'][ $type ] = isset( $day['updates'][ $type ] ) ? max( 0, (int) $day['updates'][ $type ] ) : 0;
			}
			$clean[ $date ] = $out;
		}
		ksort( $clean );

		// A régebbi, 60 napnál is korábbi függő napokat eldobjuk.
		$closed_dates = array();
		foreach ( array_keys( $clean ) as $date ) {
			if ( (string) $date < $today ) {
				$closed_dates[] = (string) $date;
			}
		}
		$excess = count( $closed_dates ) - self::MAX_PENDING_DAYS;
		for ( $i = 0; $i < $excess; $i++ ) {
			unset( $clean[ $closed_dates[ $i ] ] );
		}

		return array( 'days' => $clean );
	}

	/**
	 * Az állapot mentése (autoload kikapcsolva).
	 *
	 * @param array $state Az állapot.
	 * @return void
	 */
	private static function save_state( array $state ) {
		update_option( self::OPT_STATE, $state, false );
	}

	/**
	 * Az állapot (küldési státusz) alapértékekkel.
	 *
	 * @return array
	 */
	private static function get_status_data() {
		$status = get_option( self::OPT_STATUS, array() );
		$status = is_array( $status ) ? $status : array();
		return array_merge(
			array(
				'last_success'    => 0,
				'last_attempt'    => 0,
				'last_error'      => '',
				'last_error_time' => 0,
				'retry_step'      => 0,
				'reg_scheduled'   => 0,
			),
			$status
		);
	}

	/**
	 * A státusz módosítása.
	 *
	 * @param array $changes Módosítandó mezők.
	 * @return void
	 */
	private static function update_status( array $changes ) {
		update_option( self::OPT_STATUS, array_merge( self::get_status_data(), $changes ), false );
	}

	/**
	 * Hibaszöveg tisztítása: a kulcs és a backend címe nem szivároghat ki (admin felületre sem).
	 *
	 * @param string $text Nyers szöveg.
	 * @return string
	 */
	private static function scrub( $text ) {
		$text   = (string) $text;
		$secret = array( self::get_key(), self::get_url() );
		$host   = wp_parse_url( self::get_url(), PHP_URL_HOST );
		if ( is_string( $host ) ) {
			$secret[] = $host;
		}
		foreach ( $secret as $value ) {
			if ( '' !== $value ) {
				$text = str_replace( $value, '***', $text );
			}
		}
		return substr( wp_strip_all_tags( $text ), 0, 200 );
	}

	// ------------------------------------------------------------------
	// Tartalommódosítások.
	// ------------------------------------------------------------------

	/**
	 * Horog: bejegyzés létrehozása / módosítása.
	 *
	 * @param int $post_id A bejegyzés azonosítója.
	 * @return void
	 */
	public function on_after_insert_post( $post_id ) {
		$this->record_content_change( (int) $post_id );
	}

	/**
	 * Horog: Elementor mentés. Biztonsági háló arra az esetre, ha egy mentés nem hívna wp_update_post-ot.
	 *
	 * @param object $document Az Elementor dokumentum.
	 * @return void
	 */
	public function on_elementor_after_save( $document ) {
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			$this->record_content_change( (int) $document->get_main_id() );
		}
	}

	/**
	 * Horog: menü mentése (egy menümentés = 1 menü).
	 *
	 * @param int $menu_id A menü azonosítója.
	 * @return void
	 */
	public function on_update_nav_menu( $menu_id ) {
		if ( ! $this->is_tracked_user() ) {
			return;
		}
		$this->add_content_id( 'menu', (int) $menu_id, 'm' );
	}

	/**
	 * Az éppen bejelentkezett felhasználó az engedélyezett listán van-e.
	 * A "ki módosított" a mentés pillanatában bejelentkezett felhasználó, nem a szerző.
	 * Cron / WP-CLI / rendszerfolyamat (nincs felhasználó) nem számít, üres lista esetén senki.
	 *
	 * @return bool
	 */
	private function is_tracked_user() {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return false;
		}
		$allowed = self::get_allowed_users();
		if ( empty( $allowed ) ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return in_array( strtolower( $user->user_login ), $allowed, true );
	}

	/**
	 * A kizárt post type-ok (szűrővel módosítható).
	 *
	 * @return string[]
	 */
	private function excluded_post_types() {
		$default  = array( 'revision', 'nav_menu_item', 'attachment', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'user_request' );
		$filtered = apply_filters( 'cmm_dt_excluded_post_types', $default );
		return is_array( $filtered ) ? array_map( 'strval', $filtered ) : $default;
	}

	/**
	 * Egy bejegyzés módosításának rögzítése (a különböző azonosítók halmazába).
	 *
	 * @param int $post_id A bejegyzés azonosítója.
	 * @return void
	 */
	private function record_content_change( $post_id ) {
		if ( $post_id < 1 || ! $this->is_tracked_user() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$type = $post->post_type;
		if ( in_array( $type, $this->excluded_post_types(), true ) ) {
			return;
		}
		if ( 'page' === $type ) {
			$bucket = 'page';
		} elseif ( 'post' === $type ) {
			$bucket = 'post';
		} elseif ( in_array( $type, array( 'elementor_library', 'wp_template', 'wp_template_part' ), true ) ) {
			$bucket = 'template';
		} elseif ( 'wp_navigation' === $type ) {
			// A blokkos (FSE) navigáció is menü, ugyanúgy, mint a klasszikus nav menü.
			$bucket = 'menu';
		} else {
			$bucket = 'other';
		}

		// A show_ui-kizárás a sablon-típusokra nem vonatkozik: a wp_template és wp_template_part
		// show_ui értéke WP-ben hamis, mégis valódi, a csapat által szerkesztett tartalom.
		if ( 'template' !== $bucket ) {
			$type_object = get_post_type_object( $type );
			if ( ! $type_object || empty( $type_object->show_ui ) ) {
				return;
			}
		}
		$this->add_content_id( $bucket, $post_id, 'menu' === $bucket ? 'n' : '' );
	}

	/**
	 * Azonosító hozzáadása a mai nap vödréhez. Ha már benne van, nincs írás (az Elementor sokat ment).
	 *
	 * @param string $bucket Vödör.
	 * @param int    $id     Azonosító.
	 * @param string $prefix Kulcs-előtag. A menü vödörben két azonosító-tér találkozik (nav menü term és
	 *                       wp_navigation bejegyzés), az előtag nélkül egy 5-ös term és egy 5-ös post összeolvadna.
	 * @return void
	 */
	private function add_content_id( $bucket, $id, $prefix = '' ) {
		if ( $id < 1 || ! in_array( $bucket, self::BUCKETS, true ) ) {
			return;
		}
		$key   = '' === $prefix ? $id : $prefix . $id;
		$today = self::today();
		$state = self::load_state( $today );
		if ( ! isset( $state['days'][ $today ] ) ) {
			$state['days'][ $today ] = self::empty_day();
		}
		if ( isset( $state['days'][ $today ]['ids'][ $bucket ][ $key ] ) ) {
			return;
		}
		$state['days'][ $today ]['ids'][ $bucket ][ $key ] = 1;
		self::save_state( $state );
	}

	// ------------------------------------------------------------------
	// Frissítések: verzió-különbség.
	// ------------------------------------------------------------------

	/**
	 * Horog: frissítés után azonnali ellenőrzés.
	 *
	 * @return void
	 */
	public function on_upgrader_complete() {
		self::load_plugin_api();
		// A frissítés után a get_plugins() / wp_get_themes() gyorsítótára elavult, különben a régi verziót látnánk.
		wp_clean_plugins_cache( false );
		wp_clean_themes_cache( false );
		// Magfrissítésnél az új verzió csak a következő kérésben látszik, ezért az óránkénti kapu ne tartsa vissza.
		delete_transient( self::TRANSIENT_VCHECK );
		$this->check_versions();
	}

	/**
	 * Horog: init. Legfeljebb óránként ellenőrzi a verziókat; az ütemezést minden kérésnél önjavítóan rendben tartja.
	 *
	 * @return void
	 */
	public function on_init() {
		self::ensure_daily_scheduled();

		if ( false !== get_transient( self::TRANSIENT_VCHECK ) ) {
			return;
		}
		set_transient( self::TRANSIENT_VCHECK, 1, HOUR_IN_SECONDS );
		$this->check_versions();

		// Meglévő oldalon az 1.2.0-ra frissítés után az első konfigurált pillanatban bejelentkezés.
		$status = self::get_status_data();
		if ( empty( $status['reg_scheduled'] ) ) {
			self::schedule_registration();
		}
	}

	/**
	 * A wp-admin plugin API betöltése, ha még nincs (get_plugins, wp_clean_plugins_cache).
	 *
	 * @return void
	 */
	private static function load_plugin_api() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * A telepített verziók összehasonlítása a mentett pillanatképpel; a növekedés a mai nap frissítés-számlálója.
	 *
	 * Az elv: nem az számít, hogy lefutott-e egy horog, hanem hogy a verziószám megnőtt, így mindegy, ki indította
	 * a frissítést (MainWP, WP-CLI, automatikus, kézi), és a három hívási hely (horog / óránkénti init / napi cron)
	 * ugyanazt a pillanatképet hasonlítja, tehát nincs kettős számolás.
	 *
	 * Első futás (nincs pillanatkép): csak alapállapotot ment. Új elem: csak felveszi. Eltűnt elem: kiveszi.
	 * Visszalépés: nem számol, csak frissíti a pillanatképet.
	 *
	 * @return void
	 */
	public function check_versions() {
		if ( ! self::is_configured() ) {
			return;
		}
		self::load_plugin_api();

		$current = array(
			'plugins' => array(),
			'themes'  => array(),
			'core'    => (string) get_bloginfo( 'version' ),
		);
		foreach ( get_plugins() as $file => $data ) {
			$current['plugins'][ (string) $file ] = isset( $data['Version'] ) ? (string) $data['Version'] : '';
		}
		foreach ( wp_get_themes() as $slug => $theme ) {
			$current['themes'][ (string) $slug ] = (string) $theme->get( 'Version' );
		}

		$saved = get_option( self::OPT_VERSIONS, null );
		if ( ! is_array( $saved ) || ! isset( $saved['plugins'], $saved['themes'], $saved['core'] ) || ! is_array( $saved['plugins'] ) || ! is_array( $saved['themes'] ) ) {
			update_option( self::OPT_VERSIONS, $current, false );
			return;
		}

		$counts = array_fill_keys( self::UPDATE_TYPES, 0 );
		foreach ( $current['plugins'] as $file => $version ) {
			if ( isset( $saved['plugins'][ $file ] ) && version_compare( $version, (string) $saved['plugins'][ $file ], '>' ) ) {
				++$counts['plugin'];
			}
		}
		foreach ( $current['themes'] as $slug => $version ) {
			if ( isset( $saved['themes'][ $slug ] ) && version_compare( $version, (string) $saved['themes'][ $slug ], '>' ) ) {
				++$counts['theme'];
			}
		}
		if ( version_compare( $current['core'], (string) $saved['core'], '>' ) ) {
			++$counts['core'];
		}

		if ( $current === $saved ) {
			return;
		}
		// Előbb a pillanatkép, utána a számláló: így egy közben érkező második ellenőrzés már nem számolja újra.
		update_option( self::OPT_VERSIONS, $current, false );

		if ( array_sum( $counts ) > 0 ) {
			$today = self::today();
			$state = self::load_state( $today );
			if ( ! isset( $state['days'][ $today ] ) ) {
				$state['days'][ $today ] = self::empty_day();
			}
			foreach ( $counts as $type => $n ) {
				$state['days'][ $today ]['updates'][ $type ] += $n;
			}
			self::save_state( $state );
		}
	}

	// ------------------------------------------------------------------
	// Ütemezés.
	// ------------------------------------------------------------------

	/**
	 * A napi cron első időpontja: következő éjfél után pár perccel (Budapest), oldalanként eltolva,
	 * hogy az oldalak ne egyszerre terheljék a backendet.
	 *
	 * @return int
	 */
	private static function next_daily_timestamp() {
		$next   = new DateTime( 'tomorrow 00:05', new DateTimeZone( self::TIMEZONE ) );
		$offset = crc32( self::get_site_uuid() ) % 3000;
		return $next->getTimestamp() + $offset;
	}

	/**
	 * A napi cron önjavító ütemezése: a meglévő oldalakon az aktiválási horog nem fut újra.
	 *
	 * @return void
	 */
	private static function ensure_daily_scheduled() {
		if ( ! wp_next_scheduled( self::CRON_DAILY ) ) {
			wp_schedule_event( self::next_daily_timestamp(), 'daily', self::CRON_DAILY );
		}
	}

	/**
	 * Bejelentkezés ütemezése 60 másodperc múlvá, hogy ne a kérés közben menjen.
	 *
	 * @return void
	 */
	private static function schedule_registration() {
		wp_schedule_single_event( time() + 60, self::CRON_REGISTER );
		self::update_status( array( 'reg_scheduled' => 1 ) );
	}

	/**
	 * Napi cron.
	 *
	 * @return void
	 */
	public function cron_daily() {
		$this->check_versions();
		$this->send_report( true );
	}

	/**
	 * Egyszeri bejelentkezés.
	 *
	 * @return void
	 */
	public function cron_register() {
		$this->send_report( true );
	}

	/**
	 * Újrapróbálkozás hiba után (a láncot nem indítja újra).
	 *
	 * @return void
	 */
	public function cron_retry() {
		$this->send_report( false );
	}

	/**
	 * Opportunista pótlás az adminban: a kevés látogatójú oldalakon a WP-Cron megbízhatatlan,
	 * a szerkesztők viszont az adminban dolgoznak. Legfeljebb 6 óránként értékeli ki.
	 *
	 * @return void
	 */
	public function maybe_catch_up() {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( false !== get_transient( self::TRANSIENT_CATCHUP ) ) {
			return;
		}
		set_transient( self::TRANSIENT_CATCHUP, 1, self::CATCHUP_INTERVAL );

		$status = self::get_status_data();
		if ( time() - (int) $status['last_success'] <= self::CATCHUP_MIN_AGE ) {
			return;
		}
		if ( self::count_pending() < 1 ) {
			return;
		}
		// A kérés végén küldünk, hogy az admin oldal ne várjon a 10 s-os időkorlátra.
		add_action( 'shutdown', array( $this, 'catch_up_send' ) );
	}

	/**
	 * A pótló küldés (a kérés végén).
	 *
	 * @return void
	 */
	public function catch_up_send() {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		$this->send_report( true );
	}

	/**
	 * Admin-post kezelő: "Küldés most" gomb.
	 *
	 * @return void
	 */
	public function handle_send_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nincs jogosultságod ehhez a művelethez.' );
		}
		check_admin_referer( 'cmm_dt_send_now' );

		$ok = $this->send_report( true );
		wp_safe_redirect( add_query_arg( 'cmm_dt', $ok ? 'ok' : 'fail', admin_url( 'options-general.php?page=custom-maintenance-mode' ) ) );
		exit;
	}

	// ------------------------------------------------------------------
	// Küldés.
	// ------------------------------------------------------------------

	/**
	 * Függő (lezárt, még el nem küldött) napok száma. Csak olvas.
	 *
	 * @return int
	 */
	public static function count_pending() {
		$today = self::today();
		$state = self::load_state( $today );
		$n     = 0;
		foreach ( array_keys( $state['days'] ) as $date ) {
			if ( (string) $date < $today ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * A jelentés elküldése. Párhuzamos küldést a zár kizár.
	 *
	 * @param bool $new_chain Új újrapróbálkozási lánc indul (napi / kézi / bejelentkezés), nem a lánc folytatása.
	 * @return bool Sikerült-e.
	 */
	private function send_report( $new_chain ) {
		if ( ! self::is_configured() ) {
			return false;
		}
		if ( false !== get_transient( self::TRANSIENT_LOCK ) ) {
			return false;
		}
		set_transient( self::TRANSIENT_LOCK, 1, self::LOCK_TTL );
		$ok = $this->do_send( $new_chain );
		delete_transient( self::TRANSIENT_LOCK );
		return $ok;
	}

	/**
	 * A tényleges küldés.
	 *
	 * Adatvédelem: a jelentésbe csak a számok és az oldal nyilvános adatai kerülnek, sosem felhasználónév,
	 * e-mail, bejegyzés-azonosító, cím, URL, tartalom vagy IP.
	 *
	 * @param bool $new_chain Új újrapróbálkozási lánc indul.
	 * @return bool
	 */
	private function do_send( $new_chain ) {
		$status = self::get_status_data();
		$step   = $new_chain ? 0 : (int) $status['retry_step'];
		if ( $new_chain ) {
			// Ez a próbálkozás felváltja a függőben lévő újrapróbát.
			wp_clear_scheduled_hook( self::CRON_RETRY );
		}

		$today      = self::today();
		$state      = self::load_state( $today );
		$days       = array();
		$sent_dates = array();
		foreach ( $state['days'] as $date => $day ) {
			$date = (string) $date;
			if ( $date >= $today ) {
				continue; // Csak lezárt nap mehet.
			}
			$days[]       = array(
				'date'    => $date,
				'content' => $day['content'],
				'updates' => $day['updates'],
			);
			$sent_dates[] = $date;
		}

		$payload = array(
			'schema'         => self::SCHEMA,
			'site_uuid'      => self::get_site_uuid(),
			'home_url'       => home_url(),
			'site_name'      => get_bloginfo( 'name' ),
			'plugin_version' => defined( 'CMM_VERSION' ) ? CMM_VERSION : '',
			'wp_version'     => get_bloginfo( 'version' ),
			'days'           => $days,
		);

		$response = wp_remote_post(
			self::get_url() . self::ENDPOINT_PATH,
			array(
				'timeout'             => self::HTTP_TIMEOUT,
				// Átirányítás követése nélkül: a Bearer kulcs nem mehet át egy másik címre.
				'redirection'         => 0,
				'limit_response_size' => self::MAX_RESPONSE_SIZE,
				'headers'             => array(
					'Authorization' => 'Bearer ' . self::get_key(),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'                => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->fail( self::scrub( $response->get_error_message() ), $step );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return $this->fail( 'HTTP ' . $code, $step );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['ok'] ) || true !== $data['ok'] ) {
			return $this->fail( 'Érvénytelen válasz', $step );
		}

		// Csak a küldött napok közül visszaigazoltakat töröljük.
		$accepted = array();
		if ( isset( $data['accepted_dates'] ) && is_array( $data['accepted_dates'] ) ) {
			foreach ( $data['accepted_dates'] as $date ) {
				if ( is_string( $date ) && in_array( $date, $sent_dates, true ) ) {
					$accepted[] = $date;
				}
			}
		}
		if ( isset( $data['config']['wp_users'] ) && is_array( $data['config']['wp_users'] ) ) {
			update_option( self::OPT_USERS, self::sanitize_users( $data['config']['wp_users'] ), false );
		}

		// A küldés alatt változhatott az állapot, ezért a törlés előtt újra betöltjük.
		$state = self::load_state( self::today() );
		foreach ( $accepted as $date ) {
			unset( $state['days'][ $date ] );
		}
		self::save_state( $state );

		wp_clear_scheduled_hook( self::CRON_RETRY );
		self::update_status(
			array(
				'last_success'  => time(),
				'last_attempt'  => time(),
				'last_error'    => '',
				'retry_step'    => 0,
				'reg_scheduled' => 1,
			)
		);
		return true;
	}

	/**
	 * Hiba rögzítése és legfeljebb három újrapróbálkozás (15 perc, 1 óra, 6 óra) ütemezése.
	 * Utána a következő napi futásra vár; a függő napokat nem veszíti el, és nem hívja magát hurokban.
	 *
	 * @param string $message Rövid, a kulcsot nem tartalmazó hibaszöveg.
	 * @param int    $step    Eddigi újrapróbák száma.
	 * @return bool Mindig false.
	 */
	private function fail( $message, $step ) {
		if ( $step < count( self::RETRY_DELAYS ) ) {
			wp_schedule_single_event( time() + self::RETRY_DELAYS[ $step ], self::CRON_RETRY );
			++$step;
		}
		self::update_status(
			array(
				'last_attempt'    => time(),
				'last_error'      => $message,
				'last_error_time' => time(),
				'retry_step'      => $step,
			)
		);
		return false;
	}

	// ------------------------------------------------------------------
	// Admin felület (csak olvasható blokk).
	// ------------------------------------------------------------------

	/**
	 * A "Tracker" blokk a Beállítások → Karbantartás oldalon. A kulcsot nem jeleníti meg és nem is szerkeszthető.
	 *
	 * @return void
	 */
	public static function render_admin_block() {
		$configured = self::is_configured();
		$status     = self::get_status_data();
		$flag       = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- csak megjelenítési jelző, nem módosít semmit.
		if ( isset( $_GET['cmm_dt'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$flag = sanitize_key( wp_unslash( $_GET['cmm_dt'] ) );
		}
		?>
		<h2>Tracker</h2>
		<?php if ( 'ok' === $flag ) : ?>
			<div class="notice notice-success inline"><p>A jelentés elküldve.</p></div>
		<?php elseif ( 'fail' === $flag ) : ?>
			<div class="notice notice-error inline"><p>A küldés nem sikerült (vagy már folyamatban van egy küldés). Részletek lent.</p></div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Állapot</th>
				<td><?php echo $configured ? 'bekötve' : 'nincs bekötve'; ?></td>
			</tr>
			<?php if ( $configured ) : ?>
				<tr>
					<th scope="row">Utolsó sikeres küldés</th>
					<td><?php echo ! empty( $status['last_success'] ) ? esc_html( wp_date( 'Y-m-d H:i', (int) $status['last_success'] ) ) : 'még nem volt'; ?></td>
				</tr>
				<tr>
					<th scope="row">Függő napok</th>
					<td><?php echo esc_html( (string) self::count_pending() ); ?></td>
				</tr>
				<tr>
					<th scope="row">Utolsó hiba</th>
					<td>
						<?php
						if ( '' !== (string) $status['last_error'] ) {
							echo esc_html( (string) $status['last_error'] );
							if ( ! empty( $status['last_error_time'] ) ) {
								echo ' (' . esc_html( wp_date( 'Y-m-d H:i', (int) $status['last_error_time'] ) ) . ')';
							}
						} else {
							echo '&ndash;';
						}
						?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php if ( $configured ) : ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="cmm_dt_send_now" />
				<?php wp_nonce_field( 'cmm_dt_send_now' ); ?>
				<?php submit_button( 'Küldés most', 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}
}

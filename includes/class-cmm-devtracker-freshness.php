<?php
/**
 * Devtracker: oldal-frissesség ("site-entrópia") pillanatkép és a helyi "Elavult oldalak" lista.
 *
 * Külön osztály, hogy a jelentő (CMM_Devtracker) ne nőjön tovább. A jelentés és az admin lista ugyanazt a mérési
 * halmazt és ugyanazt a küszöböt használja (egy függvényből), így a két szám nem térhet el egymástól.
 *
 * Adatvédelem: a pillanatkép csak egy dátumot és két darabszámot ad. A konkrét elavult oldalak listája kizárólag
 * a WordPress adminban jelenik meg, a jelentésbe soha nem kerül.
 *
 * @package custom-maintenance-mode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Oldal-frissesség.
 */
class CMM_Devtracker_Freshness {

	// Egyetlen helyen, hogy régi backenddel (ami nem küld küszöböt) és új pluginnal is működjön.
	const DEFAULT_STALE_DAYS = 90;
	const MIN_STALE_DAYS     = 30;
	const MAX_STALE_DAYS     = 365;
	const MAX_LIST_ROWS      = 200;

	const OPT_STALE_DAYS = 'cmm_dt_stale_days';

	// A régi, hibás sorok nullás dátumát ("0000-00-00") nem hasonlítjuk össze közvetlenül (NO_ZERO_DATE módban hibát
	// adhat), ezért minden "van érvényes dátum" vizsgálat ehhez a valós dátumhoz viszonyít.
	const EPOCH = '1970-01-01 00:00:00';

	/**
	 * A mérési halmaz post type-jai (szűrővel módosítható). A számolás és a helyi lista is ezt használja.
	 *
	 * Szándékosan nincs benne az egyéni típus (pl. kurzus): a tömeges mentésük bármit frissnek mutatna (ADR-0015 D8),
	 * és nincs benne menü, média, revízió, automatikus mentés sem.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$default  = array( 'page', 'post', 'elementor_library', 'wp_template', 'wp_template_part' );
		$filtered = apply_filters( 'cmm_dt_freshness_post_types', $default );
		if ( ! is_array( $filtered ) ) {
			return $default;
		}
		$clean = array();
		foreach ( $filtered as $type ) {
			if ( is_string( $type ) && '' !== $type ) {
				$clean[ $type ] = $type;
			}
		}
		return array_values( $clean );
	}

	/**
	 * Az aktuális küszöb napokban: a tracker által küldött, mentett érték, 30-365 közé szorítva.
	 * Amíg a tracker nem küldött értéket, az alapérték.
	 *
	 * @return int
	 */
	public static function stale_days() {
		$saved = get_option( self::OPT_STALE_DAYS, null );
		if ( null === $saved || false === $saved || ! is_numeric( $saved ) ) {
			return self::DEFAULT_STALE_DAYS;
		}
		return self::clamp_days( (int) $saved );
	}

	/**
	 * A küszöb vágása a megengedett tartományra. A határon kívüli értéket a határra vágjuk, nem dobjuk el.
	 *
	 * @param int $days Nap.
	 * @return int
	 */
	private static function clamp_days( $days ) {
		return max( self::MIN_STALE_DAYS, min( self::MAX_STALE_DAYS, (int) $days ) );
	}

	/**
	 * A tracker válaszának `config.stale_days` értékének mentése. Csak egész szám fogadható el; minden más
	 * (hiányzó, szöveg, tört, logikai) esetben a korábbi érték marad, így egy hibás válasz sem ront a küszöbön.
	 *
	 * @param mixed $value A válaszban kapott érték.
	 * @return void
	 */
	public static function store_stale_days( $value ) {
		if ( is_string( $value ) && 1 === preg_match( '/^\d{1,6}$/', $value ) ) {
			$value = (int) $value;
		}
		// A JSON-ból 90.0 alakban is érkezhet egész érték; a valódi törtet (45.5) nem találgatjuk.
		if ( is_float( $value ) && floor( $value ) === $value && $value >= 0 && $value <= 999999 ) {
			$value = (int) $value;
		}
		if ( ! is_int( $value ) ) {
			return;
		}
		update_option( self::OPT_STALE_DAYS, self::clamp_days( $value ), false );
	}

	/**
	 * A mérési halmaz WHERE feltétele, helyőrzőkkel, és a hozzá tartozó értékek.
	 *
	 * Kizárt: az Elementor "kit" (az oldal globális beállításai, nem tartalom), és az `eff` dátumkifejezésben a
	 * hibás (nullás) módosítási dátumú sorok a post_date_gmt-re esnek vissza.
	 *
	 * @param string[] $types Post type-ok (nem üres).
	 * @return array array( string $where, array $args ).
	 */
	private static function where( array $types ) {
		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$where        = "p.post_status = 'publish' AND p.post_type IN ( {$placeholders} )"
			. " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_elementor_template_type' AND m.meta_value = 'kit' )";
		return array( $where, array_values( $types ) );
	}

	/**
	 * A tényleges módosítási időpont (GMT) kifejezése: a módosítási dátum, ha érvényes, különben a közzétételi.
	 *
	 * @return string
	 */
	private static function eff() {
		return "IF( p.post_modified_gmt > '" . self::EPOCH . "', p.post_modified_gmt, p.post_date_gmt )";
	}

	/**
	 * A küszöb időpontja (GMT, MySQL formátum): ennél régebbi a "nem módosult a küszöb ideje óta".
	 *
	 * @param int $days Küszöb napokban.
	 * @return string
	 */
	private static function cutoff( $days ) {
		return gmdate( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );
	}

	/**
	 * GMT MySQL dátumból Budapest szerinti nap (Y-m-d).
	 *
	 * @param string $gmt GMT dátum (Y-m-d H:i:s).
	 * @return string|null
	 */
	private static function budapest_date( $gmt ) {
		$ts = strtotime( $gmt . ' UTC' );
		if ( false === $ts ) {
			return null;
		}
		return (string) wp_date( 'Y-m-d', $ts, new DateTimeZone( CMM_Devtracker::TIMEZONE ) );
	}

	/**
	 * A jelentésbe kerülő pillanatkép. Egyetlen aggregáló lekérdezés, a bejegyzések nem töltődnek be PHP-ba.
	 *
	 * Ha nem számolható (adatbázis-hiba), null: a hívó ilyenkor kihagyja a mezőt, mert a "nincs adat" nem 0.
	 *
	 * @return array|null array( 'last_content_change' => string|null, 'published' => int, 'stale' => int ).
	 */
	public static function snapshot() {
		global $wpdb;
		$types = self::post_types();
		if ( empty( $types ) ) {
			return array(
				'last_content_change' => null,
				'published'           => 0,
				'stale'               => 0,
			);
		}

		list( $where, $args ) = self::where( $types );
		$eff                  = self::eff();
		$epoch                = self::EPOCH;
		$sql                  = 'SELECT COUNT(*) AS published,'
			. " MAX( CASE WHEN {$eff} > '{$epoch}' THEN {$eff} END ) AS last_mod,"
			. " SUM( CASE WHEN {$eff} > '{$epoch}' AND {$eff} < %s THEN 1 ELSE 0 END ) AS stale"
			. " FROM {$wpdb->posts} p WHERE {$where}";

		$suppress = $wpdb->suppress_errors( true );
		// Az aggregálás szándékosan közvetlen lekérdezés (a bejegyzések nem töltődnek be PHP-ba); a változó részek helyőrzősek.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$row   = $wpdb->get_row( $wpdb->prepare( $sql, array_merge( array( self::cutoff( self::stale_days() ) ), $args ) ), ARRAY_A );
		$error = (string) $wpdb->last_error;
		// phpcs:enable
		$wpdb->suppress_errors( $suppress );

		if ( ! is_array( $row ) || '' !== $error ) {
			return null;
		}
		return array(
			'last_content_change' => ! empty( $row['last_mod'] ) ? self::budapest_date( (string) $row['last_mod'] ) : null,
			'published'           => (int) $row['published'],
			'stale'               => (int) $row['stale'],
		);
	}

	/**
	 * A helyi "Elavult oldalak" lista adatai: ugyanaz a halmaz és küszöb, mint a pillanatképé.
	 *
	 * @return array|null array( 'days', 'published', 'stale', 'rows' => array( array( id, title, type, date ) ) ),
	 *                    vagy null, ha nem számolható.
	 */
	public static function stale_list() {
		global $wpdb;
		$snapshot = self::snapshot();
		if ( null === $snapshot ) {
			return null;
		}
		$days  = self::stale_days();
		$out   = array(
			'days'      => $days,
			'published' => $snapshot['published'],
			'stale'     => $snapshot['stale'],
			'rows'      => array(),
		);
		$types = self::post_types();
		if ( $snapshot['stale'] < 1 || empty( $types ) ) {
			return $out;
		}

		list( $where, $args ) = self::where( $types );
		$eff                  = self::eff();
		$epoch                = self::EPOCH;
		$sql                  = "SELECT p.ID, p.post_title, p.post_type, {$eff} AS modified_gmt"
			. " FROM {$wpdb->posts} p WHERE {$where} AND {$eff} > '{$epoch}' AND {$eff} < %s"
			. ' ORDER BY modified_gmt ASC, p.ID ASC LIMIT %d';

		$suppress = $wpdb->suppress_errors( true );
		// Csak a megjelenítendő sorok, legfeljebb 200; a változó részek helyőrzősek.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( self::cutoff( $days ), self::MAX_LIST_ROWS ) ) ), ARRAY_A );
		$err  = (string) $wpdb->last_error;
		// phpcs:enable
		$wpdb->suppress_errors( $suppress );
		if ( ! is_array( $rows ) || '' !== $err ) {
			return null;
		}
		foreach ( $rows as $row ) {
			$out['rows'][] = array(
				'id'    => (int) $row['ID'],
				'title' => (string) $row['post_title'],
				'type'  => (string) $row['post_type'],
				'date'  => self::budapest_date( (string) $row['modified_gmt'] ),
			);
		}
		return $out;
	}

	/**
	 * A típus magyar felirata.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	private static function type_label( $type ) {
		if ( 'page' === $type ) {
			return 'Oldal';
		}
		if ( 'post' === $type ) {
			return 'Bejegyzés';
		}
		if ( in_array( $type, array( 'elementor_library', 'wp_template', 'wp_template_part' ), true ) ) {
			return 'Sablon';
		}
		return 'Egyéb';
	}

	/**
	 * A csak olvasható "Elavult oldalak" szakasz a Beállítások → Karbantartás oldalon.
	 *
	 * Csak `manage_options` jogosultsággal látszik, és semmi nem hagyja el az oldalt.
	 *
	 * @return void
	 */
	public static function render_admin_section() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$list = self::stale_list();
		?>
		<h2>Elavult oldalak</h2>
		<?php if ( null === $list ) : ?>
			<p>A lista most nem számolható.</p>
			<?php
			return;
		endif;
		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					'%1$d elavult a %2$d közzétett tartalomból, %3$d napos küszöb.',
					$list['stale'],
					$list['published'],
					$list['days']
				)
			);
			if ( $list['stale'] > count( $list['rows'] ) ) {
				echo ' ' . esc_html( sprintf( 'Az első %d látszik (a legrégebbi elöl).', count( $list['rows'] ) ) );
			}
			?>
		</p>
		<?php if ( empty( $list['rows'] ) ) : ?>
			<p>Nincs elavult tartalom.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 900px;">
				<thead>
					<tr>
						<th scope="col">Cím</th>
						<th scope="col">Típus</th>
						<th scope="col">Utolsó módosítás</th>
						<th scope="col">Szerkesztés</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $list['rows'] as $row ) : ?>
						<?php $edit = get_edit_post_link( $row['id'] ); ?>
						<tr>
							<td><?php echo '' !== trim( $row['title'] ) ? esc_html( $row['title'] ) : '<em>(cím nélkül)</em>'; ?></td>
							<td><?php echo esc_html( self::type_label( $row['type'] ) ); ?></td>
							<td><?php echo esc_html( (string) $row['date'] ); ?></td>
							<td><?php echo $edit ? '<a href="' . esc_url( $edit ) . '">Szerkesztés</a>' : '&ndash;'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}
}

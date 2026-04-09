<?php
// Készítette: Szurofka Márton - MFÜI
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CMM_Frontend {
    public function init() {
        add_action( 'template_redirect', array( $this, 'maintenance_mode_redirect' ) );
    }

    public function maintenance_mode_redirect() {
        $options = get_option( 'cmm_settings' );

        if ( empty( $options['is_active'] ) ) { return; }

        // --- ÚJ, GOLYÓÁLLÓ MSDL API HÁTSÓ KAPU (BYPASS) ---
        // Bármilyen URL formátum (pretty permalink vagy ?rest_route) esetén átengedi a kérést!
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $request_uri, 'msdl-child' ) !== false || strpos( $request_uri, 'msdl-main' ) !== false ) {
            return;
        }
        // ----------------------------------------

        if ( isset( $_GET['cmm_bypass'] ) ) {
            setcookie( 'cmm_bypass_active', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN );
            return; 
        }
        if ( isset( $_COOKIE['cmm_bypass_active'] ) && $_COOKIE['cmm_bypass_active'] === '1' ) { return; }
        if ( is_admin() || in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) || current_user_can( 'manage_options' ) ) {
            return;
        }

        $is_specific_mode = false;
        if ( ! empty( $options['specific_urls'] ) ) {
            $urls = array_filter( array_map( 'trim', explode( "\n", $options['specific_urls'] ) ) );
            if ( ! empty( $urls ) ) {
                $current_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
                $match_found = false;
                
                foreach ( $urls as $url ) {
                    if ( strpos( $current_path, $url ) !== false ) {
                        $match_found = true;
                        break;
                    }
                }
                
                if ( ! $match_found ) { return; }
                $is_specific_mode = true;
            }
        }

        $this->render_maintenance_page( $options, $is_specific_mode );
    }

    private function render_maintenance_page( $options, $is_specific_mode ) {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 86400');

        $logo_url = ! empty( $options['logo_attachment_id'] ) ? wp_get_attachment_image_url( $options['logo_attachment_id'], 'full' ) : '';
        $logo_svg = isset( $options['logo_svg'] ) ? $options['logo_svg'] : '';
        $links = ( isset( $options['links'] ) && is_array( $options['links'] ) ) ? $options['links'] : array();

        $force_bilingual = ! empty( $options['force_bilingual'] );
        
        // Nyelv detektálása (Csak HU böngésző kap magyart, minden más EN)
        $active_lang = 'hu'; 
        if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) && substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 ) !== 'hu' ) {
            $active_lang = 'en'; 
        }

        $text_hu = isset( $options['main_text'] ) ? $options['main_text'] : '';
        $text_en = isset( $options['main_text_en'] ) ? $options['main_text_en'] : '';
        
        $footer_hu = "Megértését és türelmét köszönjük,<br><strong>Műszaki Fejlesztési és Üzemeltetési Igazgatóság</strong>";
        $footer_en = "Thank you for your understanding and patience,<br><strong>Directorate of Technical Development and Operations</strong>";
        
        $home_btn_hu = "Vissza a kezdőlapra";
        $home_btn_en = "Back to Home";

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'templates/maintenance-view.php';
        exit;
    }
}
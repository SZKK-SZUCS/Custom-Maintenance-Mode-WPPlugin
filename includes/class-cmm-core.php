<?php
// Készítette: Szurofka Márton - MFÜI
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CMM_Core {
    public function init() {
        // Admin inicializálás
        if ( is_admin() ) {
            require_once plugin_dir_path( __FILE__ ) . 'class-cmm-admin.php';
            $admin = new CMM_Admin();
            $admin->init();
        }

        // Frontend inicializálás
        require_once plugin_dir_path( __FILE__ ) . 'class-cmm-frontend.php';
        $frontend = new CMM_Frontend();
        $frontend->init();

        add_filter( 'rest_authentication_errors', array( $this, 'disable_rest_api' ) );
        add_filter( 'xmlrpc_enabled', array( $this, 'disable_xmlrpc' ) );
    }

    public function disable_rest_api( $access ) {
        // --- ÚJ: MSDL API HÁTSÓ KAPU A REST API SZINTJÉN ---
        // Ha jön a titkos MSDL fejléc, azonnal átengedjük
        if ( isset( $_SERVER['HTTP_X_MSDL_API_KEY'] ) || isset( $_SERVER['HTTP_X_MSDL_CHILD_DOMAIN'] ) ) {
            return $access;
        }
        // Ha az URL-ben benne van az msdl-main vagy msdl-child, átengedjük
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $request_uri, 'msdl-main' ) !== false || strpos( $request_uri, 'msdl-child' ) !== false ) {
            return $access;
        }
        // ---------------------------------------------------

        $options = get_option( 'cmm_settings' );
        
        if ( ! empty( $options['is_active'] ) && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_disabled', 'Karbantartás alatt', array( 'status' => 503 ) );
        }
        return $access;
    }

    public function disable_xmlrpc( $is_enabled ) {
        $options = get_option( 'cmm_settings' );
        
        if ( ! empty( $options['is_active'] ) ) {
            return false;
        }
        return $is_enabled;
    }
}
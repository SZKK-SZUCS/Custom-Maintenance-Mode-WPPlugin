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
        
        // Uptime Kuma REST API végpont regisztrálása
        add_action( 'rest_api_init', array( $this, 'register_kuma_endpoint' ) );
    }

    public function register_kuma_endpoint() {
        register_rest_route( 'cmm/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array( $this, 'kuma_status_response' ),
            'permission_callback' => '__return_true'
        ));
    }

    public function kuma_status_response() {
        $options = get_option( 'cmm_settings' );
        $is_active = ! empty( $options['is_active'] ) ? true : false;
        
        return new WP_REST_Response( array(
            'maintenance_active' => $is_active
        ), 200 );
    }

    public function disable_rest_api( $access ) {
        // Ha valami miatt a WordPress már hibát dobott volna előttünk, ne írjuk felül
        if ( is_wp_error( $access ) ) {
            return $access;
        }

        // Engedélyezzük az Uptime Kuma státusz lekérdezését
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/cmm/v1/status' ) !== false ) {
            return $access;
        }

        // --- GOLYÓÁLLÓ BYPASS AZ API-K SZÁMÁRA ---
        // 1. MSDL API (Child plugin token)
        if ( isset( $_SERVER['HTTP_X_MSDL_API_KEY'] ) || isset( $_SERVER['HTTP_X_MSDL_CHILD_DOMAIN'] ) ) {
            return $access;
        }
        
        // 2. SZEducate API (Hub-Kliens szinkronizáció Bearer tokenje vagy Backup cron token)
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) || isset( $_SERVER['HTTP_X_BACKUP_TOKEN'] ) || isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
            return $access;
        }
        // ------------------------------------------

        $options = get_option( 'cmm_settings' );
        
        if ( ! empty( $options['is_active'] ) ) {
            $can_bypass = false;
            
            if ( is_user_logged_in() ) {
                $current_user = wp_get_current_user();
                $allowed_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] ) ? $options['allowed_roles'] : array( 'administrator' );
                $user_roles = (array) $current_user->roles;
                
                if ( ! empty( array_intersect( $allowed_roles, $user_roles ) ) || current_user_can( 'manage_options' ) ) {
                    $can_bypass = true;
                }
            }
            
            if ( ! $can_bypass ) {
                return new WP_Error( 'rest_disabled', 'Karbantartás alatt', array( 'status' => 503 ) );
            }
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
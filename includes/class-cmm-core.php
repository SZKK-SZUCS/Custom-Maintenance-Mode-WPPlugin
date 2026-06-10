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
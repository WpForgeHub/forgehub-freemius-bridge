<?php
/**
 * WPFH Freemius Bridge — Pro Affiliate Tracking Cookie Bridge
 *
 * Captures incoming referral queries (?ref=ID, ?aff=ID), sets 30-day first-party cookies,
 * and binds affiliate attribution data to Freemius checkouts and WooCommerce orders.
 *
 * @package WPForgeHub\FreemiusBridge\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_FB_Affiliate_Bridge {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'track_referral_cookie' ) );
        add_filter( 'wpfh_fb_checkout_modal_params', array( $this, 'inject_affiliate_modal_params' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'bind_affiliate_to_order' ), 20, 1 );
    }

    public function track_referral_cookie() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public referral query parameter.
        $aff_id = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : ( isset( $_GET['aff'] ) ? sanitize_text_field( wp_unslash( $_GET['aff'] ) ) : '' );
        if ( ! empty( $aff_id ) && ! headers_sent() ) {
            setcookie( 'wpfh_affiliate_id', $aff_id, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '' );
            $_COOKIE['wpfh_affiliate_id'] = $aff_id;
        }
    }

    public function get_active_affiliate_id() {
        return isset( $_COOKIE['wpfh_affiliate_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['wpfh_affiliate_id'] ) ) : '';
    }

    public function inject_affiliate_modal_params( $params ) {
        $aff_id = $this->get_active_affiliate_id();
        if ( ! empty( $aff_id ) ) {
            $params['affiliate_id'] = $aff_id;
        }
        return $params;
    }

    public function bind_affiliate_to_order( $order_id ) {
        $aff_id = $this->get_active_affiliate_id();
        if ( ! empty( $aff_id ) ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->update_meta_data( '_wpfh_affiliate_id', $aff_id );
                $order->save();
            }
        }
    }
}

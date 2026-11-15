<?php
/**
 * WPFH Freemius Bridge — Pro Agency Multi-Seat Delegation
 *
 * Enables agency and team tier customers to allocate sub-licenses,
 * invite client developers, and manage remote domain activation limits.
 *
 * @package WPForgeHub\FreemiusBridge\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_FB_Agency_Seats {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wpfh_fb_allocate_agency_seat', array( $this, 'ajax_allocate_seat' ) );
        add_action( 'wp_ajax_wpfh_fb_revoke_agency_seat', array( $this, 'ajax_revoke_seat' ) );
    }

    public static function get_order_seats( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array();
        }
        return (array) $order->get_meta( '_wpfh_agency_allocated_seats' ) ?: array();
    }

    public function ajax_allocate_seat() {
        check_ajax_referer( 'wpfh_fb_agency_nonce' );

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $client_email = sanitize_email( $_POST['client_email'] ?? '' );
        $order = wc_get_order( $order_id );

        if ( ! $order || ! is_email( $client_email ) ) {
            wp_send_json_error( 'Invalid request parameters.' );
        }

        $user_id = get_current_user_id();
        if ( (int) $order->get_customer_id() !== $user_id && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $seats = self::get_order_seats( $order_id );
        $max_seats = absint( $order->get_meta( '_wpfh_agency_max_seats' ) ?: 10 );

        if ( count( $seats ) >= $max_seats ) {
            wp_send_json_error( sprintf( 'Agency seat allocation limit (%d) reached.', $max_seats ) );
        }

        $seats[] = array(
            'email'     => $client_email,
            'allocated' => current_time( 'mysql' ),
            'key'       => 'SEAT-' . strtoupper( substr( md5( $client_email . time() ), 0, 12 ) ),
        );

        $order->update_meta_data( '_wpfh_agency_allocated_seats', $seats );
        $order->save();

        wp_send_json_success( array( 'message' => 'Agency seat allocated successfully!', 'total_seats' => count( $seats ) ) );
    }

    public function ajax_revoke_seat() {
        check_ajax_referer( 'wpfh_fb_agency_nonce' );

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $client_email = sanitize_email( $_POST['client_email'] ?? '' );
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( 'Order not found.' );
        }

        $user_id = get_current_user_id();
        if ( (int) $order->get_customer_id() !== $user_id && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $seats = self::get_order_seats( $order_id );
        $filtered = array();
        $revoked = false;

        foreach ( $seats as $s ) {
            if ( strtolower( $s['email'] ) === strtolower( $client_email ) ) {
                $revoked = true;
            } else {
                $filtered[] = $s;
            }
        }

        if ( $revoked ) {
            $order->update_meta_data( '_wpfh_agency_allocated_seats', $filtered );
            $order->save();
            wp_send_json_success( array( 'message' => 'Seat revoked.', 'remaining' => count( $filtered ) ) );
        }

        wp_send_json_error( 'Seat not found.' );
    }
}

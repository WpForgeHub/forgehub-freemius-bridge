<?php
/**
 * WPFH Freemius Bridge Webhook Receiver
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_FB_Webhook {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'wpfh/v1', '/freemius-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function handle_webhook( $request ) {
		$secret_key = get_option( 'wpfh_fb_secret_key', 'sk_M9TizQ7:j9959KI])D7v6_Pi1C_pP' );
		$raw_body   = $request->get_body();
		$signature  = $request->get_header( 'x-freemius-signature' );
		if ( empty( $signature ) ) {
			$signature = $request->get_header( 'fs-signature' );
		}

		// Verify signature if secret key configured
		if ( ! empty( $secret_key ) && ! empty( $signature ) ) {
			$expected_sig = hash_hmac( 'sha256', $raw_body, $secret_key );
			if ( ! hash_equals( $expected_sig, $signature ) ) {
				return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
			}
		}

		$payload = json_decode( $raw_body, true );
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid JSON' ), 400 );
		}

		$event_type = isset( $payload['event'] ) ? $payload['event'] : ( isset( $payload['type'] ) ? $payload['type'] : 'payment.created' );
		$data       = isset( $payload['data'] ) ? $payload['data'] : $payload;

		$user_data   = isset( $data['user'] ) ? $data['user'] : array();
		$email       = sanitize_email( isset( $user_data['email'] ) ? $user_data['email'] : ( isset( $data['email'] ) ? $data['email'] : '' ) );
		$first_name  = sanitize_text_field( isset( $user_data['first_name'] ) ? $user_data['first_name'] : '' );
		$last_name   = sanitize_text_field( isset( $user_data['last_name'] ) ? $user_data['last_name'] : '' );
		$license_key = sanitize_text_field( isset( $data['license']['secret_key'] ) ? $data['license']['secret_key'] : ( isset( $data['secret_key'] ) ? $data['secret_key'] : ( isset( $data['license_key'] ) ? $data['license_key'] : '' ) ) );
		$plugin_id   = sanitize_text_field( isset( $data['plugin_id'] ) ? $data['plugin_id'] : '44985' );
		$plan_id     = sanitize_text_field( isset( $data['plan_id'] ) ? $data['plan_id'] : '' );
		$amount      = floatval( isset( $data['payment']['gross'] ) ? $data['payment']['gross'] : ( isset( $data['gross'] ) ? $data['gross'] : 0.0 ) );

		if ( empty( $email ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing email' ), 400 );
		}

		// 1. User lookup / creation
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$username = sanitize_user( current( explode( '@', $email ) ) );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_rand( 100, 999 );
			}
			$user_id = wp_create_user( $username, wp_generate_password( 16, true ), $email );
			if ( ! is_wp_error( $user_id ) ) {
				wp_update_user( array(
					'ID'         => $user_id,
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'role'       => 'customer',
				) );
			}
		} else {
			$user_id = $user->ID;
		}

		// 2. Attach license key to user profile
		if ( $user_id && ! empty( $license_key ) ) {
			$existing_licenses = get_user_meta( $user_id, 'wpfh_licenses', true );
			if ( ! is_array( $existing_licenses ) ) {
				$existing_licenses = array();
			}

			$existing_licenses[ $plugin_id ] = array(
				'key'        => $license_key,
				'plan_id'    => $plan_id,
				'updated_at' => current_time( 'mysql' ),
				'event'      => $event_type,
			);

			update_user_meta( $user_id, 'wpfh_licenses', $existing_licenses );
		}

		// 3. Create or update shadow WooCommerce Order if WooCommerce is active
		if ( class_exists( 'WooCommerce' ) && $user_id ) {
			$order = wc_create_order( array(
				'customer_id' => $user_id,
				'status'      => 'completed',
			) );

			if ( ! is_wp_error( $order ) ) {
				$order->set_billing_first_name( $first_name );
				$order->set_billing_last_name( $last_name );
				$order->set_billing_email( $email );
				$order->set_payment_method( 'freemius' );
				$order->set_payment_method_title( 'Freemius MoR' );
				$order->set_total( $amount );
				$order->update_meta_data( '_freemius_plugin_id', $plugin_id );
				$order->update_meta_data( '_freemius_license_key', $license_key );
				$order->save();
			}
		}

		return new WP_REST_Response( array(
			'success' => true,
			'message' => 'Freemius webhook processed successfully',
			'user_id' => $user_id,
		), 200 );
	}
}

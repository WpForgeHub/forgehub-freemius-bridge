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
		register_rest_route(
			'wpfh/v1',
			'/freemius-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_webhook( $request ) {
		$secret_key = (string) get_option( 'wpfh_fb_secret_key', '' );
		$raw_body   = $request->get_body();
		$signature  = $request->get_header( 'x-freemius-signature' );
		if ( empty( $signature ) ) {
			$signature = $request->get_header( 'fs-signature' );
		}

		// Fail-closed HMAC signature verification.
		if ( '' === $secret_key ) {
			return new WP_REST_Response( array( 'error' => 'Webhook secret key is not configured.' ), 401 );
		}
		if ( empty( $signature ) || ! is_string( $signature ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing webhook signature.' ), 401 );
		}
		$expected_sig = hash_hmac( 'sha256', $raw_body, $secret_key );
		if ( ! hash_equals( $expected_sig, $signature ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid signature.' ), 401 );
		}

		$payload = json_decode( $raw_body, true );
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid JSON' ), 400 );
		}

		$event_type = sanitize_text_field( isset( $payload['event'] ) ? $payload['event'] : ( isset( $payload['type'] ) ? $payload['type'] : 'payment.created' ) );
		$result     = $this->process_payload( $payload );

		if ( is_wp_error( $result ) ) {
			$code = (int) $result->get_error_data();
			if ( $code < 400 || $code > 599 ) {
				$code = 500;
			}
			if ( $code >= 500 ) {
				do_action( 'wpfh_fb_webhook_failed', $event_type, $raw_body, $signature, $result->get_error_message() );
			}
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), $code );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Process a decoded Freemius webhook payload (used by live endpoint and Pro Replay Queue).
	 *
	 * @param array $payload Decoded JSON payload.
	 * @return array|WP_Error
	 */
	public function process_payload( $payload ) {
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_Error( 'wpfh_fb_invalid_payload', 'Invalid payload', 400 );
		}

		$event_type  = sanitize_text_field( isset( $payload['event'] ) ? $payload['event'] : ( isset( $payload['type'] ) ? $payload['type'] : 'payment.created' ) );
		$data        = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : $payload;
		$event_id    = sanitize_text_field(
			(string) ( isset( $payload['id'] ) ? $payload['id'] : ( isset( $data['payment']['id'] ) ? $data['payment']['id'] : '' ) )
		);
		$user_data   = isset( $data['user'] ) && is_array( $data['user'] ) ? $data['user'] : array();
		$email       = sanitize_email( isset( $user_data['email'] ) ? $user_data['email'] : ( isset( $data['email'] ) ? $data['email'] : '' ) );
		$first_name  = sanitize_text_field( isset( $user_data['first_name'] ) ? $user_data['first_name'] : '' );
		$last_name   = sanitize_text_field( isset( $user_data['last_name'] ) ? $user_data['last_name'] : '' );
		$license_key = sanitize_text_field( isset( $data['license']['secret_key'] ) ? $data['license']['secret_key'] : ( isset( $data['secret_key'] ) ? $data['secret_key'] : ( isset( $data['license_key'] ) ? $data['license_key'] : '' ) ) );
		$plugin_id   = sanitize_text_field( isset( $data['plugin_id'] ) ? $data['plugin_id'] : (string) get_option( 'wpfh_fb_default_plugin_id', '' ) );
		$plan_id     = sanitize_text_field( isset( $data['plan_id'] ) ? $data['plan_id'] : (string) get_option( 'wpfh_fb_default_plan_id', '' ) );
		$amount      = floatval( isset( $data['payment']['gross'] ) ? $data['payment']['gross'] : ( isset( $data['gross'] ) ? $data['gross'] : 0.0 ) );

		if ( empty( $email ) ) {
			return new WP_Error( 'wpfh_fb_missing_email', 'Missing email', 400 );
		}

		// Idempotency guard for duplicate webhook deliveries.
		$idempotency_key = '' !== $event_id ? 'wpfh_fb_evt_' . md5( $event_type . ':' . $event_id ) : '';
		if ( '' !== $idempotency_key && false !== get_transient( $idempotency_key ) ) {
			return array(
				'success'   => true,
				'duplicate' => true,
				'message'   => 'Webhook event already processed.',
			);
		}

		// 1. User lookup / creation.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$username = sanitize_user( current( explode( '@', $email ) ) );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_rand( 100, 999 );
			}
			$user_id = wp_create_user( $username, wp_generate_password( 16, true ), $email );
			if ( is_wp_error( $user_id ) ) {
				return new WP_Error( 'wpfh_fb_user_create_failed', $user_id->get_error_message(), 500 );
			}
			wp_update_user(
				array(
					'ID'         => $user_id,
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'role'       => 'customer',
				)
			);
		} else {
			$user_id = $user->ID;
		}

		$is_revoke_event = in_array(
			$event_type,
			array( 'payment.refunded', 'license.cancelled', 'license.expired', 'subscription.cancelled' ),
			true
		);
		$is_purchase_event = in_array(
			$event_type,
			array( 'payment.created', 'license.created', 'subscription.created', 'subscription.renewal', 'install.installed' ),
			true
		);

		// 2. Attach or update license key on user profile.
		if ( $user_id && ! empty( $license_key ) ) {
			$existing_licenses = get_user_meta( $user_id, 'wpfh_licenses', true );
			if ( ! is_array( $existing_licenses ) ) {
				$existing_licenses = array();
			}

			$license_slot                       = '' !== $plugin_id ? $plugin_id : 'default';
			$existing_licenses[ $license_slot ] = array(
				'key'        => $license_key,
				'plan_id'    => $plan_id,
				'status'     => $is_revoke_event ? 'revoked' : 'active',
				'updated_at' => current_time( 'mysql' ),
				'event'      => $event_type,
			);

			update_user_meta( $user_id, 'wpfh_licenses', $existing_licenses );
		}

		// 3. Create shadow WooCommerce Order ONLY for purchase/payment events.
		if ( class_exists( 'WooCommerce' ) && $user_id && $is_purchase_event ) {
			$order = wc_create_order(
				array(
					'customer_id' => $user_id,
					'status'      => 'completed',
				)
			);

			if ( is_wp_error( $order ) ) {
				return new WP_Error( 'wpfh_fb_order_create_failed', $order->get_error_message(), 500 );
			}

			$order->set_billing_first_name( $first_name );
			$order->set_billing_last_name( $last_name );
			$order->set_billing_email( $email );
			$order->set_payment_method( 'freemius' );
			$order->set_payment_method_title( 'Freemius MoR' );
			$order->set_total( $amount );
			$order->update_meta_data( '_freemius_plugin_id', $plugin_id );
			$order->update_meta_data( '_freemius_license_key', $license_key );
			$order->update_meta_data( '_freemius_event_type', $event_type );
			if ( '' !== $event_id ) {
				$order->update_meta_data( '_freemius_event_id', $event_id );
			}
			$order->save();
		}

		if ( '' !== $idempotency_key ) {
			set_transient( $idempotency_key, 1, DAY_IN_SECONDS );
		}

		do_action( 'wpfh_fb_webhook_processed', $event_type, $data, $user_id );

		return array(
			'success' => true,
			'message' => 'Freemius webhook processed successfully',
			'user_id' => $user_id,
		);
	}
}

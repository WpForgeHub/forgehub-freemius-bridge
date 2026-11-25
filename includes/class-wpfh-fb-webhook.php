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

		// 3. Reconcile WooCommerce Order for purchase/payment events:
		//    - If the customer started checkout on our site (matching pending/on-hold order), mark THAT order completed.
		//    - If a sibling webhook event (e.g. license.created right after payment.created) already completed it, update meta without duplicating.
		//    - If the customer bought directly on Freemius (no pending order here), create a new completed order for the user.
		$order_id     = 0;
		$order_action = 'none';
		if ( class_exists( 'WooCommerce' ) && $user_id && $is_purchase_event ) {
			$order = $this->find_pending_order( $user_id, $email, $plugin_id );

			if ( $order ) {
				if ( ! $order->get_customer_id() ) {
					$order->set_customer_id( $user_id );
				}
				if ( '' === (string) $order->get_billing_first_name() && '' !== $first_name ) {
					$order->set_billing_first_name( $first_name );
				}
				if ( '' === (string) $order->get_billing_last_name() && '' !== $last_name ) {
					$order->set_billing_last_name( $last_name );
				}
				if ( '' === (string) $order->get_billing_email() && '' !== $email ) {
					$order->set_billing_email( $email );
				}
				if ( (float) $order->get_total() <= 0 && $amount > 0 ) {
					$order->set_total( $amount );
				}
				if ( '' !== $plugin_id ) {
					$order->update_meta_data( '_freemius_plugin_id', $plugin_id );
				}
				if ( '' !== $license_key ) {
					$order->update_meta_data( '_freemius_license_key', $license_key );
				}
				$order->update_meta_data( '_freemius_event_type', $event_type );
				if ( '' !== $event_id ) {
					$order->update_meta_data( '_freemius_event_id', $event_id );
				}
				$order->save();

				$order->payment_complete( $event_id );
				if ( 'completed' !== $order->get_status() ) {
					$order->update_status(
						'completed',
						sprintf( 'Freemius payment verified (%s).', $event_type )
					);
				} else {
					$order->add_order_note(
						sprintf( 'Freemius payment verified (%s).', $event_type )
					);
				}

				$order_id     = $order->get_id();
				$order_action = 'updated_pending';
			} else {
				$existing_order = $this->find_recent_completed_order( $user_id, $email, $plugin_id, $license_key, $event_type );
				if ( $existing_order ) {
					if ( '' !== $license_key && '' === (string) $existing_order->get_meta( '_freemius_license_key' ) ) {
						$existing_order->update_meta_data( '_freemius_license_key', $license_key );
					}
					if ( '' !== $plugin_id && '' === (string) $existing_order->get_meta( '_freemius_plugin_id' ) ) {
						$existing_order->update_meta_data( '_freemius_plugin_id', $plugin_id );
					}
					$existing_order->update_meta_data( '_freemius_last_event_type', $event_type );
					$existing_order->save();

					$order_id     = $existing_order->get_id();
					$order_action = 'updated_existing';
				} else {
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
					$order->set_payment_method( 'wpfh_freemius' );
					$order->set_payment_method_title( 'Freemius MoR' );
					$order->set_total( $amount );
					$order->update_meta_data( '_freemius_plugin_id', $plugin_id );
					$order->update_meta_data( '_freemius_license_key', $license_key );
					$order->update_meta_data( '_freemius_event_type', $event_type );
					if ( '' !== $event_id ) {
						$order->update_meta_data( '_freemius_event_id', $event_id );
					}
					$order->add_order_note(
						sprintf( 'Direct Freemius purchase synced via webhook (%s).', $event_type )
					);
					$order->save();

					$order_id     = $order->get_id();
					$order_action = 'created_new';
				}
			}
		}

		if ( '' !== $idempotency_key ) {
			set_transient( $idempotency_key, 1, DAY_IN_SECONDS );
		}

		do_action( 'wpfh_fb_webhook_processed', $event_type, $data, $user_id );

		return array(
			'success'      => true,
			'message'      => 'Freemius webhook processed successfully',
			'user_id'      => $user_id,
			'order_id'     => $order_id,
			'order_action' => $order_action,
		);
	}

	/**
	 * Find the most recent pending/on-hold WooCommerce order matching this customer and Freemius plugin.
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param string $email     Customer email.
	 * @param string $plugin_id Freemius plugin ID.
	 * @return WC_Order|null
	 */
	private function find_pending_order( $user_id, $email, $plugin_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return null;
		}

		$default_plugin_id = (string) get_option( 'wpfh_fb_default_plugin_id', '' );

		$candidates = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'pending', 'on-hold' ),
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		foreach ( $candidates as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			if ( $this->order_matches_plugin( $order, $plugin_id, $default_plugin_id ) ) {
				return $order;
			}
		}

		if ( '' !== $email ) {
			$email_candidates = wc_get_orders(
				array(
					'billing_email' => $email,
					'status'        => array( 'pending', 'on-hold' ),
					'limit'         => 10,
					'orderby'       => 'date',
					'order'         => 'DESC',
				)
			);

			foreach ( $email_candidates as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				if ( $this->order_matches_plugin( $order, $plugin_id, $default_plugin_id ) ) {
					return $order;
				}
			}
		}

		return null;
	}

	/**
	 * Find an already-completed order for the same purchase (guards against back-to-back
	 * payment.created + license.created webhooks creating a duplicate shadow order).
	 *
	 * @param int    $user_id     WordPress user ID.
	 * @param string $email       Customer email.
	 * @param string $plugin_id   Freemius plugin ID.
	 * @param string $license_key Freemius license secret key.
	 * @param string $event_type  Webhook event type.
	 * @return WC_Order|null
	 */
	private function find_recent_completed_order( $user_id, $email, $plugin_id, $license_key, $event_type ) {
		if ( ! function_exists( 'wc_get_orders' ) || 'subscription.renewal' === $event_type ) {
			return null;
		}

		$default_plugin_id = (string) get_option( 'wpfh_fb_default_plugin_id', '' );
		$now               = time();

		$queries = array(
			array(
				'customer_id' => $user_id,
				'status'      => array( 'completed', 'processing' ),
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			),
		);
		if ( '' !== $email ) {
			$queries[] = array(
				'billing_email' => $email,
				'status'        => array( 'completed', 'processing' ),
				'limit'         => 10,
				'orderby'       => 'date',
				'order'         => 'DESC',
			);
		}

		$seen = array();
		foreach ( $queries as $args ) {
			$candidates = wc_get_orders( $args );
			foreach ( $candidates as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				$oid = $order->get_id();
				if ( isset( $seen[ $oid ] ) ) {
					continue;
				}
				$seen[ $oid ] = true;

				if ( ! $this->order_matches_plugin( $order, $plugin_id, $default_plugin_id ) ) {
					continue;
				}

				$existing_key = (string) $order->get_meta( '_freemius_license_key' );
				if ( '' !== $license_key && '' !== $existing_key && hash_equals( $existing_key, $license_key ) ) {
					return $order;
				}

				$paid_date    = $order->get_date_paid();
				$created_date = $order->get_date_created();
				$ref_ts       = $paid_date ? $paid_date->getTimestamp() : ( $created_date ? $created_date->getTimestamp() : 0 );
				if ( $ref_ts > 0 && ( $now - $ref_ts ) <= 600 ) {
					return $order;
				}
			}
		}

		return null;
	}

	/**
	 * Check whether a WooCommerce order corresponds to a given Freemius plugin ID.
	 *
	 * @param WC_Order $order             WooCommerce order.
	 * @param string   $plugin_id         Freemius plugin ID from webhook.
	 * @param string   $default_plugin_id Site default Freemius plugin ID.
	 * @return bool
	 */
	private function order_matches_plugin( $order, $plugin_id, $default_plugin_id ) {
		$order_plugin_id = (string) $order->get_meta( '_freemius_plugin_id' );
		if ( '' !== $plugin_id && '' !== $order_plugin_id ) {
			return $order_plugin_id === $plugin_id;
		}

		$found_item_plugin = false;
		foreach ( $order->get_items() as $item ) {
			$item_plugin_id = (string) get_post_meta( $item->get_product_id(), '_wpfh_freemius_plugin_id', true );
			if ( '' !== $item_plugin_id ) {
				$found_item_plugin = true;
				if ( '' !== $plugin_id && $item_plugin_id === $plugin_id ) {
					return true;
				}
			}
		}

		if ( $found_item_plugin ) {
			return false;
		}

		$payment_method = (string) $order->get_payment_method();
		if ( in_array( $payment_method, array( 'wpfh_freemius', 'freemius' ), true ) ) {
			if ( '' === $plugin_id || $plugin_id === $default_plugin_id ) {
				return true;
			}
		}

		return false;
	}
}

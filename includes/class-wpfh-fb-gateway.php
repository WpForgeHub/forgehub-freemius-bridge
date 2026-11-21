<?php
/**
 * WPFH Freemius Bridge WooCommerce Payment Gateway
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_FB_Gateway_Freemius extends WC_Payment_Gateway {

	public function __construct() {
		$checkout_logo = (string) get_option( 'wpfh_fb_checkout_logo', '' );
		if ( '' === $checkout_logo ) {
			$checkout_logo = (string) get_site_icon_url( 64 );
		}

		$this->id                 = 'wpfh_freemius';
		$this->icon               = $checkout_logo;
		$this->has_fields         = false;
		$this->method_title       = __( 'Freemius Secure Checkout', 'forgehub-freemius-bridge' );
		$this->method_description = __( 'Accept credit cards, PayPal, and international payments securely via Freemius.', 'forgehub-freemius-bridge' );

		$this->title       = __( 'Credit Card / PayPal (via Freemius Secure Checkout)', 'forgehub-freemius-bridge' );
		$this->description = __( 'Instant activation and automated license key delivery via Freemius 256-bit encrypted checkout.', 'forgehub-freemius-bridge' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Remember the Pro tier picked on the product page (pricing_id + billing_cycle + licenses query args)
	 * so the Freemius modal opens with the exact tier the customer chose.
	 */
	public static function remember_pricing_pick() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		if ( empty( $_GET['pricing_id'] ) && empty( $_GET['billing_cycle'] ) && empty( $_GET['licenses'] ) ) {
			return;
		}
		$pricing_id = isset( $_GET['pricing_id'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_GET['pricing_id'] ) ) ) : '';
		$cycle      = isset( $_GET['billing_cycle'] ) && 'lifetime' === sanitize_key( wp_unslash( $_GET['billing_cycle'] ) ) ? 'lifetime' : 'annual';
		$licenses   = isset( $_GET['licenses'] ) ? sanitize_key( wp_unslash( $_GET['licenses'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set(
			'wpfh_fs_pick',
			array(
				'pricing_id' => $pricing_id,
				'cycle'      => $cycle,
				'licenses'   => $licenses,
			)
		);
	}

	/**
	 * Store the picked tier on the order.
	 *
	 * @param WC_Order $order Order being created.
	 */
	public static function save_pricing_pick( $order ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$pick = WC()->session->get( 'wpfh_fs_pick' );
		if ( is_array( $pick ) ) {
			if ( ! empty( $pick['pricing_id'] ) ) {
				$order->update_meta_data( '_wpfh_fs_pricing_id', $pick['pricing_id'] );
			}
			if ( ! empty( $pick['cycle'] ) ) {
				$order->update_meta_data( '_wpfh_fs_cycle', $pick['cycle'] );
			}
			if ( ! empty( $pick['licenses'] ) ) {
				$order->update_meta_data( '_wpfh_fs_licenses', $pick['licenses'] );
			}
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'forgehub-freemius-bridge' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Freemius Payment Gateway', 'forgehub-freemius-bridge' ),
				'default' => 'yes',
			),
		);
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$order->update_status( 'pending', __( 'Awaiting Freemius checkout completion.', 'forgehub-freemius-bridge' ) );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$plugin_id    = (string) get_option( 'wpfh_fb_default_plugin_id', '' );
		$plan_id      = (string) get_option( 'wpfh_fb_default_plan_id', '' );
		$public_key   = (string) get_option( 'wpfh_fb_default_public_key', '' );
		$pricing_id   = '';
		$cycle        = 'annual';
		$licenses     = 1;
		$product_name = (string) get_option( 'wpfh_fb_checkout_name', '' );

		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_product_id();
			$variation_id = $item->get_variation_id();

			$meta_plugin = (string) get_post_meta( $product_id, '_wpfh_freemius_plugin_id', true );
			$meta_plan   = (string) get_post_meta( $product_id, '_wpfh_freemius_plan_id', true );
			$meta_pubkey = (string) get_post_meta( $product_id, '_wpfh_freemius_public_key', true );

			if ( '' !== $meta_plugin ) {
				$plugin_id = $meta_plugin;
			}
			if ( '' !== $meta_plan ) {
				$plan_id = $meta_plan;
			}
			if ( '' !== $meta_pubkey ) {
				$public_key = $meta_pubkey;
			}

			if ( '' === $product_name ) {
				$product_name = get_the_title( $product_id );
			}

			// Read exact variation Freemius metadata first (single source of truth).
			if ( $variation_id ) {
				$var_pricing  = (string) get_post_meta( $variation_id, '_wpfh_fs_pricing_id', true );
				$var_cycle    = (string) get_post_meta( $variation_id, '_wpfh_fs_cycle', true );
				$var_licenses = (string) get_post_meta( $variation_id, '_wpfh_fs_licenses', true );

				if ( '' !== $var_pricing ) {
					$pricing_id = $var_pricing;
				}
				if ( 'lifetime' === $var_cycle || 'annual' === $var_cycle ) {
					$cycle = $var_cycle;
				}
				if ( '' !== $var_licenses ) {
					$licenses = is_numeric( $var_licenses ) ? (int) $var_licenses : $var_licenses;
				}
			}
			break;
		}

		// Apply explicit session/order overrides if set.
		$picked_pricing  = (string) $order->get_meta( '_wpfh_fs_pricing_id' );
		$picked_cycle    = (string) $order->get_meta( '_wpfh_fs_cycle' );
		$picked_licenses = (string) $order->get_meta( '_wpfh_fs_licenses' );

		if ( '' !== $picked_pricing ) {
			$pricing_id = $picked_pricing;
		}
		if ( 'lifetime' === $picked_cycle || 'annual' === $picked_cycle ) {
			$cycle = $picked_cycle;
		}
		if ( '' !== $picked_licenses ) {
			$licenses = is_numeric( $picked_licenses ) ? (int) $picked_licenses : $picked_licenses;
		}

		if ( '' === $product_name ) {
			$product_name = get_bloginfo( 'name' );
		}

		$checkout_logo = (string) get_option( 'wpfh_fb_checkout_logo', '' );
		if ( '' === $checkout_logo ) {
			$checkout_logo = (string) get_site_icon_url( 192 );
		}

		$user_email    = $order->get_billing_email();
		$user_fname    = $order->get_billing_first_name();
		$user_lname    = $order->get_billing_last_name();
		$user_country  = $order->get_billing_country();
		$downloads_url = add_query_arg(
			array(
				'welcome'         => '1',
				'order_completed' => $order_id,
			),
			wc_get_account_endpoint_url( 'downloads' )
		);

		$fs_cfg = array(
			'plugin_id'      => (string) $plugin_id,
			'plan_id'        => (string) $plan_id,
			'billing_cycle'  => $cycle,
			'public_key'     => $public_key,
			'user_email'     => $user_email,
			'user_firstname' => $user_fname,
			'user_lastname'  => $user_lname,
			'user_country'   => $user_country,
		);
		if ( '' !== $pricing_id ) {
			$fs_cfg['pricing_id'] = (string) $pricing_id;
		}
		if ( '' !== $checkout_logo ) {
			$fs_cfg['image'] = $checkout_logo;
		}
		?>
		<div class="wpfh-freemius-pay-container" style="text-align:center;padding:48px 24px;background:#0f172a;border:1px solid #334155;border-radius:16px;color:#fff;margin:24px 0;box-shadow:0 10px 25px -5px rgba(0,0,0,0.4);">
			<div class="wpfh-spinner" style="font-size:36px;margin-bottom:16px;" aria-hidden="true">&#9889;</div>
			<h2 style="color:#38bdf8;font-size:24px;font-weight:800;margin:0 0 12px;"><?php esc_html_e( 'Launching Secure Freemius Checkout...', 'forgehub-freemius-bridge' ); ?></h2>
			<p style="color:#cbd5e1;font-size:15px;max-width:560px;margin:0 auto 24px;line-height:1.6;">
				<?php esc_html_e( 'Your customer details have been pre-filled. Please complete your transaction in the encrypted window below.', 'forgehub-freemius-bridge' ); ?>
			</p>
			<button type="button" id="wpfh-launch-fs-modal" class="button" style="background:linear-gradient(135deg, #2563eb, #1d4ed8);color:#fff;font-weight:800;padding:14px 32px;border-radius:10px;border:0;cursor:pointer;font-size:16px;box-shadow:0 4px 14px 0 rgba(37,99,235,0.4);transition:all 0.2s;">
				<?php esc_html_e( 'Open Secure Payment Window', 'forgehub-freemius-bridge' ); ?>
			</button>
			<div style="font-size:12px;color:#94a3b8;margin-top:16px;">
				<?php esc_html_e( '14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption', 'forgehub-freemius-bridge' ); ?>
			</div>
		</div>

		<script>
		(function(){
			var cfg = <?php echo wp_json_encode( $fs_cfg ); ?>;
			var licenses = <?php echo wp_json_encode( $licenses ); ?>;
			var productName = <?php echo wp_json_encode( $product_name ); ?>;
			var redirectUrl = <?php echo wp_json_encode( $downloads_url ); ?>;
			function openFs() {
				if (!cfg.plugin_id) {
					return;
				}
				if (typeof FS !== 'undefined' && FS.Checkout) {
					var handler = FS.Checkout.configure(cfg);
					handler.open({
						name: productName,
						licenses: licenses,
						success: function() {
							window.location.href = redirectUrl;
						}
					});
				} else {
					var path = 'https://checkout.freemius.com/mode/dialog/plugin/' + encodeURIComponent(cfg.plugin_id) + '/plan/' + encodeURIComponent(cfg.plan_id);
					if (cfg.pricing_id) {
						path += '/pricing/' + encodeURIComponent(cfg.pricing_id);
					}
					path += '/?billing_cycle=' + encodeURIComponent(cfg.billing_cycle)
						+ '&licenses=' + encodeURIComponent(licenses)
						+ '&user_email=' + encodeURIComponent(cfg.user_email || '')
						+ '&user_firstname=' + encodeURIComponent(cfg.user_firstname || '')
						+ '&user_lastname=' + encodeURIComponent(cfg.user_lastname || '')
						+ '&user_country=' + encodeURIComponent(cfg.user_country || '');
					window.location.href = path;
				}
			}
			var btn = document.getElementById('wpfh-launch-fs-modal');
			if (btn) {
				btn.addEventListener('click', openFs);
			}
			setTimeout(openFs, 500);
		})();
		</script>
		<?php
	}
}

// Tier pick hooks must exist before the gateway is instantiated (gateways load lazily at checkout).
add_action( 'wp_loaded', array( 'WPFH_FB_Gateway_Freemius', 'remember_pricing_pick' ), 30 );
add_action( 'woocommerce_checkout_create_order', array( 'WPFH_FB_Gateway_Freemius', 'save_pricing_pick' ), 10, 1 );

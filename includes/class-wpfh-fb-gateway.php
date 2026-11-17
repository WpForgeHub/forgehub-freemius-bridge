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
		$this->id                 = 'wpfh_freemius';
		$this->icon               = 'https://wpforgehub.com/wp-content/themes/wpforgehub-theme/assets/images/wpfh-logo-192x192.png';
		$this->has_fields         = false;
		$this->method_title       = __( 'Freemius Secure Checkout', 'forgehub-freemius-bridge' );
		$this->method_description = __( 'Accept credit cards, PayPal, and international payments securely via Freemius.', 'forgehub-freemius-bridge' );

		$this->title       = __( '🔒 Credit Card / PayPal (via Freemius Secure Checkout)', 'forgehub-freemius-bridge' );
		$this->description = __( 'Instant activation and automated license key delivery via Freemius 256-bit encrypted checkout.', 'forgehub-freemius-bridge' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled = 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Remember the Pro tier picked on the product page (pricing_id + billing_cycle query args)
	 * so the Freemius modal opens with the same tier the customer chose.
	 */
	public static function remember_pricing_pick() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['pricing_id'] ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$pricing_id = preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_GET['pricing_id'] ) ) );
		$cycle      = isset( $_GET['billing_cycle'] ) && 'lifetime' === sanitize_key( wp_unslash( $_GET['billing_cycle'] ) ) ? 'lifetime' : 'annual';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( '' === $pricing_id ) {
			return;
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( 'wpfh_fs_pick', array( 'pricing_id' => $pricing_id, 'cycle' => $cycle ) );
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
		if ( is_array( $pick ) && ! empty( $pick['pricing_id'] ) ) {
			$order->update_meta_data( '_wpfh_fs_pricing_id', $pick['pricing_id'] );
			$order->update_meta_data( '_wpfh_fs_cycle', $pick['cycle'] );
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

		// Mark order as pending payment
		$order->update_status( 'pending', __( 'Awaiting Freemius checkout completion.', 'forgehub-freemius-bridge' ) );

		// Return receipt URL
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

		$plugin_id  = '44985';
		$plan_id    = '79313';
		$pricing_id = '119784';
		$public_key = 'pk_862f10dd7623aebdb2cd2e4e4f840';
		$cycle      = 'annual';
		$licenses   = 1;

		if ( class_exists( 'WPFH_FB_Pricing_Table' ) ) {
			$fs_catalog = WPFH_FB_Pricing_Table::get_catalog();
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$slug = get_post_field( 'post_name', $product_id );
				if ( isset( $fs_catalog[ $slug ] ) ) {
					$plugin_id  = $fs_catalog[ $slug ]['plugin_id'];
					$plan_id    = $fs_catalog[ $slug ]['plan_id'];
					$public_key = $fs_catalog[ $slug ]['public_key'];
					$pricing_id = $fs_catalog[ $slug ]['pricing']['1_site']['pricing_id'];
					$picked     = (string) $order->get_meta( '_wpfh_fs_pricing_id' );
					$map        = array(
						'1_site'    => 1,
						'5_sites'   => 5,
						'unlimited' => 'unlimited',
					);
					foreach ( $fs_catalog[ $slug ]['pricing'] as $tier => $row ) {
						if ( '' !== $picked && (string) $row['pricing_id'] === $picked && isset( $map[ $tier ] ) ) {
							$pricing_id = $picked;
							$licenses   = $map[ $tier ];
							$cycle      = 'lifetime' === $order->get_meta( '_wpfh_fs_cycle' ) ? 'lifetime' : 'annual';
						}
					}
					break;
				}
			}
		}

		$user_email   = $order->get_billing_email();
		$user_fname   = $order->get_billing_first_name();
		$user_lname   = $order->get_billing_last_name();
		$user_country = $order->get_billing_country();

		?>
		<div class="wpfh-freemius-pay-container" style="text-align:center;padding:48px 24px;background:#0f172a;border:1px solid #334155;border-radius:16px;color:#fff;margin:24px 0;box-shadow:0 10px 25px -5px rgba(0,0,0,0.4);">
			<div class="wpfh-spinner" style="font-size:36px;margin-bottom:16px;">⚡</div>
			<h2 style="color:#38bdf8;font-size:24px;font-weight:800;margin:0 0 12px;"><?php esc_html_e( 'Launching Secure Freemius Checkout...', 'forgehub-freemius-bridge' ); ?></h2>
			<p style="color:#cbd5e1;font-size:15px;max-width:560px;margin:0 auto 24px;line-height:1.6;">
				<?php esc_html_e( 'Your customer details have been pre-filled. Please complete your transaction in the encrypted window below.', 'forgehub-freemius-bridge' ); ?>
			</p>
			<button type="button" id="wpfh-launch-fs-modal" class="button" style="background:linear-gradient(135deg, #2563eb, #1d4ed8);color:#fff;font-weight:800;padding:14px 32px;border-radius:10px;border:0;cursor:pointer;font-size:16px;box-shadow:0 4px 14px 0 rgba(37,99,235,0.4);transition:all 0.2s;">
				🔒 <?php esc_html_e( 'Open Secure Payment Window', 'forgehub-freemius-bridge' ); ?>
			</button>
			<div style="font-size:12px;color:#94a3b8;margin-top:16px;">
				<?php esc_html_e( '✓ 14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption', 'forgehub-freemius-bridge' ); ?>
			</div>
		</div>

		<script>
		(function(){
			function openFs() {
				if (typeof FS !== 'undefined' && FS.Checkout) {
					var handler = FS.Checkout.configure({
						plugin_id: <?php echo json_encode( (string) $plugin_id ); ?>,
						plan_id: <?php echo json_encode( (string) $plan_id ); ?>,
						pricing_id: <?php echo json_encode( (string) $pricing_id ); ?>,
						billing_cycle: <?php echo json_encode( $cycle ); ?>,
						public_key: <?php echo json_encode( $public_key ); ?>,
						user_email: <?php echo json_encode( $user_email ); ?>,
						user_firstname: <?php echo json_encode( $user_fname ); ?>,
						user_lastname: <?php echo json_encode( $user_lname ); ?>,
						user_country: <?php echo json_encode( $user_country ); ?>,
						image: 'https://wpforgehub.com/wp-content/themes/wpforgehub-theme/assets/images/wpfh-logo-192x192.png'
					});
					handler.open({
						name: 'WPForgeHub Pro License',
						licenses: <?php echo json_encode( $licenses ); ?>,
						success: function(response) {
							window.location.href = <?php echo json_encode( wc_get_account_endpoint_url( 'downloads' ) . '?welcome=1&order_completed=' . $order_id ); ?>;
						}
					});
				} else {
					var url = 'https://checkout.freemius.com/mode/dialog/plugin/' + <?php echo json_encode( $plugin_id ); ?> + '/plan/' + <?php echo json_encode( $plan_id ); ?> + '/pricing/' + <?php echo json_encode( $pricing_id ); ?> + '/?billing_cycle=' + <?php echo json_encode( $cycle ); ?> + '&user_email=' + encodeURIComponent(<?php echo json_encode( $user_email ); ?>) + '&user_firstname=' + encodeURIComponent(<?php echo json_encode( $user_fname ); ?>) + '&user_lastname=' + encodeURIComponent(<?php echo json_encode( $user_lname ); ?>) + '&user_country=' + encodeURIComponent(<?php echo json_encode( $user_country ); ?>);
					window.location.href = url;
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

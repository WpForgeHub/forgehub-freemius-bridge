<?php
/**
 * WPFH Freemius Bridge Admin Settings
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_FB_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 70 );
			add_action( 'woocommerce_settings_tabs_wpfh_fb', array( $this, 'render_settings' ) );
			add_action( 'woocommerce_update_options_wpfh_fb', array( $this, 'save_settings' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( WPFH_FB_FILE ), array( $this, 'add_action_links' ) );
	}

	public function add_settings_tab( $tabs ) {
		$tabs['wpfh_fb'] = __( 'Freemius Bridge', 'wpfh-freemius-bridge' );
		return $tabs;
	}

	public function add_admin_menu() {
		add_options_page(
			__( 'Freemius Bridge', 'wpfh-freemius-bridge' ),
			__( 'Freemius Bridge', 'wpfh-freemius-bridge' ),
			'manage_options',
			'wpfh-freemius-bridge',
			array( $this, 'render_standalone_settings' )
		);
	}

	public function register_settings() {
		register_setting( 'wpfh_fb_options', 'wpfh_fb_secret_key' );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_default_plugin_id' );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_default_public_key' );
	}

	public function add_action_links( $links ) {
		$settings_url  = class_exists( 'WooCommerce' ) ? admin_url( 'admin.php?page=wc-settings&tab=wpfh_fb' ) : admin_url( 'options-general.php?page=wpfh-freemius-bridge' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'wpfh-freemius-bridge' ) . '</a>';
		$pro_link      = '<a href="https://wpforgehub.com/plugins/wpfh-freemius-bridge/" style="color:#10b981;font-weight:700;" target="_blank">' . __( 'Upgrade to Pro', 'wpfh-freemius-bridge' ) . '</a>';
		array_unshift( $links, $settings_link, $pro_link );
		return $links;
	}

	private function get_settings() {
		$webhook_url = home_url( '/wp-json/wpfh/v1/freemius-webhook' );
		return array(
			array(
				'title' => __( 'Freemius Bridge Settings', 'wpfh-freemius-bridge' ),
				'type'  => 'title',
				'desc'  => sprintf( __( 'Synchronize Freemius transactions, subscriptions, and licenses seamlessly. Your Webhook Endpoint is: <code>%s</code>', 'wpfh-freemius-bridge' ), esc_url( $webhook_url ) ),
				'id'    => 'wpfh_fb_general_options',
			),
			array(
				'title'   => __( 'Freemius Secret Key', 'wpfh-freemius-bridge' ),
				'desc'    => __( 'Used to verify HMAC signature on incoming webhooks.', 'wpfh-freemius-bridge' ),
				'id'      => 'wpfh_fb_secret_key',
				'default' => 'sk_M9TizQ7:j9959KI])D7v6_Pi1C_pP',
				'type'    => 'password',
			),
			array(
				'title'   => __( 'Default Plugin ID', 'wpfh-freemius-bridge' ),
				'desc'    => __( 'Primary Freemius plugin ID for default embeds.', 'wpfh-freemius-bridge' ),
				'id'      => 'wpfh_fb_default_plugin_id',
				'default' => '44985',
				'type'    => 'text',
			),
			array(
				'title'   => __( 'Default Public Key', 'wpfh-freemius-bridge' ),
				'desc'    => __( 'Primary Freemius public key for checkout initialization.', 'wpfh-freemius-bridge' ),
				'id'      => 'wpfh_fb_default_public_key',
				'default' => 'pk_f9a888c3be313c0bbfbf4a627ad2b',
				'type'    => 'text',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wpfh_fb_general_options',
			),
		);
	}

	public function render_settings() {
		woocommerce_admin_fields( $this->get_settings() );
		$this->render_pro_upsell();
	}

	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	public function render_standalone_settings() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Freemius Bridge Settings', 'wpfh-freemius-bridge' ); ?></h1>
			<p><?php echo sprintf( esc_html__( 'Webhook Endpoint: %s', 'wpfh-freemius-bridge' ), '<code>' . esc_url( home_url( '/wp-json/wpfh/v1/freemius-webhook' ) ) . '</code>' ); ?></p>
			<?php $this->render_pro_upsell(); ?>
		</div>
		<?php
	}

	private function render_pro_upsell() {
		?>
		<div class="wpfh-pro-card" style="background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:24px;margin-top:24px;color:#e2e8f0;max-width:800px;">
			<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;padding-bottom:16px;margin-bottom:16px;">
				<div>
					<h3 style="margin:0;font-size:18px;color:#f8fafc;">🚀 WPFH Freemius Bridge Pro</h3>
					<p style="margin:4px 0 0 0;color:#94a3b8;font-size:13px;">Enterprise monetization and webhook recovery automation.</p>
				</div>
				<a href="https://wpforgehub.com/plugins/wpfh-freemius-bridge/" target="_blank" style="background:#10b981;color:#ffffff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700;font-size:13px;display:inline-block;">Upgrade to Pro &rarr;</a>
			</div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;">🔄 Automated Webhook Replay</strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;">Never drop an order. Automatically queues and retries failed webhooks with exponential backoff.</p>
				</div>
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;">💱 Real-Time Multi-Currency</strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;">Detect customer geolocation and display converted pricing in EUR, GBP, CAD, or AUD.</p>
				</div>
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;">👥 Multi-Seat License Delegation</strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;">Allow agency customers to delegate sub-licenses to their client domains directly from their dashboard.</p>
				</div>
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;">🍪 Affiliate & Referral Bridge</strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;">Propagate affiliate cookies through WooCommerce sessions into the Freemius checkout modal.</p>
				</div>
			</div>
		</div>
		<?php
	}
}

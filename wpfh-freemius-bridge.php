<?php
/**
 * Plugin Name:       WPFH Freemius Bridge for WooCommerce
 * Plugin URI:        https://wpforgehub.com/plugins/wpfh-freemius-bridge/
 * Description:       Synchronize Freemius payments, subscriptions, and licenses seamlessly with WooCommerce and Customer Portal.
 * Version:           1.0.0
 * Author:            WPForgeHub
 * Author URI:        https://wpforgehub.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpfh-freemius-bridge
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFH_FB_VERSION', '1.0.0' );
define( 'WPFH_FB_FILE', __FILE__ );
define( 'WPFH_FB_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFH_FB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Freemius SDK integration wrapper
 */
if ( ! function_exists( 'wpfh_fb_fs' ) ) {
	function wpfh_fb_fs() {
		global $wpfh_fb_fs;

		if ( ! isset( $wpfh_fb_fs ) ) {
			if ( file_exists( WPFH_FB_PATH . 'freemius/start.php' ) ) {
				require_once WPFH_FB_PATH . 'freemius/start.php';
			}

			if ( function_exists( 'fs_dynamic_init' ) ) {
				$wpfh_fb_fs = fs_dynamic_init( array(
					'id'             => '17962',
					'slug'           => 'wpfh-freemius-bridge',
					'type'           => 'plugin',
					'public_key'     => 'pk_dummy_fb_placeholder_key',
					'is_premium'     => false,
					'has_addons'     => false,
					'has_paid_plans' => true,
					'menu'           => array(
						'slug'       => 'wpfh-freemius-bridge',
						'first-path' => 'admin.php?page=wc-settings&tab=wpfh_fb',
						'parent'     => array(
							'slug' => 'woocommerce',
						),
					),
				) );
			}
		}

		return $wpfh_fb_fs;
	}

	if ( file_exists( WPFH_FB_PATH . 'freemius/start.php' ) ) {
		wpfh_fb_fs();
	}
}

/**
 * Main Plugin Class
 */
final class WPFH_Freemius_Bridge {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}

	public function init() {
		load_plugin_textdomain( 'wpfh-freemius-bridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		$this->includes();
	}

	private function includes() {
		require_once WPFH_FB_PATH . 'includes/class-wpfh-fb-webhook.php';
		require_once WPFH_FB_PATH . 'includes/class-wpfh-fb-checkout-modal.php';
		require_once WPFH_FB_PATH . 'includes/class-wpfh-fb-pricing-table.php';
		require_once WPFH_FB_PATH . 'includes/class-wpfh-fb-admin.php';

		// Instantiate
		WPFH_FB_Webhook::get_instance();
		WPFH_FB_Checkout_Modal::get_instance();
		WPFH_FB_Pricing_Table::get_instance();
		WPFH_FB_Admin::get_instance();
	}
}

WPFH_Freemius_Bridge::get_instance();

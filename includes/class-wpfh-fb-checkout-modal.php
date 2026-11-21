<?php
/**
 * WPFH Freemius Bridge Checkout Modal Bridge
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_FB_Checkout_Modal {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		$should_enqueue = false;
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			$should_enqueue = true;
		}
		if ( ! $should_enqueue && is_singular() ) {
			$post = get_post();
			if ( $post && ( has_shortcode( $post->post_content, 'wpfh_freemius_pricing' ) || has_shortcode( $post->post_content, 'wpfh_freemius_checkout' ) || false !== strpos( $post->post_content, 'wpfh-fs-buy-btn' ) ) ) {
				$should_enqueue = true;
			}
		}
		$should_enqueue = (bool) apply_filters( 'wpfh_fb_should_enqueue_scripts', $should_enqueue );
		if ( ! $should_enqueue ) {
			return;
		}

		// Enqueue Freemius Checkout JS SDK.
		wp_enqueue_script(
			'freemius-checkout-sdk',
			'https://checkout.freemius.com/checkout.js',
			array(),
			WPFH_FB_VERSION,
			true
		);

		$css_path = WPFH_FB_PATH . 'assets/css/freemius-bridge.css';
		$js_path  = WPFH_FB_PATH . 'assets/js/freemius-bridge.js';

		wp_enqueue_style(
			'wpfh-fb-bridge',
			WPFH_FB_URL . 'assets/css/freemius-bridge.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPFH_FB_VERSION
		);

		wp_enqueue_script(
			'wpfh-fb-bridge',
			WPFH_FB_URL . 'assets/js/freemius-bridge.js',
			array( 'jquery', 'freemius-checkout-sdk' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPFH_FB_VERSION,
			true
		);

		// Pass current user info if logged in.
		$user_email = '';
		$user_name  = '';
		$first_name = '';
		$last_name  = '';
		$country    = '';
		if ( is_user_logged_in() ) {
			$user       = wp_get_current_user();
			$user_email = $user->user_email;
			$user_name  = $user->display_name;
			$first_name = $user->first_name;
			$last_name  = $user->last_name;
			$country    = get_user_meta( $user->ID, 'billing_country', true );
		}

		$checkout_url  = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$downloads_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'downloads' ) : home_url( '/my-account/downloads/' );
		$checkout_logo = (string) get_option( 'wpfh_fb_checkout_logo', '' );
		if ( '' === $checkout_logo ) {
			$checkout_logo = (string) get_site_icon_url( 192 );
		}
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';

		wp_localize_script(
			'wpfh-fb-bridge',
			'wpfh_fb_config',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'checkout_url'    => esc_url_raw( $checkout_url ),
				'downloads_url'   => esc_url_raw( $downloads_url ),
				'checkout_logo'   => esc_url_raw( $checkout_logo ),
				'site_name'       => get_bloginfo( 'name' ),
				'currency_symbol' => $currency_symbol,
				'user_email'      => $user_email,
				'user_name'       => $user_name,
				'user_firstname'  => $first_name,
				'user_lastname'   => $last_name,
				'user_country'    => $country,
				'is_user_logged'  => is_user_logged_in(),
			)
		);
	}
}

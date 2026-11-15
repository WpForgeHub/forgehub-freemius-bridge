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
		// Enqueue Freemius Checkout JS SDK
		wp_enqueue_script(
			'freemius-checkout-sdk',
			'https://checkout.freemius.com/checkout.js',
			array(),
			null,
			true
		);

		wp_enqueue_style(
			'wpfh-fb-bridge',
			WPFH_FB_URL . 'assets/css/freemius-bridge.css',
			array(),
			WPFH_FB_VERSION
		);

		wp_enqueue_script(
			'wpfh-fb-bridge',
			WPFH_FB_URL . 'assets/js/freemius-bridge.js',
			array( 'jquery', 'freemius-checkout-sdk' ),
			WPFH_FB_VERSION,
			true
		);

		// Pass current user info if logged in
		$user_email = '';
		$user_name  = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$user_email = $user->user_email;
			$user_name  = $user->display_name;
		}

		wp_localize_script( 'wpfh-fb-bridge', 'wpfh_fb_config', array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'user_email' => $user_email,
			'user_name'  => $user_name,
		) );
	}
}

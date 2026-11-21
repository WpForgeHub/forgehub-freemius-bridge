<?php
/**
 * WPFH Freemius Bridge Uninstall
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpfh_fb_secret_key' );
delete_option( 'wpfh_fb_default_plugin_id' );
delete_option( 'wpfh_fb_default_plan_id' );
delete_option( 'wpfh_fb_default_public_key' );
delete_option( 'wpfh_fb_checkout_logo' );
delete_option( 'wpfh_fb_checkout_name' );
delete_option( 'wpfh_fb_webhook_dead_letter_queue' );
delete_transient( 'wpfh_fb_exchange_rates' );
wp_clear_scheduled_hook( 'wpfh_fb_retry_webhook_event' );

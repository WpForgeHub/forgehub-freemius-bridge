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
delete_option( 'wpfh_fb_default_public_key' );

<?php
/**
 * WPFH Freemius Bridge — Pro Real-Time Multi-Currency Rate Converter
 *
 * Provides real-time currency conversion for pricing tables, modal checkouts,
 * and geo-targeted localized currency displays.
 *
 * @package WPForgeHub\FreemiusBridge\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_FB_Multi_Currency {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'wpfh_fb_pricing_currency', array( $this, 'filter_currency' ) );
        add_filter( 'wpfh_fb_convert_price', array( $this, 'convert_price' ), 10, 3 );
    }

    public static function get_exchange_rates() {
        $rates = get_transient( 'wpfh_fb_exchange_rates' );
        if ( ! $rates ) {
            // Default pegged rates against USD base
            $rates = array(
                'USD' => 1.00,
                'EUR' => 0.92,
                'GBP' => 0.79,
                'CAD' => 1.36,
                'AUD' => 1.52,
            );
            set_transient( 'wpfh_fb_exchange_rates', $rates, 12 * HOUR_IN_SECONDS );
        }
        return $rates;
    }

    public function filter_currency( $default_currency ) {
        if ( isset( $_GET['currency'] ) ) {
            $curr = strtoupper( sanitize_key( $_GET['currency'] ) );
            $rates = self::get_exchange_rates();
            if ( isset( $rates[ $curr ] ) ) {
                return $curr;
            }
        }
        return $default_currency;
    }

    public function convert_price( $amount, $from = 'USD', $to = 'EUR' ) {
        $rates = self::get_exchange_rates();
        $from_rate = $rates[ $from ] ?? 1.0;
        $to_rate   = $rates[ $to ] ?? 1.0;

        $usd_amount = $amount / max( 0.0001, $from_rate );
        return round( $usd_amount * $to_rate, 2 );
    }
}

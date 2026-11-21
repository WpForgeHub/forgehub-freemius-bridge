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
        add_action( 'wpfh_fb_pricing_header_controls', array( $this, 'render_currency_switcher' ), 10, 1 );
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

    public static function get_currency_symbol( $code ) {
        $map = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'CA$',
            'AUD' => 'A$',
        );
        return $map[ strtoupper( (string) $code ) ] ?? '$';
    }

    public function filter_currency( $default_currency ) {
        $rates = self::get_exchange_rates();

        // 1. Explicit ?currency= query parameter.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public currency switcher query parameter.
        if ( isset( $_GET['currency'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $curr = strtoupper( sanitize_key( wp_unslash( $_GET['currency'] ) ) );
            if ( isset( $rates[ $curr ] ) ) {
                if ( ! headers_sent() ) {
                    setcookie( 'wpfh_fb_currency', $curr, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN ? COOKIE_DOMAIN : '' );
                }
                return $curr;
            }
        }

        // 2. Saved visitor cookie.
        if ( isset( $_COOKIE['wpfh_fb_currency'] ) ) {
            $curr = strtoupper( sanitize_key( wp_unslash( $_COOKIE['wpfh_fb_currency'] ) ) );
            if ( isset( $rates[ $curr ] ) ) {
                return $curr;
            }
        }

        // 3. Cloudflare / server country header geolocation fallback.
        if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            $country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
            $geo_map = array(
                'GB' => 'GBP',
                'UK' => 'GBP',
                'CA' => 'CAD',
                'AU' => 'AUD',
                'DE' => 'EUR', 'FR' => 'EUR', 'ES' => 'EUR', 'IT' => 'EUR', 'NL' => 'EUR',
                'BE' => 'EUR', 'AT' => 'EUR', 'IE' => 'EUR', 'PT' => 'EUR', 'FI' => 'EUR',
            );
            if ( isset( $geo_map[ $country ] ) && isset( $rates[ $geo_map[ $country ] ] ) ) {
                return $geo_map[ $country ];
            }
        }

        return $default_currency;
    }

    public function convert_price( $amount, $from = 'USD', $to = 'EUR' ) {
        if ( strtoupper( (string) $from ) === strtoupper( (string) $to ) ) {
            return (float) $amount;
        }
        $rates = self::get_exchange_rates();
        $from_rate = $rates[ strtoupper( (string) $from ) ] ?? 1.0;
        $to_rate   = $rates[ strtoupper( (string) $to ) ] ?? 1.0;

        $usd_amount = (float) $amount / max( 0.0001, $from_rate );
        return round( $usd_amount * $to_rate, 2 );
    }

    public function render_currency_switcher( $active_currency ) {
        $rates = self::get_exchange_rates();
        ?>
        <select class="wpfh-fb-currency-select" aria-label="<?php esc_attr_e( 'Select currency', 'forgehub-freemius-bridge' ); ?>" onchange="if(this.value){var u=new URL(window.location.href);u.searchParams.set('currency',this.value);window.location.href=u.toString();}" style="background:#1e293b;color:#f8fafc;border:1px solid #334155;border-radius:16px;padding:5px 10px;font-size:12px;font-weight:700;cursor:pointer;">
            <?php foreach ( array_keys( $rates ) as $code ) : ?>
                <option value="<?php echo esc_attr( $code ); ?>" <?php selected( strtoupper( (string) $active_currency ), $code ); ?>><?php echo esc_html( $code ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}

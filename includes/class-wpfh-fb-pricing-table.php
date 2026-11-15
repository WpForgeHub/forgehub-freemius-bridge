<?php
/**
 * WPFH Freemius Bridge Hybrid Pricing Table & Shortcode
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_FB_Pricing_Table {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'wpfh_freemius_pricing', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'plugin_id'     => '44985',
			'public_key'    => 'pk_f9a888c3be313c0bbfbf4a627ad2b',
			'plan_id'       => '79313',
			'annual_price'  => '39',
			'lifetime_price'=> '99',
		), $atts, 'wpfh_freemius_pricing' );

		ob_start();
		?>
		<div class="wpfh-pricing-bridge" data-plugin-id="<?php echo esc_attr( $atts['plugin_id'] ); ?>" data-public-key="<?php echo esc_attr( $atts['public_key'] ); ?>" data-plan-id="<?php echo esc_attr( $atts['plan_id'] ); ?>">
			<div class="wpfh-pricing-toggle-wrap">
				<button type="button" class="wpfh-cycle-btn active" data-cycle="annual"><?php esc_html_e( 'Annual Billing', 'wpfh-freemius-bridge' ); ?></button>
				<button type="button" class="wpfh-cycle-btn" data-cycle="lifetime"><?php esc_html_e( 'Lifetime Access', 'wpfh-freemius-bridge' ); ?> <span class="wpfh-save-tag"><?php esc_html_e( 'Best Value', 'wpfh-freemius-bridge' ); ?></span></button>
			</div>

			<div class="wpfh-tier-grid">
				<!-- Tier 1: Single Site -->
				<div class="wpfh-tier-card" data-tier="1" data-annual="39" data-lifetime="99" data-annual-pricing="119784" data-lifetime-pricing="119787">
					<div class="wpfh-tier-header">
						<h4><?php esc_html_e( 'Single Site', 'wpfh-freemius-bridge' ); ?></h4>
						<p class="wpfh-tier-desc"><?php esc_html_e( 'For solo creators & single projects', 'wpfh-freemius-bridge' ); ?></p>
					</div>
					<div class="wpfh-tier-price">
						<span class="wpfh-currency">$</span>
						<span class="wpfh-amount">39</span>
						<span class="wpfh-period">/ year</span>
					</div>
					<ul class="wpfh-tier-features">
						<li>✓ <?php esc_html_e( '1 Production Website', 'wpfh-freemius-bridge' ); ?></li>
						<li>✓ <?php esc_html_e( 'All Pro AI Features Included', 'wpfh-freemius-bridge' ); ?></li>
						<li>✓ <?php esc_html_e( '1 Year Priority Updates & Support', 'wpfh-freemius-bridge' ); ?></li>
					</ul>
					<button type="button" class="wpfh-buy-btn" data-licenses="1"><?php esc_html_e( 'Get Started', 'wpfh-freemius-bridge' ); ?></button>
				</div>

				<!-- Tier 2: 5 Sites (Featured) -->
				<div class="wpfh-tier-card featured" data-tier="5" data-annual="79" data-lifetime="199" data-annual-pricing="119785" data-lifetime-pricing="119788">
					<div class="wpfh-popular-badge"><?php esc_html_e( 'MOST POPULAR', 'wpfh-freemius-bridge' ); ?></div>
					<div class="wpfh-tier-header">
						<h4><?php esc_html_e( '5 Sites', 'wpfh-freemius-bridge' ); ?></h4>
						<p class="wpfh-tier-desc"><?php esc_html_e( 'For growing businesses & portfolios', 'wpfh-freemius-bridge' ); ?></p>
					</div>
					<div class="wpfh-tier-price">
						<span class="wpfh-currency">$</span>
						<span class="wpfh-amount">79</span>
						<span class="wpfh-period">/ year</span>
					</div>
					<ul class="wpfh-tier-features">
						<li>✓ <?php esc_html_e( 'Up to 5 Production Websites', 'wpfh-freemius-bridge' ); ?></li>
						<li>✓ <?php esc_html_e( 'All Pro AI Features Included', 'wpfh-freemius-bridge' ); ?></li>
						<li>✓ <?php esc_html_e( 'Priority VIP Support Desk', 'wpfh-freemius-bridge' ); ?></li>
					</ul>
					<button type="button" class="wpfh-buy-btn featured" data-licenses="5"><?php esc_html_e( 'Upgrade to 5 Sites', 'wpfh-freemius-bridge' ); ?></button>
				</div>

				<!-- Tier 3: Unlimited Sites -->
				<div class="wpfh-tier-card" data-tier="unlimited" data-annual="149" data-lifetime="399" data-annual-pricing="119786" data-lifetime-pricing="119789">
					<div class="wpfh-tier-header">
						<h4><?php esc_html_e( 'Unlimited Sites', 'wpfh-freemius-bridge' ); ?></h4>
						<p class="wpfh-tier-desc"><?php esc_html_e( 'For agencies & web developers', 'wpfh-freemius-bridge' ); ?></p>
					</div>
					<div class="wpfh-tier-price">
						<span class="wpfh-currency">$</span>
						<span class="wpfh-amount">149</span>
						<span class="wpfh-period">/ year</span>
					</div>
					<ul class="wpfh-tier-features">
						<li>✓ <?php esc_html_e( 'Unlimited Client & In-House Sites', 'wpfh-freemius-bridge' ); ?></li>
						<li>✓ <?php esc_html_e( 'Client License Key Re-assignment', 'wpfh-freemius-bridge' ); ?></li>
						<li>✓ <?php esc_html_e( 'Dedicated VIP Support & Feature Requests', 'wpfh-freemius-bridge' ); ?></li>
					</ul>
					<button type="button" class="wpfh-buy-btn" data-licenses="null"><?php esc_html_e( 'Get Unlimited Access', 'wpfh-freemius-bridge' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

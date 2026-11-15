<?php
/**
 * WPFH Freemius Bridge Hybrid Pricing Table & Single Product Bridge
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
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_pricing' ), 25 );
	}

	/**
	 * Render hybrid pricing selector on WooCommerce single product pages
	 */
	public function render_single_product_pricing() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$product_id   = $product->get_id();
		$plugin_id    = get_post_meta( $product_id, '_wpfh_freemius_plugin_id', true );
		if ( ! $plugin_id ) {
			$plugin_id = get_option( 'wpfh_fb_default_plugin_id', '44985' );
		}

		$public_key   = get_post_meta( $product_id, '_wpfh_freemius_public_key', true );
		if ( ! $public_key ) {
			$public_key = get_option( 'wpfh_fb_default_public_key', 'pk_862f10dd7623aebdb2cd2e4e4f840' );
		}

		$plan_id      = get_post_meta( $product_id, '_wpfh_freemius_plan_id', true ) ?: '79313';
		$pricing      = get_post_meta( $product_id, '_wpfh_freemius_pricing', true );
		$free_prod_id = get_post_meta( $product_id, '_wpfh_free_product_id', true ) ?: '44';

		if ( empty( $pricing ) || ! is_array( $pricing ) ) {
			return;
		}
		?>
		<div class="wpfh-pricing-bridge" style="background:#0f172a;color:#f8fafc;padding:24px;border-radius:14px;margin:24px 0;border:1px solid #334155;box-shadow:0 10px 25px -5px rgba(0, 0, 0, 0.3);">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
				<div>
					<h3 style="margin:0;font-size:18px;color:#38bdf8;font-weight:800;">⚡ <?php esc_html_e( 'Choose Your License & Edition', 'wpfh-freemius-bridge' ); ?></h3>
					<p style="margin:4px 0 0;font-size:12px;color:#94a3b8;"><?php esc_html_e( 'Start free forever or unlock enterprise automation.', 'wpfh-freemius-bridge' ); ?></p>
				</div>
				<div class="wpfh-cycle-toggle" style="background:#1e293b;border-radius:20px;padding:3px;display:flex;gap:4px;">
					<button type="button" class="wpfh-cycle-btn active" data-cycle="annual" style="background:#2563eb;color:#fff;border:0;padding:6px 14px;border-radius:16px;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.2s;">
						Annual
					</button>
					<button type="button" class="wpfh-cycle-btn" data-cycle="lifetime" style="background:transparent;color:#94a3b8;border:0;padding:6px 14px;border-radius:16px;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.2s;">
						Lifetime
					</button>
				</div>
			</div>

			<!-- 4-Tier License Grid -->
			<div class="wpfh-plan-grid" style="display:grid;grid-template-columns:repeat(4, 1fr);gap:10px;margin-bottom:20px;">
				<!-- 1. Free Community Edition -->
				<div class="wpfh-plan-opt" data-plan-type="free" data-free-id="<?php echo esc_attr( $free_prod_id ); ?>" style="background:#1e293b;border:2px solid #334155;border-radius:10px;padding:14px 8px;text-align:center;cursor:pointer;position:relative;transition:all 0.2s;">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">Community</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#10b981;margin:6px 0;">$0<span style="font-size:11px;color:#94a3b8;">/free</span></div>
					<div style="font-size:11px;color:#10b981;font-weight:600;">Forever Free</div>
				</div>

				<!-- 2. Pro 1 Site -->
				<div class="wpfh-plan-opt selected" data-plan-type="pro" data-pricing-id="<?php echo esc_attr( isset( $pricing['1_site']['pricing_id'] ) ? $pricing['1_site']['pricing_id'] : '119784' ); ?>" data-annual="19" data-lifetime="39" style="background:#1e293b;border:2px solid #2563eb;border-radius:10px;padding:14px 8px;text-align:center;cursor:pointer;position:relative;transition:all 0.2s;">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">1 Site</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;">$19<span style="font-size:11px;color:#94a3b8;">/yr</span></div>
					<div style="font-size:11px;color:#38bdf8;font-weight:600;">Standard Pro</div>
				</div>

				<!-- 3. Pro 5 Sites -->
				<div class="wpfh-plan-opt" data-plan-type="pro" data-pricing-id="<?php echo esc_attr( isset( $pricing['5_sites']['pricing_id'] ) ? $pricing['5_sites']['pricing_id'] : '119785' ); ?>" data-annual="39" data-lifetime="79" style="background:#1e293b;border:2px solid #334155;border-radius:10px;padding:14px 8px;text-align:center;cursor:pointer;position:relative;transition:all 0.2s;">
					<div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px;text-transform:uppercase;">Popular</div>
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">5 Sites</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;">$39<span style="font-size:11px;color:#94a3b8;">/yr</span></div>
					<div style="font-size:11px;color:#10b981;font-weight:600;">Best Value</div>
				</div>

				<!-- 4. Pro Unlimited -->
				<div class="wpfh-plan-opt" data-plan-type="pro" data-pricing-id="<?php echo esc_attr( isset( $pricing['unlimited']['pricing_id'] ) ? $pricing['unlimited']['pricing_id'] : '119786' ); ?>" data-annual="69" data-lifetime="129" style="background:#1e293b;border:2px solid #334155;border-radius:10px;padding:14px 8px;text-align:center;cursor:pointer;position:relative;transition:all 0.2s;">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;">Unlimited</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;">$69<span style="font-size:11px;color:#94a3b8;">/yr</span></div>
					<div style="font-size:11px;color:#a855f7;font-weight:600;">Agencies</div>
				</div>
			</div>

			<!-- Dynamic CTA Button -->
			<button type="button" id="wpfh-instant-buy-btn" 
					data-plan-type="pro"
					data-plugin-id="<?php echo esc_attr( $plugin_id ); ?>" 
					data-public-key="<?php echo esc_attr( $public_key ); ?>" 
					data-plan-id="<?php echo esc_attr( $plan_id ); ?>"
					data-pricing-id="<?php echo esc_attr( isset( $pricing['1_site']['pricing_id'] ) ? $pricing['1_site']['pricing_id'] : '119784' ); ?>"
					data-free-id="<?php echo esc_attr( $free_prod_id ); ?>"
					data-cycle="annual"
					style="width:100%;background:linear-gradient(135deg, #2563eb, #1d4ed8);color:#fff;font-weight:800;font-size:15px;padding:14px;border:0;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 14px 0 rgba(37, 99, 235, 0.4);transition:all 0.2s;">
				<span class="wpfh-btn-text">🔒 <?php esc_html_e( 'Secure Checkout via Freemius', 'wpfh-freemius-bridge' ); ?></span>
			</button>

			<div id="wpfh-guarantee-text" style="text-align:center;font-size:12px;color:#94a3b8;margin-top:12px;">
				<?php esc_html_e( '✓ 14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption', 'wpfh-freemius-bridge' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode render
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'plugin_id'     => '44985',
			'public_key'    => 'pk_862f10dd7623aebdb2cd2e4e4f840',
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
					<button type="button" class="wpfh-buy-btn" data-licenses="1"><?php esc_html_e( 'Get Started', 'wpfh-freemius-bridge' ); ?></button>
				</div>

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
					<button type="button" class="wpfh-buy-btn featured" data-licenses="5"><?php esc_html_e( 'Upgrade to 5 Sites', 'wpfh-freemius-bridge' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

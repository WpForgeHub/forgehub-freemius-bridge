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
		add_action( 'wp', array( $this, 'remove_default_variations_form' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_pricing' ), 25 );
	}

	public function remove_default_variations_form() {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	}

	/**
	 * Ecosystem Catalog Map
	 */
	public static function get_catalog() {
		return array(
			'ai-alt-text'           => array(
				'plugin_id'  => '44985',
				'plan_id'    => '79313',
				'public_key' => 'pk_862f10dd7623aebdb2cd2e4e4f840',
				'pricing'    => array(
					'1_site'    => array( 'pricing_id' => '119784', 'annual' => 19, 'lifetime' => 39 ),
					'5_sites'   => array( 'pricing_id' => '119785', 'annual' => 39, 'lifetime' => 79 ),
					'unlimited' => array( 'pricing_id' => '119786', 'annual' => 69, 'lifetime' => 129 ),
				),
			),
			'customer-portal'       => array(
				'plugin_id'  => '45079',
				'plan_id'    => '79477',
				'public_key' => 'pk_6f94939b02bf51f6626a5e2803403',
				'pricing'    => array(
					'1_site'    => array( 'pricing_id' => '120014', 'annual' => 19, 'lifetime' => 39 ),
					'5_sites'   => array( 'pricing_id' => '120015', 'annual' => 39, 'lifetime' => 79 ),
					'unlimited' => array( 'pricing_id' => '120016', 'annual' => 69, 'lifetime' => 129 ),
				),
			),
			'frictionless-checkout' => array(
				'plugin_id'  => '45082',
				'plan_id'    => '79483',
				'public_key' => 'pk_56291fa562e162ad646487ee9c46f',
				'pricing'    => array(
					'1_site'    => array( 'pricing_id' => '120017', 'annual' => 19, 'lifetime' => 39 ),
					'5_sites'   => array( 'pricing_id' => '120018', 'annual' => 39, 'lifetime' => 79 ),
					'unlimited' => array( 'pricing_id' => '120019', 'annual' => 69, 'lifetime' => 129 ),
				),
			),
			'image-memory-guard'    => array(
				'plugin_id'  => '45083',
				'plan_id'    => '79485',
				'public_key' => 'pk_c3faa2866670be0e103e2a06f97c7',
				'pricing'    => array(
					'1_site'    => array( 'pricing_id' => '120020', 'annual' => 19, 'lifetime' => 39 ),
					'5_sites'   => array( 'pricing_id' => '120021', 'annual' => 39, 'lifetime' => 79 ),
					'unlimited' => array( 'pricing_id' => '120022', 'annual' => 69, 'lifetime' => 129 ),
				),
			),
			'smart-redirect'        => array(
				'plugin_id'  => '45084',
				'plan_id'    => '79487',
				'public_key' => 'pk_af5c1cdaff59df492e19650419acc',
				'pricing'    => array(
					'1_site'    => array( 'pricing_id' => '120023', 'annual' => 19, 'lifetime' => 39 ),
					'5_sites'   => array( 'pricing_id' => '120024', 'annual' => 39, 'lifetime' => 79 ),
					'unlimited' => array( 'pricing_id' => '120025', 'annual' => 69, 'lifetime' => 129 ),
				),
			),
			'freemius-bridge'       => array(
				'plugin_id'  => '45085',
				'plan_id'    => '79489',
				'public_key' => 'pk_e7ed05bc2b2a042c51a1f56353eb6',
				'pricing'    => array(
					'1_site'    => array( 'pricing_id' => '120026', 'annual' => 19, 'lifetime' => 39 ),
					'5_sites'   => array( 'pricing_id' => '120027', 'annual' => 39, 'lifetime' => 79 ),
					'unlimited' => array( 'pricing_id' => '120028', 'annual' => 69, 'lifetime' => 129 ),
				),
			),
		);
	}

	/**
	 * Render hybrid pricing selector on WooCommerce single product pages
	 */
	public function render_single_product_pricing() {
		global $product;
		if ( ! $product || ! is_object( $product ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product ) {
			return;
		}

		$product_id = $product->get_id();
		$slug       = $product->get_slug();
		$catalog    = self::get_catalog();
		$cfg        = isset( $catalog[ $slug ] ) ? $catalog[ $slug ] : array();

		$plugin_id  = get_post_meta( $product_id, '_wpfh_freemius_plugin_id', true ) ?: ( isset( $cfg['plugin_id'] ) ? $cfg['plugin_id'] : '44985' );
		$public_key = get_post_meta( $product_id, '_wpfh_freemius_public_key', true ) ?: ( isset( $cfg['public_key'] ) ? $cfg['public_key'] : 'pk_862f10dd7623aebdb2cd2e4e4f840' );
		$plan_id    = get_post_meta( $product_id, '_wpfh_freemius_plan_id', true ) ?: ( isset( $cfg['plan_id'] ) ? $cfg['plan_id'] : '79313' );

		$meta_pricing = get_post_meta( $product_id, '_wpfh_freemius_pricing', true );
		$pricing      = ( ! empty( $meta_pricing ) && is_array( $meta_pricing ) ) ? $meta_pricing : ( isset( $cfg['pricing'] ) ? $cfg['pricing'] : array(
			'1_site'    => array( 'pricing_id' => '119784', 'annual' => 19, 'lifetime' => 39 ),
			'5_sites'   => array( 'pricing_id' => '119785', 'annual' => 39, 'lifetime' => 79 ),
			'unlimited' => array( 'pricing_id' => '119786', 'annual' => 69, 'lifetime' => 129 ),
		) );

		// Discover Free & Pro variation IDs dynamically
		$free_var_id = 0;
		$pro_var_id  = 0;
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$var_edition = get_post_meta( $child_id, 'attribute_edition', true );
				if ( 'Free' === $var_edition ) {
					$free_var_id = $child_id;
				} elseif ( 'Pro' === $var_edition ) {
					$pro_var_id = $child_id;
				}
			}
		}
		if ( ! $free_var_id ) {
			$free_var_id = $product_id;
		}
		if ( ! $pro_var_id ) {
			$pro_var_id = $product_id;
		}

		$p_1site     = isset( $pricing['1_site'] ) ? $pricing['1_site'] : array( 'pricing_id' => '119784', 'annual' => 19, 'lifetime' => 39 );
		$p_5sites    = isset( $pricing['5_sites'] ) ? $pricing['5_sites'] : array( 'pricing_id' => '119785', 'annual' => 39, 'lifetime' => 79 );
		$p_unlimited = isset( $pricing['unlimited'] ) ? $pricing['unlimited'] : array( 'pricing_id' => '119786', 'annual' => 69, 'lifetime' => 129 );
		?>
		<style>
			.single-product form.variations_form { display: none !important; }
			.wpfh-pricing-bridge {
				background: #0f172a;
				color: #f8fafc;
				padding: 24px;
				border-radius: 14px;
				margin: 24px 0;
				border: 1px solid #334155;
				box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35);
			}
			.wpfh-plan-opt {
				background: #1e293b;
				border: 2px solid #334155;
				border-radius: 10px;
				padding: 14px 8px;
				text-align: center;
				cursor: pointer;
				position: relative;
				transition: all 0.2s ease;
			}
			.wpfh-plan-opt:hover {
				border-color: #64748b;
				transform: translateY(-2px);
			}
			.wpfh-plan-opt.selected {
				border-color: #2563eb;
				background: #1e293b;
				box-shadow: 0 0 0 1px #2563eb;
			}
			.wpfh-plan-opt[data-plan-type="free"].selected {
				border-color: #10b981;
				box-shadow: 0 0 0 1px #10b981;
			}
			@media (max-width: 680px) {
				.wpfh-plan-grid {
					grid-template-columns: repeat(2, 1fr) !important;
				}
			}
		</style>

		<div class="wpfh-pricing-bridge" id="wpfh-pricing-bridge">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
				<div>
					<h3 style="margin:0;font-size:18px;color:#38bdf8;font-weight:800;letter-spacing:-0.02em;">⚡ <?php esc_html_e( 'Choose Your License & Edition', 'forgehub-freemius-bridge' ); ?></h3>
					<p style="margin:4px 0 0;font-size:13px;color:#94a3b8;"><?php esc_html_e( 'Start free forever or unlock enterprise automation.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
				<div class="wpfh-cycle-toggle" style="background:#1e293b;border:1px solid #334155;border-radius:20px;padding:3px;display:flex;gap:4px;">
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
				<div class="wpfh-plan-opt" data-plan-type="free" data-var-id="<?php echo esc_attr( $free_var_id ); ?>">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Community</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#10b981;margin:6px 0;">$0<span style="font-size:11px;color:#94a3b8;">/free</span></div>
					<div style="font-size:11px;color:#10b981;font-weight:600;">Forever Free</div>
				</div>

				<!-- 2. Pro 1 Site -->
				<div class="wpfh-plan-opt selected" data-plan-type="pro" data-var-id="<?php echo esc_attr( $pro_var_id ); ?>" data-pricing-id="<?php echo esc_attr( $p_1site['pricing_id'] ); ?>" data-annual="<?php echo esc_attr( $p_1site['annual'] ); ?>" data-lifetime="<?php echo esc_attr( $p_1site['lifetime'] ); ?>" data-licenses="1">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">1 Site</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;">$<?php echo esc_html( $p_1site['annual'] ); ?><span style="font-size:11px;color:#94a3b8;">/yr</span></div>
					<div style="font-size:11px;color:#38bdf8;font-weight:600;">Standard Pro</div>
				</div>

				<!-- 3. Pro 5 Sites -->
				<div class="wpfh-plan-opt" data-plan-type="pro" data-var-id="<?php echo esc_attr( $pro_var_id ); ?>" data-pricing-id="<?php echo esc_attr( $p_5sites['pricing_id'] ); ?>" data-annual="<?php echo esc_attr( $p_5sites['annual'] ); ?>" data-lifetime="<?php echo esc_attr( $p_5sites['lifetime'] ); ?>" data-licenses="5">
					<div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px;text-transform:uppercase;letter-spacing:0.05em;">Popular</div>
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">5 Sites</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;">$<?php echo esc_html( $p_5sites['annual'] ); ?><span style="font-size:11px;color:#94a3b8;">/yr</span></div>
					<div style="font-size:11px;color:#10b981;font-weight:600;">Best Value</div>
				</div>

				<!-- 4. Pro Unlimited -->
				<div class="wpfh-plan-opt" data-plan-type="pro" data-var-id="<?php echo esc_attr( $pro_var_id ); ?>" data-pricing-id="<?php echo esc_attr( $p_unlimited['pricing_id'] ); ?>" data-annual="<?php echo esc_attr( $p_unlimited['annual'] ); ?>" data-lifetime="<?php echo esc_attr( $p_unlimited['lifetime'] ); ?>" data-licenses="unlimited">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Unlimited</div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;">$<?php echo esc_html( $p_unlimited['annual'] ); ?><span style="font-size:11px;color:#94a3b8;">/yr</span></div>
					<div style="font-size:11px;color:#a855f7;font-weight:600;">Agencies</div>
				</div>
			</div>

			<!-- Dynamic CTA Button -->
			<button type="button" id="wpfh-instant-buy-btn" 
					data-plan-type="pro"
					data-plugin-id="<?php echo esc_attr( $plugin_id ); ?>" 
					data-public-key="<?php echo esc_attr( $public_key ); ?>" 
					data-plan-id="<?php echo esc_attr( $plan_id ); ?>"
					data-pricing-id="<?php echo esc_attr( $p_1site['pricing_id'] ); ?>"
					data-free-id="<?php echo esc_attr( $free_var_id ); ?>"
					data-pro-id="<?php echo esc_attr( $pro_var_id ); ?>"
					data-cycle="annual"
					data-licenses="1"
					style="width:100%;background:linear-gradient(135deg, #2563eb, #1d4ed8);color:#fff;font-weight:800;font-size:15px;padding:14px;border:0;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 14px 0 rgba(37, 99, 235, 0.4);transition:all 0.2s;">
				<span class="wpfh-btn-text">🔒 <?php esc_html_e( 'Proceed to Pro Checkout ($19/yr)', 'forgehub-freemius-bridge' ); ?></span>
			</button>

			<div id="wpfh-guarantee-text" style="text-align:center;font-size:12px;color:#94a3b8;margin-top:12px;">
				<?php esc_html_e( '✓ 14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption', 'forgehub-freemius-bridge' ); ?>
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
				<button type="button" class="wpfh-cycle-btn active" data-cycle="annual"><?php esc_html_e( 'Annual Billing', 'forgehub-freemius-bridge' ); ?></button>
				<button type="button" class="wpfh-cycle-btn" data-cycle="lifetime"><?php esc_html_e( 'Lifetime Access', 'forgehub-freemius-bridge' ); ?> <span class="wpfh-save-tag"><?php esc_html_e( 'Best Value', 'forgehub-freemius-bridge' ); ?></span></button>
			</div>

			<div class="wpfh-tier-grid">
				<div class="wpfh-tier-card" data-tier="1" data-annual="39" data-lifetime="99" data-annual-pricing="119784" data-lifetime-pricing="119787">
					<div class="wpfh-tier-header">
						<h4><?php esc_html_e( 'Single Site', 'forgehub-freemius-bridge' ); ?></h4>
						<p class="wpfh-tier-desc"><?php esc_html_e( 'For solo creators & single projects', 'forgehub-freemius-bridge' ); ?></p>
					</div>
					<div class="wpfh-tier-price">
						<span class="wpfh-currency">$</span>
						<span class="wpfh-amount">39</span>
						<span class="wpfh-period">/ year</span>
					</div>
					<button type="button" class="wpfh-buy-btn" data-licenses="1"><?php esc_html_e( 'Get Started', 'forgehub-freemius-bridge' ); ?></button>
				</div>

				<div class="wpfh-tier-card featured" data-tier="5" data-annual="79" data-lifetime="199" data-annual-pricing="119785" data-lifetime-pricing="119788">
					<div class="wpfh-popular-badge"><?php esc_html_e( 'MOST POPULAR', 'forgehub-freemius-bridge' ); ?></div>
					<div class="wpfh-tier-header">
						<h4><?php esc_html_e( '5 Sites', 'forgehub-freemius-bridge' ); ?></h4>
						<p class="wpfh-tier-desc"><?php esc_html_e( 'For growing businesses & portfolios', 'forgehub-freemius-bridge' ); ?></p>
					</div>
					<div class="wpfh-tier-price">
						<span class="wpfh-currency">$</span>
						<span class="wpfh-amount">79</span>
						<span class="wpfh-period">/ year</span>
					</div>
					<button type="button" class="wpfh-buy-btn featured" data-licenses="5"><?php esc_html_e( 'Upgrade to 5 Sites', 'forgehub-freemius-bridge' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

<?php
/**
 * WPFH Freemius Bridge Hybrid Pricing Table & Single Product Bridge
 *
 * 100% generic: reads all Freemius IDs from product post meta (or wp_options defaults)
 * and reads all tier prices directly from WooCommerce product variations.
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
		add_action( 'wp', array( $this, 'maybe_remove_default_variations_form' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_single_product_pricing' ), 25 );
	}

	/**
	 * Read Freemius configuration stored on a WooCommerce product.
	 * Returns null when the product is not configured for Freemius Bridge.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array|null
	 */
	public static function get_product_config( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return null;
		}
		$product_id = $product->get_id();
		$plugin_id  = (string) get_post_meta( $product_id, '_wpfh_freemius_plugin_id', true );
		$plan_id    = (string) get_post_meta( $product_id, '_wpfh_freemius_plan_id', true );
		$public_key = (string) get_post_meta( $product_id, '_wpfh_freemius_public_key', true );

		if ( '' === $plugin_id ) {
			return null;
		}

		return array(
			'plugin_id'  => $plugin_id,
			'plan_id'    => '' !== $plan_id ? $plan_id : (string) get_option( 'wpfh_fb_default_plan_id', '' ),
			'public_key' => '' !== $public_key ? $public_key : (string) get_option( 'wpfh_fb_default_public_key', '' ),
		);
	}

	/**
	 * Format a numeric price cleanly (drop trailing .00 for whole numbers).
	 *
	 * @param float|string $price Numeric price.
	 * @return string
	 */
	public static function format_amount( $price ) {
		$num = (float) $price;
		if ( floor( $num ) === $num ) {
			return (string) (int) $num;
		}
		return number_format( $num, 2, '.', '' );
	}

	/**
	 * Extract structured Free + Pro tiers from a WooCommerce variable product's variations.
	 * Each variation's price comes directly from WooCommerce ($variation->get_price()).
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array{free:array|null,tiers:array,has_annual:bool,has_lifetime:bool}
	 */
	public static function get_product_tiers( $product ) {
		$result = array(
			'free'         => null,
			'tiers'        => array(),
			'has_annual'   => false,
			'has_lifetime' => false,
		);

		if ( ! $product || ! is_object( $product ) || ! $product->is_type( 'variable' ) ) {
			return $result;
		}

		foreach ( $product->get_children() as $child_id ) {
			$var = wc_get_product( $child_id );
			if ( ! $var || 'publish' !== $var->get_status() ) {
				continue;
			}

			$raw_price = $var->get_price();
			if ( '' === $raw_price ) {
				$raw_price = $var->get_regular_price();
			}
			$price = is_numeric( $raw_price ) ? (float) $raw_price : 0.0;

			$attrs      = $var->get_attributes();
			$attr_label = trim( implode( ' ', array_values( $attrs ) ) );
			$attr_lower = strtolower( $attr_label );

			$plan_type  = (string) get_post_meta( $child_id, '_wpfh_fs_plan_type', true );
			$cycle      = (string) get_post_meta( $child_id, '_wpfh_fs_cycle', true );
			$licenses   = (string) get_post_meta( $child_id, '_wpfh_fs_licenses', true );
			$pricing_id = (string) get_post_meta( $child_id, '_wpfh_fs_pricing_id', true );
			$label      = (string) get_post_meta( $child_id, '_wpfh_fs_tier_label', true );
			$subtitle   = (string) get_post_meta( $child_id, '_wpfh_fs_tier_subtitle', true );
			$badge      = (string) get_post_meta( $child_id, '_wpfh_fs_tier_badge', true );

			// Auto-detect edition if meta not explicitly set.
			if ( '' === $plan_type ) {
				if ( 0.0 === $price || false !== strpos( $attr_lower, 'free' ) ) {
					$plan_type = 'free';
				} else {
					$plan_type = 'pro';
				}
			}

			if ( 'free' === $plan_type ) {
				$result['free'] = array(
					'variation_id' => $child_id,
					'price'        => $price,
					'label'        => '' !== $label ? $label : __( 'Community', 'forgehub-freemius-bridge' ),
					'subtitle'     => '' !== $subtitle ? $subtitle : __( 'Forever Free', 'forgehub-freemius-bridge' ),
				);
				continue;
			}

			// Auto-detect billing cycle if meta not explicitly set.
			if ( '' === $cycle ) {
				$cycle = ( false !== strpos( $attr_lower, 'lifetime' ) ) ? 'lifetime' : 'annual';
			}
			if ( 'lifetime' !== $cycle ) {
				$cycle = 'annual';
			}

			// Auto-detect license count if meta not explicitly set.
			if ( '' === $licenses ) {
				if ( false !== strpos( $attr_lower, 'unlimited' ) ) {
					$licenses = 'unlimited';
				} elseif ( preg_match( '/(\d+)/', $attr_label, $m ) ) {
					$licenses = $m[1];
				} else {
					$licenses = '1';
				}
			}

			if ( '' === $label ) {
				if ( 'unlimited' === $licenses ) {
					$label = __( 'Unlimited', 'forgehub-freemius-bridge' );
				} elseif ( '1' === $licenses ) {
					$label = __( '1 Site', 'forgehub-freemius-bridge' );
				} else {
					/* translators: %s: number of sites. */
					$label = sprintf( __( '%s Sites', 'forgehub-freemius-bridge' ), $licenses );
				}
			}

			$tier_key = (string) $licenses;
			if ( ! isset( $result['tiers'][ $tier_key ] ) ) {
				$result['tiers'][ $tier_key ] = array(
					'licenses' => $licenses,
					'label'    => $label,
					'subtitle' => $subtitle,
					'badge'    => $badge,
					'annual'   => null,
					'lifetime' => null,
				);
			} else {
				if ( '' !== $subtitle && '' === $result['tiers'][ $tier_key ]['subtitle'] ) {
					$result['tiers'][ $tier_key ]['subtitle'] = $subtitle;
				}
				if ( '' !== $badge && '' === $result['tiers'][ $tier_key ]['badge'] ) {
					$result['tiers'][ $tier_key ]['badge'] = $badge;
				}
			}

			$result['tiers'][ $tier_key ][ $cycle ] = array(
				'variation_id' => $child_id,
				'price'        => $price,
				'pricing_id'   => $pricing_id,
			);

			if ( 'annual' === $cycle ) {
				$result['has_annual'] = true;
			} elseif ( 'lifetime' === $cycle ) {
				$result['has_lifetime'] = true;
			}
		}

		// Preserve natural license order: numeric ascending, then 'unlimited'.
		uksort(
			$result['tiers'],
			function ( $a, $b ) {
				if ( 'unlimited' === $a && 'unlimited' === $b ) {
					return 0;
				}
				if ( 'unlimited' === $a ) {
					return 1;
				}
				if ( 'unlimited' === $b ) {
					return -1;
				}
				return (int) $a - (int) $b;
			}
		);

		return $result;
	}

	/**
	 * Resolve Freemius tier pricing array from WooCommerce variations (or optional post meta).
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array
	 */
	public static function get_product_pricing( $product ) {
		$parsed = self::get_product_tiers( $product );
		if ( ! empty( $parsed['tiers'] ) ) {
			$out = array();
			foreach ( $parsed['tiers'] as $key => $tier ) {
				$out[ $key ] = array(
					'pricing_id' => ! empty( $tier['annual']['pricing_id'] ) ? $tier['annual']['pricing_id'] : ( ! empty( $tier['lifetime']['pricing_id'] ) ? $tier['lifetime']['pricing_id'] : '' ),
					'annual'     => isset( $tier['annual']['price'] ) ? $tier['annual']['price'] : null,
					'lifetime'   => isset( $tier['lifetime']['price'] ) ? $tier['lifetime']['price'] : null,
				);
			}
			return $out;
		}
		if ( $product && is_object( $product ) ) {
			$meta = get_post_meta( $product->get_id(), '_wpfh_freemius_pricing', true );
			if ( is_array( $meta ) ) {
				return $meta;
			}
		}
		return array();
	}

	/**
	 * Remove WooCommerce default variations form ONLY on products configured for Freemius Bridge.
	 */
	public function maybe_remove_default_variations_form() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}
		$cfg   = self::get_product_config( $product );
		$tiers = self::get_product_tiers( $product );
		if ( $cfg && ( ! empty( $tiers['tiers'] ) || ! empty( $tiers['free'] ) ) ) {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		}
	}

	/**
	 * Render hybrid pricing selector on WooCommerce single product pages using WC variations.
	 */
	public function render_single_product_pricing() {
		global $product;
		if ( ! $product || ! is_object( $product ) ) {
			$product = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- intentionally restores WooCommerce's global product.
		}
		if ( ! $product ) {
			return;
		}

		$cfg = self::get_product_config( $product );
		if ( ! $cfg ) {
			return;
		}

		$parsed = self::get_product_tiers( $product );
		$free   = $parsed['free'];
		$tiers  = array_values( $parsed['tiers'] );

		if ( empty( $tiers ) && empty( $free ) ) {
			return;
		}

		$base_currency    = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$display_currency = (string) apply_filters( 'wpfh_fb_pricing_currency', $base_currency );
		if ( class_exists( 'WPFH_FB_Multi_Currency' ) && strtoupper( $display_currency ) !== strtoupper( $base_currency ) ) {
			$currency = WPFH_FB_Multi_Currency::get_currency_symbol( $display_currency );
		} else {
			$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
		}
		$convert = function ( $amount ) use ( $base_currency, $display_currency ) {
			if ( strtoupper( $display_currency ) === strtoupper( $base_currency ) ) {
				return (float) $amount;
			}
			return (float) apply_filters( 'wpfh_fb_convert_price', (float) $amount, $base_currency, $display_currency );
		};
		$default_cycle  = $parsed['has_annual'] ? 'annual' : 'lifetime';
		$show_toggle    = ( $parsed['has_annual'] && $parsed['has_lifetime'] );
		$card_count     = count( $tiers ) + ( $free ? 1 : 0 );
		$grid_cols      = max( 1, min( 4, $card_count ) );
		$initial_pro    = ! empty( $tiers ) ? $tiers[0] : null;
		$initial_type   = $initial_pro ? 'pro' : 'free';
		$initial_var_id = 0;
		$initial_pid    = '';
		$initial_price  = '0';
		$initial_lic    = '1';

		if ( $initial_pro ) {
			$slot           = ! empty( $initial_pro[ $default_cycle ] ) ? $initial_pro[ $default_cycle ] : ( ! empty( $initial_pro['annual'] ) ? $initial_pro['annual'] : $initial_pro['lifetime'] );
			$initial_var_id = $slot ? $slot['variation_id'] : $product->get_id();
			$initial_pid    = $slot ? $slot['pricing_id'] : '';
			$initial_price  = $slot ? self::format_amount( $convert( $slot['price'] ) ) : '0';
			$initial_lic    = $initial_pro['licenses'];
		} elseif ( $free ) {
			$initial_var_id = $free['variation_id'];
		}

		$free_var_id = $free ? $free['variation_id'] : 0;
		$period_text = 'lifetime' === $default_cycle ? __( 'lifetime', 'forgehub-freemius-bridge' ) : __( 'yr', 'forgehub-freemius-bridge' );
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

		<div class="wpfh-pricing-bridge" id="wpfh-pricing-bridge" data-currency="<?php echo esc_attr( $currency ); ?>">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
				<div>
					<h3 style="margin:0;font-size:18px;color:#38bdf8;font-weight:800;letter-spacing:-0.02em;"><?php esc_html_e( 'Choose Your License & Edition', 'forgehub-freemius-bridge' ); ?></h3>
					<p style="margin:4px 0 0;font-size:13px;color:#94a3b8;"><?php esc_html_e( 'Select your plan below to continue.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
				<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
				<?php do_action( 'wpfh_fb_pricing_header_controls', $display_currency ); ?>
				<?php if ( $show_toggle ) : ?>
				<div class="wpfh-cycle-toggle" style="background:#1e293b;border:1px solid #334155;border-radius:20px;padding:3px;display:flex;gap:4px;">
					<button type="button" class="wpfh-cycle-btn active" data-cycle="annual" style="background:#2563eb;color:#fff;border:0;padding:6px 14px;border-radius:16px;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.2s;">
						<?php esc_html_e( 'Annual', 'forgehub-freemius-bridge' ); ?>
					</button>
					<button type="button" class="wpfh-cycle-btn" data-cycle="lifetime" style="background:transparent;color:#94a3b8;border:0;padding:6px 14px;border-radius:16px;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.2s;">
						<?php esc_html_e( 'Lifetime', 'forgehub-freemius-bridge' ); ?>
					</button>
				</div>
				<?php endif; ?>
				</div>
			</div>

			<div class="wpfh-plan-grid" style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( (string) $grid_cols ); ?>, 1fr);gap:10px;margin-bottom:20px;">
				<?php if ( $free ) : ?>
				<div class="wpfh-plan-opt<?php echo 'free' === $initial_type ? ' selected' : ''; ?>"
					data-plan-type="free"
					data-var-id="<?php echo esc_attr( (string) $free['variation_id'] ); ?>">
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html( $free['label'] ); ?></div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#10b981;margin:6px 0;"><?php echo esc_html( $currency . self::format_amount( $free['price'] ) ); ?><span style="font-size:11px;color:#94a3b8;">/<?php esc_html_e( 'free', 'forgehub-freemius-bridge' ); ?></span></div>
					<div style="font-size:11px;color:#10b981;font-weight:600;"><?php echo esc_html( $free['subtitle'] ); ?></div>
				</div>
				<?php endif; ?>

				<?php
				foreach ( $tiers as $idx => $tier ) :
					$ann        = $tier['annual'];
					$lft        = $tier['lifetime'];
					$active     = ( 'lifetime' === $default_cycle && $lft ) ? $lft : ( $ann ? $ann : $lft );
					$ann_price  = $ann ? self::format_amount( $convert( $ann['price'] ) ) : '';
					$lft_price  = $lft ? self::format_amount( $convert( $lft['price'] ) ) : '';
					$ann_var_id = $ann ? (string) $ann['variation_id'] : '';
					$lft_var_id = $lft ? (string) $lft['variation_id'] : '';
					$ann_pid    = $ann ? (string) $ann['pricing_id'] : '';
					$lft_pid    = $lft ? (string) $lft['pricing_id'] : '';
					$disp_price = $active ? self::format_amount( $convert( $active['price'] ) ) : '0';
					$disp_var   = $active ? (string) $active['variation_id'] : '';
					$disp_pid   = $active ? (string) $active['pricing_id'] : '';
					$is_first   = ( 0 === $idx && 'pro' === $initial_type );
					$sub_color  = ! empty( $tier['badge'] ) ? '#10b981' : ( 'unlimited' === $tier['licenses'] ? '#a855f7' : '#38bdf8' );
					?>
				<div class="wpfh-plan-opt<?php echo $is_first ? ' selected' : ''; ?>"
					data-plan-type="pro"
					data-var-id="<?php echo esc_attr( $disp_var ); ?>"
					data-var-annual="<?php echo esc_attr( $ann_var_id ); ?>"
					data-var-lifetime="<?php echo esc_attr( $lft_var_id ); ?>"
					data-pricing-id="<?php echo esc_attr( $disp_pid ); ?>"
					data-annual-pricing="<?php echo esc_attr( $ann_pid ); ?>"
					data-lifetime-pricing="<?php echo esc_attr( $lft_pid ); ?>"
					data-annual="<?php echo esc_attr( $ann_price ); ?>"
					data-lifetime="<?php echo esc_attr( $lft_price ); ?>"
					data-licenses="<?php echo esc_attr( (string) $tier['licenses'] ); ?>">
					<?php if ( ! empty( $tier['badge'] ) ) : ?>
						<div style="position:absolute;top:-9px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:4px;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html( $tier['badge'] ); ?></div>
					<?php endif; ?>
					<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;"><?php echo esc_html( $tier['label'] ); ?></div>
					<div class="wpfh-price-val" style="font-size:22px;font-weight:800;color:#fff;margin:6px 0;"><?php echo esc_html( $currency . $disp_price ); ?><span style="font-size:11px;color:#94a3b8;"><?php echo 'lifetime' === $default_cycle ? ' ' . esc_html__( 'lifetime', 'forgehub-freemius-bridge' ) : '/' . esc_html__( 'yr', 'forgehub-freemius-bridge' ); ?></span></div>
					<?php if ( ! empty( $tier['subtitle'] ) ) : ?>
						<div style="font-size:11px;color:<?php echo esc_attr( $sub_color ); ?>;font-weight:600;"><?php echo esc_html( $tier['subtitle'] ); ?></div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>

			<button type="button" id="wpfh-instant-buy-btn"
				data-plan-type="<?php echo esc_attr( $initial_type ); ?>"
				data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
				data-plugin-id="<?php echo esc_attr( $cfg['plugin_id'] ); ?>"
				data-public-key="<?php echo esc_attr( $cfg['public_key'] ); ?>"
				data-plan-id="<?php echo esc_attr( $cfg['plan_id'] ); ?>"
				data-pricing-id="<?php echo esc_attr( $initial_pid ); ?>"
				data-free-id="<?php echo esc_attr( (string) $free_var_id ); ?>"
				data-pro-id="<?php echo esc_attr( (string) $initial_var_id ); ?>"
				data-var-id="<?php echo esc_attr( (string) $initial_var_id ); ?>"
				data-cycle="<?php echo esc_attr( $default_cycle ); ?>"
				data-licenses="<?php echo esc_attr( (string) $initial_lic ); ?>"
				style="width:100%;background:linear-gradient(135deg, #2563eb, #1d4ed8);color:#fff;font-weight:800;font-size:15px;padding:14px;border:0;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 14px 0 rgba(37, 99, 235, 0.4);transition:all 0.2s;">
				<span class="wpfh-btn-text">
					<?php
					if ( 'free' === $initial_type ) {
						esc_html_e( 'Get Free Edition ($0 — Instant Order)', 'forgehub-freemius-bridge' );
					} else {
						/* translators: 1: currency symbol + price, 2: billing period (yr or lifetime). */
						echo esc_html( sprintf( __( 'Proceed to Pro Checkout (%1$s/%2$s)', 'forgehub-freemius-bridge' ), $currency . $initial_price, $period_text ) );
					}
					?>
				</span>
			</button>

			<div id="wpfh-guarantee-text" style="text-align:center;font-size:12px;color:#94a3b8;margin-top:12px;">
				<?php esc_html_e( '14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption', 'forgehub-freemius-bridge' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode [wpfh_freemius_pricing].
	 * Supports product_id="<wc_product_id>" to render dynamically from WooCommerce variations,
	 * or explicit plugin_id / plan_id / public_key / annual_price / lifetime_price attributes.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id'     => '',
				'plugin_id'      => (string) get_option( 'wpfh_fb_default_plugin_id', '' ),
				'public_key'     => (string) get_option( 'wpfh_fb_default_public_key', '' ),
				'plan_id'        => (string) get_option( 'wpfh_fb_default_plan_id', '' ),
				'pricing_id'     => '',
				'annual_price'   => '',
				'lifetime_price' => '',
				'product_name'   => get_bloginfo( 'name' ),
			),
			$atts,
			'wpfh_freemius_pricing'
		);

		// When linked to a WooCommerce product, reuse the variation-driven renderer directly.
		if ( ! empty( $atts['product_id'] ) && function_exists( 'wc_get_product' ) ) {
			$target = wc_get_product( absint( $atts['product_id'] ) );
			if ( $target ) {
				global $product;
				$prev    = $product;
				$product = $target; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				ob_start();
				$this->render_single_product_pricing();
				$html    = ob_get_clean();
				$product = $prev; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				return $html;
			}
		}

		if ( '' === $atts['plugin_id'] || '' === $atts['annual_price'] ) {
			return '';
		}

		$currency = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
		$has_lft  = '' !== $atts['lifetime_price'];

		ob_start();
		?>
		<div class="wpfh-pricing-bridge"
			data-plugin-id="<?php echo esc_attr( $atts['plugin_id'] ); ?>"
			data-public-key="<?php echo esc_attr( $atts['public_key'] ); ?>"
			data-plan-id="<?php echo esc_attr( $atts['plan_id'] ); ?>"
			data-product-name="<?php echo esc_attr( $atts['product_name'] ); ?>">
			<?php if ( $has_lft ) : ?>
			<div class="wpfh-pricing-toggle-wrap">
				<button type="button" class="wpfh-cycle-btn active" data-cycle="annual"><?php esc_html_e( 'Annual Billing', 'forgehub-freemius-bridge' ); ?></button>
				<button type="button" class="wpfh-cycle-btn" data-cycle="lifetime"><?php esc_html_e( 'Lifetime Access', 'forgehub-freemius-bridge' ); ?></button>
			</div>
			<?php endif; ?>

			<div class="wpfh-tier-grid">
				<div class="wpfh-tier-card"
					data-tier="1"
					data-annual="<?php echo esc_attr( $atts['annual_price'] ); ?>"
					data-lifetime="<?php echo esc_attr( $atts['lifetime_price'] ); ?>"
					data-annual-pricing="<?php echo esc_attr( $atts['pricing_id'] ); ?>"
					data-lifetime-pricing="<?php echo esc_attr( $atts['pricing_id'] ); ?>">
					<div class="wpfh-tier-header">
						<h4><?php esc_html_e( 'Pro License', 'forgehub-freemius-bridge' ); ?></h4>
					</div>
					<div class="wpfh-tier-price">
						<span class="wpfh-currency"><?php echo esc_html( $currency ); ?></span>
						<span class="wpfh-amount"><?php echo esc_html( $atts['annual_price'] ); ?></span>
						<span class="wpfh-period">/ <?php esc_html_e( 'year', 'forgehub-freemius-bridge' ); ?></span>
					</div>
					<button type="button" class="wpfh-buy-btn" data-licenses="1"><?php esc_html_e( 'Get Started', 'forgehub-freemius-bridge' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

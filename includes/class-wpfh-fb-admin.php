<?php
/**
 * WPFH Freemius Bridge Admin Settings & WooCommerce Product/Variation Meta
 *
 * @package WPFH_Freemius_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPFH_FB_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 70 );
			add_action( 'woocommerce_settings_tabs_wpfh_fb', array( $this, 'render_settings' ) );
			add_action( 'woocommerce_update_options_wpfh_fb', array( $this, 'save_settings' ) );

			// WooCommerce Product Data tab for per-product Freemius configuration.
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data_panel' ) );

			// WooCommerce Variation fields for per-variation Freemius mapping.
			add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
		} else {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( WPFH_FB_FILE ), array( $this, 'add_action_links' ) );
	}

	public function add_settings_tab( $tabs ) {
		$tabs['wpfh_fb'] = __( 'Freemius Bridge', 'forgehub-freemius-bridge' );
		return $tabs;
	}

	public function add_admin_menu() {
		add_options_page(
			__( 'Freemius Bridge', 'forgehub-freemius-bridge' ),
			__( 'Freemius Bridge', 'forgehub-freemius-bridge' ),
			'manage_options',
			'forgehub-freemius-bridge',
			array( $this, 'render_standalone_settings' )
		);
	}

	public function register_settings() {
		register_setting( 'wpfh_fb_options', 'wpfh_fb_secret_key', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_default_plugin_id', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_default_plan_id', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_default_public_key', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_checkout_logo', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'wpfh_fb_options', 'wpfh_fb_checkout_name', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public function add_action_links( $links ) {
		$settings_url  = class_exists( 'WooCommerce' ) ? admin_url( 'admin.php?page=wc-settings&tab=wpfh_fb' ) : admin_url( 'options-general.php?page=forgehub-freemius-bridge' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'forgehub-freemius-bridge' ) . '</a>';
		$is_pro        = function_exists( 'wpfh_fb_fs' ) && wpfh_fb_fs()->can_use_premium_code();
		if ( $is_pro ) {
			array_unshift( $links, $settings_link );
		} else {
			$upgrade_url = function_exists( 'wpfh_fb_fs' ) ? wpfh_fb_fs()->get_upgrade_url() : 'https://wpforgehub.com/product/freemius-bridge/#pricing';
			$pro_link    = '<a href="' . esc_url( $upgrade_url ) . '" style="color:#10b981;font-weight:700;" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'forgehub-freemius-bridge' ) . '</a>';
			array_unshift( $links, $settings_link, $pro_link );
		}
		return $links;
	}

	private function get_settings() {
		$webhook_url = home_url( '/wp-json/wpfh/v1/freemius-webhook' );
		return array(
			array(
				'title' => __( 'Freemius Bridge Settings', 'forgehub-freemius-bridge' ),
				'type'  => 'title',
				/* translators: %s: Webhook endpoint URL. */
				'desc'  => sprintf( __( 'Synchronize Freemius transactions, subscriptions, and licenses seamlessly. Configure per-product Freemius IDs in the product editor (Freemius Bridge tab) and map prices directly on WooCommerce variations. Webhook Endpoint: <code>%s</code>', 'forgehub-freemius-bridge' ), esc_url( $webhook_url ) ),
				'id'    => 'wpfh_fb_general_options',
			),
			array(
				'title'       => __( 'Freemius Secret Key', 'forgehub-freemius-bridge' ),
				'desc'        => __( 'Used to verify HMAC-SHA256 signature on incoming webhooks.', 'forgehub-freemius-bridge' ),
				'id'          => 'wpfh_fb_secret_key',
				'default'     => '',
				'placeholder' => __( 'sk_...', 'forgehub-freemius-bridge' ),
				'type'        => 'password',
			),
			array(
				'title'       => __( 'Default Plugin ID', 'forgehub-freemius-bridge' ),
				'desc'        => __( 'Optional fallback Freemius Plugin ID for shortcodes or webhooks when not set on the product.', 'forgehub-freemius-bridge' ),
				'id'          => 'wpfh_fb_default_plugin_id',
				'default'     => '',
				'placeholder' => __( 'e.g. 12345', 'forgehub-freemius-bridge' ),
				'type'        => 'text',
			),
			array(
				'title'       => __( 'Default Plan ID', 'forgehub-freemius-bridge' ),
				'desc'        => __( 'Optional fallback Freemius Plan ID when not set on the product.', 'forgehub-freemius-bridge' ),
				'id'          => 'wpfh_fb_default_plan_id',
				'default'     => '',
				'placeholder' => __( 'e.g. 67890', 'forgehub-freemius-bridge' ),
				'type'        => 'text',
			),
			array(
				'title'       => __( 'Default Public Key', 'forgehub-freemius-bridge' ),
				'desc'        => __( 'Optional fallback Freemius Public Key for checkout initialization.', 'forgehub-freemius-bridge' ),
				'id'          => 'wpfh_fb_default_public_key',
				'default'     => '',
				'placeholder' => __( 'pk_...', 'forgehub-freemius-bridge' ),
				'type'        => 'text',
			),
			array(
				'title'       => __( 'Checkout Modal Logo URL', 'forgehub-freemius-bridge' ),
				'desc'        => __( 'Optional logo displayed in the Freemius Checkout modal and payment gateway. Leave blank to use your WordPress Site Icon.', 'forgehub-freemius-bridge' ),
				'id'          => 'wpfh_fb_checkout_logo',
				'default'     => '',
				'placeholder' => esc_url( get_site_icon_url( 192 ) ),
				'type'        => 'url',
			),
			array(
				'title'       => __( 'Default Checkout Title', 'forgehub-freemius-bridge' ),
				'desc'        => __( 'Optional fallback title shown inside the Freemius Checkout modal when product name is unavailable. Leave blank to use product/site title.', 'forgehub-freemius-bridge' ),
				'id'          => 'wpfh_fb_checkout_name',
				'default'     => '',
				'placeholder' => get_bloginfo( 'name' ),
				'type'        => 'text',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wpfh_fb_general_options',
			),
		);
	}

	public function render_settings() {
		woocommerce_admin_fields( $this->get_settings() );
		do_action( 'wpfh_fb_after_settings' );
		$this->render_pro_upsell();
	}

	public function save_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Add "Freemius Bridge" tab to WooCommerce Product Data metabox.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['wpfh_freemius'] = array(
			'label'    => __( 'Freemius Bridge', 'forgehub-freemius-bridge' ),
			'target'   => 'wpfh_freemius_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Render "Freemius Bridge" product data panel fields.
	 */
	public function render_product_data_panel() {
		global $post;
		$product_id = $post ? $post->ID : 0;
		?>
		<div id="wpfh_freemius_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => '_wpfh_freemius_plugin_id',
						'label'       => __( 'Freemius Plugin ID', 'forgehub-freemius-bridge' ),
						'placeholder' => __( 'e.g. 12345', 'forgehub-freemius-bridge' ),
						'desc_tip'    => true,
						'description' => __( 'Numeric Freemius product/plugin ID. Required to activate the Freemius hybrid pricing selector on this product.', 'forgehub-freemius-bridge' ),
						'value'       => get_post_meta( $product_id, '_wpfh_freemius_plugin_id', true ),
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'          => '_wpfh_freemius_plan_id',
						'label'       => __( 'Freemius Plan ID', 'forgehub-freemius-bridge' ),
						'placeholder' => __( 'e.g. 67890', 'forgehub-freemius-bridge' ),
						'desc_tip'    => true,
						'description' => __( 'Numeric Freemius paid plan ID for Pro variations.', 'forgehub-freemius-bridge' ),
						'value'       => get_post_meta( $product_id, '_wpfh_freemius_plan_id', true ),
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'          => '_wpfh_freemius_public_key',
						'label'       => __( 'Freemius Public Key', 'forgehub-freemius-bridge' ),
						'placeholder' => __( 'pk_...', 'forgehub-freemius-bridge' ),
						'desc_tip'    => true,
						'description' => __( 'Freemius public key used to initialize Freemius Checkout for this product.', 'forgehub-freemius-bridge' ),
						'value'       => get_post_meta( $product_id, '_wpfh_freemius_public_key', true ),
					)
				);
				?>
				<p class="form-field">
					<span class="description">
						<?php esc_html_e( 'Tier prices are read directly from your WooCommerce Variations. Open the Variations tab to set Regular Price, Billing Cycle, License Count, and optional Freemius Pricing ID for each variation.', 'forgehub-freemius-bridge' ); ?>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Freemius product-level meta fields.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_data_panel( $post_id ) {
		// WooCommerce already verifies woocommerce_meta_nonce before firing woocommerce_process_product_meta.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$fields = array(
			'_wpfh_freemius_plugin_id',
			'_wpfh_freemius_plan_id',
			'_wpfh_freemius_public_key',
		);
		foreach ( $fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( '' === $val ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $val );
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Render Freemius mapping fields inside each WooCommerce Variation panel.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation meta array.
	 * @param WP_Post $variation      Variation post object.
	 */
	public function render_variation_fields( $loop, $variation_data, $variation ) {
		$var_id = $variation->ID;
		?>
		<div class="wpfh-fb-variation-fields" style="border-top:1px solid #eee;padding-top:10px;margin-top:10px;clear:both;">
			<p class="form-row form-row-first">
				<label for="wpfh_fs_plan_type_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Freemius Edition', 'forgehub-freemius-bridge' ); ?></label>
				<select id="wpfh_fs_plan_type_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_plan_type[<?php echo esc_attr( $loop ); ?>]" class="select short">
					<?php $plan_type = get_post_meta( $var_id, '_wpfh_fs_plan_type', true ); ?>
					<option value="" <?php selected( $plan_type, '' ); ?>><?php esc_html_e( 'Auto (by price)', 'forgehub-freemius-bridge' ); ?></option>
					<option value="free" <?php selected( $plan_type, 'free' ); ?>><?php esc_html_e( 'Free', 'forgehub-freemius-bridge' ); ?></option>
					<option value="pro" <?php selected( $plan_type, 'pro' ); ?>><?php esc_html_e( 'Pro / Paid', 'forgehub-freemius-bridge' ); ?></option>
				</select>
			</p>
			<p class="form-row form-row-last">
				<label for="wpfh_fs_cycle_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Freemius Billing Cycle', 'forgehub-freemius-bridge' ); ?></label>
				<select id="wpfh_fs_cycle_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_cycle[<?php echo esc_attr( $loop ); ?>]" class="select short">
					<?php $cycle = get_post_meta( $var_id, '_wpfh_fs_cycle', true ); ?>
					<option value="" <?php selected( $cycle, '' ); ?>><?php esc_html_e( 'Auto', 'forgehub-freemius-bridge' ); ?></option>
					<option value="free" <?php selected( $cycle, 'free' ); ?>><?php esc_html_e( 'Free', 'forgehub-freemius-bridge' ); ?></option>
					<option value="annual" <?php selected( $cycle, 'annual' ); ?>><?php esc_html_e( 'Annual', 'forgehub-freemius-bridge' ); ?></option>
					<option value="lifetime" <?php selected( $cycle, 'lifetime' ); ?>><?php esc_html_e( 'Lifetime', 'forgehub-freemius-bridge' ); ?></option>
				</select>
			</p>
			<p class="form-row form-row-first">
				<label for="wpfh_fs_licenses_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Freemius Licenses (1, 5, unlimited)', 'forgehub-freemius-bridge' ); ?></label>
				<input type="text" id="wpfh_fs_licenses_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_licenses[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( get_post_meta( $var_id, '_wpfh_fs_licenses', true ) ); ?>" placeholder="1" />
			</p>
			<p class="form-row form-row-last">
				<label for="wpfh_fs_pricing_id_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Freemius Pricing ID (optional)', 'forgehub-freemius-bridge' ); ?></label>
				<input type="text" id="wpfh_fs_pricing_id_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_pricing_id[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( get_post_meta( $var_id, '_wpfh_fs_pricing_id', true ) ); ?>" placeholder="" />
			</p>
			<p class="form-row form-row-first">
				<label for="wpfh_fs_tier_label_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Tier Card Label', 'forgehub-freemius-bridge' ); ?></label>
				<input type="text" id="wpfh_fs_tier_label_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_tier_label[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( get_post_meta( $var_id, '_wpfh_fs_tier_label', true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. 1 Site', 'forgehub-freemius-bridge' ); ?>" />
			</p>
			<p class="form-row form-row-last">
				<label for="wpfh_fs_tier_subtitle_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Tier Card Subtitle', 'forgehub-freemius-bridge' ); ?></label>
				<input type="text" id="wpfh_fs_tier_subtitle_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_tier_subtitle[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( get_post_meta( $var_id, '_wpfh_fs_tier_subtitle', true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Best Value', 'forgehub-freemius-bridge' ); ?>" />
			</p>
			<p class="form-row form-row-full">
				<label for="wpfh_fs_tier_badge_<?php echo esc_attr( $loop ); ?>"><?php esc_html_e( 'Tier Highlight Badge (optional)', 'forgehub-freemius-bridge' ); ?></label>
				<input type="text" id="wpfh_fs_tier_badge_<?php echo esc_attr( $loop ); ?>" name="wpfh_fs_tier_badge[<?php echo esc_attr( $loop ); ?>]" value="<?php echo esc_attr( get_post_meta( $var_id, '_wpfh_fs_tier_badge', true ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Popular', 'forgehub-freemius-bridge' ); ?>" />
			</p>
		</div>
		<?php
	}

	/**
	 * Save Freemius variation-level meta fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation_fields( $variation_id, $loop ) {
		// WooCommerce verifies nonce before firing woocommerce_save_product_variation.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$map = array(
			'wpfh_fs_plan_type'     => '_wpfh_fs_plan_type',
			'wpfh_fs_cycle'         => '_wpfh_fs_cycle',
			'wpfh_fs_licenses'      => '_wpfh_fs_licenses',
			'wpfh_fs_pricing_id'    => '_wpfh_fs_pricing_id',
			'wpfh_fs_tier_label'    => '_wpfh_fs_tier_label',
			'wpfh_fs_tier_subtitle' => '_wpfh_fs_tier_subtitle',
			'wpfh_fs_tier_badge'    => '_wpfh_fs_tier_badge',
		);
		foreach ( $map as $post_key => $meta_key ) {
			if ( isset( $_POST[ $post_key ][ $loop ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $post_key ][ $loop ] ) );
				if ( '' === $val ) {
					delete_post_meta( $variation_id, $meta_key );
				} else {
					update_post_meta( $variation_id, $meta_key, $val );
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function render_standalone_settings() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Freemius Bridge Settings', 'forgehub-freemius-bridge' ); ?></h1>
			<?php
			/* translators: %s: Webhook endpoint URL. */
			$wpfh_fb_webhook_label = esc_html__( 'Webhook Endpoint: %s', 'forgehub-freemius-bridge' );
			?>
			<p><?php echo wp_kses( sprintf( $wpfh_fb_webhook_label, '<code>' . esc_url( home_url( '/wp-json/wpfh/v1/freemius-webhook' ) ) . '</code>' ), array( 'code' => array() ) ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpfh_fb_options' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpfh_fb_secret_key"><?php esc_html_e( 'Freemius Secret Key', 'forgehub-freemius-bridge' ); ?></label></th>
						<td><input name="wpfh_fb_secret_key" id="wpfh_fb_secret_key" type="password" value="<?php echo esc_attr( get_option( 'wpfh_fb_secret_key', '' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpfh_fb_default_plugin_id"><?php esc_html_e( 'Default Plugin ID', 'forgehub-freemius-bridge' ); ?></label></th>
						<td><input name="wpfh_fb_default_plugin_id" id="wpfh_fb_default_plugin_id" type="text" value="<?php echo esc_attr( get_option( 'wpfh_fb_default_plugin_id', '' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpfh_fb_default_plan_id"><?php esc_html_e( 'Default Plan ID', 'forgehub-freemius-bridge' ); ?></label></th>
						<td><input name="wpfh_fb_default_plan_id" id="wpfh_fb_default_plan_id" type="text" value="<?php echo esc_attr( get_option( 'wpfh_fb_default_plan_id', '' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpfh_fb_default_public_key"><?php esc_html_e( 'Default Public Key', 'forgehub-freemius-bridge' ); ?></label></th>
						<td><input name="wpfh_fb_default_public_key" id="wpfh_fb_default_public_key" type="text" value="<?php echo esc_attr( get_option( 'wpfh_fb_default_public_key', '' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpfh_fb_checkout_logo"><?php esc_html_e( 'Checkout Modal Logo URL', 'forgehub-freemius-bridge' ); ?></label></th>
						<td><input name="wpfh_fb_checkout_logo" id="wpfh_fb_checkout_logo" type="url" value="<?php echo esc_attr( get_option( 'wpfh_fb_checkout_logo', '' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpfh_fb_checkout_name"><?php esc_html_e( 'Default Checkout Title', 'forgehub-freemius-bridge' ); ?></label></th>
						<td><input name="wpfh_fb_checkout_name" id="wpfh_fb_checkout_name" type="text" value="<?php echo esc_attr( get_option( 'wpfh_fb_checkout_name', '' ) ); ?>" class="regular-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php do_action( 'wpfh_fb_after_settings' ); ?>
			<?php $this->render_pro_upsell(); ?>
		</div>
		<?php
	}

	private function render_pro_upsell() {
		if ( function_exists( 'wpfh_fb_fs' ) && wpfh_fb_fs()->can_use_premium_code() ) {
			return;
		}
		?>
		<div class="wpfh-pro-card" style="background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:24px;margin-top:24px;color:#e2e8f0;max-width:800px;">
			<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #334155;padding-bottom:16px;margin-bottom:16px;">
				<div>
					<h3 style="margin:0;font-size:18px;color:#f8fafc;"><?php esc_html_e( 'ForgeHub Freemius Bridge Pro', 'forgehub-freemius-bridge' ); ?></h3>
					<p style="margin:4px 0 0 0;color:#94a3b8;font-size:13px;"><?php esc_html_e( 'Enterprise monetization and webhook recovery automation.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
				<a href="https://wpforgehub.com/product/freemius-bridge/" target="_blank" rel="noopener noreferrer" style="background:#10b981;color:#ffffff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700;font-size:13px;display:inline-block;"><?php esc_html_e( 'Upgrade to Pro', 'forgehub-freemius-bridge' ); ?> &rarr;</a>
			</div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;"><?php esc_html_e( 'Automated Webhook Replay', 'forgehub-freemius-bridge' ); ?></strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;"><?php esc_html_e( 'Never drop an order. Automatically queues and retries failed webhooks with exponential backoff.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;"><?php esc_html_e( 'Real-Time Multi-Currency', 'forgehub-freemius-bridge' ); ?></strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;"><?php esc_html_e( 'Detect customer geolocation and display converted pricing in EUR, GBP, CAD, or AUD.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;"><?php esc_html_e( 'Multi-Seat License Delegation', 'forgehub-freemius-bridge' ); ?></strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;"><?php esc_html_e( 'Allow agency customers to delegate sub-licenses to their client domains directly from their dashboard.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
				<div style="background:#1e293b;padding:12px 16px;border-radius:8px;">
					<strong style="color:#38bdf8;"><?php esc_html_e( 'Affiliate & Referral Bridge', 'forgehub-freemius-bridge' ); ?></strong>
					<p style="margin:4px 0 0 0;color:#cbd5e1;"><?php esc_html_e( 'Propagate affiliate cookies through WooCommerce sessions into the Freemius checkout modal.', 'forgehub-freemius-bridge' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}

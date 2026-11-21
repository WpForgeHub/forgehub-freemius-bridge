<?php
/**
 * WPFH Freemius Bridge — Pro Agency Multi-Seat Delegation
 *
 * Enables agency and team tier customers to allocate sub-licenses,
 * invite client developers, and manage remote domain activation limits.
 *
 * @package WPForgeHub\FreemiusBridge\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_FB_Agency_Seats {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wpfh_fb_allocate_agency_seat', array( $this, 'ajax_allocate_seat' ) );
        add_action( 'wp_ajax_wpfh_fb_revoke_agency_seat', array( $this, 'ajax_revoke_seat' ) );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_order_seats_panel' ), 20, 1 );
    }

    public static function get_order_seats( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array();
        }
        return (array) $order->get_meta( '_wpfh_agency_allocated_seats' ) ?: array();
    }

    public function ajax_allocate_seat() {
        check_ajax_referer( 'wpfh_fb_agency_nonce' );

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $client_email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        $order = wc_get_order( $order_id );

        if ( ! $order || ! is_email( $client_email ) ) {
            wp_send_json_error( 'Invalid request parameters.' );
        }

        $user_id = get_current_user_id();
        if ( (int) $order->get_customer_id() !== $user_id && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $seats = self::get_order_seats( $order_id );
        $max_seats = absint( $order->get_meta( '_wpfh_agency_max_seats' ) ?: 10 );

        foreach ( $seats as $existing ) {
            if ( isset( $existing['email'] ) && strtolower( $existing['email'] ) === strtolower( $client_email ) ) {
                wp_send_json_error( 'This client email already has an allocated seat.' );
            }
        }

        if ( count( $seats ) >= $max_seats ) {
            wp_send_json_error( sprintf( 'Agency seat allocation limit (%d) reached.', $max_seats ) );
        }

        $seats[] = array(
            'email'     => $client_email,
            'allocated' => current_time( 'mysql' ),
            'key'       => 'SEAT-' . strtoupper( substr( md5( $client_email . time() ), 0, 12 ) ),
        );

        $order->update_meta_data( '_wpfh_agency_allocated_seats', $seats );
        $order->save();

        wp_send_json_success( array( 'message' => 'Agency seat allocated successfully!', 'total_seats' => count( $seats ) ) );
    }

    public function ajax_revoke_seat() {
        check_ajax_referer( 'wpfh_fb_agency_nonce' );

        $order_id = absint( $_POST['order_id'] ?? 0 );
        $client_email = isset( $_POST['client_email'] ) ? sanitize_email( wp_unslash( $_POST['client_email'] ) ) : '';
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( 'Order not found.' );
        }

        $user_id = get_current_user_id();
        if ( (int) $order->get_customer_id() !== $user_id && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $seats = self::get_order_seats( $order_id );
        $filtered = array();
        $revoked = false;

        foreach ( $seats as $s ) {
            if ( strtolower( $s['email'] ) === strtolower( $client_email ) ) {
                $revoked = true;
            } else {
                $filtered[] = $s;
            }
        }

        if ( $revoked ) {
            $order->update_meta_data( '_wpfh_agency_allocated_seats', $filtered );
            $order->save();
            wp_send_json_success( array( 'message' => 'Seat revoked.', 'remaining' => count( $filtered ) ) );
        }

        wp_send_json_error( 'Seat not found.' );
    }

    public function render_order_seats_panel( $order ) {
        if ( ! $order || ! is_object( $order ) || ! is_user_logged_in() ) {
            return;
        }
        $order_id = $order->get_id();
        if ( (int) $order->get_customer_id() !== get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        if ( ! $order->has_status( array( 'completed', 'processing' ) ) ) {
            return;
        }

        $seats     = self::get_order_seats( $order_id );
        $max_seats = absint( $order->get_meta( '_wpfh_agency_max_seats' ) ?: 10 );
        $nonce     = wp_create_nonce( 'wpfh_fb_agency_nonce' );
        $ajax_url  = admin_url( 'admin-ajax.php' );
        ?>
        <section class="wpfh-agency-seats-panel" style="margin-top:24px;padding:20px;background:#0f172a;color:#e2e8f0;border:1px solid #1e293b;border-radius:10px;">
            <h3 style="margin-top:0;color:#f8fafc;"><?php esc_html_e( 'Agency Sub-License Seats', 'forgehub-freemius-bridge' ); ?> (<?php echo absint( count( $seats ) ); ?> / <?php echo absint( $max_seats ); ?>)</h3>
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
                <input type="email" id="wpfh-agency-seat-email" placeholder="client@example.com" style="padding:8px 12px;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#f8fafc;min-width:240px;" />
                <button type="button" id="wpfh-agency-seat-add" class="button" data-order="<?php echo absint( $order_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Allocate Seat', 'forgehub-freemius-bridge' ); ?></button>
            </div>
            <?php if ( ! empty( $seats ) ) : ?>
                <table class="shop_table" style="width:100%;color:#e2e8f0;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Client Email', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Seat Key', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'forgehub-freemius-bridge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $seats as $seat ) : ?>
                            <tr>
                                <td><?php echo esc_html( (string) ( $seat['email'] ?? '' ) ); ?></td>
                                <td><code><?php echo esc_html( (string) ( $seat['key'] ?? '' ) ); ?></code></td>
                                <td><button type="button" class="button wpfh-agency-seat-revoke" data-order="<?php echo absint( $order_id ); ?>" data-email="<?php echo esc_attr( (string) ( $seat['email'] ?? '' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Revoke', 'forgehub-freemius-bridge' ); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <script>
            jQuery(function($){
                var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
                $('#wpfh-agency-seat-add').on('click', function(){
                    var btn = $(this), email = $('#wpfh-agency-seat-email').val().trim();
                    if (!email) { return; }
                    btn.prop('disabled', true);
                    $.post(ajaxUrl, {
                        action: 'wpfh_fb_allocate_agency_seat',
                        _ajax_nonce: btn.data('nonce'),
                        order_id: btn.data('order'),
                        client_email: email
                    }, function(res){
                        if (res && res.success) { window.location.reload(); }
                        else { btn.prop('disabled', false); alert((res && res.data) ? res.data : 'Allocation failed.'); }
                    });
                });
                $('.wpfh-agency-seat-revoke').on('click', function(){
                    var btn = $(this);
                    btn.prop('disabled', true);
                    $.post(ajaxUrl, {
                        action: 'wpfh_fb_revoke_agency_seat',
                        _ajax_nonce: btn.data('nonce'),
                        order_id: btn.data('order'),
                        client_email: btn.data('email')
                    }, function(res){
                        if (res && res.success) { window.location.reload(); }
                        else { btn.prop('disabled', false); alert((res && res.data) ? res.data : 'Revoke failed.'); }
                    });
                });
            });
            </script>
        </section>
        <?php
    }
}

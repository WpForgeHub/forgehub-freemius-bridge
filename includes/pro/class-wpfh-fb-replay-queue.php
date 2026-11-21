<?php
/**
 * WPFH Freemius Bridge — Pro Automated Webhook Replay Queue
 *
 * Implements durable dead-letter logging, exponential backoff retries,
 * and 1-click manual webhook replaying in WP Admin.
 *
 * @package WPForgeHub\FreemiusBridge\Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPFH_FB_Replay_Queue {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wpfh_fb_webhook_failed', array( __CLASS__, 'enqueue_failed_webhook' ), 10, 4 );
        add_action( 'wpfh_fb_retry_webhook_event', array( $this, 'process_scheduled_retry' ) );
        add_action( 'wp_ajax_wpfh_fb_manual_replay', array( $this, 'ajax_manual_replay' ) );
        add_action( 'wpfh_fb_after_settings', array( $this, 'render_admin_queue_panel' ) );
    }

    /**
     * Enqueue a failed webhook attempt for exponential retry
     */
    public static function enqueue_failed_webhook( $event_type, $raw_body, $signature, $error_msg ) {
        $queue = get_option( 'wpfh_fb_webhook_dead_letter_queue', array() );
        $webhook_id = 'wh_' . substr( md5( $raw_body . microtime() ), 0, 12 );

        $queue[ $webhook_id ] = array(
            'id'          => $webhook_id,
            'event'       => sanitize_text_field( $event_type ),
            'body'        => $raw_body,
            'signature'   => $signature,
            'attempts'    => 1,
            'last_error'  => sanitize_text_field( $error_msg ),
            'first_seen'  => current_time( 'mysql' ),
            'next_retry'  => time() + 300, // 5 minutes backoff
            'status'      => 'pending_retry',
        );

        update_option( 'wpfh_fb_webhook_dead_letter_queue', $queue, false );

        // Schedule cron retry
        if ( ! wp_next_scheduled( 'wpfh_fb_retry_webhook_event' ) ) {
            wp_schedule_single_event( time() + 300, 'wpfh_fb_retry_webhook_event' );
        }

        return $webhook_id;
    }

    public function process_scheduled_retry() {
        $queue = get_option( 'wpfh_fb_webhook_dead_letter_queue', array() );
        if ( empty( $queue ) ) {
            return;
        }

        $now = time();
        foreach ( $queue as $id => &$item ) {
            if ( $item['status'] !== 'pending_retry' || $item['next_retry'] > $now ) {
                continue;
            }

            // Retry replay
            $success = self::execute_replay( $item['body'], $item['signature'] );
            if ( $success ) {
                $item['status'] = 'resolved';
                $item['resolved_at'] = current_time( 'mysql' );
            } else {
                $item['attempts']++;
                if ( $item['attempts'] >= 5 ) {
                    $item['status'] = 'failed_exhausted';
                } else {
                    // Exponential backoff: 15m, 1h, 4h
                    $backoff = 300 * pow( 3, $item['attempts'] - 1 );
                    $item['next_retry'] = $now + $backoff;
                }
            }
        }

        update_option( 'wpfh_fb_webhook_dead_letter_queue', $queue, false );
    }

    public static function execute_replay( $body, $signature ) {
        if ( ! class_exists( 'WPFH_FB_Webhook' ) ) {
            return false;
        }

        $handler = WPFH_FB_Webhook::get_instance();
        if ( ! method_exists( $handler, 'process_payload' ) ) {
            return false;
        }

        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return false;
        }

        $result = $handler->process_payload( $payload );
        return ! is_wp_error( $result ) && ! empty( $result['success'] );
    }

    public function ajax_manual_replay() {
        check_ajax_referer( 'wpfh_fb_admin_nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $webhook_id = isset( $_POST['webhook_id'] ) ? sanitize_key( wp_unslash( $_POST['webhook_id'] ) ) : '';
        $queue = get_option( 'wpfh_fb_webhook_dead_letter_queue', array() );

        if ( empty( $queue[ $webhook_id ] ) ) {
            wp_send_json_error( 'Webhook record not found in dead-letter queue.' );
        }

        $item = &$queue[ $webhook_id ];
        $success = self::execute_replay( $item['body'], $item['signature'] );

        if ( $success ) {
            $item['status'] = 'resolved';
            $item['resolved_at'] = current_time( 'mysql' );
            update_option( 'wpfh_fb_webhook_dead_letter_queue', $queue, false );
            wp_send_json_success( array( 'message' => 'Webhook successfully replayed and resolved!' ) );
        }

        $item['attempts']++;
        update_option( 'wpfh_fb_webhook_dead_letter_queue', $queue, false );
        wp_send_json_error( 'Replay attempt failed. Check WooCommerce log files for errors.' );
    }

    public function render_admin_queue_panel() {
        $queue = get_option( 'wpfh_fb_webhook_dead_letter_queue', array() );
        $nonce = wp_create_nonce( 'wpfh_fb_admin_nonce' );
        ?>
        <div class="wpfh-pro-card" style="background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:20px;margin-top:24px;color:#e2e8f0;max-width:900px;">
            <h3 style="margin-top:0;color:#f8fafc;"><?php esc_html_e( 'Pro Webhook Dead-Letter Replay Queue', 'forgehub-freemius-bridge' ); ?></h3>
            <?php if ( empty( $queue ) || ! is_array( $queue ) ) : ?>
                <p style="color:#94a3b8;margin-bottom:0;"><?php esc_html_e( 'No failed webhooks in the dead-letter queue. Failed deliveries are automatically queued with exponential backoff.', 'forgehub-freemius-bridge' ); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="background:#1e293b;color:#e2e8f0;border:none;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Event', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Attempts', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Last Error', 'forgehub-freemius-bridge' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'forgehub-freemius-bridge' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $queue as $id => $item ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $id ); ?></code></td>
                                <td><?php echo esc_html( (string) ( $item['event'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $item['status'] ?? '' ) ); ?></td>
                                <td><?php echo absint( $item['attempts'] ?? 0 ); ?></td>
                                <td><?php echo esc_html( (string) ( $item['last_error'] ?? '' ) ); ?></td>
                                <td>
                                    <?php if ( 'resolved' !== ( $item['status'] ?? '' ) ) : ?>
                                        <button type="button" class="button button-small wpfh-fb-replay-btn" data-id="<?php echo esc_attr( $id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Replay Now', 'forgehub-freemius-bridge' ); ?></button>
                                    <?php else : ?>
                                        <span style="color:#10b981;font-weight:700;"><?php esc_html_e( 'Resolved', 'forgehub-freemius-bridge' ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                jQuery(function($){
                    $('.wpfh-fb-replay-btn').on('click', function(){
                        var btn = $(this);
                        btn.prop('disabled', true).text('Replaying...');
                        $.post(ajaxurl, {
                            action: 'wpfh_fb_manual_replay',
                            _ajax_nonce: btn.data('nonce'),
                            webhook_id: btn.data('id')
                        }, function(res){
                            if (res && res.success) {
                                btn.replaceWith('<span style="color:#10b981;font-weight:700;">Resolved</span>');
                            } else {
                                btn.prop('disabled', false).text('Replay Now');
                                alert((res && res.data) ? res.data : 'Replay failed.');
                            }
                        });
                    });
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
}

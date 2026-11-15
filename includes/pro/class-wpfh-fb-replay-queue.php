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
        add_action( 'wpfh_fb_retry_webhook_event', array( $this, 'process_scheduled_retry' ) );
        add_action( 'wp_ajax_wpfh_fb_manual_replay', array( $this, 'ajax_manual_replay' ) );
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
        if ( ! $payload || empty( $payload['event'] ) ) {
            return false;
        }

        return (bool) $handler->process_payload( $payload );
    }

    public function ajax_manual_replay() {
        check_ajax_referer( 'wpfh_fb_admin_nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $webhook_id = sanitize_key( $_POST['webhook_id'] ?? '' );
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
}

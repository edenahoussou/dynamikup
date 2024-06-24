<?php
namespace Dynamickup\WooCommerce;

class WooCommerceEvents {
    public static function init() {
        error_log('Initializing WooCommerceEvents');
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'order_created'], 10, 1);
        error_log('Added action for woocommerce_checkout_order_processed');
    }

    public static function order_created($order_id) {
        error_log('Processing order_created event for order ID: ' . $order_id);

        $order = wc_get_order($order_id);
        error_log('Retrieved order object for order ID: ' . $order_id);

        $data = [
            'id' => $order->get_id(),
            'total' => $order->get_total(),
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
            ],
            'status' => $order->get_status(),
        ];

        error_log('Generated data for order ID: ' . $order_id);

        self::send_to_laravel(DYNAMIK_WEBHOOK_BASE_URL . 'order', $data);
        error_log('Sent order data to Laravel for order ID: ' . $order_id);
    }

    private static function send_to_laravel($url, $data) {
        error_log('Sending order data to Laravel URL: ' . $url);
        error_log('Order data: ' . json_encode($data));

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending order to Laravel: ' . $response->get_error_message());
            set_transient('my_custom_webhook_error', 'Error sending order to Laravel: ' . $response->get_error_message(), 30);
        } else {
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);

            if (isset($decoded_body['success']) && $decoded_body['success'] == true) {
                error_log('Order data sent successfully to Laravel');
                set_transient('my_custom_webhook_success', 'Order data sent successfully!', 30);
            } else {
                error_log('Failed to send order data to Laravel: ' . json_encode($decoded_body));
            }
        }
    }
}

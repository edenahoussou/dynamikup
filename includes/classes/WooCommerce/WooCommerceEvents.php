<?php
namespace Dynamickup\WooCommerce;

class WooCommerceEvents
{
    public static function init()
    {
        error_log('Initializing WooCommerceEvents');
        add_action('woocommerce_order_status_completed', [__CLASS__, 'order_created'], 10, 1);
        error_log('Added action for woocommerce_order_status_processing');
    }

    public static function order_created($order_id)
    {
        error_log('Processing order_created event for order ID: ' . $order_id);

        $order = wc_get_order($order_id);
        error_log('Retrieved order object for order ID: ' . $order_id);

        $user = get_user_by('id', $order->get_customer_id());
        error_log('Retrieved order object for user ID: ' . $user);

        $data = self::transform_order_data($order, $user);
        error_log('Transformed data for order ID: ' . $order_id . ' with  user : ' . $user . 'data: ' . $data);

        self::send_to_laravel(DYNAMIK_WEBHOOK_BASE_URL . 'webhooks/order', $data);
        error_log('Sent order data to Laravel for order ID: ' . $order_id);
    }

    private static function transform_order_data($order, $user)
    {
        return [
            'firstname' => $order->get_billing_first_name(),
            'lastname' => $order->get_billing_last_name(),
            'civility' => $user->user_civility,
            'address' => $order->get_billing_address_1(),
            'birth' => $user->birth,
            'email' => $user->email,
            'organization' => $user->organization,
            'language' => $user->language,
            'function' => $user->function,
            'phone' => $order->get_billing_phone(),
            'admin' => [
                'firstname' => $order->get_billing_first_name(),
                'lastname' => $order->get_billing_last_name(),
                'civility' => $user->user_civility,
                'email' => $order->get_billing_last_email(),
            ],
            'consultants' => [
                [
                    'firstname' => $order->get_billing_first_name(),
                    'lastname' => $order->get_billing_last_name(),
                    'civility' => $user->user_civility,
                    'email' => $order->get_billing_last_email(),
                ],
                [
                    'firstname' => $order->get_billing_first_name(),
                    'lastname' => $order->get_billing_last_name(),
                    'civility' => $user->user_civility,
                    'email' => $order->get_billing_last_email(),
                ],
            ],
            'offer_subscription' => array_map(function ($item) use ($order) {
                return [
                    'order_id' => $order->get_id(),
                    'name' => $item->get_name(),
                    'is_white_mark' => $item->is_type('simple') ? false : true,
                    'number_of_consultants' => $item->get_meta('number_of_consultants'),
                    'number_of_test' => $item->get_meta('number_of_test'),
                    'product_id' => $item->get_product_id(),
                    'quantity' => $item->get_quantity(),
                    'price' => $item->get_total(),
                    'validity' => $item->get_meta('validity'),
                    'expire_at' => $item->get_meta('expire_at'),
                ];
            }, $order->get_items()),
            'custom_offer' => array_map(function ($item) use ($order) {
                return [
                    'order_id' => $order->get_id(),
                    'name_of_test' => 'avatars',
                    'number_of_test' => 5,
                    'product_id' => $item->get_product_id(),
                    'quantity' => $item->get_quantity(),
                    'price' => $item->get_total(),
                ];
            }, $order->get_items()),
        ];
    }

    private static function send_to_laravel($url, $data)
    {
        error_log('Sending order data to Laravel URL: ' . $url);
        error_log('Order data: ' . json_encode($data));

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Signature' => DYNAMIK_SIGNATURE,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending order to Laravel: ' . $response->get_error_message());
            set_transient('my_custom_webhook_error', 'Error sending order to Laravel: ' . $response->get_error_message(), 30);
        } else {
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);

            if (isset($decoded_body['message']) && $decoded_body['message'] == 'Order processed successfully.') {
                error_log('Order data sent successfully to Laravel');
                set_transient('my_custom_webhook_success', 'Order data sent successfully!', 30);
            } else {
                error_log('Failed to send order data to Laravel: ' . json_encode($decoded_body));
            }
        }
    }
}
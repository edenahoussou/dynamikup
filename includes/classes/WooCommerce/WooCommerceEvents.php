<?php

namespace Dynamickup\WooCommerce;

use Exception;



class WooCommerceEvents
{
    public static function init()
    /**
     * Initializes the WooCommerceEvents class by adding an action to the 'woocommerce_order_status_completed' hook.
     *
     * @return void
     */
    {
        add_action('woocommerce_order_status_completed', [__CLASS__, 'order_created'], 10, 1);
    }

    /**
     * Handles the order creation event by retrieving the order and user information,
     * building the order data, and sending it to Laravel via a webhook.
     *
     * @param int $order_id The ID of the created order.
     * @throws \Exception If there is an error retrieving the order or user information.
     * @return void
     */
    public static function order_created($order_id)
    {
        $order = wc_get_order($order_id);
        $user = get_user_by('id', $order->get_customer_id());

        $data = self::build_order_data($order, $user);
        self::send_to_laravel(DYNAMIK_WEBHOOK_BASE_URL . 'webhooks/order', $data);
    }

    /**
     * Builds the order data based on the given order and user.
     *
     * @param WC_Order $order The order object.
     * @param WP_User $user The user object.
     * @return array The order data.
     */
    private static function build_order_data($order, $user)
    {
        $order_item = $order->get_items();
        $data = [
            'firstname' => $order->get_billing_first_name() ?: get_user_meta($user->ID, 'first_name', true),
            'lastname' => $order->get_billing_last_name() ?: get_user_meta($user->ID, 'last_name', true),
            'civility' => $user->civility ?: $order->get_meta('_billing_civility'),
            'address' => $order->get_billing_address_1(),
            'birth' => $order->get_meta('_billing_birth'),
            'email' => get_user_meta($user->ID, 'user_email', true) ?: $order->get_billing_email(),
            'organization' => $order->get_billing_company() ?: $user->organization,
            'language' => $order->get_meta('_billing_language') ?: 'fr',
            'function' => $user->function,
            'phone' => $order->get_billing_phone() ?: $user->phone,
            'admin' => self::build_user_data($order, $user),
            // 'consultants' => self::build_consultants($order_item, $order),
            'offer_subscription' => self::build_offer_subscription($order_item),
            // 'custom_offer' => self::build_custom_offer($order_items),
        ];

        error_log('Order data: ' . print_r($data, true));

        return $data;
    }

    /**
     * Builds the user data based on the given order and user.
     *
     * @param WC_Order $order The order object.
     * @param WP_User $user The user object.
     * @return array The user data.
     */
    private static function build_user_data($order, $user)
    {
        return [
            'firstname' =>  get_user_meta($user->ID, 'first_name', true) ?: $order->get_billing_first_name(),
            'lastname' => get_user_meta($user->ID, 'last_name', true) ?: $order->get_billing_last_name(),
            'civility' => $order->get_meta('_billing_civility') ?: $user->user_civility,
            'email' => $user->email ?: $order->get_billing_email(),
        ];
    }

    /**
     * Builds an array of consultants based on the given order items.
     *
     * @param array $order_items The order items to build consultants from.
     * @return array The array of consultants.
     */
    private static function build_consultants($order_items, $order = null)
    {
        $consultants = [];

        foreach (range(0, 1) as $i) {
            $order_item = $order_items[$i] ?? null;
            $consultants[] = self::build_user_data($order, $order_item);
        }

        return $consultants;
    }

    /**
     * Builds offers for subscription based on the given order items.
     *
     * @param array $order_items The order items to build subscription offers from.
     * @return array The array of subscription offers.
     */
    private static function build_offer_subscription($order_items)
    {
        foreach ($order_items as $item) {
            $offers = self::build_offer($item);
        }

        return $offers;
    }

    /**
     * Builds an array of custom offers based on the given order items.
     * @param array $order_items The order items to build custom offers from.
     * @return array The array of custom offers.
     */
    private static function build_custom_offer($order_items)
    {
        $offers = [];

        foreach ($order_items as $item) {
            $offers[] = self::build_offer($item, 5);
        }

        return $offers;
    }

    /**
     * Builds an offer based on the given item and number of tests.
     * @param object $item The item to build the offer from.
     * @return array The offer as an associative array.
     */
    private static function build_offer($item)
    {
        error_log('Building offer for item: ' . $item->get_name());
        error_log('Item : ' . $item);

        $validity = function ($item) {
            // Check if the item is a variation and has the specific option selected
           if($item->get_meta('pa_duree') && $item->get_meta('pa_duree') == '1-an-payable-en-12-mois'){
               
               return 12;
           } 
           else {

               return 1;
           }
                
        };

        $validity = $validity($item);

        error_log('Validity: ' . $validity);

        $expire_at = date('Y-m-d', strtotime("+" . $validity . " months"));

        error_log('Expire date: ' . $expire_at);
        
        return [
            'order_id' => $item->get_order_id(),
            'name' => $item->get_name(),
            'is_white_mark' => in_array('Marque Blanche', wp_get_post_terms($item->get_product_id(), 'product_cat', ['fields' => 'names'])),
            'number_of_consultants' => get_post_meta($item->get_product_id(), 'number_of_consultants', true) ? : 1,
            'number_of_test' => get_post_meta($item->get_product_id(), 'number_of_test', true) ? :1,
            'product_id' => $item->get_product_id(),
            'quantity' => $item->get_quantity(),
            'price' => $item->get_total(),
            'validity' => isset($validity) ? $validity : null, // Assuming $validity might be defined elsewhere
            'expire_at' => isset($expire_at) ? $expire_at : null, // Assuming $expire_at might be defined elsewhere
        ];
    }

    /**
     * Sends data to Dynamik Up endpoint via a POST request.
     *
     * @param string $url The URL of the Laravel endpoint.
     * @param array $data The data to be sent.
     * @throws Exception If the request to the Laravel endpoint fails.
     * @return void
     */
    private static function send_to_laravel($url, $data)
    {       // Convertir les données en JSON
        $body = json_encode($data);
    
        // Générer la signature HMAC SHA256
        $secret = DYNAMIK_SIGNATURE; // Assurez-vous d'utiliser le même secret que votre API Laravel
        $signature = hash_hmac('sha256', $body, $secret);

        //log
    
        // Préparation de la requête
        $response = wp_remote_post($url, [
            'method'    => 'POST',
            'timeout'   => 45,
            'body'      => $body,
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Signature' => $signature, // Utiliser la signature générée dynamiquement
            ],
            'blocking' => true,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending order to Laravel: ' . $response->get_error_message());
            set_transient('my_custom_webhook_error', 'Error sending order to Laravel: ' . $response->get_error_message(), 30);
        } else {
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);

            if (isset($decoded_body['message']) && $decoded_body['message'] == 'Order processed successfully.') {
                error_log('Order data sent successfully to Dynamik Up Saas: ' . json_encode($decoded_body));
                
                self::send_notification_to_customers($data['email'], $decoded_body['data']['authLink']);
                
                set_transient('my_custom_webhook_success', 'Order data sent successfully!', 30);
            } else {
                error_log('Failed to send order data to Laravel: ' . json_encode($decoded_body));
                set_transient('my_custom_webhook_error', 'Failed to send order data to Laravel: ' . json_encode($decoded_body), 30);
            }
        }
    }


    private static function send_notification_to_customers($email, $auth_url) {
    
        $to = $email;
                $subject = 'Dynamik Up Saas Order Completed';
                $message = $auth_url;
                $headers = "From: WordPress <wordpress@dynamikup.com/>\r\n" .
                    "Content-type: text/plain\r\n";
                
        try {
            wp_mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            error_log('Error sending order notification to customers: ' . $e->getMessage());
        } finally {
            error_log('Order notification sent successfully to customer with email: ' . $email);
            return true;
        }
    }
}

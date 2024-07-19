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
        add_action('woocommerce_thankyou', [__CLASS__, 'redirect_freemium_user'], 10, 1);
        add_action('init', [__CLASS__, 'init_dynamik_webhook']);
    }

    
    public static function add_api_menu() {
        add_submenu_page(
            'email-settings',
            'Base Url de l\'endpoint',
            'Domaine de l\'endpoint + \api',
            'manage_options',
            'dynamikup-api',
            [__CLASS__, 'render_api_settings_page']
        );
        
    }

    /**
     * Renders the API settings page for Dynamik Up.
     *
     * @return void
     */
    public static function render_api_settings_page() {
        ?>
        <div class="wrap">
            <h1>Dynamik Up API Settings</h1>
            <form method="post" action="options.php">
                <?php wp_nonce_field('dynamikup-api-settings-nonce', 'dynamikup-api-settings-nonce'); ?>
                <label for="dynamik_webhook_base_url">Dynamik Up Webhook Base URL:</label>
                <input type="text" id="dynamik_webhook_base_url" name="dynamik_webhook_base_url" value="<?php echo get_option('dynamik_webhook_base_url'); ?>" required>
                <input type="submit" name="submit" value="Save">
            </form>
        </div>
        <?php
    }
    
    /**
     * Saves the API base URL submitted via the form in the WooCommerce API settings page.
     *
     * This function checks if the form has been submitted and if the nonce is valid.
     * If so, it sanitizes the submitted base URL and updates the 'dynamik_webhook_base_url' option.
     *
     * @return void
     */
    public static function save_api_base_url() {
        if (isset($_POST['submit']) && check_admin_referer('dynamikup-api-settings-nonce', 'dynamikup-api-settings-nonce')) {
            $dynamik_webhook_base_url = sanitize_text_field($_POST['dynamik_webhook_base_url']);
            update_option('dynamik_webhook_base_url', $dynamik_webhook_base_url);
        }
    }
    
    public static function load_api_base_url() {
        add_action('admin_init', [__CLASS__, 'save_api_base_url']);
        add_action('admin_menu', [__CLASS__, 'add_api_menu']);
        
        $dynamik_webhook_base_url = get_option('dynamik_webhook_base_url');
        if ($dynamik_webhook_base_url) {
            define('DYNAMIK_WEBHOOK_BASE_URL', $dynamik_webhook_base_url);
        }
    }
    
    /**
     * Initializes the Dynamik Webhook by calling the init methods of the UserEvents,
     * WooCommerceEvents, and EmailSettings classes.
     *
     * @return void
     */
    public static function init_dynamik_webhook() {
        \Dynamickup\User\UserEvents::init();
        \Dynamickup\WooCommerce\WooCommerceEvents::load_api_base_url();
        \Dynamickup\WooCommerce\WooCommerceEvents::init();
        \Dynamickup\WooCommerce\EmailSettings::init();
    }

    /**
     * Redirects the user to a specific page if they have purchased the "freemium" product.
     *
     * @param int $order_id The ID of the created order.
     * @return void
     */
    public static function redirect_freemium_user($order_id)
    {
        error_log('redirect_freemium_user started for order ID: ' . $order_id);
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Order not found with ID: ' . $order_id);
            return;
        }
        $order_items = $order->get_items();
        $product_id = 601;
        $page_slug = 'freemium';

        $product_found = array_reduce($order_items, function ($found, $item) use ($product_id) {
            if ($found || $item->get_product_id() == $product_id) {
                return true;
            }
            return $found;
        }, false);

        if ($product_found) {
            error_log('Freemium product found in order ID: ' . $order_id);
            $page = get_page_by_path($page_slug, OBJECT, 'page');
            if ($page) {
                error_log('Redirecting to page: ' . $page_slug);
                self::order_created($order->get_id());
                \wp_safe_redirect(get_permalink($page->ID));
                exit;
            } else {
                error_log('Page not found for slug: ' . $page_slug);
            }
        } else {
            error_log('Freemium product not found in order ID: ' . $order_id);
        }
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
        ];


        if (!empty($order_item)) {
            foreach ($order_item as $item) {
                if ($item->get_product_id() == 1369 || $item->get_product_id() == 1373) {
                    $data['custom_offer'] = self::build_custom_offer($item);
                    break;
                } else {
                    $data['offer_subscription'] = self::build_offer_subscription($item);
                    break;
                }
            }
        }

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
        return self::build_offer($order_items);
    }


    /**
     * Builds offers for custom subscription based on the given order items.
     *
     * @param array $order_items The order items to build custom subscription offers from.
     * @return array The array of custom subscription offers.
     */
    private static function build_custom_offer($order_items)
    {
        return self::build_offer($order_items);
    }



    /**
     * Builds an offer based on the given order item.
     *
     * @param WC_Order_Item_Product $item The order item.
     * @return array The offer array.
     */
    private static function build_offer($item)
    {
        $product_id = $item->get_product_id();

        $validity = $product_id == 601 ? 12 : (($item->get_meta('pa_duree') && $item->get_meta('pa_duree') == '1-an-payable-en-12-mois') ? 12 : 1);

        $expire_at = date('Y-m-d', strtotime("+" . $validity . " months"));

        if ($product_id == 601) {
            $duree = $item->get_meta('pa_duree');

            if ($duree && $duree == 'pack-de-10-outils') {
                $number_of_tests = 10;
            } elseif ($duree && $duree == 'pack-de-100-outils') {
                $number_of_tests = 100;
            } elseif ($duree && $duree == '2-outils-gratuits') {
                $number_of_tests = 0;
            }
        } else {
            $number_of_tests = get_post_meta($item->get_product_id(), 'number_of_test', true) ?? 1;
        }

        $is_white_mark = $product_id != 601 && in_array('Marque Blanche', wp_get_post_terms($item->get_product_id(), 'product_cat', ['fields' => 'names']));
        $number_of_consultants = $product_id != 601 ? (get_post_meta($item->get_product_id(), 'number_of_consultants', true) ?? 1) : 1;


        return [
            'order_id' => $item->get_order_id(),
            'name_of_test' => ($item->get_product_id() == 1369 || $item->get_product_id() == 1373) ? 'avatars' : null,
            'name' => ($item->get_product_id() != 1369 || $item->get_product_id() != 1373) ? $item->get_name() : null,
            'is_white_mark' => $is_white_mark,
            'number_of_consultants' => $number_of_consultants,
            'number_of_test' => $number_of_tests,
            'product_id' => $product_id,
            'quantity' => $item->get_quantity(),
            'price' => $item->get_total(),
            'validity' => $validity,
            'expire_at' => $expire_at
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
    {
        $body = json_encode($data);

        $secret = DYNAMIK_SIGNATURE;
        $signature = hash_hmac('sha256', $body, $secret);


        $response = wp_remote_post($url, [
            'method'    => 'POST',
            'timeout'   => 3600,
            'body'      => $body,
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Signature' => $signature,
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

                EmailSettings::send_notification_to_customers($data['email'], $decoded_body['data']['authLink']);

                set_transient('my_custom_webhook_success', 'Order data sent successfully!', 30);
            } else {
                error_log('Failed to send order data to Laravel: ' . json_encode($decoded_body));
                set_transient('my_custom_webhook_error', 'Failed to send order data to Laravel: ' . json_encode($decoded_body), 30);
            }
        }
    }
}

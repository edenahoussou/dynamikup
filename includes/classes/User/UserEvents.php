<?php
namespace Dynamickup\User;

class UserEvents {
    public static function init() {
        error_log('Initializing UserEvents');
        add_action('user_register', [__CLASS__, 'user_registered'], 10, 1);
        error_log('User_register hook added');
    }

    public static function user_registered($user_id) {
        error_log('user_registered called with user_id: ' . $user_id);
        $user = get_userdata($user_id);
        error_log('user_registered got user: ' . print_r($user, true));
        $data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
        ];
        error_log('user_registered built data: ' . print_r($data, true));

        self::send_to_laravel(DYNAMIK_WEBHOOK_BASE_URL . 'user/register', $data);
        error_log('user_registered called send_to_laravel');
    }
    private static function send_to_laravel($url, $data) {
        error_log('Sending user data to Laravel URL: ' . $url);
        error_log('User data: ' . json_encode($data));

        $response = wp_remote_post($url, [
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Signature' => DYNAMIK_SIGNATURE
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending user to Laravel: ' . $response->get_error_message());
            set_transient('my_custom_webhook_error', 'Error sending user to Laravel: ' . $response->get_error_message(), 30);
        } else {
            $body = wp_remote_retrieve_body($response);
            $decoded_body = json_decode($body, true);

            if (isset($decoded_body['success']) && $decoded_body['success'] == true) {
                error_log('User data sent successfully to Laravel');
                set_transient('my_custom_webhook_success', 'User data sent successfully!', 30);
            } else {
                error_log('Failed to send user data to Laravel: ' . json_encode($decoded_body));
            }
        }
    }
}

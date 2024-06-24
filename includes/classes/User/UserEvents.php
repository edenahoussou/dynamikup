<?php
namespace Dynamickup\User;

class UserEvents {
    public static function init() {
        add_action('user_register', [__CLASS__, 'userRegistered'], 10, 1);
    }

    private static function userRegistered($userId) {
        $user = get_userdata($userId);
        $data = self::buildUserData($user);
        self::sendToLaravel($data);
    }

    private static function buildUserData($user) {
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'firstName' => get_user_meta($user->ID, 'first_name', true),
            'lastName' => get_user_meta($user->ID, 'last_name', true),
            'email' => $user->user_email,
        ];
    }

    private static function sendToLaravel($data) {
        $url = DYNAMIK_WEBHOOK_BASE_URL . 'user/register';
        $response = wp_remote_post($url, self::buildRequestArgs($data));
        self::handleResponse($response);
    }

    private static function buildRequestArgs($data) {
        return [
            'method' => 'POST',
            'body' => wp_json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Signature' => DYNAMIK_SIGNATURE
            ],
        ];
    }

    private static function handleResponse($response) {
        if (is_wp_error($response)) {
            self::handleError($response);
        } else {
            self::handleSuccess($response);
        }
    }

    private static function handleError($error) {
        error_log('Error sending user to Laravel: ' . $error->get_error_message());
        set_transient('my_custom_webhook_error', 'Error sending user to Laravel: ' . $error->get_error_message(), 30);
    }

    private static function handleSuccess($response) {
        $body = wp_remote_retrieve_body($response);
        $decodedBody = json_decode($body, true);
        if (isset($decodedBody['success']) && $decodedBody['success']) {
            error_log('User data sent successfully to Laravel');
            set_transient('my_custom_webhook_success', 'User data sent successfully!', 30);
        } else {
            error_log('Failed to send user data to Laravel: ' . wp_json_encode($decodedBody));
        }
    }
}


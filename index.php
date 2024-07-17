<?php
/*
Plugin Name: DynamiK Up API
Description: DynamiK Up Webhook plugin to listen for WordPress events
Version: 1.0
Author: Eden Ahoussou & Banel Semasoussi
Author URI: https://github.com/edenahoussou
*/

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent direct access to the file
defined('ABSPATH') || exit;

if (defined('WEBHOOK_SECRET_KEY')) {
    $webhook_secret_key = WEBHOOK_SECRET_KEY;
    define('DYNAMIK_SIGNATURE', $webhook_secret_key);
    error_log('WEBHOOK_SECRET_KEY is defined');
} else {
    echo 'WEBHOOK_SECRET_KEY is not defined';
    exit;
}

define('DYNAMIK_WEBHOOK_VERSION', '1.0');
define('DYNAMIK_WEBHOOK_BASE_URL', 'https://app.dynamikmood.com/api/');

function display_admin_notice()
{
    if ($message = get_transient('my_custom_webhook_success')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        delete_transient('my_custom_webhook_success');
    }

    if ($message = get_transient('my_custom_webhook_error')) {
        echo '<div class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
        delete_transient('my_custom_webhook_error');
    }
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'Dynamickup\\';
    $base_dir = __DIR__ . '/includes/classes/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function init_dynamik_webhook()
{
    \Dynamickup\User\UserEvents::init();
    \Dynamickup\WooCommerce\WooCommerceEvents::init();
}

add_action('plugins_loaded', 'init_dynamik_webhook', 0);

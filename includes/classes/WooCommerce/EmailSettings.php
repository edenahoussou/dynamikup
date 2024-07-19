<?php

namespace Dynamickup\WooCommerce;

use Exception;

class EmailSettings {
    /**
     * Initialize the settings and hooks.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Adds the Email Settings page to the WordPress admin menu.
     */
    public static function add_admin_menu() {
        add_menu_page(
            'Email Settings', // Page title
            'Dynamik Up Api', // Menu title
            'manage_options', // Capability
            'email-settings', // Menu slug
            [__CLASS__, 'render_email_settings_page'] // Callback function
        );
    }

    /**
     * Renders the Email Settings page.
     */
    public static function render_email_settings_page() {
        ?>
        <div class="wrap">
            <h1>Email Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('email_settings');
                do_settings_sections('email-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registers the email settings fields.
     */
    public static function register_settings() {
        register_setting('email_settings', 'email_subject');
        register_setting('email_settings', 'email_body');

        add_settings_section('email_settings_section', 'Email Settings', null, 'email-settings');

        add_settings_field('email_subject', 'Email Subject', function () {
            $value = get_option('email_subject', 'Dynamik Up Saas - Initialisation du mot de passe');
            echo '<input type="text" name="email_subject" value="' . esc_attr($value) . '" class="regular-text">';
        }, 'email-settings', 'email_settings_section');

        add_settings_field('email_body', 'Email Body', function () {
            $value = get_option('email_body', "Bonjour,\n\nVotre compte Dynamik Up Saas a été crée avec succes. pour terminer votre inscription, cliquez sur le lien ci-dessous.\n{{auth_link}}\n\nCordialement,\nDynamik Up Saas");
            echo '<textarea name="email_body" class="large-text code" rows="10">' . esc_textarea($value) . '</textarea>';
            echo '<p>Use <code>{{auth_link}}</code> to insert the authentication link.</p>';
        }, 'email-settings', 'email_settings_section');
    }

    /**
     * Sends a notification email to customers with a password initialization link.
     *
     * @param string $email The email address of the customer.
     * @param string $auth_url The URL for the customer to initialize their password.
     * @throws Exception If there is an error sending the email.
     * @return bool True if the email was sent successfully.
     */
    public static function send_notification_to_customers($email, $auth_url) {
        $subject = get_option('email_subject', 'Dynamik Up Saas - Initialisation du mot de passe');
        $body_template = get_option('email_body', "Bonjour,\n\nVotre compte Dynamik Up Saas a été crée avec succes. pour terminer votre inscription, cliquez sur le lien ci-dessous.\n{{auth_link}}\n\nCordialement,\nDynamik Up Saas");

        // Replace the {{auth_link}} placeholder with the actual link
        $auth_button = '<a href="' . esc_url($auth_url) . '" style="background-color:#029de2;color:white;padding:10px 20px;text-align:center;text-decoration:none;display:inline-block;">Vous connecter</a>';
        $body = nl2br(str_replace('{{auth_link}}', $auth_button, $body_template));

        $to = $email;
        $headers = "From: WordPress <wordpress@dynamikmood.com>\r\n" .
                   "Content-type: text/html\r\n"; // Changed to text/html for HTML emails

        try {
            wp_mail($to, $subject, $body, $headers);
        } catch (Exception $e) {
            error_log('Error sending order notification to customers: ' . $e->getMessage());
        } finally {
            error_log('Order notification sent successfully to customer with email: ' . $email);
            return true;
        }
    }
}

?>
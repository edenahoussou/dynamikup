# Dynamikup

Dynamikup is a WordPress plugin that listens for WooCommerce's `woocommerce_order_status_completed` hook and sends structured data with a secret key to an API.

## Features

- Listens for WooCommerce's `woocommerce_order_status_completed` hook
- Sends structured data with a secret key to an API

## Installation

1. Upload the plugin files to the `/wp-content/plugins/ directory` or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

 ## Configuration

### Step 1: Define WEBHOOK_SECRET_KEY in your '/wp-config.php'

. Define WEBHOOK_SECRET_KEY in your '/wp-config.php'
. Go to the 'Dynamikup' settings page.
. Enter the API endpoint URL and secret key.


## Usage

1. Trigger an order completion in WooCommerce.
2. The plugin will send the structured data to the API.



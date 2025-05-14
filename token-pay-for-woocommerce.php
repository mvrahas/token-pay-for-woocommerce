<?php
/**
 * Plugin Name: Token Pay
 * Description: Accept crypto payments on your e-commerce store.
 * Version: 1.0.0
 *
 * Author: Numin Labs
 * Author URI: https://numin.xyz
 *
 * Requires at least: 4.2
 * Tested up to: 6.8
 *
 * Copyright: © 2025 Numin Labs.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Crypto Payment gateway plugin class.
 *
 * @class WC_Crypto_Payments
 */
class WC_Crypto_Payments {

	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {

		// Crypto Payments gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Make the Crypto Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

		// Registers WooCommerce Blocks integration.
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_crypto_woocommerce_block_support' ) );

	}

	/**
	 * Add the Crypto Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {

		$gateways[] = 'WC_Gateway_Crypto';

		return $gateways;
		
	}

	/**
	 * Plugin includes.
	 */
	public static function includes() {

		// Make the WC_Gateway_Crypto class available.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-gateway-crypto.php';
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_gateway_crypto_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-crypto-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_Crypto_Blocks_Support() );
				}
			);
		}
	}
}

WC_Crypto_Payments::init();

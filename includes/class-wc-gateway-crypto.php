<?php
/**
 * WC_Gateway_Crypto class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Crypto Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crypto Gateway.
 *
 * @class    WC_Gateway_Crypto
 * @version  1.10.0
 */
class WC_Gateway_Crypto extends WC_Payment_Gateway {

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'crypto';
	
	/**
	 * Base URL of the API.
	 * @var string
	 *
	 */
	public $base_url = 'https://api.numin.xyz';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		// Metadata
		$this->title = 'Crypto'; //shown on order summary
		$this->description = 'Accept crypto payments on your e-commerce store.';
		
		// Create the plugin
		$this->icon               = apply_filters( 'woocommerce_crypto_gateway_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = 'Token Pay';
		$this->method_description = 'Accept crypto payments on your e-commerce store.';

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_gateway-payment-complete', array( $this, 'handle_webhook' ) );

	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'api_key' => array(
        		'title'       => 'API Key',
        		'type'        => 'password',
        		'description' => 'Enter your API key here. Please visit the plugin website for access to the gateway.',
        		'default'     => '',
        		'desc_tip'    => true,
    		),
			'enabled' => array(
				'title'   => 'Options',
				'type'    => 'checkbox',
				'label'   => 'Enable gateway on checkout',
				'default' => 'no',
			),
			'sandbox_mode' => array(
				'type'    => 'checkbox',
				'label'   => 'Enable sandbox mode',
				'default' => 'no',
			),
		);
	}

	/**
	 * Hide if store currency is not USD.
	 */
	public function is_available() {

		//confirm currency is USD
		$currency = get_woocommerce_currency();
    	if ($currency !== 'USD') {
    	    return false;
    	}

		//confirm gateway enabled
		$enabled = $this->get_option('enabled') === 'yes';
		if (!$enabled) {
    	    return false;
    	}

		//OK ->
		return true;

	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		//get order
		$order = wc_get_order( $order_id );

		//parameters for function
		$api_key = $this->get_option('api_key');
		$sandbox_enabled = $this->get_option('sandbox_mode') === 'yes';
		$orderId = strval($order_id);
		$amount = floatval($order->get_total());
    	$return_url = $this->get_return_url($order);
		$webhook_url = get_site_url( null, 'wc-api/gateway-payment-complete' );
		
		//api reqest to create portal
		$response = wp_remote_post($this->base_url . '/payment/url', array(
        	'headers' => array(
        	    'Content-Type'  => 'application/json',
        	    'Authorization' => 'Bearer ' . $api_key,
        	),
        	'body'    => json_encode(array(
        	    'amountUSD'   => $amount,
        	    'memo'        => 'e-commerce order',
        	    'metadata'    => array('orderId' => $orderId),
        	    'returnURL'   => $return_url,
        	    'webhookURL'  => $webhook_url,
        	    'sandbox'     => $sandbox_enabled,
        	)),
        	'timeout' => 60,
    	));

		//handle general api errors
		if (is_wp_error($response)) {
        	wc_add_notice('Error : Something went wrong when initializing this payment method.', 'error');
        	return array(
				'result' => 'failure', 
				'redirect' => '',
			);
    	}

		//handle error if not 200 status
		if (wp_remote_retrieve_response_code($response) !== 200) {
			wc_add_notice('Error : Something went wrong when initializing this payment method.', 'error');
			return array(
				'result' => 'failure', 
				'redirect' => '',
			);
		}

		//get portal url
		$body = wp_remote_retrieve_body($response);
    	$data = json_decode($body, true);

		//OK->
        return array(
            'result'   => 'success',
            'redirect' => $data['portalURL'],
        );

	}


	public function handle_webhook() {

		// This is a webhook endpoint triggered by a trusted external server.
		// No nonce is used or required because it's not a user-initiated request.
		// Changes to order data only take place with a valid id from the server.

		$orderId = isset($_GET['id']) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : null;
		$api_key = $this->get_option('api_key');
		$url = $this->base_url . '/receipt?id=' . $orderId;

		$response = wp_remote_get($url, array(
        	'headers' => array(
        	    'Content-Type'  => 'application/json',
        	    'Authorization' => 'Bearer ' . $api_key,
        	),
        	'timeout' => 60,
    	));

		$body = wp_remote_retrieve_body($response);
    	$data = json_decode($body, true);

		$orderId = intval($data['metadata']["orderId"]);
		$memo = $data['memo'];

		$order = wc_get_order( $orderId );

		$order->payment_complete();

	}


}

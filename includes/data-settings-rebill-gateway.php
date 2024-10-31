<?php
/**
 * Rebill
 *
 * Form fields
 *
 * @package    Rebill
 * @link       https://rebill.to
 * @since      1.0.0
 */

return array(
	'rebill_back_nonce' => array(
		'type'  => 'hidden',
		'value' => wp_create_nonce( 'rebill_back_nonce' ),
	),
	'enabled'           => array(
		'title'   => __( 'Activate', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Rebill', 'wc-rebill-subscription' ),
		'default' => 'yes',
	),
	'title'             => array(
		'title'       => __( 'Title', 'wc-rebill-subscription' ),
		'type'        => 'text',
		'description' => __( 'Add the name to rebill that will be shown to the client', 'wc-rebill-subscription' ),
		'desc_tip'    => true,
		'default'     => __( 'Rebill', 'wc-rebill-subscription' ),
	),
	'description'       => array(
		'title'       => __( 'Description', 'wc-rebill-subscription' ),
		'type'        => 'textarea',
		'description' => __( 'Add a description to this payment method', 'wc-rebill-subscription' ),
		'default'     => '',
	),
	'user'              => array(
		'title'       => __( 'User', 'wc-rebill-subscription' ),
		'type'        => 'text',
		'description' => __( 'User access to the api', 'wc-rebill-subscription' ),
		'default'     => '',
	),
	'pass'              => array(
		'title'       => __( 'Password', 'wc-rebill-subscription' ),
		'type'        => 'text',
		'description' => __( 'Password access to the api', 'wc-rebill-subscription' ),
		'default'     => '',
	),
	'UUID'              => array(
		'title'       => __( 'Organization UUID', 'wc-rebill-subscription' ),
		'type'        => 'text',
		'description' => __( 'Input you Organization UUID', 'wc-rebill-subscription' ),
		'default'     => '',
	),
	'rebill_thankpage'  => array(
		'title'       => __( 'URL of the Thank You Page for Subscribing', 'wc-rebill-subscription' ),
		'type'        => 'text',
		'description' => __( 'Leave empty to use the default woocommerce page', 'wc-rebill-subscription' ),
		'default'     => '',
	),
	'btn_suscribe'      => array(
		'title'   => __( 'Text for button "Subscribe to this product"', 'wc-rebill-subscription' ),
		'type'    => 'text',
		'default' => __( 'Subscribe to this product', 'wc-rebill-subscription' ),
	),
	'btn_onetime'       => array(
		'title'   => __( 'Text for button "One-time purchase"', 'wc-rebill-subscription' ),
		'type'    => 'text',
		'default' => __( 'One-time purchase', 'wc-rebill-subscription' ),
	),
	'mp_completed'      => array(
		'title'   => __( 'Leave orders with payment Accepted in Completed', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'default' => 'no',
	),
	'sandbox'           => array(
		'title'   => __( 'Sandbox', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable sandbox mode', 'wc-rebill-subscription' ),
		'default' => 'no',
	),
	'mix_cart'          => array(
		'title'   => __( 'Allow one pay products and subscription products in same cart', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable mixed cart', 'wc-rebill-subscription' ),
		'default' => 'no',
	),
	'one_by_cart'       => array(
		'title'   => __( 'Allow only one subscription products by cart', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'label'   => __( 'Only one subscription products by cart', 'wc-rebill-subscription' ),
		'default' => 'no',
	),
	'one_by_customer'   => array(
		'title'   => __( 'Allow only one subscription active by customer', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'label'   => __( 'Only one subscription active by customer', 'wc-rebill-subscription' ),
		'default' => 'no',
	),
	'only_for_sub'      => array(
		'title'   => __( 'Deactivate rebill if the customer has not added subscriptions in the shopping cart', 'wc-rebill-subscription' ),
		'type'    => 'checkbox',
		'label'   => __( 'Deactivate in one-time purchase', 'wc-rebill-subscription' ),
		'default' => 'no',
	),
	'debug'             => array(
		'title'       => __( 'Debug', 'wc-rebill-subscription' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable log', 'wc-rebill-subscription' ),
		'default'     => 'no',
		// translators: %s: Path of log file.
		'description' => sprintf( __( 'To review the Log download the file: %s', 'wc-rebill-subscription' ), '<code>/wp-content/plugins/wc-rebill-subscription/logs/</code>' ),
	),
);

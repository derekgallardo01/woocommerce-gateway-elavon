<?php
/**
 * WooCommerce Elavon Converge
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Elavon Converge to newer
 * versions in the future. If you wish to customize WooCommerce Elavon Converge for your
 * needs please refer to http://docs.woocommerce.com/document/elavon-vm-payment-gateway/
 *
 * @package     WC-Elavon
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2020, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Elavon_Converge;

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_10_4 as Framework;
use WC_Gateway_Elavon_Converge;

/**
 * AJAX handler.
 *
 * @since 2.8.0
 */
class AJAX {


	/** @var string the get transaction token AJAX action */
	const ACTION_GET_TRANSACTION_TOKEN = 'wc_elavon_vm_get_transaction_token';

	/** @var string the get transaction token AJAX nonce prefix */
	const GET_TRANSACTION_TOKEN_NONCE_PREFIX = 'transaction-token-';


	/**
	 * Constructs the handler.
	 *
	 * @since 2.8.0
	 */
	public function __construct() {

		$this->add_hooks();
	}


	/**
	 * Adds the necessary action and filter hooks.
	 *
	 * @since 2.8.0
	 */
	protected function add_hooks() {

		add_action( 'wp_ajax_' . self::ACTION_GET_TRANSACTION_TOKEN,        [ $this, 'get_transaction_token' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_GET_TRANSACTION_TOKEN, [ $this, 'get_transaction_token' ] );
	}


	/**
	 * Handles the AJAX action used to get a new Hosted Payments transaction token.
	 *
	 * @internal
	 *
	 * @since 2.8.0
	 */
	public function get_transaction_token() {

		try {

			$order_id = Framework\SV_WC_Helper::get_requested_value( 'order_id' );

			// not using empty() to accept 0 as a valid Order ID (the ID can be 0 on the Add Payment Method page)
			if ( 0 === strlen( $order_id ) ) {
				throw new Framework\SV_WC_Plugin_Exception( __( 'Order ID is required.', 'woocommerce-gateway-elavon' ) );
			}

			if ( ! wp_verify_nonce( Framework\SV_WC_Helper::get_requested_value( 'security' ), self::GET_TRANSACTION_TOKEN_NONCE_PREFIX . $order_id ) ) {
				throw new Framework\SV_WC_Plugin_Exception( __( 'Invalid nonce.', 'woocommerce-gateway-elavon' ) );
			}

			$gateway_id              = Framework\SV_WC_Helper::get_requested_value( 'gateway_id' );
			$tokenize_payment_method = wc_string_to_bool( Framework\SV_WC_Helper::get_requested_value( 'tokenize_payment_method' ) );
			$test_amount             = Framework\SV_WC_Helper::get_requested_value( 'test_amount' );

			/** @var WC_Gateway_Elavon_Converge $gateway */
			$gateway = wc_elavon_converge()->get_gateway( $gateway_id );

			$payment_data = $gateway->get_checkout_js_payment_data( $order_id, $tokenize_payment_method );

			if ( $gateway->is_test_environment() && strlen( $test_amount ) > 0 ) {
				$payment_data['ssl_amount'] = $test_amount;
			}

			$transaction_token = $gateway->get_checkout_js_transaction_token( $payment_data['ssl_transaction_type'], isset( $payment_data['ssl_amount'] ) ? $payment_data['ssl_amount'] : 0 );

			wp_send_json_success( [
				'payment_data'      => $payment_data,
				'transaction_token' => $transaction_token,
			] );

		} catch ( Framework\SV_WC_Plugin_Exception $exception ) {

			wp_send_json_error( $exception->getMessage() );
		}
	}


}

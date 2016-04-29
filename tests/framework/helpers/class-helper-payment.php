<?php

/**
 * Class Give_Helper_Payment.
 *
 * Helper class to create and delete a payment easily.
 */
class Give_Helper_Payment extends WP_UnitTestCase {

	/**
	 * Delete a payment.
	 *
	 * @since 1.0
	 *
	 * @param int $payment_id ID of the payment to delete.
	 */
	public static function delete_payment( $payment_id ) {

		// Delete the payment
		give_delete_purchase( $payment_id );

	}
	
	/**
	 * Create a simple donation form.
	 *
	 * @since 2.3
	 */
	public static function create_simple_donation_form() {

		$post_id = wp_insert_post( array(
			'post_title'    => 'Test Donation Form',
			'post_name'     => 'test-donation-form',
			'post_type'     => 'give_forms',
			'post_status'   => 'publish'
		) );
		
		$meta = array(
			'give_price'               => '20.00',
			'_give_price_option'       => 'set',
			'_give_form_earnings'            => 40,
			'_give_form_sales'               => 2,
			
			'edd_price'                         => '20.00',
			'_variable_pricing'                 => 0,
			'edd_variable_prices'               => false,
			'edd_download_files'                => array_values( $_download_files ),
			'_edd_download_limit'               => 20,
			'_edd_hide_purchase_link'           => 1,
			'edd_product_notes'                 => 'Purchase Notes',
			'_edd_product_type'                 => 'default',
			'_edd_download_earnings'            => 40,
			'_edd_download_sales'               => 2,
			'_edd_download_limit_override_1'    => 1,
			'edd_sku'                           => 'sku_0012'
		);

		foreach( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return get_post( $post_id );

	}
	/**
	 * Create a simple donation payment.
	 *
	 * @since 1.0
	 */
	public static function create_simple_payment() {

		global $give_options;

		// Enable a few options
		$give_options['enable_sequential'] = '1';
		$give_options['sequential_prefix'] = 'GIVE-';
		update_option( 'give_settings', $give_options );

		$simple_form     = Give_Helper_Form::create_simple_form();
		$multilevel_form = Give_Helper_Form::create_multilevel_form();

		/** Generate some donations */
		$user      = get_userdata( 1 );
		$user_info = array(
			'id'         => $user->ID,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name
		);

		$total               = 0;
		$simple_price        = get_post_meta( $simple_form->ID, 'give_price', true );
		$variable_prices     = get_post_meta( $multilevel_form->ID, 'give_variable_prices', true );
		$variable_item_price = $variable_prices[1]['amount']; // == $100

		$total += $variable_item_price + $simple_price;

		$purchase_data = array(
			'price'           => number_format( (float) $total, 2 ),
			'give_form_title' => 'Test Donation',
			'give_form_id'    => $simple_form->ID,
			'date'            => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'purchase_key'    => strtolower( md5( uniqid() ) ),
			'user_email'      => $user_info['email'],
			'user_info'       => $user_info,
			'currency'        => 'USD',
			'status'          => 'pending'
		);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['SERVER_NAME'] = 'give_virtual';

		$payment_id = give_insert_payment( $purchase_data );
		$key        = $purchase_data['purchase_key'];

		$transaction_id = 'FIR3SID3';
		give_set_payment_transaction_id( $payment_id, $transaction_id );
		give_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'give' ), $transaction_id ) );

		return $payment_id;

	}

}

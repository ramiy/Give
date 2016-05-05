<?php

/**
 * Class Give_Helper_Payment.
 *
 * Helper class to create and delete a donation payment easily.
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
	 * Create a simple donation payment.
	 *
	 * @since 1.0
	 */
	public static function create_simple_payment() {

		global $give_options;

		// Enable a few options
		$give_options['enable_sequential'] = '1'; //Not yet in use
		$give_options['sequential_prefix'] = 'GIVE-'; //Not yet in use
		update_option( 'give_settings', $give_options );

		$simple_form     = Give_Helper_Form::create_simple_form();
		$multilevel_form = Give_Helper_Form::create_multilevel_form();

		// Generate some donations
		$user      = get_userdata( 1 );
		$user_info = array(
			'id'         => $user->ID,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name
		);
		
		$donation_details = array(
			array(
				'id'      => $simple_form->ID,
				'options' => array(
					'price_id' => 0
				)
			),
			array(
				'id'      => $multilevel_form->ID,
				'options' => array(
					'price_id' => 1
				)
			),
		);

		$total               = 0;
		$simple_price        = get_post_meta( $simple_form->ID, '_give_set_price', true );
		$variable_prices     = get_post_meta( $multilevel_form->ID, '_give_donation_levels', true );
		$variable_item_price = $variable_prices[3]['_give_amount']; // == $100
		$total += $variable_item_price + $simple_price;

		$payment_details = array(
			array(
				'name'        => 'Test Download',
				'id'          => $simple_form->ID,
				'item_number' => array(
					'id'      => $simple_form->ID,
					'options' => array(
						'price_id' => 1
					)
				),
				'price'       => $simple_price,
				'item_price'  => $simple_price,
				'tax'         => 0,
				'quantity'    => 1
			),
			array(
				'name'        => 'Variable Test Download',
				'id'          => $multilevel_form->ID,
				'item_number' => array(
					'id'      => $multilevel_form->ID,
					'options' => array(
						'price_id' => 1
					)
				),
				'price'       => $variable_item_price,
				'item_price'  => $variable_item_price,
				'tax'         => 0,
				'quantity'    => 1
			),
		);


		$purchase_data = array(
			'price'           => number_format( (float) $total, 2 ),
			'date'            => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'purchase_key'    => strtolower( md5( uniqid() ) ),
			'user_email'      => $user_info['email'],
			'user_info'       => $user_info,
			'currency'        => 'USD',
			'donations'       => $donation_details,
			'payment_details' => $payment_details,
			'status'          => 'pending'
		);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['SERVER_NAME'] = 'give_virtual';

		$payment_id = give_insert_payment( $purchase_data );

		$transaction_id          = 'FIR3SID3';
		$payment                 = new Give_Payment( $payment_id );
		$payment->transaction_id = $transaction_id;
		$payment->save();

		give_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'give' ), $transaction_id ) );

		return $payment_id;

	}


	/**
	 * Create a simple payment with a quantity of two
	 *
	 * @since 2.3
	 */
	public static function create_simple_payment_with_quantity() {

		global $give_options;

		// Enable a few options
		$give_options['sequential_prefix'] = 'GIVE-';

		$simple_form   = Give_Helper_Form::create_simple_form();
		$multilevel_form = Give_Helper_Form::create_multilevel_form();

		// Generate some sales
		$user      = get_userdata(1);
		$user_info = array(
			'id'            => $user->ID,
			'email'         => $user->user_email,
			'first_name'    => $user->first_name,
			'last_name'     => $user->last_name,
			'discount'      => 'none'
		);

		$donation_details = array(
			array(
				'id' => $simple_form->ID,
				'options' => array(
					'price_id' => 0
				),
				'quantity' => 2,
			),
			array(
				'id' => $multilevel_form->ID,
				'options' => array(
					'price_id' => 1
				),
				'quantity' => 2,
			),
		);

		$total                  = 0;
		$simple_price        = get_post_meta( $simple_form->ID, '_give_set_price', true );
		$variable_prices     = get_post_meta( $multilevel_form->ID, '_give_donation_levels', true );
		$variable_item_price    = $variable_prices[1]['amount']; // == $100

		$total += $variable_item_price + $simple_price;

		$payment_details = array(
			array(
				'name'          => 'Test Download',
				'id'            => $simple_form->ID,
				'item_number'   => array(
					'id'        => $simple_form->ID,
					'options'   => array(
						'price_id' => 1
					)
				),
				'price'         => $simple_price * 2,
				'item_price'    => $simple_price,
				'quantity'      => 2
			),
			array(
				'name'          => 'Variable Test Download',
				'id'            => $multilevel_form->ID,
				'item_number'   => array(
					'id'        => $multilevel_form->ID,
					'options'   => array(
						'price_id' => 1
					)
				),
				'price'         => $variable_item_price * 2,
				'item_price'    => $variable_item_price,
				'quantity'      => 2
			),
		);

		$purchase_data = array(
			'price'         => number_format( (float) $total, 2 ),
			'date'          => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'purchase_key'  => strtolower( md5( uniqid() ) ),
			'user_email'    => $user_info['email'],
			'user_info'     => $user_info,
			'currency'      => 'USD',
			'downloads'     => $donation_details,
			'cart_details'  => $payment_details,
			'status'        => 'pending',
		);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['SERVER_NAME'] = 'give_virtual';

		$payment_id = give_insert_payment( $purchase_data );

		$transaction_id = 'FIR3SID3';
		$payment = new Give_Payment( $payment_id );
		$payment->transaction_id = $transaction_id;
		$payment->save();

		give_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'give' ), $transaction_id ) );

		return $payment_id;

	}

	/**
	 * Create Simple Payment w/ Fee
	 *
	 * @return bool|int
	 */
	public static function create_simple_payment_with_fee() {

		global $give_options;

		// Enable a few options
		$give_options['sequential_prefix'] = 'GIVE-';

		$simple_form   = Give_Helper_Form::create_simple_form();

		/** Generate some sales */
		$user      = get_userdata(1);
		$user_info = array(
			'id'            => $user->ID,
			'email'         => $user->user_email,
			'first_name'    => $user->first_name,
			'last_name'     => $user->last_name,
			'discount'      => 'none'
		);

		$donation_details = array(
			array(
				'id' => $simple_form->ID,
				'options' => array(
					'price_id' => 0
				),
				'quantity' => 2,
			),
		);

		$total                  = 0;
		$simple_price           = get_post_meta( $simple_form->ID, 'give_price', true );

		$total += $simple_price;

		$payment_details = array(
			array(
				'name'          => 'Test Download',
				'id'            => $simple_form->ID,
				'item_number'   => array(
					'id'        => $simple_form->ID,
					'options'   => array(
						'price_id' => 1
					),
				),
				'price'         => $simple_price * 2, //quantity = 2
				'item_price'    => $simple_price,
				'quantity'      => 2
			),
		);

		$purchase_data = array(
			'price'         => number_format( (float) $total, 2 ),
			'date'          => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'purchase_key'  => strtolower( md5( uniqid() ) ),
			'user_email'    => $user_info['email'],
			'user_info'     => $user_info,
			'currency'      => 'USD',
			'downloads'     => $donation_details,
			'cart_details'  => $payment_details,
			'status'        => 'pending',
		);

		$fee_args = array(
			'label'  => 'Test Fee',
			'type'   => 'test',
			'amount' => 5,
		);

		Give()->fees->add_fee( $fee_args );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['SERVER_NAME'] = 'give_virtual';

		$payment_id = give_insert_payment( $purchase_data );

		$transaction_id = 'FIR3SID3';
		$payment = new Give_Payment( $payment_id );
		$payment->transaction_id = $transaction_id;
		$payment->save();

		give_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'give' ), $transaction_id ) );

		return $payment_id;

	}

}

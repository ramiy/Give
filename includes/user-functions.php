<?php
/**
 * User Functions
 *
 * Functions related to users / donors
 *
 * @package     Give
 * @subpackage  Functions
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get Users Purchases
 *
 * Retrieves a list of all purchases by a specific user.
 *
 * @since  1.0
 *
 * @param int $user User ID or email address
 * @param int $number Number of purchases to retrieve
 * @param bool $pagination
 * @param string $status
 *
 * @return bool|object List of all user purchases
 */
function give_get_users_purchases( $user = 0, $number = 20, $pagination = false, $status = 'complete' ) {

	if ( empty( $user ) ) {
		$user = get_current_user_id();
	}

	if ( 0 === $user && ! Give()->email_access->token_exists ) {
		return false;
	}

	$status = $status === 'complete' ? 'publish' : $status;

	if ( $pagination ) {
		if ( get_query_var( 'paged' ) ) {
			$paged = get_query_var( 'paged' );
		} else if ( get_query_var( 'page' ) ) {
			$paged = get_query_var( 'page' );
		} else {
			$paged = 1;
		}
	}

	$args = apply_filters( 'give_get_users_purchases_args', array(
		'user'    => $user,
		'number'  => $number,
		'status'  => $status,
		'orderby' => 'date'
	) );

	if ( $pagination ) {

		$args['page'] = $paged;

	} else {

		$args['nopaging'] = true;

	}

	$by_user_id = is_numeric( $user ) ? true : false;
	$customer   = new Give_Customer( $user, $by_user_id );

	if ( ! empty( $customer->payment_ids ) ) {

		unset( $args['user'] );
		$args['post__in'] = array_map( 'absint', explode( ',', $customer->payment_ids ) );

	}

	$purchases = give_get_payments( apply_filters( 'give_get_users_purchases_args', $args ) );

	// No purchases
	if ( ! $purchases ) {
		return false;
	}

	return $purchases;
}

/**
 * Get Users Donations
 *
 * Returns a list of unique donation forms given to by a specific user
 *
 * @since  1.0
 *
 * @param int $user User ID or email address
 * @param string $status
 *
 * @return bool|object List of unique forms purchased by user
 */
function give_get_users_completed_donations( $user = 0, $status = 'complete' ) {
	if ( empty( $user ) ) {
		$user = get_current_user_id();
	}

	if ( empty( $user ) ) {
		return false;
	}

	$by_user_id = is_numeric( $user ) ? true : false;

	$customer = new Give_Customer( $user, $by_user_id );

	if ( empty( $customer->payment_ids ) ) {
		return false;
	}

	// Get all the items purchased
	$payment_ids    = array_reverse( explode( ',', $customer->payment_ids ) );
	$limit_payments = apply_filters( 'give_users_completed_donations_payments', 50 );
	if ( ! empty( $limit_payments ) ) {
		$payment_ids = array_slice( $payment_ids, 0, $limit_payments );
	}
	$donation_data = array();
	foreach ( $payment_ids as $payment_id ) {
		$donation_data[] = give_get_payment_meta( $payment_id );
	}

	if ( empty( $donation_data ) ) {
		return false;
	}

	// Grab only the post ids of the forms for this donation
	$completed_donations_ids = array();
	foreach ( $donation_data as $purchase_meta ) {
		$completed_donations_ids[] = @wp_list_pluck( $purchase_meta, 'id' );
	}

	// Ensure that grabbed forms actually HAVE donations
	$purchase_product_ids = array_filter( $completed_donations_ids );

	if ( empty( $completed_donations_ids ) ) {
		return false;
	}

	// Merge all donations into a single array of all items purchased
	$completed_donations = array();
	foreach ( $completed_donations_ids as $donation ) {
		$completed_donations = array_merge( $donation, $completed_donations );
	}

	// Only include each donations given once
	$form_ids = array_unique( $completed_donations );

	// Make sure we still have some products and a first item
	if ( empty ( $form_ids ) || ! isset( $form_ids[0] ) ) {
		return false;
	}

	$post_type = get_post_type( $form_ids[0] );

	$args = apply_filters( 'give_get_users_completed_donations_args', array(
		'include'        => $form_ids,
		'post_type'      => $post_type,
		'posts_per_page' => - 1
	) );

	return apply_filters( 'give_users_completed_donations_list', get_posts( $args ) );
}

/**
 * Has User Purchased
 *
 * Checks to see if a user has given to a donation form.
 *
 * @access      public
 * @since       1.5
 *
 * @param       int $user_id - the ID of the user to check
 * @param       array $donations - Array of IDs to check if purchased. If an int is passed, it will be converted to an array
 * @param       int $variable_price_id - the variable price ID to check for
 *
 * @return      boolean - true if has purchased, false otherwise
 */
function give_has_user_purchased( $user_id, $donations, $variable_price_id = null ) {

	if ( empty( $user_id ) ) {
		return false;
	}

	$users_purchases = give_get_users_purchases( $user_id );

	$return = false;

	if ( ! is_array( $donations ) ) {
		$donations = array( $donations );
	}

	if ( $users_purchases ) {
		foreach ( $users_purchases as $purchase ) {
			$payment             = new Give_Payment( $purchase->ID );
			$completed_donations = $payment->payment_details;

			if ( is_array( $completed_donations ) ) {
				foreach ( $completed_donations as $donation ) {
					if ( in_array( $donation['id'], $donations ) ) {
						$variable_prices = give_has_variable_prices( $donation['id'] );
						if ( $variable_prices && ! is_null( $variable_price_id ) && $variable_price_id !== false ) {
							if ( isset( $donation['item_number']['options']['price_id'] ) && $variable_price_id == $donation['item_number']['options']['price_id'] ) {
								return true;
							} else {
								$return = false;
							}
						} else {
							$return = true;
						}
					}
				}
			}
		}
	}

	return $return;
}

/**
 * Has Purchases
 *
 * Checks to see if a user has given to at least one form.
 *
 * @access      public
 * @since       1.0
 *
 * @param       $user_id int - the ID of the user to check
 *
 * @return      bool - true if has purchased, false other wise.
 */
function give_has_purchases( $user_id = null ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( give_get_users_purchases( $user_id, 1 ) ) {
		return true; // User has at least one purchase
	}

	return false; // User has never purchased anything
}


/**
 * Get Purchase Status for User
 *
 * Retrieves the purchase count and the total amount spent for a specific user
 *
 * @access      public
 * @since       1.0
 *
 * @param       $user int|string - the ID or email of the donor to retrieve stats for
 *
 * @return      array
 */
function give_get_purchase_stats_by_user( $user = '' ) {

	if ( is_email( $user ) ) {

		$field = 'email';

	} elseif ( is_numeric( $user ) ) {

		$field = 'user_id';

	}

	$stats    = array();
	$customer = Give()->customers->get_customer_by( $field, $user );

	if ( $customer ) {

		$customer = new Give_Customer( $customer->id );

		$stats['purchases']   = absint( $customer->purchase_count );
		$stats['total_spent'] = give_sanitize_amount( $customer->purchase_value );

	}


	return (array) apply_filters( 'give_purchase_stats_by_user', $stats, $user );
}


/**
 * Count number of purchases of a donor
 *
 * @description: Returns total number of purchases a donor has made
 *
 * @access      public
 * @since       1.0
 *
 * @param       $user mixed - ID or email
 *
 * @return      int - the total number of purchases
 */
function give_count_purchases_of_customer( $user = null ) {

	//Logged in?
	if ( empty( $user ) ) {
		$user = get_current_user_id();
	}

	//Email access?
	if ( empty( $user ) && Give()->email_access->token_email ) {
		$user = Give()->email_access->token_email;
	}
	
	$stats = ! empty( $user ) ? give_get_purchase_stats_by_user( $user ) : false;

	return isset( $stats['purchases'] ) ? $stats['purchases'] : 0;
}

/**
 * Calculates the total amount spent by a user
 *
 * @access      public
 * @since       1.0
 *
 * @param       $user mixed - ID or email
 *
 * @return      float - the total amount the user has spent
 */
function give_purchase_total_of_user( $user = null ) {

	$stats = give_get_purchase_stats_by_user( $user );

	return $stats['total_spent'];
}


/**
 * Validate a potential username
 *
 * @access      public
 * @since       1.0
 *
 * @param       $username string - the username to validate
 *
 * @return      bool
 */
function give_validate_username( $username ) {
	$sanitized = sanitize_user( $username, false );
	$valid     = ( $sanitized == $username );

	return (bool) apply_filters( 'give_validate_username', $valid, $username );
}

/**
 * Attach the newly created user_id to a customer, if one exists
 *
 * @since  1.5
 * @param  int $user_id The User ID that was created
 * @return void
 */
function give_connect_existing_customer_to_new_user( $user_id ) {
	$email = get_the_author_meta( 'user_email', $user_id );

	// Update the user ID on the customer
	$customer = new Give_Customer( $email );

	if( $customer->id > 0 ) {
		$customer->update( array( 'user_id' => $user_id ) );
	}
}
add_action( 'user_register', 'give_connect_existing_customer_to_new_user', 10, 1 );

/**
 * Looks up purchases by email that match the registering user
 *
 * This is for users that purchased as a guest and then came
 * back and created an account.
 *
 * @access      public
 * @since       1.0
 *
 * @param       $user_id INT - the new user's ID
 *
 * @return      void
 */
function give_add_past_purchases_to_new_user( $user_id ) {

	$email = get_the_author_meta( 'user_email', $user_id );

	$payments = give_get_payments( array( 's' => $email ) );

	if ( $payments ) {
		foreach ( $payments as $payment ) {
			if ( intval( give_get_payment_user_id( $payment->ID ) ) > 0 ) {
				continue;
			} // This payment already associated with an account

			$meta                    = give_get_payment_meta( $payment->ID );
			$meta['user_info']       = maybe_unserialize( $meta['user_info'] );
			$meta['user_info']['id'] = $user_id;
			$meta['user_info']       = $meta['user_info'];

			// Store the updated user ID in the payment meta
			give_update_payment_meta( $payment->ID, '_give_payment_meta', $meta );
			give_update_payment_meta( $payment->ID, '_give_payment_user_id', $user_id );
		}
	}

}

add_action( 'user_register', 'give_add_past_purchases_to_new_user' );


/**
 * Counts the total number of donors.
 *
 * @access        public
 * @since         1.0
 * @return        int - The total number of donors.
 */
function give_count_total_customers() {
	return Give()->customers->count();
}


/**
 * Returns the saved address for a donor
 *
 * @access        public
 * @since         1.0
 * @return        array - The donor's address, if any
 */
function give_get_donor_address( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$address = get_user_meta( $user_id, '_give_user_address', true );

	if ( ! isset( $address['line1'] ) ) {
		$address['line1'] = '';
	}

	if ( ! isset( $address['line2'] ) ) {
		$address['line2'] = '';
	}

	if ( ! isset( $address['city'] ) ) {
		$address['city'] = '';
	}

	if ( ! isset( $address['zip'] ) ) {
		$address['zip'] = '';
	}

	if ( ! isset( $address['country'] ) ) {
		$address['country'] = '';
	}

	if ( ! isset( $address['state'] ) ) {
		$address['state'] = '';
	}

	return $address;
}

/**
 * Give New User Notification
 *
 * @description   : Sends the new user notification email when a user registers within the donation form
 *
 * @access        public
 * @since         1.0
 *
 * @param int $user_id
 * @param array $user_data
 *
 * @return        void
 */
function give_new_user_notification( $user_id = 0, $user_data = array() ) {

	if ( empty( $user_id ) || empty( $user_data ) ) {
		return;
	}
	
	$emails     = new Give_Emails();
	$from_name  = give_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$from_email = give_get_option( 'from_email', get_bloginfo( 'admin_email' ) );
	
	$emails->__set( 'from_name', $from_name );
	$emails->__set( 'from_email', $from_email );
	
	//Admin Notification Email
	$admin_subject  = sprintf( __('[%s] New User Registration', 'give' ), $from_name );
	$admin_heading  = __( 'New user registration', 'give' );
	$admin_message  = sprintf( __( 'Username: %s', 'give'), $user_data['user_login'] ) . "\r\n\r\n";
	$admin_message .= sprintf( __( 'E-mail: %s', 'give'), $user_data['user_email'] ) . "\r\n";

	$emails->__set( 'heading', $admin_heading );
	$emails->send( get_option( 'admin_email' ), $admin_subject, $admin_message );

	//Email to New User
	$user_subject  = sprintf( __( '[%s] Your username and password', 'give' ), $from_name );
	$user_heading  = __( 'Your account info', 'give' );
	$user_message  = sprintf( __( 'Username: %s', 'give' ), $user_data['user_login'] ) . "\r\n";
	$user_message .= sprintf( __( 'Password: %s' ), __( '[Password entered at checkout]', 'give' ) ) . "\r\n";
	$user_message .= '<a href="' . wp_login_url() . '"> ' . esc_attr__( 'Click Here to Log In', 'give' ) . ' &raquo;</a>' . "\r\n";

	$emails->__set( 'heading', $user_heading );
	$emails->send( $user_data['user_email'], $user_subject, $user_message );
	
}

add_action( 'give_insert_user', 'give_new_user_notification', 10, 2 );

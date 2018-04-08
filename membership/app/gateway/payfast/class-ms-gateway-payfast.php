<?php

/**

 * Gateway: PayFast Single

 *

 * Officially: PayPal Payments Standard

 * https://developer.payFast.com/docs/classic/payFast-payments-standard/gs_PayPalPaymentsStandard/

 *

 * Process single payFast purchases/payments.

 *

 * Persisted by parent class MS_Model_Option. Singleton.

 *

 * @since  1.0.0

 * @package Membership2

 * @subpackage Model

 */

class MS_Gateway_PayFast extends MS_Gateway {



	const ID = 'payfast';

	public static $instance;

	protected $merchant_id;
	protected $merchant_key;
	protected $passphrase;
	protected $log;

	public function after_load() {

		parent::after_load();

		$this->id 				= self::ID;

		$this->name 			= __( 'PayFast Gateway', 'membership2' );

		$this->group 			= 'PayFast';

		$this->manual_payment 	= true; // Recurring billed/paid manually

		$this->pro_rate 		= true;
        $this->log = true;
	}

	/**

	 * Processes gateway IPN return.

	 *

	 * @since  1.0.0

	 * @param  MS_Model_Transactionlog $log Optional. A transaction log item

	 *         that will be updated instead of creating a new log entry.

	 */
    private function SecurityChecks($pfData, $pfHost){
        // Construct variables 
        $pfParamString='';
        foreach( $pfData as $key => $val )
        {
            if( $key != 'signature' )
            {
                $pfParamString .= $key .'='. urlencode( $val ) .'&';
            }
        }
        
        // Remove the last '&' from the parameter string
        $pfParamString = substr( $pfParamString, 0, -1 );
        $pfTempParamString = $pfParamString;
        // Passphrase stored in website database

        if( !empty( $passphrase ) )
        {
            $pfTempParamString .= '&passphrase='.urlencode( $passphrase );
        }
        $signature = md5( $pfTempParamString );
        
        if($signature!=$pfData['signature'])
        {
            write_log('Security Check: Invalid Signature');
            die('Invalid Signature');
        }
        
        $validHosts = array(
            'www.payfast.co.za',
            'sandbox.payfast.co.za',
            'w1w.payfast.co.za',
            'w2w.payfast.co.za',
        );
        
        $validIps = array();
        
        foreach( $validHosts as $pfHostname )
        {
            $ips = gethostbynamel( $pfHostname );
            if( $ips !== false )
            {
                $validIps = array_merge( $validIps, $ips );
            }
        }
        
        // Remove duplicates
        $validIps = array_unique( $validIps );
        
        if( !in_array( $_SERVER['REMOTE_ADDR'], $validIps ) )
        {
            write_log('Security Check: Source IP not Valid');
            die('Source IP not Valid');
        }
        
        $url = $pfHost .'/eng/query/validate';

        // Create default cURL object
        $ch = curl_init();
        
        // Set cURL options - Use curl_setopt for greater PHP compatibility
        // Base settings
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HEADER, false );      
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 1 );
        
        // Standard settings
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $pfParamString );
        
        // Execute CURL
        $response = curl_exec( $ch );
        curl_close( $ch );
        
        $lines = explode( "\r\n", $response );
        $verifyResult = trim( $lines[0] );
        
        if( strcasecmp( $verifyResult, 'VALID' ) != 0 )
        {
            write_log('Security Check: Data not valid');
            die('Data not valid');
        }
        
        write_log('Passed security checks');
    }
    
	public function handle_return( $log = false ) {
        write_log('Handle return now');
            
		$success 			= false;

		$exit 				= false;

		$redirect 			= false;

		$notes 				= '';

		$status 			= null;

		$invoice_id 		= 0;

		$subscription_id 	= 0;

		$amount 			= 0;



		do_action(

			'ms_gateway_payfast_handle_return_before',

			$this

		);


		if ( isset($_POST['payment_status']) && ! empty( $_POST['m_payment_id'] ) ) {

			if ( $this->is_live_mode() ) {

				$domain = 'https://www.payfast.co.za';

			} else {

				$domain = 'https://sandbox.payfast.co.za';

			}

		    $this->SecurityChecks($_POST, $domain);
 

			$invoice_id 	= intval( $_POST['m_payment_id'] );

			$external_id 	= $_POST['pf_payment_id'];

			$amount 		= (float) $_POST['amount_gross'];

			$invoice 		= MS_Factory::load( 'MS_Model_Invoice', $invoice_id);



			if ( ! is_wp_error( $response )

				&& ! MS_Model_Transactionlog::was_processed( self::ID, $external_id )

				&& 200 == $response['response']['code']

				&& ! empty( $response['body'] )

				&& 'VERIFIED' == $response['body']

				&& $invoice->id == $invoice_id

			) {

				$new_status 		= false;

				$subscription 		= $invoice->get_subscription();

				$membership 		= $subscription->get_membership();

				$member 			= $subscription->get_member();

				$subscription_id 	= $subscription->id;



				// Process PayPal response

				switch ( $_POST['payment_status'] ) {

					// Successful payment

					case 'COMPLETED':
						$success 	= true;

						if ( $amount == $invoice->total ) {

							$notes .= __( 'Payment successful', 'membership2' );

						} else {

							$notes .= __( 'Payment registered, though amount differs from invoice.', 'membership2' );

						}

						break;

					case 'CANCELLED':

						$notes 	= __( 'Last transaction has been reversed. Reason: Payment Denied', 'membership2' );

						$status = MS_Model_Invoice::STATUS_DENIED;

						break;


					default:

						$success = null;

						break;

				}


				if ( ! empty( $notes ) ) { 
				    $invoice->add_notes( $notes ); 
				}

				if ( $success ) {

					$invoice->pay_it( self::ID, $external_id );

				} elseif ( ! empty( $status ) ) {

					$invoice->status = $status;

					$invoice->save();

					$invoice->changed();

				}



				do_action(

					'ms_gateway_payfast_payment_processed_' . $status,

					$invoice,

					$subscription

				);

			} else {

				$reason = 'Unexpected transaction response';

				switch ( true ) {

					case is_wp_error( $response ):

						$reason = 'Response is error';

						break;



					case 200 != $response['response']['code']:

						$reason = 'Response code is ' . $response['response']['code'];

						break;



					case empty( $response['body'] ):

						$reason = 'Response is empty';

						break;



					case 'VERIFIED' != $response['body']:

						$reason = sprintf(

							'Expected response "%s" but got "%s"',

							'VERIFIED',

							(string) $response['body']

						);

						break;



					case $invoice->id != $invoice_id:

						$reason = sprintf(

							'Expected invoice_id "%s" but got "%s"',

							$invoice->id,

							$invoice_id

						);

						break;



					case MS_Model_Transactionlog::was_processed( self::ID, $external_id ):

						$reason = 'Duplicate: Already processed that transaction.';

						break;

				}



				$notes = 'Response Error: ' . $reason;

				$exit = true;

			}

			$invoice->gateway_id = self::ID;

			$invoice->save();

		} else {

			// Did not find expected POST variables. Possible access attempt from a non PayPal site.



			$u_agent = $_SERVER['HTTP_USER_AGENT'];

			if ( false === strpos( $u_agent, 'PayPal' ) ) {

				// Very likely someone tried to open the URL manually. Redirect to home page

				$notes 		= 'Error: Missing POST variables. Redirect user to Home-URL.';

				$redirect 	= MS_Helper_Utility::home_url( '/' );

			} else {

				$notes = 'Error: Missing POST variables. Identification is not possible.';

			}

			$exit = true;

		}



		if ( ! $log ) {

			do_action(

				'ms_gateway_transaction_log',

				self::ID, // gateway ID

				'handle', // request|process|handle

				$success, // success flag

				$subscription_id, // subscription ID

				$invoice_id, // invoice ID

				$amount, // charged amount

				$notes, // Descriptive text

				$external_id // External ID

			);



			if ( $redirect ) {

				wp_safe_redirect( $redirect );

				exit;

			}

			if ( $exit ) {

				exit;

			}

		} else {

			$log->invoice_id 		= $invoice_id;

			$log->subscription_id 	= $subscription_id;

			$log->amount 			= $amount;

			$log->description 		= $notes;

			$log->external_id 		= $external_id;

			if ( $success ) {

				$log->manual_state( 'ok' );

			}

			$log->save();

		}



		do_action(

			'ms_gateway_payfast_handle_return_after',

			$this,

			$log

		);



		if ( $log ) {

			return $log;

		}

	}

	/**
	 * Verify required fields.
	 * @since  1.0.0
	 * @return boolean
	 */

	public function is_configured() {

		$is_configured 	= true;
		$required 		= array( 'merchant_id', 'merchant_key', 'passphrase' );


		foreach ( $required as $field ) {

			$value = $this->$field;

			if ( empty( $value ) ) {

				$is_configured = false;

				break;

			}

		}

		return apply_filters(

			'ms_gateway_payfast_is_configured',

			$is_configured

		);

	}



	/**

	 * Validate specific property before set.

	 *

	 * @since  1.0.0

	 *

	 * @access public

	 * @param string $name The name of a property to associate.

	 * @param mixed $value The value of a property.

	 */

	public function __set( $property, $value ) {

		if ( property_exists( $this, $property ) ) {
			parent::__set( $property, $value );

		}

		do_action(

			'ms_gateway_payfast__set_after',

			$property,

			$value,

			$this

		);
	}
}
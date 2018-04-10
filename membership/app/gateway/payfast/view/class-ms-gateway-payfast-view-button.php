<?php



class MS_Gateway_PayFast_View_Button extends MS_View {



	public function to_html() {

		$fields 		= $this->prepare_fields();

		$subscription 	= $this->data['ms_relationship'];

		$invoice 		= $subscription->get_current_invoice();

		$gateway 		= $this->data['gateway'];

        

		$action_url 	= apply_filters(

			'ms_gateway_payfast_view_button_form_action_url',

			$this->data['action_url']

		);



		$row_class 		= 'gateway_' . $gateway->id;

		if ( ! $gateway->is_live_mode() ) {

			$row_class .= ' sandbox-mode';

		}



		ob_start();

		?>

		<form action="<?php echo esc_url( $action_url ); ?>" method="post">

			<?php

			foreach ( $fields as $field ) {

				MS_Helper_Html::html_element( $field );

			}

			?>

			<img alt="" border="0" width="1" height="1" src="https://www.payfast.co.za/images/buttons/light-small-paynow.png" >

		</form>

		<?php

		$payment_form = apply_filters(

			'ms_gateway_form',

			ob_get_clean(),

			$gateway,

			$invoice,

			$this

		);



		ob_start();

		?>

		<tr class="<?php echo esc_attr( $row_class ); ?>">

			<td class="ms-buy-now-column" colspan="2">

				<?php echo $payment_form; ?>

			</td>

		</tr>

		<?php

		$html = ob_get_clean();



		$html = apply_filters(

			'ms_gateway_button-' . $gateway->id,

			$html,

			$this

		);



		$html = apply_filters(

			'ms_gateway_button',

			$html,

			$gateway->id,

			$this

		);



		return $html;

	}



	/**

	 *

	 */

	private function prepare_fields() {

		$subscription = $this->data['ms_relationship'];
		$membership	= $subscription->get_membership();
        
		if ( 0 === $membership->price ) {

			return;

		}
		
        $member = $subscription->get_member();
		$gateway = $this->data['gateway'];
		$invoice = $subscription->get_current_invoice();
		
     /*  
     SANDBOX DETAILS
        Merchant ID	10000100
        Merchant Key	46f0cd694581a
    */
        
        $merchant_id_to_use = 10000100;
        $merchant_key_to_use = '46f0cd694581a';
        
        if ( $gateway->is_live_mode() ) {
            $merchant_id_to_use = $gateway->merchant_id;
            $merchant_key_to_use = $gateway->merchant_key;
		}

		$fields = array(
        /*Merchant details*/
			'merchant_id' => array(

				'id' 	=> 'merchant_id',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $merchant_id_to_use,

			),

			'merchant_key' => array(

				'id' 	=> 'merchant_key',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $merchant_key_to_use,

			),

			'passphrase'	=> array(

				'id' 	=> 'passphrase',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $gateway->passphrase,

			),
			
            'return' 		=> array(

				'id' 	=> 'return_url',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => esc_url_raw(

					add_query_arg(

						array( 'ms_relationship_id' => $subscription->id ),

						MS_Model_Pages::get_page_url( MS_Model_Pages::MS_PAGE_REG_COMPLETE, false )

					)

				),

			),

			'cancel_return' => array(

				'id' 	=> 'cancel_url',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => MS_Model_Pages::get_page_url( MS_Model_Pages::MS_PAGE_REGISTER ),

			),

			'notify_url' 	=> array(

				'id' 	=> 'notify_url',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $gateway->get_return_url(),

			),
		/*Buyer details*/
			'first_name' 	=> array(

				'id' 	=> 'name_first',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $member->first_name,

			),
			'last_name' 	=> array(

				'id' 	=> 'name_last',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $member->last_name,

			),
			'email' 	=> array(

				'id' 	=> 'email_address',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $member->email,

			),
		/*Transaction details*/
			'invoice' 		=> array(

				'id' 	=> 'm_payment_id',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $invoice->id,

			),
			
			'amount' 		=> array(

				'id' 	=> 'amount',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => MS_Helper_Billing::format_price( $invoice->total ),

			),
			
			'item_name' 	=> array(

				'id' 	=> 'item_name',

				'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

				'value' => $membership->name,

			),
		);

       if($subscription->payment_type == 'recurring'){
           $frequency = 0;
           switch($membership->pay_cycle_period['period_type'])
           {
               case 'months':
                   $frequency = 3;
                   break;
               case 'years':
                   $frequency = 6;
                   break;
               
           }
           //$billingDate = $subscription->TODO
           
           $fields[] = array('id' => 'subscription_type','type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,'value' => 1);
           //$fields[] = array('id' 	=> 'billing_date','type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,'value' => $membership->name);
           $fields[] = array('id' => 'frequency','type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,'value' => $frequency);
           $fields[] = array('id' => 'cycles','type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,'value' => 0);//indefinite subscription
       }
		// Don't send to paypal if free

		if ( 0 === $invoice->total ) {

			$fields = array(

				'gateway' 				=> array(

					'id' 	=> 'gateway',

					'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

					'value' => $gateway->id,

				),

				'ms_relationship_id' 	=> array(

					'id' 	=> 'ms_relationship_id',

					'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

					'value' => $subscription->id,

				),

				'step' 					=> array(

					'id' 	=> 'step',

					'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

					'value' => MS_Controller_Frontend::STEP_PROCESS_PURCHASE,

				),

				'_wpnonce' 				=> array(

					'id' 	=> '_wpnonce',

					'type' 	=> MS_Helper_Html::INPUT_TYPE_HIDDEN,

					'value' => wp_create_nonce(

						$gateway->id . '_' .$subscription->id

					),

				),

			);

			$this->data['action_url'] = null;

		} else {

			if ( $gateway->is_live_mode() ) {

				$this->data['action_url'] = 'https://www.payfast.co.za/eng/process';

			} else {

				$this->data['action_url'] = 'https://sandbox.payfast.co.za/eng/process';

			}

		}



		$fields['submit'] = array(

			'id' 	=> 'submit',

			'type' 	=> MS_Helper_Html::INPUT_TYPE_IMAGE,

			'value' => 'https://www.payfast.co.za/images/buttons/light-small-paynow.png',

			'alt' 	=> __( 'PayFast - The safer, easier way to pay online', 'membership2' ),

		);



		// custom pay button defined in gateway settings

		$custom_label = $gateway->pay_button_url;

		if ( ! empty( $custom_label ) ) {

			if ( false !== strpos( $custom_label, '://' ) ) {

				$fields['submit']['value'] = $custom_label;

			} else {

				$fields['submit'] = array(

					'id' 	=> 'submit',

					'type' 	=> MS_Helper_Html::INPUT_TYPE_SUBMIT,

					'value' => $custom_label,

				);

			}

		}



		return apply_filters(

			'ms_gateway_payfast_view_prepare_fields',

			$fields, $invoice

		);

	}

}
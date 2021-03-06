<?php
/**
 * Plugin Name: Jigoshop Platron
 * Plugin URI: http://platron.ru/
 * Description: Gateway platron for jigoshop.
 * Author: lashnev
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: jigoplatron
 * Domain Path: /languages/
 */

require_once('include/PG_Signature.php');
/**
 * Jigoshop fallback notice.
 */
function jigoplatron_jigoshop_fallback_notice() {
    $message = '<div class="error">';
        $message .= '<p>' . __( 'Jigoshop platron Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/jigoshop/">Jigoshop</a> to work!' , 'jigoplatron' ) . '</p>';
    $message .= '</div>';

    echo $message;
}

/**
 * Load functions.
 */
add_action( 'plugins_loaded', 'jigoplatron_gateway_load', 0 );

function jigoplatron_gateway_load() {

    if ( !class_exists( 'jigoshop_payment_gateway' ) ) {
        add_action( 'admin_notices', 'jigoplatron_jigoshop_fallback_notice' );

        return;
    }

    /**
     * Load textdomain.
     */
    load_plugin_textdomain( 'jigoplatron', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    /**
     * Add the gateway to Jigoshop.
     *
     * @access public
     * @param array $methods
     * @return array
     */
    add_filter( 'jigoshop_payment_gateways', 'jigoplatron_add_gateway' );

    function jigoplatron_add_gateway( $methods ) {
        $methods[] = 'platron_Gateway';
        return $methods;
    }

    /**
     * platron Gateway Class.
     *
     * Built the platron method.
     */
    class Platron_Gateway extends jigoshop_payment_gateway {

        /**
         * Constructor for the gateway.
         *
         * @return void
         */
        public function __construct() {

            parent::__construct();

            $this->id             = 'platron';
            $this->icon           = plugins_url( 'images/platron.png', __FILE__ );
            $this->has_fields     = false;
            $this->payment_url    = 'https://platron.ru/payment.php';
            $this->method_title   = __( 'platron', 'jigoplatron' );

            // Define user set variables.
            $this->enabled		= Jigoshop_Base::get_options()->get_option( 'jigoplatron_enabled' );
            $this->title		= Jigoshop_Base::get_options()->get_option( 'jigoplatron_title' );
            $this->description	= Jigoshop_Base::get_options()->get_option( 'jigoplatron_description' );
            $this->merchant_id	= Jigoshop_Base::get_options()->get_option( 'jigoplatron_merchant_id' );
            $this->secret_key	= Jigoshop_Base::get_options()->get_option( 'jigoplatron_secret_key' );
            $this->lifetime		= Jigoshop_Base::get_options()->get_option( 'jigoplatron_lifetime' );
			$this->testmode		= Jigoshop_Base::get_options()->get_option( 'jigoplatron_testmode' );

            // Actions.
            add_action( 'receipt_platron', array( &$this, 'receipt_page' ) );
            add_action( 'wp_head', array( &$this, 'css' ) );
			add_action('jigoshop_api_js_gateway_platron', array($this, 'platron_callback'));

            // Valid for use.
            $this->enabled = ( 'yes' == $this->enabled ) && !empty( $this->merchant_id ) && !empty( $this->secret_key ) && $this->is_valid_for_use();

            // Checks if merchant -d is not empty.
            $this->merchant_id == '' ? add_action( 'admin_notices', array( &$this, 'merchant_id_missing_message' ) ) : '';

            // Checks if secret key is not empty.
            $this->secret_key == '' ? add_action( 'admin_notices', array( &$this, 'secret_key_missing_message' ) ) : '';
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( ! in_array( Jigoshop_Base::get_options()->get_option( 'jigoshop_currency' ), array( 'RUB','USD','EUR' ) ) ) {
                return false;
            }

            return true;
        }

        /**
         * Default Option settings for WordPress Settings API using the Jigoshop_Options class.
         *
         * These will be installed on the Jigoshop_Options 'Payment Gateways' tab by the parent class 'jigoshop_payment_gateway'.
         *
         */
        protected function get_default_options() {

            $defaults = array();

            // Define the Section name for the Jigoshop_Options.
            $defaults[] = array(
                'name' => __( 'Platron', 'jigoplatron' ),
                'type' => 'title',
                'desc' => __( 'Platron payment gate', 'jigoplatron' )
            );

            // List each option in order of appearance with details.
            $defaults[] = array(
                'name'      => __( 'Enable platron', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => '',
                'id'        => 'jigoplatron_enabled',
                'std'       => 'yes',
                'type'      => 'checkbox',
                'choices'   => array(
                    'no'            => __( 'No', 'jigoplatron' ),
                    'yes'           => __( 'Yes', 'jigoplatron' )
                )
            );

            $defaults[] = array(
                'name'      => __( 'Method Title', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => __( 'This controls the title which the user sees during checkout.', 'jigoplatron' ),
                'id'        => 'jigoplatron_title',
                'std'       => __( 'platron', 'jigoplatron' ),
                'type'      => 'text'
            );

            $defaults[] = array(
                'name'      => __( 'Description', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => __( 'This controls the description which the user sees during checkout.', 'jigoplatron' ),
                'id'        => 'jigoplatron_description',
                'std'       => __( 'Pay via platron', 'jigoplatron' ),
                'type'      => 'longtext'
            );

            $defaults[] = array(
                'name'      => __( 'Merchant id', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => __( 'See it on <a target="_blank" href="https://www.platron.ru/merchants.php">platron</a>.', 'jigoplatron' ),
                'id'        => 'jigoplatron_merchant_id',
                'std'       => '',
                'type'      => 'text'
            );

            $defaults[] = array(
                'name'      => __( 'Secret key', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => __( 'Used for sign. See it on <a target="_blank" href="https://www.platron.ru/merchants.php">platron</a>.', 'jigoplatron' ),
                'id'        => 'jigoplatron_secret_key',
                'std'       => '',
                'type'      => 'text'
            );

            $defaults[] = array(
                'name'      => __( 'Lifetime', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => __( 'Set minutes. For payment systems, which dont use check request. 0 - not set. Max 7 day', 'jigoplatron' ),
                'id'        => 'jigoplatron_lifetime',
                'std'       => '0',
                'type'      => 'text'
            );
			
			$defaults[] = array(
                'name'      => __( 'Test mode', 'jigoplatron' ),
                'desc'      => '',
                'tip'       => __( 'Use it to test connection and integration', 'jigoplatron' ),
                'id'        => 'jigoplatron_testmode',
                'std'       => 'yes',
                'type'      => 'checkbox',
                'choices'   => array(
                    'no'            => __( 'No', 'jigoplatron' ),
                    'yes'           => __( 'Yes', 'jigoplatron' )
                )
            );

            return $defaults;
        }

        /**
         * There are no payment fields, but we want to show the description if set.
         */
        function payment_fields() {
            if ( $this->description ) {
                echo wpautop( wptexturize( $this->description ) );
            }
        }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form( $order_id ) {
            global $jigoshop;

			// Filter redirect page.
			$my_account_page_id = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id( 'myaccount' ) );
			
            $order = new jigoshop_order( $order_id );
			$strCurrency = Jigoshop_Base::get_options()->get_option( 'jigoshop_currency' );
			if($strCurrency == 'RUB')
				$strCurrency = 'RUR';
			
			$strDescription = '';
			foreach($order->items as $arrItem){
				$strDescription .= $arrItem['name'];
				if($arrItem['qty'] > 1)
					$strDescription .= '*'.$arrItem['qty']."; ";
				else
					$strDescription .= "; ";
			}

			$form_fields = array(
				'pg_merchant_id'	=> $this->merchant_id,
				'pg_order_id'		=> $order->_data['id'],
				'pg_currency'		=> $strCurrency,
				'pg_language'		=> (WPLANG == 'ru_RU')?'ru':'en',
				'pg_amount'			=> number_format($order->order_total, 2, '.', ''),
				'pg_lifetime'		=> $this->lifetime*60, // в секундах
				'pg_testing_mode'	=> ($this->testmode == 'yes') ? 1 : 0,
				'pg_description'	=> mb_substr($strDescription, 0, 255, "UTF-8"),
				'pg_check_url'		=> jigoshop_request_api::query_request('index.php?js-api=JS_Gateway_Platron', false),
				'pg_result_url'		=> jigoshop_request_api::query_request('index.php?js-api=JS_Gateway_Platron', false),
				'pg_request_method'	=> 'GET',
				'pg_success_url'	=> add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( $my_account_page_id ) ) ),
				'pg_failure_url'	=> add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( $my_account_page_id ) ) ),
				'pg_salt'			=> rand(21,43433), // Параметры безопасности сообщения. Необходима генерация pg_salt и подписи сообщения.
			);

			preg_match_all("/\d/", $order->billing_phone, $array);
			$strPhone = implode('',$array[0]);
			if(!empty($strPhone))
			$form_fields['pg_user_phone'] = $strPhone;	

			if(preg_match('/^.+@.+\..+$/', $order->billing_email)){
				$form_fields['pg_user_email'] = $order->billing_email;
				$form_fields['pg_user_contact_email'] = $order->billing_email;
			}
			
			$form_fields['pg_sig'] = PG_Signature::make('payment.php', $form_fields, $this->secret_key);
            jigoshop_log( 'Payment arguments for order #' . $order_id . ': ' . print_r( $form_fields, true ) );

			foreach ($form_fields as $strFieldName => $strFieldValue) {
				$args_array[] = '<input type="hidden" name="'.esc_attr($strFieldName).'" value="'.esc_attr($strFieldValue).'" />';
			}

			return '<form action="'.esc_url($this->payment_url).'" method="POST" id="platron_payment_form">'."\n".
				implode("\n", $args_array).
				'<input type="submit" class="button alt" id="submit_platron_payment_form" value="'.__('Pay', 'jigoplatron').'" />'.
				'</form>'
				.'<script type="text/javascript">
				setTimeout(function () {
					document.getElementById("platron_payment_form").submit();
				}, 1000);
				</script>'
				;
        }

        /**
         * Fix platron CSS.
         *
         * @return string Styles.
         */
        public function css() {
            echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>';
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = new jigoshop_order( $order_id );

            return array(
                'result'    => 'success',
                'redirect'  => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( jigoshop_get_page_id( 'pay' ) ) ) )
            );

        }

        /**
         * Output for the order received page.
         *
         * @return void
         */
        function receipt_page( $order ) {
            echo $this->generate_form( $order );
        }


        /**
         * Adds error message when not configured the client_id.
         *
         * @return string Error Mensage.
         */
        public function merchant_id_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your merchant id in platron. %sClick here to configure!%s' , 'jigoplatron' ), '<a href="' . get_admin_url() . 'admin.php?page=jigoshop_settings&tab=расчет">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }

        /**
         * Adds error message when not configured the client_secret.
         *
         * @return string Error Mensage.
         */
        public function secret_key_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your secret key in platron. %sClick here to configure!%s' , 'jigoplatron' ), '<a href="' . get_admin_url() . 'admin.php?page=jigoshop_settings&tab=расчет">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }
		
		public function platron_callback(){
			if(!empty($_POST))
				$arrRequest = $_POST;
			else
				$arrRequest = $_GET;

			$thisScriptName = PG_Signature::getOurScriptName();
			if (empty($arrRequest['pg_sig']) || !PG_Signature::check($arrRequest['pg_sig'], $thisScriptName, $arrRequest, $this->secret_key))
				die("Wrong signature");

			$objOrder = new jigoshop_order( $arrRequest['pg_order_id'] );
			$arrStatuses = jigoshop_order::get_order_statuses_and_names();
			
			$arrResponse = array();
			$aGoodCheckStatuses = array('new','pending','processing');
			$aGoodResultStatuses = array('new','pending','processing','completed');

			if(isset($arrRequest['pg_payment_date']))
				$arrRequest['type'] = 'result';
			else
				$arrRequest['type'] = 'check';
				
			switch($arrRequest['type']){
				case 'check':
					$bCheckResult = 1;			
					if(empty($objOrder) || !in_array($objOrder->status, $aGoodCheckStatuses)){
						$bCheckResult = 0;
						$error_desc = 'Order status '.$arrStatuses[$objOrder->status].' or deleted order';
					}
					if(intval($objOrder->order_total) != intval($arrRequest['pg_amount'])){
						$bCheckResult = 0;
						$error_desc = 'Wrong amount';
					}

					$arrResponse['pg_salt']              = $arrRequest['pg_salt']; 
					$arrResponse['pg_status']            = $bCheckResult ? 'ok' : 'error';
					$arrResponse['pg_error_description'] = $bCheckResult ?  ""  : $error_desc;
					$arrResponse['pg_sig']				 = PG_Signature::make($thisScriptName, $arrResponse, $this->secret_key);

					$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
					$objResponse->addChild('pg_salt', $arrResponse['pg_salt']);
					$objResponse->addChild('pg_status', $arrResponse['pg_status']);
					$objResponse->addChild('pg_error_description', $arrResponse['pg_error_description']);
					$objResponse->addChild('pg_sig', $arrResponse['pg_sig']);
					break;

				case 'result':	
					if(intval($objOrder->order_total) != intval($arrRequest['pg_amount'])){
						$strResponseDescription = 'Wrong amount';
						if($arrRequest['pg_can_reject'] == 1)
							$strResponseStatus = 'rejected';
						else
							$strResponseStatus = 'error';
					}
					elseif((empty($objOrder) || !in_array($objOrder->status, $aGoodResultStatuses)) && 
							!($arrRequest['pg_result'] == 0 && $objOrder->status == 'failed')){
						$strResponseDescription = 'Order status '.$arrStatuses[$objOrder->status].' or deleted order';
						if($arrRequest['pg_can_reject'] == 1)
							$strResponseStatus = 'rejected';
						else
							$strResponseStatus = 'error';
					} else {
						$strResponseStatus = 'ok';
						$strResponseDescription = "Request cleared";
						if ($arrRequest['pg_result'] == 1){
							// Обновить статус
							$objOrder->payment_complete();
//							$objOrder->update_status('completed', 'Platron transaction id '.$arrRequest['pg_transaction_id']);
						}
						else{
							// Обновить статус
//							$objOrder->cancel_order('Platron transaction id '.$arrRequest['pg_transaction_id'].' failure description: '.$arrRequest['pg_failure_description']);
							$objOrder->update_status('failed', 'Platron transaction id '.$arrRequest['pg_transaction_id'].' failure description: '.$arrRequest['pg_failure_description']);
						}
					}

					$objResponse = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><response/>');
					$objResponse->addChild('pg_salt', $arrRequest['pg_salt']);
					$objResponse->addChild('pg_status', $strResponseStatus);
					$objResponse->addChild('pg_description', $strResponseDescription);
					$objResponse->addChild('pg_sig', PG_Signature::makeXML($thisScriptName, $objResponse, $this->secret_key));

					break;
				case 'success':
					wp_redirect( $this->get_return_url( $objOrder ) );
					break;
				case 'failed':
					wp_redirect($objOrder->get_cancel_order_url());
					break;
				default :
					die('wrong type');
			}

			header("Content-type: text/xml");
			echo $objResponse->asXML();
			die();
		}

    } // class platron_Gateway.
} // function jigoplatron_gateway_load.

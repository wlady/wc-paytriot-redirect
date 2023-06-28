<?php

namespace WCPaytriotRedirect;


/**
 * Paytriot Redirect class
 */
class PaytriotRedirect extends \WC_Payment_Gateway_CC {

	const GATEWAY_TITLE = 'Paytriot ReDirect';
	const GATEWAY_NAME = 'WC_Paytriot_Redirect';
	const GATEWAY_ID = 'paytriot_redirect';
	const TEXT_DOMAIN = 'wc-paytriot';

	public function __construct() {

		$this->has_fields         = false;
		$this->id                 = self::GATEWAY_ID;
		$this->icon               = '';
		$this->method_title       = __( self::GATEWAY_TITLE, self::TEXT_DOMAIN );
		$this->method_description = __( 'Pay via Credit / Debit Card with ' . self::GATEWAY_TITLE . ' secure card processing.',
			self::TEXT_DOMAIN );

		$this->init_form_fields();
		$this->init_settings();

		// Get setting values
		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' ) ?? self::GATEWAY_TITLE;
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways', [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'woocommerce_api_' . strtolower( self::GATEWAY_NAME ), [ $this, 'process_response' ] );
	}

	/**
	 * Initialise Gateway Settings
	 */
	function init_form_fields() {

		$this->form_fields = [
			'enabled' => [
				'title'       => __( 'Enable/Disable', self::TEXT_DOMAIN ),
				'label'       => __( 'Enable ' . self::GATEWAY_TITLE, self::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],

			'title' => [
				'title'       => __( 'Title', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.',
					self::TEXT_DOMAIN ),
				'default'     => __( self::GATEWAY_TITLE, self::TEXT_DOMAIN ),
			],

			'description' => [
				'title'       => __( 'Description', self::TEXT_DOMAIN ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.',
					self::TEXT_DOMAIN ),
				'default'     => 'Pay securely via Credit / Debit Card with ' . self::GATEWAY_TITLE,
			],

			'merchantID' => [
				'title'       => __( 'Merchant ID', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Please enter your ' . self::GATEWAY_TITLE . ' merchant ID', self::TEXT_DOMAIN ),
			],

			'signature' => [
				'title'       => __( 'Signature Key', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Please enter the signature key for the merchant account.', self::TEXT_DOMAIN ),
			],

			'countryCode' => [
				'title'       => __( 'Country Code', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Please enter your 3 digit <a href="http://en.wikipedia.org/wiki/ISO_3166-1" target="_blank">ISO country code</a>',
					self::TEXT_DOMAIN ),
				'default'     => '826',
			],

			'exclude' => [
				'title'       => __( 'Exclude 3DS', self::TEXT_DOMAIN ),
				'type'        => 'text',
				'description' => __( 'Exclude bank names from 3DS check. List of names divided by commas.',
					self::TEXT_DOMAIN ),
			],

			'debug' => [
				'title'       => __( 'Debug mode', self::TEXT_DOMAIN ),
				'label'       => __( 'Enable debug mode', self::TEXT_DOMAIN ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],

		];

	}

	function throw_empty_response_ajax() {
		$message = 'Payment unsuccessful - empty response (contact Administrator)';
		wc_add_notice( $message, $notice_type = 'error' );
	}

	public function payment_scripts() {

		if ( ! is_cart() && ! is_checkout() && 'no' === $this->enabled ) {
			return;
		}

		wp_enqueue_style(
			'paytriot_redirect_css',
			plugins_url( 'assets/style.css', PAYTRIOT_REDIRECT_PLUGIN_NAME ),
			false,
			PAYTRIOT_REDIRECT_VERSION
		);

		// and this is our custom JS in your plugin directory that works with token.js
		wp_enqueue_script(
			'paytriot_redirect_script',
			plugins_url( 'assets/script.js', PAYTRIOT_REDIRECT_PLUGIN_NAME ),
			[ 'jquery' ],
			PAYTRIOT_REDIRECT_VERSION
		);

	}

	public function payment_fields() {
		$this->form();
	}

	/**
	 * Outputs fields for entering credit card information
	 *
	 * @since 2.6.0
	 */
	public function form() {
		wp_enqueue_script( 'wc-credit-card-form' );

		$fields = [];

		$default_fields = [
			'card-number-field' => '<p class="form-row form-row-wide">
                    <label for="' . esc_attr( $this->get_name( 'card-number' ) ) . '">' . esc_html__( 'Card number',
					'wc-paytriot' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->get_name( 'card-number' ) ) . '" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
                    <span style="color:red" id="card-number-error"></span>
                </p>',
			'card-expiry-field' => '<p class="form-row form-row-first">
                    <label for="' . esc_attr( $this->get_name( 'card-expiry' ) ) . '">' . esc_html__( 'Expiry (MM/YY)',
					'wc-paytriot' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->get_name( 'card-expiry' ) ) . '" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="7" placeholder="' . esc_attr__( 'MM / YY',
					'wc-paytriot' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
                </p>',
			'card-cvc-field' => '<p class="form-row form-row-last">
                    <label for="' . esc_attr( $this->get_name( 'card-cvc' ) ) . '">' . esc_html__( 'Card code',
				'wc-paytriot' ) . '&nbsp;<span class="required">*</span></label>
                    <input id="' . esc_attr( $this->get_name( 'card-cvc' ) ) . '" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC',
				'wc-paytriot' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
                </p>',
		];

		$fields = wp_parse_args( $fields,
			apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
		?>

        <fieldset id="wc-<?php echo esc_attr( $this->get_name( 'cc-form' ) ); ?>"
                  class='wc-credit-card-form wc-payment-form'>
			<?php _e( wpautop( wptexturize( $this->get_option( 'description' ) ) ) ); ?>
			<?php
			foreach ( $fields as $field ) {
				_e( $field );
			}
			?>
            <div class="clear"></div>
        </fieldset>
		<?php
	}

	public function validate_fields() {
		$card_number = sanitize_text_field( $_POST[ $this->get_name( 'card-number' ) ] );
		if ( empty( $card_number ) ) {
			wc_add_notice( 'Card Number is required!', 'error' );

			return false;
		}
		$expire_data = sanitize_text_field( $_POST[ $this->get_name( 'card-expiry' ) ] );
		if ( empty( $expire_data ) ) {
			wc_add_notice( 'Card Expire date is required!', 'error' );

			return false;
		}
		$card_cvc = sanitize_text_field( $_POST[ $this->get_name( 'card-cvc' ) ] );
		if ( empty( $card_cvc ) ) {
			wc_add_notice( 'Card CVC is required!', 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Output field name HTML
	 *
	 * Gateways which support tokenization do not require names - we don't want the data to post to the server.
	 *
	 * @param string $name Field name.
	 *
	 * @return string
	 * @since  2.6.0
	 */
	public function field_name( $name ) {
		return $this->supports( 'tokenization' ) ? '' : ' name="' . esc_attr( $this->get_name( $name ) ) . '" ';
	}

	private function get_name( $name ) {
		return $this->id . '-' . $name;
	}

	public function process_payment( $order_id ) {

		// we need it to get any order details
		$order = wc_get_order( $order_id );

		if ( $order->is_paid() ) {
			return [
				'result'   => 'failure',
				'messages'  => __( 'Order is already paid. Please check your account', self::TEXT_DOMAIN ),
			];
		}

		/*
		  * get  parameters for API interaction
		 */
		$card_number = sanitize_text_field( $_POST[ $this->get_name( 'card-number' ) ] );
		$expire_data = sanitize_text_field( $_POST[ $this->get_name( 'card-expiry' ) ] );
		$card_cvc    = sanitize_text_field( $_POST[ $this->get_name( 'card-cvc' ) ] );
		$date_parts  = explode( '/', $expire_data );

		if ( empty( $card_number ) || empty( $expire_data ) || empty( $card_cvc ) || count( $date_parts ) != 2 ) {
			return [
				'result'   => 'failure',
				'messages'  => __( 'Wrong card data', self::TEXT_DOMAIN ),
			];
		}

		$threeDSRedirectURL = $this->get_return_url( $order );

		$checkout_page = wc_get_checkout_url();

		$callback = add_query_arg( [
			'wc-api'   => self::GATEWAY_NAME,
			'order_id' => $order_id,
			'acs'      => 1,
		], $checkout_page );

		$device_data = [
			'deviceChannel'          => 'browser',
			'deviceIdentity'         => ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? htmlentities( $_SERVER['HTTP_USER_AGENT'] ) : null ),
			'deviceTimeZone'         => '0',
			'deviceCapabilities'     => '',
			'deviceScreenResolution' => '1x1x1',
			'deviceAcceptContent'    => ( isset( $_SERVER['HTTP_ACCEPT'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT'] ) : null ),
			'deviceAcceptEncoding'   => ( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT_ENCODING'] ) : null ),
			'deviceAcceptLanguage'   => ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : null ),
			'deviceAcceptCharset'    => ( isset( $_SERVER['HTTP_ACCEPT_CHARSET'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT_CHARSET'] ) : null ),
		];

		$fields = array_map( 'stripslashes_deep', $_POST );

		$exclude = array_map( 'trim', array_map( 'strtoupper', explode( ',', $this->get_option( 'exclude' ) ) ) );

		$req = array_merge( $this->capture_order( $order_id ), [
			'type'               => 1,
			'cardNumber'         => $card_number,
			'cardExpiryMonth'    => trim( $date_parts[0] ),
			'cardExpiryYear'     => trim( $date_parts[1] ),
			'cardCVV'            => $card_cvc,
			'remoteAddress'      => $_SERVER['REMOTE_ADDR'],
			'threeDSRedirectURL' => $callback,
			'processor'          => PAYTRIOT_REDIRECT_PLUGIN_URL . 'pay.php?sid=' . session_id(),
			'back_url'           => $threeDSRedirectURL,
			'checkout_url'       => $checkout_page,
			'merchantID'         => $this->get_option( 'merchantID' ),
			'merchantKey'        => $this->get_option( 'signature' ),
			'countryCode'        => $this->get_option( 'countryCode' ),
			'currencyCode'       => $order->get_currency(),
			'exclude'            => $exclude,
			'debug'              => $this->get_option( 'debug' ) == 'yes',
		], $device_data, $fields['browserInfo'] ?? [] );

		$_SESSION['request'] = $req;

//		if ( $this->get_option( 'debug' ) == 'yes' ) {
//			Logger::info( 'New request', [
//				'request' => $req,
//			] );
//		}

		return [
			'result'   => 'success',
			'request'  => $req,
			'redirect' => '',
		];
	}


	public function capture_order( $order_id ) {

		$order  = wc_get_order( $order_id );
		$amount = intval( bcmul( round( $order->get_total(), 2 ), 100, 0 ) );

		$billing_address = $order->get_billing_address_1();
		$billing2        = $order->get_billing_address_2();

		if ( ! empty( $billing2 ) ) {
			$billing_address .= "\n" . $billing2;
		}
		$billing_address .= "\n" . $order->get_billing_city();
		$state           = $order->get_billing_state();
		if ( ! empty( $state ) ) {
			$billing_address .= "\n" . $state;
			unset( $state );
		}
		$country = $order->get_billing_country();
		if ( ! empty( $country ) ) {
			$billing_address .= "\n" . $country;
			unset( $country );
		}

		// Fields for hash
		$req = [
			'amount'            => $amount,
			'countryCode'       => $this->get_option( 'countryCode' ),
			'currencyCode'      => $order->get_currency(),
			'transactionUnique' => $order->get_order_key() . '-' . time(),
			'orderRef'          => $order_id,
			'customerName'      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'customerAddress'   => $billing_address,
			'customerPostcode'  => $order->get_billing_postcode(),
			'customerEmail'     => $order->get_billing_email(),
		];

		$phone = $order->get_billing_phone();
		if ( ! empty( $phone ) ) {
			$req['customerPhone'] = $phone;
			unset( $phone );
		}

		return $req;
	}

	/**
	 * Check for response from payment script
	 */
	function process_response( $data = null ) {

		$response = array_map( 'stripslashes_deep', $_POST );
		$order_id = sanitize_text_field( $_GET['order_id'] );
		$is_acs   = sanitize_text_field( $_GET['acs'] );
		$wc_api   = sanitize_text_field( $_GET['wc-api'] );

		$order = wc_get_order( $order_id );

		if ( $order_id && $is_acs && $wc_api == self::GATEWAY_NAME ) {

			if ( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {
				if ( $response['responseCode'] == 65802 ) {
//					if ( $this->get_option( 'debug' ) == 'yes' ) {
//						Logger::info( 'Continuation response', [
//							'response' => $response,
//						] );
//					}
				} elseif ( $response['responseCode'] != 0 ) {
//					if ( $this->get_option( 'debug' ) == 'yes' ) {
//						Logger::info( 'Payment error', [
//							'response' => $response,
//						] );
//					}
				} else {

//					if ( $this->get_option( 'debug' ) == 'yes' ) {
//						Logger::info( 'Payment success', [
//							'response' => $response,
//						] );
//					}

					$orderNotes = "\r\nResponse Code : {$response['responseCode']}\r\n";
					$orderNotes .= "Message : {$response['responseMessage']}\r\n";
					$orderNotes .= "Amount Received : " . number_format( $response['amount'] / 100, 2, '.',
							',' ) . "\r\n";
					$orderNotes .= "Unique Transaction Code : {$response['transactionUnique']}";
					$order->add_order_note( __( self::GATEWAY_TITLE . ' payment completed.' . $orderNotes ) );
					$order->payment_complete();
					$order->update_meta_data( '_thankyou_action_done', true );
					$order->save();
					echo '<script>window.top.location.href = ' . '"' . $response['back_url'] . '"' . '</script>';
					exit;
				}
			}
		}
		echo '<script>window.top.location.href = ' . '"' . wc_get_checkout_url() . '"' . '</script>';
		exit;
	}
}

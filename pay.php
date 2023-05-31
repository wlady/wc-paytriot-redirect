<?php

$scriptURL = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
header( 'Cache-Control: post-check=0, pre-check=0', false );
header( 'Pragma: no-cache' );

$sid = '';
if ( isset( $_GET['sid'] ) ) {
	session_id( $_GET['sid'] );
	$sid = $_GET['sid'];
}
unset( $_GET['sid'] );

session_start();

$request = $_SESSION['request'];

// Output input form
if ( $_SERVER['REQUEST_METHOD'] === 'GET' && empty( $_GET ) ) {

	echo <<< HTML
    <!doctype html>
    <html lang="en">
      <head>
        <title>3DS v2</title>
      </head>
      <body style="height: 100%; width: 100%; position: absolute;">
       <div style="height: 100%; width: 100%; background: #fff url('./assets/spinner.gif') center center no-repeat;">
      	<div style="visibility: hidden">
	    <form action="?process&sid={$sid}" method="post" name="myform" id="myform">
		        <input type="text" name="gatewayURL" value="https://gateway.paytriot.co.uk/direct/" required />
		        <input type="text" name="customerName" value="{$request['customerName']}" required/>
				<input type="text" name="cardNumber" value="{$request['cardNumber']}" required/>
		        <input type="text" name="cardExpiry" value="{$request['cardExpiryMonth']}{$request['cardExpiryYear']}" placeholder="e.g. 1222 for 12th of Dec 2022" required/>
				<input type="text" name="cardCVV" value="{$request['cardCVV']}" required/>
				<input type="text" name="customerAddress" value="{$request['customerAddress']}" required/>
				<input type="text" name="customerPostCode" value="{$request['customerPostcode']}" required/>
				<input type="text" name="amount" value="{$request['amount']}" placeholder="e.g. £1 is 100, £10 is 10000" required/>
				<input type="text" name="orderRef" value="{$request['orderRef']}" required/>
				<input type="text" name="transactionUnique" value="{$request['transactionUnique']}" required/>
				<button type="submit" >Submit</button>
			</form>
			<script>window.setTimeout('document.myform.submit()', 100);</script>
		</div>
	 </div>
	</body>
    </html>
HTML;

} elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_GET['process'] ) && ! isset( $_GET['3ds'] ) ) {

	// Request
	$gatewayRequest = [
		'merchantID'             => $request['merchantID'],
		'action'                 => 'SALE',
		'amount'                 => $_POST['amount'],
		'type'                   => 1,
		'countryCode'            => $request['countryCode'],
		'currencyCode'           => $request['currencyCode'],
		'orderRef'               => $_POST['orderRef'],
		'transactionUnique'      => $_POST['transactionUnique'] . '-' . uniqid( 5 ),
		// Credit card details
		'cardNumber'             => $_POST['cardNumber'],
		'cardExpiryDate'         => $_POST['cardExpiry'],
		'cardCVV'                => $_POST['cardCVV'],
		// Customer details
		'customerAddress'        => $_POST['customerAddress'],
		'customerPostCode'       => $_POST['customerPostCode'],
		'customerName'           => $_POST['customerName'],
		//Three DS V2 fields required
		'remoteAddress'          => $_SERVER['REMOTE_ADDR'],
		'threeDSRedirectURL'     => $scriptURL . '&3ds',
		'deviceChannel'          => $request['deviceChannel'] ?? 'browser',
		'deviceIdentity'         => $request['deviceIdentity'] ?? ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? htmlentities( $_SERVER['HTTP_USER_AGENT'] ) : null ),
		'deviceTimeZone'         => $request['deviceTimeZone'] ?? '0',
		'deviceCapabilities'     => $request['deviceCapabilities'] ?? '',
		'deviceScreenResolution' => $request['deviceScreenResolution'] ?? '1x1x1',
		'deviceAcceptContent'    => $request['deviceAcceptContent'] ?? ( isset( $_SERVER['HTTP_ACCEPT'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT'] ) : null ),
		'deviceAcceptEncoding'   => $request['deviceAcceptEncoding'] ?? 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
		'deviceAcceptLanguage'   => $request['deviceAcceptLanguage'] ?? ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : null ),
		'deviceAcceptCharset'    => $request['deviceAcceptCharset'] ?? ( isset( $_SERVER['HTTP_ACCEPT_CHARSET'] ) ? htmlentities( $_SERVER['HTTP_ACCEPT_CHARSET'] ) : null ),
	];

	if ( $request['debug'] ) {
		flog( [
			'New request',
			[
				'request' => $gatewayRequest,
			],
		] );
	}

	// save for for future use
	$_SESSION['request']['gatewayRequest'] = $gatewayRequest;

	// Sign the request
	$gatewayRequest['signature'] = createSignature( $gatewayRequest, $request['merchantKey'] );

	// Send the request to the gateway and get back the response
	$gatewayResponse = sendRequest( $gatewayRequest, $_POST['gatewayURL'] );
	// save for filtering in UPPER CASE
	$_SESSION['request']['cardIssuer'] = strtoupper( $gatewayResponse['cardIssuer'] );

	if ( $request['debug'] ) {
		flog( [
			'Continuation response 1',
			[
				'response' => $gatewayResponse,
			],
		] );
	}
	if ( $gatewayResponse['responseCode'] == 65802 ) {
		setcookie( 'gatewayURL', $_POST['gatewayURL'], time() + 500 );
		$_SESSION['request']['gatewayURL'] = $_POST['gatewayURL'];
		echo get3dshtmls( $gatewayResponse );
	} elseif ( $gatewayResponse['responseCode'] == 0 ) {
		if ( $request['debug'] ) {
			flog( [
				'Payment success',
				[
					'message' => $gatewayResponse['responseMessage'],
				],
			] );
		}
		sendRequest( $gatewayResponse, $request['threeDSRedirectURL'] );
		$url = $request['back_url'];
		session_write_close();
		header( 'Location: ' . $url );
		exit;
	} else {
		if ( $request['debug'] ) {
			flog( [
				'Payment error',
				[
					'message' => $gatewayResponse['responseMessage'],
				],
			] );
		}
		echo <<< HTML2
<body style="text-align: center">
    <div>
    	<h1>Payment Gateway Error</h1>
		<h2 style="color:red">{$gatewayResponse['responseMessage']}</h2>
    	<button onclick="window.location.href='{$request['checkout_url']}'" style="margin: 10px;padding:10px;font-size:20px;cursor:pointer;">Back to checkout page</button>
    </div>
</body>
HTML2;

	}
} elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_GET['process'] ) && isset( $_GET['3ds'] ) ) {

	// Build the request containing the threeDSResponse with data posted back from the 3DS page
	// and include the threeDSRef stored from the previous gateway response.
	$threeDSRequest = [
		'threeDSRef'      => $_COOKIE['threeDSRef'],
		// This is the threeDSref store in the cookie from the previous gateway response.
		'threeDSResponse' => $_POST,
		// <-- Note here no fields are hard coded. Whatever is POSTED from 3DS is returned.
	];
	// add exception for certain bank names
	if ( in_array( $request['cardIssuer'], $request['exclude'] ) ) {
		$threeDSRequest['threeDSCheckPref'] = 'not known,not checked,not authenticated,attempted authentication,authenticated';
	}

	$url = urldecode( $_COOKIE['gatewayURL'] );
	if ( empty( $url ) ) {
		$url = $request['gatewayURL'];
	}
	// Send the 3DS response back to the gateway and get the response.
	$gatewayResponse = sendRequest( $threeDSRequest, $url );

	if ( $request['debug'] ) {
		flog( [
			'Continuation response 2',
			[
				'response' => $gatewayResponse,
			],
		] );
	}
	if ( $gatewayResponse['responseCode'] == 65802 ) {
		echo get3dshtmls( $gatewayResponse );
	} elseif ( $gatewayResponse['responseCode'] == 0 ) {
		if ( $request['debug'] ) {
			flog( [
				'Payment success',
				[
					'message' => $gatewayResponse['responseMessage'],
				],
			] );
		}
		sendRequest( $gatewayResponse, $request['threeDSRedirectURL'] );
		$url = $request['back_url'];
		session_write_close();
		header( 'Location: ' . $url );
		exit;
	} else {
		if ( $request['debug'] ) {
			flog( [
				'Payment error',
				[
					'message' => $gatewayResponse['responseMessage'],
				],
			] );
		}
		echo <<< HTML3
<body style="text-align: center">
    <div>
    	<h1>Payment Gateway Error</h1>
		<h2 style="color:red">{$gatewayResponse['responseMessage']}</h2>
    	<button onclick="window.location.href='{$request['checkout_url']}'" style="margin: 10px;padding:10px;font-size:20px;cursor:pointer;">Back to checkout page</button>
    </div>
</body>
HTML3;

	}

} else {
	if ( $request['debug'] ) {
		flog( [
			'Unknown state',
			[
				'request' => $_REQUEST,
			],
		] );
	}
}

session_write_close();


function get3dshtmls( $gatewayResponse ) {

	$html = '<body style="position: absolute; height: 100%; width: 100%"><div style="height: 100%; width: 100%; background: #fff url(\'./assets/spinner.gif\') center center no-repeat;"><div style="visibility: hidden">';
	// Store the threeDSRef in a cookie for reuse.  (this is just one way of storeing it)
	setcookie( 'threeDSRef', $gatewayResponse['threeDSRef'], time() + 500 );

	// Start of HTML form with URL
	$html .= "<form action=\"" . htmlentities( $gatewayResponse['threeDSURL'] ) . "\"method=\"post\" name=\"myform\" id=\"myform\">";

	// Add threeDSRef from the gateway response
	$html .= '<input type="hidden" name="threeDSRef" value="' . $gatewayResponse['threeDSRef'] . '">';

	// For each of the fields in threeDSRequest output a hidden input field with it's key/value
	foreach ( $gatewayResponse['threeDSRequest'] as $key => $value ) {
		$html .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
	}

	$html .= "<input type=\"submit\" value=\"Continue\">
	</form></div></div>
	<script>window.setTimeout('document.myform.submit()', 100);</script></body>
	";

	return $html;
}

// send non 3DS request
function tryNon3DS() {
	$request   = $_SESSION['request'];
	$reqFields = [
		'merchantID',
		'action',
		'amount',
		'type',
		'countryCode',
		'currencyCode',
		'orderRef',
		'transactionUnique',
		'cardNumber',
		'cardExpiryDate',
		'cardCVV',
		'customerAddress',
		'customerPostCode',
		'customerName',
	];
	// remove unnecessary fields
	$req              = array_intersect_key(
		$request['gatewayRequest'],
		array_combine( $reqFields, $reqFields )
	);
	$req['signature'] = createSignature( $req, $request['merchantKey'] );
	if ( $request['debug'] ) {
		flog( [
			'Try Non 3DS',
			[
				'request' => $req,
			],
		] );
	}
	$res = sendRequest( $req, 'https://gateway.paytriot.co.uk/direct/' );
	// Extract the return signature as this isn't hashed
	$signature = null;
	if ( isset( $res['signature'] ) ) {
		$signature = $res['signature'];
		unset( $res['signature'] );
	}
	// Check the return signature
	if ( ! $signature || $signature !== createSignature( $res, $request['merchantKey'] ) ) {
		if ( $request['debug'] ) {
			flog( [
				'Try Non 3DS',
				[
					'message' => 'The signature check failed',
				],
			] );
		}
		die( 'Sorry, the signature check failed' );
	}
	if ( $request['debug'] ) {
		flog( [
			'Try Non 3DS',
			[
				'response' => $res,
			],
		] );
	}
	// Check the response code
	if ( $res['responseCode'] == 0 ) {
		sendRequest( $res, $request['threeDSRedirectURL'] );
		$url = $request['back_url'];
		session_write_close();
		header( 'Location: ' . $url );
		exit;
	} else {
		echo <<< HTML1
<body style="text-align: center">
    <div>
    	<h1>Failed to take payment</h1>
		<h2 style="color:red">{$res['responseMessage']}</h2>
    	<button onclick="window.location.href='{$request['checkout_url']}'" style="margin: 10px;padding:10px;font-size:20px;cursor:pointer;">Back to checkout page</button>
    </div>
</body>
HTML1;
	}
}

/**
 * Send request
 *
 * @param Array $request
 * @param String $gatewayURL
 *
 * @return Array $responseponse
 */
function sendRequest( $request, $gatewayURL ) {
	// Send request to the gateway
	// Initiate and set curl options to post to the gateway
	$ch = curl_init( $gatewayURL );
	curl_setopt( $ch, CURLOPT_USERAGENT,
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36' );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_MAXREDIRS, 3 );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $request ) );
	curl_setopt( $ch, CURLOPT_HEADER, false );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
	// Send the request and parse the response
	parse_str( curl_exec( $ch ), $response );
	// Close the connection to the gateway
	curl_close( $ch );

	return $response;
}

/**
 * Sign request
 *
 * @param Array $data
 * @param String $key
 *
 * @return String Hash
 */
function createSignature( array $data, $key ) {
	// Sort by field name
	ksort( $data );

	// Create the URL encoded signature string
	$ret = http_build_query( $data, '', '&' );

	// Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
	$ret = str_replace( [ '%0D%0A', '%0A%0D', '%0D' ], '%0A', $ret );

	// Hash the signature string and the key together
	return hash( 'SHA512', $ret . $key );
}


function flog( $var ) {
	$fn = function ( $var ) {
		ob_start();
		print_r( $var );
		$v = ob_get_contents();
		ob_end_clean();

		return $v;
	};
	file_put_contents( __DIR__ . '/../../uploads/wc-logs/paytriot_redirect-' . date( 'Y-m-d' ) . '.log',
		'+---+ ' . date( 'H:i:s d-m-Y' ) . ' +-----+' . PHP_EOL . $fn( $var ) . PHP_EOL . PHP_EOL,
		FILE_APPEND );
}

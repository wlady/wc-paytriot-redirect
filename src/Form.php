<?php
/**
 * Created by PhpStorm.
 * User: Vladimir Zabara <wlady2001@gmail.com>
 * Date: 14.04.2023
 * Time: 18:31
 */

namespace WCPaytriotRedirect;


use Paytriot\SDK\Gateway;

class Form {
	public static function show_and_submit( $request ) {
		echo <<< HTML
    <!doctype html>
    <html lang="en">
      <head>
        <title>3DS v2 Test script</title>
      </head>
      <body>
	      <div style="display: none">
		    <form action="?process" method="post">
		        <input type="text" name="gatewayURL" value="{Gateway::$directUrl}" required />
				<input type="text" name="merchantID" value="{Gateway::$merchantID}" required/>
				<input type="text" name="merchantKey" value="{Gateway::$merchantSecret}" required/>
		        <input type="text" name="customerName" value="{$request['customerName']}" required/>
				<input type="text" name="cardNumber" value="{$request['cardNumber']}" required/>
		        <input type="text" name="cardExpiry" value="{$request['cardExpiryMonth']}{$request['cardExpiryYear']}" placeholder="e.g. 1222 for 12th of Dec 2022" required/>
				<input type="text" name="cardCVV" value="{$request['cardCVV']}" required/>
				<input type="text" name="customerAddress" value="{$request['customerAddress']}" required/>
				<input type="text" name="customerPostCode" value="{$request['customerPostcode']}" required/>
				<input type="text" name="testAmount" value="{$request['amount']}" placeholder="e.g. £1 is 100, £10 is 10000" required/>
				<button type="submit" >Submit</button>
			</form>
			<script></script>
		</div>
	</body>
    </html>
HTML;
	}
}
<?php
/**
 * Created by PhpStorm.
 * User: Vladimir Zabara <wlady2001@gmail.com>
 * Date: 28.02.2023
 * Time: 17:37
 */

namespace WCPaytriotRedirect;


class HttpClient {

	public static function post_acs_request( array $args ) {
		$ch = curl_init( $args['threeDSURL'] );
		$fields = [
			'threeDSRef' => $args['threeDSRef'] ?? '',
		] + $args['threeDSRequest'];
		curl_setopt_array( $ch, [
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => http_build_query( $fields ),
				CURLOPT_HEADER         => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
			]
		);
		$response = curl_exec( $ch );
		curl_close( $ch );

		return [ $fields, $response ];
	}

	public static function add_base_to_html( $url, $html ) {
		$base_parse = parse_url( $url );
		$base       = $base_parse['scheme'] . "://" . $base_parse['host'] . "/";

		return str_replace( '<head>', '<head><base href="' . $base . '"/>', $html );	}
}
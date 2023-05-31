<?php

namespace WCPaytriotRedirect;


class Logger {

	public static function error( $message, $data = null ) {
		wc_get_logger()->critical(
			sprintf( 'Error: %s <br/> %s',
				$message,
				self::dumper( $data )
			),
			[
				'source' => PaytriotRedirect::GATEWAY_ID . '-errors',
			]
		);
	}

	public static function info( $message, $data = null ) {
		wc_get_logger()->info(
			sprintf( 'Debug: %s <br/> %s',
				$message,
				self::dumper( $data )
			),
			[
				'source' => PaytriotRedirect::GATEWAY_ID . '-info',
			]
		);
	}

	public static function dumper( $data ) {
		ob_start();
		print_r( $data );
		$v = ob_get_contents();
		ob_end_clean();

		return $v;
	}
}
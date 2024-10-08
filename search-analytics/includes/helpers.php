<?php
defined("ABSPATH") || exit;

if ( ! function_exists( 'mwt_array_val' ) ) {
	function mwt_array_val( $arr, $key ) {
		return ( is_array( $arr ) && isset( $arr[ $key ] ) ) ? $arr[ $key ] : false;
	}
}

if ( ! function_exists( 'mwt_get_current_user_roles' ) ) {
	function mwt_get_current_user_roles() {
		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			return ( array ) $user->roles;
		}

		return array();
	}
}

if ( ! function_exists( 'mwt_get_user_roles' ) ) {
	function mwt_get_user_roles( $user ) {
		if ( ! empty( $user ) ) {
			return ( array ) $user->roles;
		}

		return array();
	}
}

if ( ! function_exists( 'mwt_wp_date_format_to_js_datepicker_format' ) ) {
	/**
	 * @deprecated  since 1.4.4 to be removed in 2.0.0
	 */
	function mwt_wp_date_format_to_js_datepicker_format( $dateFormat ) {

		$chars = array(
			// Day
			'd' => 'dd',
			'j' => 'd',
			'l' => 'DD',
			'D' => 'D',
			// Month
			'm' => 'mm',
			'n' => 'm',
			'F' => 'MM',
			'M' => 'M',
			// Year
			'Y' => 'yy',
			'y' => 'y',
		);

		return strtr( (string) $dateFormat, $chars );
	}
}

if ( ! function_exists( 'mwt_create_date_range' ) ) {
	function mwt_create_date_range( $startDate, $endDate, $format = "Y-m-d", $include_today = true ) {
		$begin = new DateTime( $startDate );
		$end   = new DateTime( $endDate );

		if ( $include_today ) {
			$end->add( new DateInterval( 'P1D' ) );
		}

		$range = array();

		try {
			$interval  = new DateInterval( 'P1D' ); // 1 Day
			$dateRange = new DatePeriod( $begin, $interval, $end );
			foreach ( $dateRange as $date ) {
				$range[] = $date->format( $format );
			}
		} catch ( Exception $e ) {

		}

		return $range;
	}
}

if ( ! function_exists( 'mwt_get_current_user_ip' ) ) {
	function mwt_get_current_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		} else {
			$ip = '0.0.0.0';
		}

		return $ip;
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() {
		$timezone_string = get_option( 'timezone_string' );

		if ( $timezone_string ) {
			return $timezone_string;
		}

		$offset  = (float) get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = ( $offset - $hours );

		$sign      = ( $offset < 0 ) ? '-' : '+';
		$abs_hour  = abs( $hours );
		$abs_minutes  = abs( $minutes * 60 );

		return sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_minutes );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() {
		return new DateTimeZone( wp_timezone_string() );
	}
}
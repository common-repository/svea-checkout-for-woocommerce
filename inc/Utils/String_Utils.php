<?php

namespace Svea_Checkout_For_Woocommerce\Utils;

/**
 * Class String_Utils
 */
class String_Utils {

	/**
	 * Check if a string starts with another string
	 *
	 * @param string $string
	 * @param string $start_string
	 *
	 * @return boolean
	 */
	public static function starts_with( $string, $start_string ) {
		$len = self::length( $start_string );

		return self::substr( $string, 0, $len ) === $start_string;
	}

	/**
	 * Check if a string ends with another string
	 *
	 * @param string $string
	 * @param string $end_string
	 *
	 * @return boolean
	 */
	public static function ends_with( $string, $end_string ) {
		$len = self::length( $end_string );

		if ( $len === 0 ) {
			return true;
		}

		return self::substr( $string, -$len ) === $end_string;
	}

	/**
	 * Substring a string with multibyte support
	 *
	 * @param string $string
	 * @param int $start
	 * @param int|null $length
	 *
	 * @return string
	 */
	public static function substr( $string, $start, $length = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			if ( $length !== null ) {
				return mb_substr( $string, $start, $length );
			} else {
				return mb_substr( $string, $start );
			}
		} else {
			if ( $length !== null ) {
				return substr( $string, $start, $length );
			} else {
				return substr( $string, $start );
			}
		}
	}

	/**
	 * Check length of string with multibyte support
	 *
	 * @param string $string
	 *
	 * @return int
	 */
	public static function length( $string ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $string );
		} else {
			return strlen( $string );
		}
	}

	/**
	 * Create tel-link from phone number
	 *
	 * @param string $phone_number
	 *
	 * @return string
	 */
	public static function make_phone_link( $phone_number ) {
		$telephone_link = preg_replace( '/[^\d]/', '', $phone_number );

		return 'tel:' . $telephone_link;
	}

	/**
	 * Separates string in two pieces and returns first part as first name and last part as lastname
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	public static function separate_full_name( $name ) {
		return [
			'first_name' => strpos( $name, ' ' ) ? explode( ' ', $name )[0] : $name,
			'last_name'  => strpos( $name, ' ' ) ? explode( ' ', $name )[1] : $name,
		];
	}

}
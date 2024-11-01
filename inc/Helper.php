<?php

namespace Svea_Checkout_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Helper
 */
class Helper {

	/**
	 * Convert codes into messages
	 *
	 * @param \Exception $e
	 * @return string
	 */
	public static function get_svea_error_message( \Exception $e ) {
		$code = $e->getCode();

		switch ( $code ) {
			case 400:
				return esc_html__( 'The current currency is not supported in the selected country. Please switch country or currency and reload the page.', 'svea-checkout-for-woocommerce' );
			case 401:
				return esc_html__( 'The checkout cannot be displayed due to an error in the connection to Svea. Please contact the shop owner regarding this issue.', 'svea-checkout-for-woocommerce' );
			case 403:
				return esc_html__( 'Order could not be fetched. Please contact the shop owner regarding this issue', 'svea-checkout-for-woocommerce' );
			case 1000:
				return esc_html__( 'Could not connect to Svea - 404', 'svea-checkout-for-woocommerce' );
			default:
				return esc_html( $e->getMessage() );
		}
	}

	/**
	 * Get the locale needed for Svea
	 *
	 * @param string $locale
	 * @param string $country
	 * @return string
	 */
	public static function get_svea_locale( $locale, $country = '' ) {
		$cross_language_countries = ['se'];
		$fallback_country = '';

		switch ( $locale ) {
			case 'sv_SE':
				$svea_locale = 'sv-SE';
				break;
			case 'nn_NO':
			case 'nb_NO':
				$svea_locale = 'nn-NO';
				break;
			case 'fi_FI':
				$svea_locale = 'fi-FI';
				break;
			case 'da_DK':
				$svea_locale = 'da-DK';
				break;
			case 'en_GB':
			case 'en_US':
				$svea_locale = 'en-';
				$fallback_country = 'US';
				break;
			default:
				$svea_locale = 'sv-SE';
				break;
		}

		if ( ! empty( $fallback_country ) ) {
			if ( in_array( strtolower( $country ), $cross_language_countries, true ) ) {
				$svea_locale .= strtoupper( $country );
			} else {
				$svea_locale .= strtoupper( $fallback_country );
			}
		}

		return $svea_locale;
	}

	/**
	 * Splits full names into first name and last name
	 *
	 * @param $full_name
	 * @return string[]
	 */
	public static function split_customer_name( $full_name ) {
		$customer_name = [
			'first_name' => '',
			'last_name'  => '',
		];

		// Split name and trim whitespace
		$full_name_split = array_map( 'trim', explode( ' ', trim( $full_name ) ) );

		$full_name_split_count = count( $full_name_split );

		if ( $full_name_split_count > 0 ) {
			$customer_name['first_name'] = $full_name_split[0];

			if ( $full_name_split_count > 1 ) {
				$customer_name['last_name'] = implode( ' ', array_slice( $full_name_split, 1, $full_name_split_count - 1 ) );
			}
		}

		return $customer_name;
	}

	/**
	 * Get the array value of a string position.
	 * Example: BillingAddress:FirstName would return $array['BillingAddress']['FirstName']
	 *
	 * @param array  $array
	 * @param string $address
	 * @param string $delimiter
	 * @return mixed
	 */
	public static function delimit_address( $array, $address, $delimiter = ':' ) {
		$parts = explode( ',', $address );

		foreach ( $parts as $part ) {
			$address = explode( $delimiter, $part );
			$steps   = count( $address );

			$val = $array;
			for ( $i = 0; $i < $steps; $i++ ) {
				// Every iteration brings us closer to the truth
				if ( isset( $address[ $i ] ) && isset( $val[ $address[ $i ] ] ) ) {
					$val = $val[ $address[ $i ] ];
				} else {
					$val = '';
				}
			}

			if ( ! empty( $val ) ) {
				break;
			}
		}

		return $val;
	}


	/**
	 * Get the first key of the array
	 *
	 * @param array $arr
	 * @return string
	 */
	public static function array_key_first( $arr ) {
		reset( $arr );
		$first_key = key( $arr );

		return ! empty( $arr ) ? $first_key : '';
	}

	/**
	 * Locate a template and return the path
	 *
	 * @param string $path
	 * @return string
	 */
	public static function sco_locate_template( $path ) {
		$template = locate_template( 'woocommerce/' . $path );

		if ( empty( $template ) ) {
			$template = SVEA_CHECKOUT_FOR_WOOCOMMERCE_DIR . '/templates/' . $path;
		}

		return $template;
	}

}

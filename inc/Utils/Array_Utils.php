<?php

namespace Svea_Checkout_For_Woocommerce\Utils;

/**
 * Class Array_Utils
 */
class Array_Utils {

	/**
	 * Sort an array by the provided field
	 *
	 * @param string|callable $key
	 * @param string $order
	 *
	 * @return array
	 */
	public static function sort_by( $arr, $key, $order = 'ASC' ) {
		$sort_data = [];
		$sorted_arr = [];

		$arr_length = count( $arr );

		for ( $i = 0; $i < $arr_length; ++$i ) {
			// Call the provided function if it is a function
			if ( is_callable( $key ) ) {
				$sort_data[ $i ] = call_user_func( $key, $arr[ $i ] );
			} else {
				$sort_data[ $i ] = $arr[ $i ][ $key ];
			}
		}

		// Make order case insensitive
		$order = strtoupper( $order );

		if ( $order === 'ASC' ) {
			// Sort ASC and keep indexes
			asort( $sort_data );
		} else if ( $order === 'DESC' ) {
			// Sort DESC and keep indexes
			arsort( $sort_data );
		}

		// Fill the sorted array
		foreach ( $sort_data as $data_key => $data ) {
			$sorted_arr[ $data_key ] = $arr[ $data_key ];
		}

		// Reset indexes by just returning the array values
		return array_values( $sorted_arr );
	}

}
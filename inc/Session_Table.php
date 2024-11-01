<?php

namespace Svea_Checkout_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles actions related to the custom database tables
 */
class Session_Table {

	/**
	 * Databse version
	 */
	private const DB_VERSION = '2.0.0';

	/**
	 * Database version option key
	 */
	private const DB_VERSION_KEY = 'sco_db_version';

	/**
	 * Table name
	 *
	 * @var string
	 */
	private static $table = 'sco_sessions';

	/**
	 * Cron name
	 *
	 * @var string
	 */
	private const CRON_NAME = 'sco_delete_expired_sessions';

	/**
	 * Does the database exist?
	 *
	 * @var bool
	 */
	private static $db_exists = false;

	/**
	 * Init function
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'current_screen', [ $this, 'maybe_update_database_version' ] );
		add_action( self::CRON_NAME, [ $this, 'delete_expired_sessions' ] );
		add_action( 'init', [ $this, 'check_for_database_presence' ] );
	}

	/**
	 * Check if database exists
	 *
	 * @return void
	 */
	public function check_for_database_presence() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			self::$db_exists = true;
		}
	}

	/**
	 * Delete sessions that've expired
	 *
	 * @return void
	 */
	public function delete_expired_sessions() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table;
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE expires < %d", time() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Add to table
	 *
	 * @param string $sco_session_token
	 * @param string $wc_session_key
	 * @return void
	 */
	public static function add( $sco_session_token, $wc_session_key ) {
		if ( self::$db_exists ) {
			global $wpdb;

			$table_name = $wpdb->prefix . self::$table;

			$result = $wpdb->insert(
				$table_name,
				[
					'sco_session_token' => $sco_session_token,
					'wc_session_key'    => $wc_session_key,
					'expires'           => time() + ( DAY_IN_SECONDS * 3 ),
				]
			);
		}

		// Fallback on session
		if ( ! self::$db_exists || ! $result ) {
			WC()->session->set( 'sco_original_session_key', $sco_session_token );
		}
	}

	/**
	 * Get the token by the session key
	 *
	 * @param string $wc_session_key
	 * @return string
	 */
	public static function get_token_by_session_key( $wc_session_key ) {
		$result = '';
		global $wpdb;

		if ( self::$db_exists ) {
			$table_name = $wpdb->prefix . self::$table;

			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT sco_session_token FROM $table_name WHERE wc_session_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wc_session_key
				)
			);
		}

		return $result;
	}

	/**
	 * Get WC session via the SCO token
	 *
	 * @param string $sco_session_token
	 * @return string
	 */
	public static function get_session_key_by_sco_token( $sco_session_token ) {
		$result = '';
		global $wpdb;

		if ( self::$db_exists ) {

			$table_name = $wpdb->prefix . self::$table;

			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT wc_session_key FROM $table_name WHERE sco_session_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$sco_session_token
				)
			);
		}

		// Fallback on session which is slower
		if ( ! self::$db_exists || ! $result ) {
			$table = $wpdb->prefix . 'woocommerce_sessions';
			$val = '%"sco_original_session_key";' . serialize( $sco_session_token ) . '%'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT session_key FROM $table WHERE session_value LIKE %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$val
				)
			);
		}

		return $result;
	}

	/**
	 * Update wc_session key
	 *
	 * @param string $sco_session_token
	 * @param string $wc_session_key
	 * @return void
	 */
	public static function update_wc_session_key( $sco_session_token, $wc_session_key ) {
		if ( self::$db_exists ) {
			global $wpdb;

			$table_name = $wpdb->prefix . self::$table;

			$result = $wpdb->update(
				$table_name,
				[
					'wc_session_key' => $wc_session_key,
					'expires'        => time() + ( DAY_IN_SECONDS * 3 ), // Always refresh
				],
				[ 'sco_session_token' => $sco_session_token ]
			);
		}

		// Fallback on session
		if ( ! self::$db_exists || ! $result ) {
			WC()->session->set( 'sco_original_session_token', $sco_session_token );
		}
	}

	/**
	 * Delete row based on SCO session key
	 *
	 * @param string $sco_session_token
	 * @return void
	 */
	public static function delete_session_by_sco_token( $sco_session_token ) {
		if ( self::$db_exists ) {
			global $wpdb;

			$table_name = $wpdb->prefix . self::$table;
			$wpdb->delete( $table_name, [ 'sco_session_token' => $sco_session_token ] );
		}
	}

	/**
	 * Check for update in database version
	 *
	 * @return void
	 */
	public function maybe_update_database_version() {
		$sites = is_multisite() ? get_sites( [ 'fields' => 'ids' ] ) : [ get_current_blog_id() ];

		// Setup for all sites if multisite
		foreach ( $sites as $site_id ) {
			if ( is_multisite() ) {
				switch_to_blog( $site_id );
			}

			$version = get_option( self::DB_VERSION_KEY, '0.0.0' );

			if ( version_compare( $version, '1.0.0', '<' ) ) {
				$this->update_database_1();
				$version = '1.0.0';
				update_option( self::DB_VERSION_KEY, $version );
			}

			// TODO: Delete session table

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Update to version 1.0.0
	 *
	 * @return void
	 */
	public function update_database_1() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::$table;
		$charset_collate = $wpdb->get_charset_collate();

		// Setup new table if not already exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			$sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                sco_session_token varchar(255) NOT NULL,
                wc_session_key varchar(255) NOT NULL,
                expires varchar(255) NOT NULL,
                PRIMARY KEY  (id),
                INDEX sco_session_token (sco_session_token),
                INDEX wc_session_key (wc_session_key)
            ) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

		// Add cron to delete older entries
		if ( ! wp_next_scheduled( self::CRON_NAME ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_NAME );
		}
	}

}

<?php
/**
 * Bootstrap.
 *
 * @package StaffWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires everything together.
 */
class StaffWarden_Plugin {

	/**
	 * Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( get_option( 'staffwarden_db_version' ) !== STAFFWARDEN_DB_VERSION ) {
			StaffWarden_Log::install();
			StaffWarden_Features::install();
		}
		add_action( 'staffwarden_hourly', array( 'StaffWarden_Features', 'hourly' ) );
		add_action( 'staffwarden_daily', array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		StaffWarden_Features::init();
		if ( is_admin() ) {
			StaffWarden_Admin::init();
		}
	}

	/**
	 * Make sure the jobs are scheduled.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'staffwarden_hourly' ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', 'staffwarden_hourly' );
		}
		if ( ! wp_next_scheduled( 'staffwarden_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'staffwarden_daily' );
		}
	}

	/**
	 * Daily housekeeping.
	 *
	 * @return void
	 */
	public static function daily() {
		StaffWarden_Log::purge();
		StaffWarden_Features::daily();
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		StaffWarden_Log::install();
		if ( class_exists( 'WooCommerce' ) ) {
			StaffWarden_Features::install();
			StaffWarden_Features::activate();
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'staffwarden_hourly' );
		wp_clear_scheduled_hook( 'staffwarden_daily' );
	}
}

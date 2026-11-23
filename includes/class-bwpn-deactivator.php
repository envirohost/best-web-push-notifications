<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://github.com/rwngallego
 * @since      1.0.0
 *
 * @package    BWPN
 * @subpackage BWPN/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    BWPN
 * @subpackage BWPN/includes
 * @author     Rowinson Gallego <rwn.gallego@gmail.com>
 */
class BWPN_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		BWPN_Lib_Utils::clean_messages();
		delete_option( 'bwpn_activated' );
	}

}

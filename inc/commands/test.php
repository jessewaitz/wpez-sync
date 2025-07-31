<?php
/**
 * CLI Test Command
 *
 * This command will run tests and display test information.
 *
 * @package WPEZ\Sync
 * @author  WPEZtools.com
 */

namespace WPEZ\Sync;

// Only proceed if WP-CLI is available
if ( ! defined( 'WP_CLI' ) || ! class_exists( 'WP_CLI' ) ) {
	return;
}

use WP_CLI;
use WP_CLI_Command;

defined( 'ABSPATH' ) || die( 'No direct access!' );

// ╔══════════════════════════════════════════════════════════════════════════════════════════════════════════╗
// ║  ████████ ███████ ███████ ████████      ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║     ██    ██      ██         ██        ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║     ██    █████   ███████    ██        ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║     ██    ██           ██    ██        ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║     ██    ███████ ███████    ██         ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Test' ) ) {
	/**
	 * Class Test Command
	 */
	class Test extends WP_CLI_Command {
		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Test command, used for running tests before adding to the actual commands.
		 *
		 * @param array $args Command option names.
		 * @param array $args_assoc Command flags and attributes.
		 *
		 * @return void
		 */
		public function __invoke( $args, $args_assoc ) {
			// LOAD TOOLS
			$sync_tools = new Tools();

			// undocumented debugging arguments
			$delete  = isset( $args_assoc['delete'] ) ? $args_assoc['delete'] : true; // delete the local files after import. the NO (--no flag is supported)
			$import  = isset( $args_assoc['import'] ) ? $args_assoc['import'] : true; // import the local files into db after download (--no flag is supported)
			$replace = isset( $args_assoc['replace'] ) ? $args_assoc['replace'] : true; // run search-replace the local db after import (--no flag is supported)
			echo 'delete: '; var_export( $delete ); echo "\n"; // phpcs:ignore
			echo 'import: '; var_export( $import ); echo "\n"; // phpcs:ignore
			echo 'replace: '; var_export( $replace ); echo "\n"; // phpcs:ignore
			die;

			//$file = dirname(ABSPATH) . '/wpezsync/tmp/fulldb.sql';
			//$sync_tools->gzip_file($file);
			//$sync_tools->encrypt_file($file.'.gz'); //die;
			//$sync_tools->decrypt_file($file.'.gz');
			//$sync_tools->gunzip_file($file.'.gz');
			//$table    = 'wp_options';
			//$filename = $table . '.sql';
			//$path     = dirname( ABSPATH ) . '/wpezsync/databases/';
			//$filename = $sync_tools->db_dump_file( $table, $filename, $path, true );
			//echo 'db_dump_file: ' . $filename . "\n";
			//sleep( 5 );
			//$filename = $sync_tools->encrypt_file( $filename, $path );
			//echo 'encrypted_file: ' . $filename . "\n";
			//sleep( 5 ); //die;
			//$filename = $sync_tools->decrypt_file( $filename, $path );
			//echo 'decrypted_file: ' . $filename . "\n";
			//sleep( 5 );
			//$filename = $sync_tools->gunzip_file( $filename, $path );
			//echo 'gunzipped: ' . $filename . "\n";
		}
	}
}

WP_CLI::add_command( 'ez test', __NAMESPACE__ . '\\Test' );

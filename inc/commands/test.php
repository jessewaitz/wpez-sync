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

			// LOAD VARS AND ARGS
			$get_remote = $sync_tools->verify_remote( $args_assoc );
			$remote_env = $get_remote['env'] ?? '';
			$remote_tag = $get_remote['tag'] ?? '';
			$remote_url = $get_remote['url'] ?? '';

			$server_put = trim( $remote_url ) . '/wp-json/wpez/v1/put/';

			// GET THE CURRENT AUTH TOKEN FROM THE REMOTE SERVER
			$options  = array( 'return' => true, 'parse' => false, 'launch' => false, 'exit_error' => true, ); //phpcs:ignore
			$auth_token = WP_CLI::runcommand( 'ez auth ' . $remote_tag, $options );
			if ( substr( $auth_token, 0, 7 ) === 'ERROR: ' ) {
				$message = str_replace( 'ERROR: ', '', $auth_token );
				//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
				WP_CLI::error( $message );
			}

			// undocumented debugging arguments
			//!$delete  = isset( $args_assoc['delete'] ) ? $args_assoc['delete'] : true; // delete the local files after import. the NO (--no flag is supported)
			//!$import  = isset( $args_assoc['import'] ) ? $args_assoc['import'] : true; // import the local files into db after download (--no flag is supported)
			//!$replace = isset( $args_assoc['replace'] ) ? $args_assoc['replace'] : true; // run search-replace the local db after import (--no flag is supported)
			//!echo 'delete: '; var_export( $delete ); echo "\n"; // phpcs:ignore
			//!echo 'import: '; var_export( $import ); echo "\n"; // phpcs:ignore
			//!echo 'replace: '; var_export( $replace ); echo "\n"; // phpcs:ignore
			
			$file_path = '/sites/html/dev.wpeztools.com/www/wp-content/uploads/2025/07/';
			$file_name = 'australia-beach.jpg';
			$full_file_path = $file_path . $file_name;
			$file_mime = mime_content_type($full_file_path);
			$ch_url    = $server_put . '?auth_token=' . urlencode( $auth_token ) . '&type=files';
			
			$sync_tools->logFile( 'file_path: ' . print_r( $file_path, true ), 'wpez-debug.log' );
			$sync_tools->logFile( 'file_name: ' . print_r( $file_name, true ), 'wpez-debug.log' );
			$sync_tools->logFile( 'file_mime: ' . print_r( $file_mime, true ), 'wpez-debug.log' );
			$sync_tools->logFile( 'ch_url: ' . print_r( $ch_url, true ), 'wpez-debug.log' );

			$ch = $sync_tools->setup_curl_put_data( $ch_url, $file_name, $full_file_path, $file_mime );
			if ( $ch ) {
				$response = curl_exec($ch);
				if (curl_errno($ch)) {
					$sync_tools->logFile( 'Upload Curl error: ' . curl_error($ch), 'wpez-debug.log' );
				}
				curl_close($ch);

				$sync_tools->logFile( "Upload Curl Response:" . print_r($response, true), 'wpez-debug.log' );
			} else {
				$sync_tools->logFile( "Could not start CURL upload for {$file_name}", 'wpez-debug.log' );
			}

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

<?php
/**
 * CLI Fetch Command
 *
 * This command will run Fetch function.
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

// ╔═════════════════════════════════════════════════════════════════════════════════════════════════════════════════╗
// ║  ███████ ███████ ████████  ██████ ██   ██      ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║  ██      ██         ██    ██      ██   ██     ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║  █████   █████      ██    ██      ███████     ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║  ██      ██         ██    ██      ██   ██     ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║  ██      ███████    ██     ██████ ██   ██      ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚═════════════════════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Fetch' ) ) {
	/**
	 * Class Fetch Command
	 */
	class Fetch extends WP_CLI_Command {

		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Fetch Command, is used for getting information from the remote related to the get command,
		 * and for getting and setting the remote's activity status.
		 *
		 * ## OPTIONS
		 *
		 * <sub-command>
		 * What to fetch from remote server? either -- [tables|stored|file|status|env]
		 *
		 * --all-tables
		 * if set it will return all tables from a tables request. default is just the prefixed tables for this site.
		 *
		 * --name=<filename>
		 * file to check remote server for.
		 *
		 * --dir=<foldername>
		 * folder to look in for the file on the remote server.
		 *
		 * --live --wp
		 * Flags for what server to pull from.  Either live or wp are defaults, but you can add additional remotes in the WPEZ Sync settings.
		 *
		 * --get --set --clear --data --files
		 * Flags for what what operation to complete based on the subcommand
		 *
		 * ## EXAMPLES
		 *
		 * TABLE EXAMPLE (only WP tables):
		 * wp ez fetch tables --live
		 *
		 * TABLE EXAMPLE (only all tables):
		 * wp ez fetch tables --all-tables --live
		 *
		 * GET STORED DB'S EXAMPLE:
		 * wp ez fetch stored --live
		 *
		 * GET JSON FILE NAME EXAMPLE:
		 * wp ez fetch file --live --name=wp_file_array.json --dir=files
		 *
		 * GET DB FILE NAME EXAMPLE:
		 * wp ez fetch file --live --name=ls_users.sql.tar.gz --dir=databases
		 *
		 * FETCH REMOTE ENV EXAMPLE:
		 * wp ez fetch env --live
		 *
		 * CHECK TO SEE IF REMOTE IS BUSY OR NOT EXAMPLE:
		 * wp ez fetch status --get --data --live
		 *
		 * SET STATUS OF REMOTE AS BUSY FOR DATABASES EXAMPLE:
		 * wp ez fetch status --set --data --live
		 *
		 * SET DATA STATUS OF REMOTE AS READY EXAMPLE:
		 * wp ez fetch status --clear --data --live
		 *
		 * @subcommand fetch
		 *
		 * @param array $args Command option names.
		 * @param array $args_assoc Command flags and attributes.
		 *
		 * @return void
		 * @throws WP_CLI\ExitException WP error returned.
		 */
		public function __invoke( $args, $args_assoc ) {
			// LOAD TOOLS
			$sync_tools = new Tools();

			// LOAD GLOBAL VARIABLE TO USED LATER
			$options  = array( 'return' => true, 'parse' => false, 'launch' => false, 'exit_error' => true, ); //phpcs:ignore
			$options2 = array( 'return' => 'all', 'parse' => 'json', 'launch' => true, 'exit_error' => false, ); //phpcs:ignore

			// LOAD VARS AND ARGS
			$type       = $args[0] ?? false;
			$all_tables = $args_assoc['all-tables'] ?? false;
			$name       = $args_assoc['name'] ?? false;
			$dir        = $args_assoc['dir'] ?? false;
			$get        = $args_assoc['get'] ?? false;
			$set        = $args_assoc['set'] ?? false;
			$clear      = $args_assoc['clear'] ?? false;
			$data       = $args_assoc['data'] ?? false;
			$files      = $args_assoc['files'] ?? false;

			// LOAD VARS AND ARGS
			$get_remote = $sync_tools->verify_remote( $args_assoc );
			$remote_uri = '/wp-json/wpez/v1/fetch/';
			$remote_env = $get_remote['env'] ?? '';
			$remote_tag = $get_remote['tag'] ?? '';
			$remote_url = $get_remote['url'] ?? '';
			$server     = trim( $remote_url ) . $remote_uri;

			// GET THE CURRENT AUTH TOKEN FROM THE REMOTE SERVER
			$auth_token = WP_CLI::runcommand( 'ez auth ' . $remote_tag, $options );
			if ( substr( $auth_token, 0, 7 ) === 'ERROR: ' ) {
				$message = str_replace( 'ERROR: ', '', $auth_token );
				//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
				WP_CLI::error( $message );
			}

			// SETUP CURL DATA OBJECT
			$ch_data    = array();
			$ch_payload = array();
			if ( $all_tables ) {
				$ch_payload['all_tables'] = $all_tables;
			}
			if ( $name ) {
				$ch_payload['name'] = $name;
			}
			if ( $dir ) {
				$ch_payload['dir'] = $dir;
			}
			if ( $get ) {
				$ch_payload['action'] = 'get';
			}
			if ( $set ) {
				$ch_payload['action'] = 'set';
			}
			if ( $clear ) {
				$ch_payload['action'] = 'clear';
			}
			if ( $data ) {
				$ch_payload['target'] = 'data';
			}
			if ( $files ) {
				$ch_payload['target'] = 'files';
			}
			$ch_data['data']       = json_encode( $ch_payload );
			$ch_data['type']       = $type;
			$ch_data['auth_token'] = $auth_token;

			// SETUP CURL COMMAND TO POST TO REMOTE SERVER.
			$ch = $sync_tools->setup_curl_post( $ch_data, $server );
			if ( $ch ) {
				$response = curl_exec( $ch );
				$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
				$decoded  = json_decode( $response, true );
				curl_close( $ch );
			} else {
				WP_CLI::error( 'CURL Fetch Failed.' );
			}

			//$sync_tools->logFile("fetch: -- Success: response: ".print_r($response, true));
			//$sync_tools->logFile("fetch: -- Success: decoded: ".print_r($decoded, true));

			// CHECK FOR ERRORS
			if ( ! empty( $decoded ) && $status == 400 ) {
				echo $decoded['message']; //phpcs:ignore
			}
			// IF SUCCESS RETURN APPROPRIATE DATA.
			if ( ! empty( $decoded ) && $status == 200 ) {
				echo $decoded['output']; //phpcs:ignore
			}
		}
	}
}

WP_CLI::add_command( 'ez fetch', __NAMESPACE__ . '\\Fetch' );

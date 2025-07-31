<?php
/**
 * CLI Put Command
 *
 * This command will run Put function.
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

// ╔══════════════════════════════════════════════════════════════════════════════════════════════════╗
// ║  ██████  ██    ██ ████████      ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║  ██   ██ ██    ██    ██        ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║  ██████  ██    ██    ██        ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║  ██      ██    ██    ██        ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║  ██       ██████     ██         ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚══════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Put' ) ) {
	/**
	 * Class Put Command
	 */
	class Put extends WP_CLI_Command {

		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Put Command is used for putting data or files to the remote server.
		 *
		 * ## OPTIONS
		 *
		 * <sub-command>
		 * What to upload remotely? either -- [data|files]
		 *
		 * --tables=<tablenames>
		 * create import of only selected tables. save as a comma separated list. ie: "tablename1,tablename2,tablename3"
		 *
		 * --exclude=<tablenames>
		 * create import of all tables except the ones listed. save as a comma separated list. ie: "tablename1,tablename2,tablename3"
		 *
		 * --[no-]encrypt
		 * If set the downloaded files will be encrypted with aes file encryption, before you download them. If you enter --no-encrypt then no encryption will be added. Note: You cannot encrypte and gzip the same file. Default is no encryption.
		 *
		 * --[no-]gzip
		 * If set the downloaded files will be gzipped, before you download them. If you enter --no-gzip then no zipping will happen. Note: You cannot encrypte and gzip the same file. Default is no zipping.
		 *
		 * --stored
		 * Import the previously stored versions of the database on the remote server
		 *
		 * --live --dev
		 * Flags for what server to pull from.  Either live or wp are defaults, but you can add additional remotes in the WPEZ Sync settings.
		 *
		 * --verbose
		 * Show table by table database import to help debugging and see specific progress
		 *
		 * --run=<path/to/file.sh>
		 * optionally run a bash script (locally only) after the command is run.  Can select a path to a file or use the default script in the wpez sync folder.
		 *
		 * ## EXAMPLES
		 *
		 * DATA EXAMPLE
		 * wp ez put data --live
		 *
		 * FILE EXAMPLE:
		 * wp ez put files --live
		 *
		 * @subcommand put
		 *
		 * @param array $args Command option names.
		 * @param array $args_assoc Command flags and attributes.
		 *
		 * @return void
		 * @throws WP_CLI\ExitException WP error returned.
		 */
		public function __invoke( $args, $args_assoc ) {
			// LOAD TOOLS AND SETTINGS CLASSES
			$sync_tools    = new Tools();

			// LOAD GLOBAL VARIABLE TO USED LATER
			$options  = array( 'return' => true, 'parse' => false, 'launch' => false, 'exit_error' => true, ); //phpcs:ignore
			$options2 = array( 'return' => 'all', 'parse' => 'json', 'launch' => true, 'exit_error' => false, ); //phpcs:ignore
			$user_loc = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
			$local_url = site_url();

			// SETUP ARGS, ASSOCIATED ARGS, AND OPTIONS
			$type       = $args[0] ?? false;
			$stored     = $args_assoc['stored'] ?? false;
			$tables     = $args_assoc['tables'] ?? false;
			$exclude    = $args_assoc['exclude'] ?? false;
			$encrypt    = isset( $args_assoc['encrypt'] ) ? $args_assoc['encrypt'] : false; // Encrypt the files before download. Always false unless the '--[no]' flag is set.
			$gzip       = isset( $args_assoc['gzip'] ) ? $args_assoc['gzip'] : false; // GZip the files before download. Always false unless the '--[no]' flag is set.

			// SETUP THE TABLES ARRAY, BASED ON FLAGS.
			$tables_tmp = ( $tables && is_string( $tables ) ) ? explode( ',', $tables ) : array();
			$tables_exc = ( $exclude && is_string( $exclude ) ) ? explode( ',', $exclude ) : array();

			// RUN SCRIPTS AFTER THE SYNC IS COMPLETE
			$run        = $args_assoc['run'] ?? false;
			$run_script = ( $exclude && is_string( $run ) ) ? $run : '';

			// UNDOCUMENTED DEBUGGING ARGUMENTS
			$step    = $args_assoc['step'] ?? false; // Enter step mode and step through the functions one by one.
			$verbose = $args_assoc['verbose'] ?? false; // show more information
			$delete  = isset( $args_assoc['delete'] ) ? $args_assoc['delete'] : true; // delete the local files after import. Always true unless the '--[no]' flag is set.
			$import  = isset( $args_assoc['import'] ) ? $args_assoc['import'] : true; // import the local files into db after download. Always true unless the '--[no]' flag is set.
			$replace = isset( $args_assoc['replace'] ) ? $args_assoc['replace'] : true; // run search-replace the local db after import. Always true unless the '--[no]' flag is set.

			// LOAD VARS AND ARGS
			$put_remote = $sync_tools->verify_remote( $args_assoc );
			$remote_env = $put_remote['env'] ?? '';
			$remote_tag = $put_remote['tag'] ?? '';
			$remote_url = $put_remote['url'] ?? '';

			// SET THE GET AND DL URLS
			$server_dl  = trim( $remote_url ) . '/wp-json/wpez/v1/download/';
			$server_put = trim( $remote_url ) . '/wp-json/wpez/v1/put/';

			// DECLARE EMPTY ARRAYS FOR VARIABLE USED LATER
			$ch_payload = array();
			$ch_data    = array();
			$errors     = array();
			$success    = array();
			$filenames  = array();
			$tables_arr = array();
			$notify_all = array();
			$notify_err = array();

			// CHECK TO SEE IF THE FILE IS GZIPPED AND ENCRYPTED
			if ( $encrypt && $gzip ) {
				WP_CLI::error( 'You cannot gzip and encrypt files in the same download. G-zipping encrypted files is unstable, and causes unpredictable results.  You must choose one or the other but not both at the same time.' );
				die;
			}

			// GET THE REMOTE SERVER TO USE FOR SYNCING
			if ( WPEZ_SYSTEM_USR === 'root' ) {
				$server_ask = $sync_tools->ask( "\nYou are currently logged in as the root user. This program cannot be run as root user.\nPlease quit, and change to the user that owns the websites files (usually www-user): \nPress any key quit program:\n" );
				WP_CLI::error( 'Command Stopped. Please login as a different user.' );
				die;
			}

			// CHECK TO SEE IF THE LOCAL SERVER IS ACTUALLY THE CURRENTLY ACTIVE WWW SERVER.
			$wpez_env = WP_CLI::runcommand( 'ez fetch env ' . $remote_tag, $options );
			if ( substr( $wpez_env, 0, 7 ) === 'ERROR: ' ) {
				$message = str_replace( 'ERROR: ', '', "Response from the remote server: {$wpez_env}" );
				//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
				WP_CLI::error( $message );
			}

			// CHECK IF THE REQUEST IS THE SAME SYSTEM.
			if ( $wpez_env === WPEZ_SYSTEM_URL ) {
				$message = "The remote and local ENV's (" . WPEZ_SYSTEM_URL . ') are the same. This action is not allowed.';
				//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
				WP_CLI::error( $message );
			}

			// SETUP THE USER AND LOCATION LABEL
			$timestamp_dir = $sync_tools->get_sync_dir( 'timestamps' );
			$timefile      = $timestamp_dir . $user_loc . '.txt';
			$timestamp     = (int) ( file_exists( $timefile ) ) ? file_get_contents( $timefile ) : 0;

			// GET THE CURRENT AUTH TOKEN FROM THE REMOTE SERVER
			$auth_token = WP_CLI::runcommand( 'ez auth ' . $remote_tag, $options );
			if ( substr( $auth_token, 0, 7 ) === 'ERROR: ' ) {
				$message = str_replace( 'ERROR: ', '', $auth_token );
				//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
				WP_CLI::error( $message );
			}

			// ╔══════════════════════════════════════════════╗
			// ║  GET DATABASE DUMP FILES FROM REMOTE SERVER  ║
			// ╚══════════════════════════════════════════════╝

			if ( $type === 'data' ) {

				// +-------------------------------------------------+
				// | Create database dump files on the local server |
				// +-------------------------------------------------+

				// STEP MODE -- STEP THROUGH FUNCTIONS ONE AT A TIME.
				if ( $step && $sync_tools->ask( "\nReady to Dump DB's, Continue?" ) === 'n' ) {
					WP_CLI::error( 'Stopped at Dump Databases' );
					die;
				}

				

				// +-----------------------------------------------------+
				// | Upload database dump files from the remote server |
				// +-----------------------------------------------------+

				// STEP MODE -- STEP THROUGH FUNCTIONS ONE AT A TIME.
				if ( $step && $sync_tools->ask( "\nReady to Upload DB's, Continue?" ) === 'n' ) {
					WP_CLI::error( 'Stopped at Upload Databases' );
					die;
				}



			}

			// ╔════════════════════════════════╗
			// ║  GET FILES FROM REMOTE SERVER  ║
			// ╚════════════════════════════════╝
			if ( $type === 'files' ) {

			}

			// FLUSH THE LOCAL CACHE
			WP_CLI::runcommand( 'cache flush' );
			// FLUSH THE REDIS CACHE
			$output = array();
			$output = $sync_tools->clear_cache( $user_loc );
			if ( $output['status'] === 'Failed' ) {
				WP_CLI::error( $output['message'] );
			}
			if ( $output['status'] === 'success' ) {
				WP_CLI::success( $output['message'] );
			}
		}
	}
}

WP_CLI::add_command( 'ez put', __NAMESPACE__ . '\\Put' );

<?php
/**
 * CLI Get Command
 *
 * This command will run Get function.
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
// ║   ██████  ███████ ████████      ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║  ██       ██         ██        ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║  ██   ███ █████      ██        ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║  ██    ██ ██         ██        ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║   ██████  ███████    ██         ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚══════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Get' ) ) {
	/**
	 * Class Get Command
	 */
	class Get extends WP_CLI_Command {

		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Get Command is used for getting data or files from the remote server.
		 *
		 * ## OPTIONS
		 *
		 * <sub-command>
		 * What to backup locally? either -- [data|files]
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
		 * wp ez get data --live
		 *
		 * FILE EXAMPLE:
		 * wp ez get files --live
		 *
		 * @subcommand get
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
			$get_remote = $sync_tools->verify_remote( $args_assoc );
			$remote_env = $get_remote['env'] ?? '';
			$remote_tag = $get_remote['tag'] ?? '';
			$remote_url = $get_remote['url'] ?? '';

			// SET THE GET AND DL URLS
			$server_dl  = trim( $remote_url ) . '/wp-json/wpez/v1/download/';
			$server_get = trim( $remote_url ) . '/wp-json/wpez/v1/get/';

			// DECLARE EMPTY ARRAYS FOR VARIABLE USED LATER
			$ch_payload = array();
			$ch_data    = array();
			$errors     = array();
			$success    = array();
			$filenames  = array();
			$tables_arr = array();
			$notify_all = array();
			$notify_err = array();

			// CHECK TO SEE IF THE FILE IS GZIPPED AND ENCRYPTED AND THE STORED FLAG IS SET.
			//!if ( $stored && ( $encrypt || $gzip ) ) {
			//!	WP_CLI::error( 'You cannot designate gzip or encrypt files if you are downloading the stored files. G-zipping && encrypting files is only available for fresh requests.  You must choose either stored files, or encrypted/gzipped files, not both.' );
			//!	die;
			//!}

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
				// SAVE THE SYNC SETTINGS SO THAT THEY CAN BE RESTORED LATER.
				$wpez_get_sync_settings = get_option( 'wpez_sync_settings' ) ?? array();
				//$sync_tools->logFile( "wpez_get_sync_settings:". print_r($wpez_get_sync_settings, true), 'wpez-debug.log' ); die;

				// GET THE BUSY STATUS FROM THE REMOTE SERVER
				$ts_status = WP_CLI::runcommand( 'ez fetch status --get --data ' . $remote_tag, $options );
				if ( substr( $ts_status, 0, 7 ) === 'ERROR: ' ) {
					$ts_response                       = str_replace( 'ERROR: ', '', $ts_status );
					list($ts_time, $ts_user, $ts_type) = explode( '|', $ts_response );
					$ts_message                        = "{$remote_env} server busy -- {$ts_user} is backing up {$ts_type} -- started: " . wp_date( 'm/d/Y H:i:s', $ts_time ) . ' UTC';
					//$sync_tools->notify($sync_tools->format_message( $ts_message, 'error' ), 'error');
					WP_CLI::error( $ts_message );
				}
				// SET STATUS EQUAL TO BUSY ON THE REMOTE SERVER
				$ts_set = WP_CLI::runcommand( 'ez fetch status --set --data ' . $remote_tag, $options );
				// START RECORDING THE TIMER FOR THE "GET DATA" COMMAND.
				$startTime    = microtime( true );
				$notify_all[] = $sync_tools->format_message( 'Database sync started at: ' . wp_date( 'm/d/Y -- h:i:s A' ) . ' -- ' . $user_loc . ' is getting data from ' . $remote_env, 'attention' );

				// +-------------------------------------------------+
				// | Create database dump files on the remote server |
				// +-------------------------------------------------+

				// STEP MODE -- STEP THROUGH FUNCTIONS ONE AT A TIME.
				if ( $step && $sync_tools->ask( "\nReady to Dump DB's, Continue?" ) === 'n' ) {
					WP_CLI::error( 'Stopped at Dump Databases' );
					die;
				}

				// SETTING UP COMMAND OVERRIDES FOR SPECIFIC ARGS
				if ( $stored ) {
					$stored_tables = WP_CLI::runcommand( 'ez fetch stored ' . $remote_tag, $options );
					if ( substr( $stored_tables, 0, 7 ) === 'ERROR: ' ) {
						$message = str_replace( 'ERROR: ', '', $stored_tables );
						//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
						WP_CLI::error( $message );
					}
					$filenames = json_decode( $stored_tables, false );
					//$sync_tools->logFile( "filenames:". print_r($filenames, true), 'wpez-debug.log' );
					$tables_arr = array_keys( (array) $filenames );
					//$sync_tools->logFile( "tables_arr:". print_r($tables_arr, true), 'wpez-debug.log' );
				}

				// IF STORED IS NOT SET RUN THE REMOTE DATA BACKUP
				if ( ! $stored ) {
					// IF ALL TABLES SET GET LIST OF TABLES AND SET TABLE ARRAY
					// OTHERWISE REVERT TO SPECIFIED TABLES LIST OR EMPTY ARRAY
					if ( $type === 'data' ) {
						if ( $tables ) {
							$tables_arr = $tables_tmp;
						} elseif ( $exclude ) {
							$all_tables = WP_CLI::runcommand( 'ez fetch tables ' . $remote_tag, $options );
							if ( substr( $all_tables, 0, 7 ) === 'ERROR: ' ) {
								$message = str_replace( 'ERROR: ', '', $all_tables );
								//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
								WP_CLI::error( $message );
							}
							$tables_arr = json_decode( $all_tables );
							$tables_arr = array_diff( $tables_arr, $tables_exc );
						} else { // defaults to all tables if you choose nothing, or select --all
							$all_tables = WP_CLI::runcommand( 'ez fetch tables ' . $remote_tag, $options );
							if ( substr( $all_tables, 0, 7 ) === 'ERROR: ' ) {
								$message = str_replace( 'ERROR: ', '', $all_tables );
								//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
								WP_CLI::error( $message );
							}
							$tables_arr = json_decode( $all_tables );
						}
					}

					$notify_all[] = $sync_tools->format_message( 'Starting backup of database files on the remote server', 'heading' );

					WP_CLI::line( WP_CLI::colorize( "\n" . '%BStarting backup of database files on the remote server%n' ) );
					$sync_tools->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
					$sync_tools->logFile( 'STARTING: data backup' );
					$backup_progress = \WP_CLI\Utils\make_progress_bar( 'Creating files', count( $tables_arr ) );
					$i               = 1;

					$fileTime              = microtime( true );
					$ch_payload['type']    = 'data';
					$ch_payload['tables']  = $tables_arr;
					$ch_payload['encrypt'] = $encrypt;
					$ch_payload['gzip']    = $gzip;
					$ch_data['data']       = json_encode( $ch_payload );
					$ch_data['auth_token'] = $auth_token;

					// GET THE CURL OPTIONS
					$ch = $sync_tools->setup_curl_post( $ch_data, $server_get );
					if ( $ch ) {
						$response = curl_exec( $ch );
						$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
						$error    = curl_error( $ch );
						curl_close( $ch );
						$decoded = json_decode( $response, true );
						if ( ! empty( $decoded ) && $status == 200 ) {
							// LOOP THROUGH THE SELECTED TABLES.
							foreach ( $tables_arr as $table ) {
								// START LOOP WAITING FOR THE FILES TO BE CREATED ON REMOTE
								for ( $x = 1; $x <= 100; $x++ ) {
									// SINCE WE DON'T KNOW IF THE FILE HAS FINISHED BUILDING, SEND FETCH COMMAND TO SEE IF THE DB FILE EXISTS.
									$filename = $table . '.sql';
									$filename = ( $gzip ) ? $filename . '.gz' : $filename;
									$filename = ( $encrypt ) ? $filename . '.aes' : $filename;
									$file     = WP_CLI::runcommand( 'ez fetch file --name=' . $filename . ' --dir=databases ' . $remote_tag, $options );
									if ( substr( $file, 0, 7 ) === 'ERROR: ' ) {
										// IF SERVER RETURNS ERROR, THEN WAIT 30 SECONDS AND THEN LOOP AND TRY AGAIN.
										$sync_tools->logFile( "data backup of {$table} -- Still waiting... Total elapsed time is " . $sync_tools->elapsed_time( time(), (int) $fileTime ) );
										sleep( 10 );
									} else {
										// IF SERVER RETURNS THAT IT FOUND THE FILE, SAVE THE NAME OF THE FILE AND PRINT MESSAGE TO SCREEN
										$fetched_file        = ( $file && is_string( $file ) ) ? explode( ',', $file ) : array();
										$filenames[ $table ] = array(
											'file' => $fetched_file[0],
											'hash' => $fetched_file[1],
										);
										$success[]           = "({$i}) " . $fetched_file[0] . ' -- created successfully in ' . $sync_tools->elapsed_time( time(), (int) $fileTime );
										$sync_tools->logFile( "data backup of {$table} -- Success: " . $fetched_file[0] . ' -- created successfully' );
										$backup_progress->tick();
										++$i;
										break;
									}
								}
							}
							// IF CURL POST SUCCEEDED, SAVE FILE INFO AND PRINT MESSAGE TO SCREEN
							//$filenames[$table]=array('file'=>$decoded['file'], 'hash'=>$decoded['hash']);
							//$success[]= "({$i}) ".$decoded['file'].' -- created successfully in ' . $sync_tools->elapsed_time( time(), (int)$fileTime );
							//$sync_tools->logFile("data backup of {$table} -- status: {$status} -- Success: ".$decoded['message']);
						} else {
							// CHECK FOR ERRORS
							$errors[] = "({$i}) " . $decoded['file'] . ' -- ' . $decoded['message'];
							$sync_tools->logFile( "data backup request Failed -- status: {$status} -- Error: " . $decoded['message'] . " -- Curl Message: {$error}" );
						}
					} else {
						$message = 'CURL post could not be setup.';
						//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
						WP_CLI::error( $message );
					}
					// CREATE AN OBJECT BECAUSE THE DATA IS IN ARRAY FORMAT
					$filenames = json_decode( json_encode( $filenames ), false );
					//$sync_tools->logFile( "filenames:". print_r($filenames, true), 'wpez-debug.log' );
					$backup_progress->finish();

					// DISPLAY SUCCESS MESSAGES TO SCREEN
					if ( ! empty( $success ) ) {
						$message      = 'These tables were created without issue';
						$notify_all[] = $sync_tools->format_message( $message . "\n\n" . implode( "\n\n", $success ), 'success' );
						WP_CLI::line( WP_CLI::colorize( "\n" . '%G' . $message . '%n' . "\n" . implode( "\n", $success ) ) );
						WP_CLI::success( 'END OF LIST' );
					}
					// DISPLAY ERRORS MESSAGES TO SCREEN
					if ( ! empty( $errors ) ) {
						$message      = 'There was a problem creating the following tables';
						$notification = $sync_tools->format_message( $message . "\n\n" . implode( "\n\n", $errors ), 'error' );
						$notify_all[] = $notification;
						$notify_err[] = $notification;
						WP_CLI::line( WP_CLI::colorize( "\n" . '%R' . $message . '%n' . "\n" . implode( "\n", $errors ) ) );
						WP_CLI::error( 'END OF LIST', false );
					}
					$sync_tools->logFile( 'FINISHED: data backup' );
					$sync_tools->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" ); //die;
				}

				// +-----------------------------------------------------+
				// | Download database dump files from the remote server |
				// +-----------------------------------------------------+

				// STEP MODE -- STEP THROUGH FUNCTIONS ONE AT A TIME.
				if ( $step && $sync_tools->ask( "\nReady to Download DB's, Continue?" ) === 'n' ) {
					WP_CLI::error( 'Stopped at Download Databases' );
					die;
				}

				// IF THE LIST OF FILENAMES IS NOT Get START DOWNLOAD OF DUMP FILES
				if ( ! empty( $filenames ) ) {
					$errors       = array();
					$success      = array();
					$notify_all[] = $sync_tools->format_message( 'Starting download of database files from the remote server', 'heading' );
					WP_CLI::line( WP_CLI::colorize( "\n" . '%BStarting download of database files from the remote server%n' ) );
					$download_progress = \WP_CLI\Utils\make_progress_bar( 'Downloading Files.', count( (array) $filenames ) );
					$i                 = 1;
					$sync_tools->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
					$sync_tools->logFile( 'STARTING: data download' );

					// CREATE TMP FOLDER IF DOES NOT EXIST
					$dir = $sync_tools->get_sync_dir( 'tmp' );

					// EMPTY THE TMP DIRECTORY OF ALL OLD DB FILES
					foreach ( $tables_arr as $table ) {
						if ( file_exists( $dir . $table . '.sql' ) ) {
							unlink( $dir . $table . '.sql' );
						}
					}

					// LOOP THROUGH THE SELECTED FILES.
					foreach ( $tables_arr as $table ) {
						$file   = $filenames->{$table}->file;
						$hash   = $filenames->{$table}->hash;
						$ch_url = $server_dl . '?auth_token=' . urlencode( $auth_token ) . '&type=data&file=' . $file;
						if ( ! empty( $file ) ) {
							$fp = fopen( $dir . $file, 'wb' );
							if ( $fp ) {
								// SETUP THE CURL CONNECTION.
								$ch = $sync_tools->setup_curl_get( $ch_url, $fp );
								if ( $ch ) {
									$response = curl_exec( $ch );
									$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
									$error    = curl_error( $ch );
									curl_close( $ch );
									fclose( $fp );
									$decoded = json_decode( $response, true );
									// IF SUCCESS -- CREATE ARRAY OF SUCCESS MESSAGES FOR DISPLAY OUTSIDE OF LOOP
									if ( $status == 200 ) {
										// CHECK MD5, IF FALSE ADD WP_CLI::ERROR
										if ( md5_file( $dir . $file ) === $hash ) {
											$success[] = "({$i}) {$file} -- downloaded successfully";
											$sync_tools->logFile( "data download of {$table} -- status: {$status} -- Success: {$file} downloaded successfully" );
										} else {
											$errors[] = "({$i}) MD5 checksum of {$file} did not match after download";
											$sync_tools->logFile( "data download of {$table} -- status: {$status} -- Error: MD5 checksum of {$file} did not match after download -- Curl Message: {$error}" );
										}
									} else {
										// CHECK FOR ERRORS -- CREATE ARRAY OF ERRORS FOR DISPLAY OUTSIDE OF LOOP
										$errors[] = $decoded['message'];
										$sync_tools->logFile( "data download of {$table} -- status: {$status} -- Error:" . $decoded['message'] . " -- Curl Message: {$error}" );
									}
								} else {
									$message = "CURL file download Failed on {$file}";
									//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
									WP_CLI::error( $message );
								}
							}
						}
						$download_progress->tick();
						++$i;
					}
					$download_progress->finish();

					// DISPLAY SUCCESS MESSAGES TO SCREEN
					if ( ! empty( $success ) ) {
						$notify_all[] = $sync_tools->format_message( 'These tables were downloaded without issue' . "\n\n" . implode( "\n\n", $success ), 'success' );
						WP_CLI::line( WP_CLI::colorize( "\n" . '%GThese tables were downloaded without issue%n' . "\n" . implode( "\n", $success ) ) );
						WP_CLI::success( 'END OF LIST' );
					}
					// DISPLAY ERRORS MESSAGES TO SCREEN
					if ( ! empty( $errors ) ) {
						$notification = $sync_tools->format_message( 'There was a problem downloading the following tables' . "\n\n" . implode( "\n\n", $errors ), 'error' );
						$notify_all[] = $notification;
						$notify_err[] = $notification;
						WP_CLI::line( WP_CLI::colorize( "\n" . '%RThere was a problem downloading the following tables%n' . "\n" . implode( "\n", $errors ) ) );
						WP_CLI::error( 'END OF LIST', false );
					}
					$sync_tools->logFile( 'FINISHED: data download' );
					$sync_tools->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" ); //die;
				}
				// CLEAR OUT THE BUSY STATUS FROM THE REMOTE SERVER
				$ts_clear = WP_CLI::runcommand( 'ez fetch status --clear --data ' . $remote_tag, $options );

				// IF THE LIST OF FILENAMES IS NOT Get START DOWNLOAD OF DUMP FILES
				if ( ! empty( $filenames ) ) {

					// +---------------------------------------------------+
					// | Import database dump files from the remote server |
					// +---------------------------------------------------+

					// IF IMPORT IS ENABLED RUN THE IMPORT SCRIPT.
					if ( $import ) {

						// STEP MODE -- STEP THROUGH FUNCTIONS ONE AT A TIME.
						if ( $step && $sync_tools->ask( "\nReady to Import DB's, Continue?" ) === 'n' ) {
							WP_CLI::error( 'Stopped at Import Databases' );
							die;
						}

						$errors       = array();
						$success      = array();
						$notify_all[] = $sync_tools->format_message( 'Starting import of downloaded database tables', 'heading' );
						WP_CLI::line( WP_CLI::colorize( "\n" . '%BStarting import of downloaded database tables%n' ) );
						$total_files = count( (array) $filenames );
						$i           = 1;
						if ( ! $verbose ) {
							$import_progress = \WP_CLI\Utils\make_progress_bar( 'Importing Files.', $total_files );
						}
						$sync_tools->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
						$sync_tools->logFile( 'STARTING: data import' );

						// CREATE TMP FOLDER IF DOES NOT EXIST.
						$dir = $sync_tools->get_sync_dir( 'tmp' );

						// LOOP THROUGH THE SELECTED FILES.
						foreach ( $tables_arr as $table ) {
							// CHECK AND EXPAND THE TAR FILE.
							$filename = $table . '.sql';
							if ( $verbose ) {
								WP_CLI::line( "Expanding file for table {$table}" );
							}
							if ( $encrypt ) {
								if ( file_exists( $dir . $filename . '.aes' ) ) {
									$filename = $filename . '.aes';
									$filename = $sync_tools->decrypt_file( $filename, $dir );
									if ( ! empty( $filename ) ) {
										$sync_tools->logFile( "data import: Success decrypting archive: {$filename}" );
									}
									if ( empty( $filename ) ) {
										$sync_tools->logFile( "data import: Error decrypting archive: {$filename}" );
										$errors[] = "({$i}) Failed decrypting archive: {$filename}";
									}
								} else {
									$sync_tools->logFile( "data import: File is not an AES archive: {$filename}" );
									$errors[] = "({$i}) Downloaded file: {$filename} is not an AES archive.";
								}
							}
							if ( $gzip ) {
								if ( file_exists( $dir . $filename . '.gz' ) ) {
									$filename = $filename . '.gz';
									$filename = $sync_tools->gunzip_file( $filename, $dir );
									if ( ! empty( $filename ) ) {
										$sync_tools->logFile( "data import: Success expanding archive: {$filename}" );
									}
									if ( empty( $filename ) ) {
										$sync_tools->logFile( "data import: Error expanding archive: {$filename}" );
										$errors[] = "({$i}) Failed expanding archive: {$filename}";
									}
								} else {
									$sync_tools->logFile( "data import: File is not a GZIP archive: {$filename}" );
									$errors[] = "({$i}) Downloaded file: {$filename} is not a GZIP archive.";
								}
							}

							// IMPORT THE SQL FILE AND CHECK FOR SUCCESSFUL
							$startTime = time();
							if ( $verbose ) {
								WP_CLI::line( "Importing SQL file for table {$table}" );
							}
							$command = 'db import ' . $dir . $filename;
							$run     = WP_CLI::runcommand( $command, $options2 );

							// CHECK FOR ERRORS -- CREATE ARRAY OF ERRORS FOR DISPLAY OUTSIDE OF LOOP
							if ( $run->stderr !== '' ) {
								$problem  = "({$i}) {$filename} -- import Failed with response: " . $run->stderr;
								$errors[] = $problem;
								if ( $verbose ) {
									WP_CLI::warning( $problem );
								}
								$sync_tools->logFile( "data import: Running '{$command}' -- " . $run->stderr );
							}
							// IF SUCCESS -- CREATE ARRAY OF SUCCESS MESSAGES FOR DISPLAY OUTSIDE OF LOOP
							if ( $run->stdout !== '' ) {
								$elapsed   = time() - $startTime;
								$good      = "({$i}) {$filename} -- imported successfully taking {$elapsed} seconds";
								$success[] = $good;
								if ( $verbose ) {
									WP_CLI::line( $good );
								}
								$sync_tools->logFile( "data import: Running '{$command}' -- " . $run->stdout );
								// IF COMPLETED SUCCESSFULLY, DELETE FILES FROM THE TMP DIRECTORY.
								if ( $delete ) {
									$sync_tools->logFile( "data import: Deleting {$filename} from tmp directory" );
									unlink( $dir . $filename );
								}
							}
							if ( ! $verbose ) {
								$import_progress->tick();
							}
							++$i;
						}
						if ( ! $verbose ) {
							$import_progress->finish();
						}

						// DISPLAY SUCCESS MESSAGES TO SCREEN
						if ( ! empty( $success ) ) {
							$notify_all[] = $sync_tools->format_message( 'These tables were imported without issue' . "\n\n" . implode( "\n\n", $success ), 'success' );
							WP_CLI::line( WP_CLI::colorize( "\n" . '%GThese tables were imported without issue%n' . "\n" . implode( "\n", $success ) ) );
							WP_CLI::success( 'END OF LIST' );
						}
						// DISPLAY ERRORS MESSAGES TO SCREEN
						if ( ! empty( $errors ) ) {
							$notification = $sync_tools->format_message( 'There was a problem importing the following tables' . "\n\n" . implode( "\n\n", $errors ), 'error' );
							$notify_all[] = $notification;
							$notify_err[] = $notification;
							WP_CLI::line( WP_CLI::colorize( "\n" . '%RThere was a problem importing the following tables%n' . "\n" . implode( "\n", $errors ) ) );
							WP_CLI::error( 'END OF LIST', false );
						}
						WP_CLI::line( 'Reactivating wpez-sync' );
						$run = WP_CLI::runcommand( 'plugin activate wpez-sync', $options2 );

						$sync_tools->logFile( 'FINISHED: data import' );
						$sync_tools->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" ); //die;
					}

					// +--------------------------------------------------+
					// | Search & Replace URL's on the imported databases |
					// +--------------------------------------------------+

					// IF IMPORT IS ENABLED RUN THE IMPORT SCRIPT.
					if ( $replace ) {

						// STEP MODE -- STEP THROUGH FUNCTIONS ONE AT A TIME.
						if ( $step && $sync_tools->ask( "\nReady to Search-Replace DB's, Continue?" ) === 'n' ) {
							WP_CLI::error( 'Stopped at Search-Replace Databases' );
							die;
						}

						$errors       = array();
						$success      = array();
						$notify_all[] = $sync_tools->format_message( 'Starting search and replace of imported database files', 'heading' );
						WP_CLI::line( WP_CLI::colorize( "\n" . '%BStarting search and replace of imported database files%n' ) );
						$update_progress = \WP_CLI\Utils\make_progress_bar( 'Search Replace.', count( (array) $filenames ) );
						$i               = 1;
						$sync_tools->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
						$sync_tools->logFile( 'STARTING: search replace' );

						// LOOP THROUGH THE SELECTED FILES.
						foreach ( $tables_arr as $table ) {
							// RUNNING SEARCH REPLACE ON EACH TABLE IN THE ARRAY
							$command = 'search-replace "' . $remote_url . '" "' . $local_url . '" ' . $table . ' --format=count --all-tables-with-prefix';
							$run     = WP_CLI::runcommand( $command, $options2 );

							// CHECK FOR ERRORS -- CREATE ARRAY OF ERRORS FOR DISPLAY OUTSIDE OF LOOP
							if ( $run->stderr !== '' ) {
								$errors[] = "({$i}) {$table} -- " . $run->stderr;
								$sync_tools->logFile( "search replace: Running '{$command}' -- " . $run->stderr );
							}
							// IF SUCCESS -- CREATE ARRAY OF SUCCESS MESSAGES FOR DISPLAY OUTSIDE OF LOOP
							if ( $run->stdout !== '' ) {
								$success[] = "({$i}) {$table} -- search-replace completed with " . $run->stdout . ' fields changed.';
								$sync_tools->logFile( "search replace: Running '{$command}' -- Success: Search-Replace completed with " . $run->stdout . ' fields changed.' );
							}
							$update_progress->tick();
							++$i;
						}
						$update_progress->finish();

						// DISPLAY SUCCESS MESSAGES TO SCREEN
						if ( ! empty( $success ) ) {
							$message      = 'Search-Replace on these tables completed without issue';
							$notify_all[] = $sync_tools->format_message( $message . "\n\n" . implode( "\n\n", $success ), 'success' );
							WP_CLI::line( WP_CLI::colorize( "\n" . '%G' . $message . '%n' . "\n" . implode( "\n", $success ) ) );
							WP_CLI::success( 'END OF LIST' );
						}
						// DISPLAY ERRORS MESSAGES TO SCREEN
						if ( ! empty( $errors ) ) {
							$message      = 'There was a problem with Search-Replace on the following tables';
							$notification = $sync_tools->format_message( $message . "\n\n" . implode( "\n\n", $errors ), 'error' );
							$notify_all[] = $notification;
							$notify_err[] = $notification;
							WP_CLI::line( WP_CLI::colorize( "\n" . '%R' . $message . '%n' . "\n" . implode( "\n", $errors ) ) );
							WP_CLI::error( 'END OF LIST', false );
						}
						$sync_tools->logFile( 'FINISHED: search replace' );
						$sync_tools->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" );
					}
				} else {
					$message = 'There was a problem! No files were found on the remote server. You must run a remote backup first, or use the --all command to do a live backup.';
					//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
					WP_CLI::error( $message );
				}
				$done         = 'Total elapsed time was ' . $sync_tools->elapsed_time( time(), (int) $startTime );
				$notify_all[] = $sync_tools->format_message( 'Get data complete: ' . $done, 'attention' );
				//$sync_tools->notify( implode( "\n", $notify_all ), 'all' );
				if ( ! empty( $notify_err ) ) {
					$notification = $sync_tools->format_message( 'Database sync errors occurred at: ' . wp_date( 'm/d/Y -- h:i:s A' ) . ' -- ' . $user_loc . ' is getting data from ' . $remote_env, 'attention' );
					array_unshift( $notify_err, $notification );
					//$sync_tools->notify( implode( "\n", $notify_err ), 'error' );
				}

				WP_CLI::line( "\n" . 'Restoring the Remote Settings' );
				delete_option( 'wpez_sync_settings' );
				if ( ! update_option( 'wpez_sync_settings', $wpez_get_sync_settings ) ) {
					WP_CLI::warning( 'Option update failed.' );
				}

				WP_CLI::line( WP_CLI::colorize( "\n" . '%YGet data complete: %n' . $done . "\n" ) );

				//! put the run script after get functions here.
			}

			// ╔════════════════════════════════╗
			// ║  GET FILES FROM REMOTE SERVER  ║
			// ╚════════════════════════════════╝
			if ( $type === 'files' ) {
				// GET THE BUSY STATUS FROM THE REMOTE SERVER
				$ts_status = WP_CLI::runcommand( 'ez fetch status --get --files ' . $remote_tag, $options );
				if ( substr( $ts_status, 0, 7 ) === 'ERROR: ' ) {
					$ts_response                       = str_replace( 'ERROR: ', '', $ts_status );
					list($ts_time, $ts_user, $ts_type) = explode( '|', $ts_response );
					$ts_message                        = "{$remote_env} server busy -- {$ts_user} is backing up {$ts_type} -- started: " . wp_date( 'm/d/Y H:i:s', $ts_time ) . ' UTC';
					//$sync_tools->notify($sync_tools->format_message( $ts_message, 'error' ), 'error');
					WP_CLI::error( $ts_message );
				}
				// SET STATUS EQUAL TO BUSY ON THE REMOTE SERVER
				$ts_set = WP_CLI::runcommand( 'ez fetch status --set --files ' . $remote_tag, $options );
				// START THE "GET FILES" COMMAND AND STARTING THE RECORDING TIMER.
				$sync_tools->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
				$sync_tools->logFile( 'STARTING: file sync' );
				$startTime    = microtime( true );
				$memStart     = memory_get_usage();
				$notify_all[] = $sync_tools->format_message( 'File sync started at: ' . wp_date( 'm/d/Y -- h:i:s A' ) . ' -- ' . $user_loc . ' is getting files from ' . $remote_env, 'attention' );
				$notify_all[] = $sync_tools->format_message( 'Starting synchronization of files in the uploads directory.', 'heading' );
				WP_CLI::line( WP_CLI::colorize( '%BStarting synchronization of files in the uploads directory.%n' . "\n" ) );

				// CREATE TMP FOLDER IF DOES NOT EXIST
				$file_dir = $sync_tools->get_sync_dir( 'files' );

				// +------------------------------+
				// | Create local file list array |
				// +------------------------------+

				$notify_all[] = $sync_tools->format_message( '(1) Creating local array of files.', 'line' );
				WP_CLI::line( '(1) Creating local array of files.' );
				// DELETE OLD FILE IF EXISTS
				if ( file_exists( $file_dir . WPEZ_SYSTEM_URL . '_file_array.json' ) ) {
					unlink( $file_dir . WPEZ_SYSTEM_URL . '_file_array.json' );
				}

				// GENERATE LOCAL FILE LIST ACCORDING TO TIME FILTER
				$local_files_json = $sync_tools->get_file_list( WPEZ_UPLOADS_DIR, WPEZ_SYSTEM_URL, $timestamp );
				$sync_tools->logFile( "file sync: local json file name: {$local_files_json}" );

				// CREATE PHP ARRAY FROM THE RETRIEVED JSON FILE
				$local_files      = ( file_exists( $file_dir . $local_files_json ) ) ? json_decode( file_get_contents( $file_dir . $local_files_json ), true ) : array();
				$memUsed          = number_format( ( memory_get_usage() - $memStart ) / 1000000, 0 );
				$local_file_count = count( $local_files );
				$sync_tools->logFile( "file sync: local_files count: {$local_file_count} ({$memUsed}Mb)" );
				$notify_all[] = $sync_tools->format_message( "Local array of files Created. Total Files in array: {$local_file_count}", 'success' );
				WP_CLI::success( "Local array of files Created. Total Files in array: {$local_file_count}\n" ); //die;

				// +------------------------------+
				// | Create remote JSON file list |
				// +------------------------------+

				// OTHERWISE GENERATE A NEW JSON FILE
				$notify_all[] = $sync_tools->format_message( '(2) Creating remote array of files, and saving as JSON.', 'line' );
				WP_CLI::line( '(2) Creating remote array of files, and saving as JSON.' );
				// RESETTING THE TIMESTAMP TO CATCH ANY FILES THAT MIGHT BE UPLOADED AFTER THIS PROCESS BEGINS.
				$sync_tools->setTimeStamp( $user_loc, time() );

				// CREATE DATA OBJECT FOR CURL POST
				$ch_payload['type']      = 'files';
				$ch_payload['timestamp'] = $timestamp;
				$ch_payload['encrypt']   = $encrypt;
				$ch_payload['gzip']      = $gzip;
				$ch_data['data']         = json_encode( $ch_payload );
				$ch_data['auth_token']   = $auth_token;

				// SETUP CURL POST
				$ch = $sync_tools->setup_curl_post( $ch_data, $server_get );
				if ( $ch ) {
					$response = curl_exec( $ch ); //$sync_tools->logFile( "file sync: create remote -- response: " . print_r( $response, true ));
					$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); //$sync_tools->logFile( "file sync: create remote -- status: " . print_r( $status, true ));
					$error    = curl_error( $ch ); //$sync_tools->logFile( "file sync: create remote -- error: " . print_r( $error, true ));
					curl_close( $ch );
					$decoded = json_decode( $response, true );
					if ( ! empty( $decoded ) ) {
						// IF CURL POST SUCCEEDED, SAVE FILE NAME FOR DOWNLOAD AND PRINT MESSAGE TO SCREEN
						$remote_files_json = $decoded['data'];
						$remote_files_hash = $decoded['hash'];
						$notify_all[]      = $sync_tools->format_message( "Remote JSON file created. Remote json file name: {$remote_files_json}", 'success' );
						WP_CLI::success( "Remote JSON file created. Remote json file name: {$remote_files_json}\n" );
						$sync_tools->logFile( "file sync: remote json file name: {$remote_files_json} -- status: {$status} -- Curl Message: {$error}" );
					} else if ( $status == 504 ) {
						// IF TIME OUT OCCURS, CREATE A LOOP TO QUERY FOR FILE COMPLETION.
						WP_CLI::warning( 'Remote JSON file creation is taking a long time to complete...' );
						$jsonTime = microtime( true );

						// CREATE A LOOP THAT WILL NOT TIMEOUT FOR HOURS.
						for ( $x = 1; $x <= 100000; $x++ ) {
							// SINCE WE DON'T KNOW IF THE FILE HAS FINISHED BUILDING, SEN FETCH COMMAND TO SEE IF THE JSON FILE EXISTS.
							$json = WP_CLI::runcommand( 'ez fetch file --name=json --dir=files ' . $remote_tag, $options );
							if ( substr( $json, 0, 7 ) === 'ERROR: ' ) {
								// IF SERVER RETURNS ERROR, THEN WAIT 60 SECONDS AND THEN LOOP AND TRY AGAIN.
								WP_CLI::line( 'still waiting... Total elapsed time is ' . $sync_tools->elapsed_time( time(), (int) $jsonTime ) );
								sleep( 10 );
							} else {
								// IF SERVER RETURNS THAT IT FOUND THE FILE, SAVE THE NAME OF THE FILE AND PRINT MESSAGE TO SCREEN
								$fetched_file      = ( $json && is_string( $json ) ) ? explode( ',', $json ) : array();
								$remote_files_json = $fetched_file[0];
								$remote_files_hash = $fetched_file[1];
								$notify_all[]      = $sync_tools->format_message( "Remote JSON file created. Remote json file name: {$remote_files_json}", 'success' );
								WP_CLI::success( "Remote JSON file created. Remote json file name: {$remote_files_json}\n" );
								$sync_tools->logFile( "file sync: remote json file name: {$remote_files_json} -- status: {$status}" );
								break;
							}
						}
					} else {
						// IF CURL FAILS FOR ANOTHER REASON AND SENDS EMPTY RESPONSE
						$message = "The remote JSON file name was returned empty: -- status: {$status} -- Curl Message: {$error}\n";
						//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
						WP_CLI::error( $message );
					}
				} else {
					// IF CURL FAILS FOR SOME OTHER REASON PRINT GENERAL ERROR MESSAGE.
					$message = 'CURL Post Failed getting remote JSON file name.';
					//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
					WP_CLI::error( $message );
					die;
				}

				// +--------------------------------+
				// | Download remote JSON file list |
				// +--------------------------------+

				$notify_all[] = $sync_tools->format_message( '(3) Downloading the remote JSON file from the remote server.', 'line' );
				WP_CLI::line( '(3) Downloading the remote JSON file from the remote server.' );
				$ch_url = $server_dl . '?auth_token=' . urlencode( $auth_token ) . '&type=json&file=' . $remote_files_json;

				// IF THERE IS A FILE TO DOWNLOAD START THE DOWNLOAD PROCESS.
				if ( ! empty( $remote_files_json ) ) {
					// CREATE FILE FILE TO SAVE THE REMOTE JSON TO.
					$remoteJsonFile = $file_dir . $remote_files_json;
					$fp             = fopen( $remoteJsonFile, 'wb' );
					if ( $fp ) {
						// SETUP THE CURL CONNECTION
						$ch = $sync_tools->setup_curl_get( $ch_url, $fp );
						if ( $ch ) { // if file is found and downloaded, save file to local.
							$response = curl_exec( $ch );
							$sync_tools->logFile( 'file sync: create remote -- response: ' . print_r( $response, true ) );
							$status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
							$sync_tools->logFile( 'file sync: create remote -- status: ' . print_r( $status, true ) );
							$error = curl_error( $ch );
							$sync_tools->logFile( 'file sync: create remote -- error: ' . print_r( $error, true ) );
							curl_close( $ch );
							fclose( $fp );
							$decoded = json_decode( $response, true );
							// IF SUCCESS DISPLAY MESSAGE THAT FILE DOWNLOADED.
							if ( $status == 200 ) {
								// CHECK MD5, IF FALSE ADD WP_CLI::ERROR
								if ( md5_file( $remoteJsonFile ) === $remote_files_hash ) {
									$notify_all[] = $sync_tools->format_message( "Remote JSON file: {$remote_files_json}, downloaded successfully", 'success' );
									WP_CLI::success( "Remote JSON file: {$remote_files_json}, downloaded successfully\n" );
									$sync_tools->logFile( "download of JSON file: {$remote_files_json} -- status: {$status} -- Success: {$remote_files_json} downloaded successfully" );
								} else {
									$errors[] = "({$i}) MD5 checksum of {$remote_files_json} did not match after download";
									$sync_tools->logFile( "data download of {$remote_files_json} -- status: {$status} -- Error: MD5 checksum of {$remote_files_json} did not match after download -- Curl Message: {$error}" );
								}
							} else {
								// IF ERROR DISPLAY ERROR MESSAGE.
								$message = "There was a problem! the Remote JSON file: {$remote_files_json}, did not download properly";
								//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
								$sync_tools->logFile( "download of JSON file: {$remote_files_json} -- status: {$status} -- Error:" . $decoded['message'] . " -- Curl Message: {$error}" );
								WP_CLI::error( $message );
							}
							// IF CURL ERROR -- REPORT Failed
						} else {
							$message = "CURL file download Failed on {$file}";
							//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
							WP_CLI::error( $message );
						}
					} else {
						$message = "Cannot create local JSON file {$$remoteJsonFile}";
						//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
						$sync_tools->logFile( "ERROR: {$message}" );
						WP_CLI::error( $message );
					}
				}
				// CLEAR OUT THE BUSY STATUS FROM THE REMOTE SERVER
				$ts_clear = WP_CLI::runcommand( 'ez fetch status --clear --files ' . $remote_tag, $options );

				// +-------------------------------------------------------------+
				// | Create remote file list array from the downloaded JSON file |
				// +-------------------------------------------------------------+

				$notify_all[] = $sync_tools->format_message( '(4) Creating remote array of files.', 'line' );
				WP_CLI::line( '(4) Creating remote array of files.' );
				// CREATE PHP ARRAY FROM THE RETRIEVED JSON FILE.
				$remote_files      = ( file_exists( $file_dir . $remote_files_json ) ) ? json_decode( file_get_contents( $file_dir . $remote_files_json ), true ) : array();
				$remote_file_count = count( $remote_files );
				$sync_tools->logFile( "file sync: remote_files count: {$remote_file_count}" );
				$notify_all[] = $sync_tools->format_message( "Remote array of files Created. Total Files in array: {$remote_file_count}", 'success' );
				WP_CLI::success( "Remote array of files Created. Total Files in array: {$remote_file_count}\n" );

				// +--------------------------------------------------------------+
				// | Compare the local and remote file list array for differences |
				// +--------------------------------------------------------------+

				$notify_all[] = $sync_tools->format_message( '(5) Comparing local and remote file arrays to get updated files array.', 'line' );
				WP_CLI::line( '(5) Comparing local and remote file arrays to get updated files array.' );
				$updated_files = array();
				// CREATE PHP ARRAY OF THE DIFFERENCES BETWEEN LOCAL AND REMOTE.
				foreach ( $remote_files as $file_path => $info ) {
					if ( empty( $local_files[ $file_path ] ) || $local_files[ $file_path ]['md5'] != $info['md5'] ) {
						$updated_files[ $file_path ] = $info;
					}
				}
				$updated_file_count = count( $updated_files );
				$sync_tools->logFile( "handlers: updated_files count: {$updated_file_count}" );
				$notify_all[] = $sync_tools->format_message( "Updated array of files Created. Total Files in array: {$updated_file_count}", 'success' );
				WP_CLI::success( "Updated array of files Created. Total Files in array: {$updated_file_count}\n" ); //die;

				// +--------------------------------------------------------------------+
				// | Download files that are not present locally from the remote server |
				// +--------------------------------------------------------------------+

				if ( ! empty( $updated_files ) ) {
					$errors       = array();
					$errors_md5   = array();
					$success      = array();
					$status       = array();
					$decoded      = array();
					$notify_all[] = $sync_tools->format_message( 'Starting download of new files from the remote server.', 'heading' );
					WP_CLI::line( WP_CLI::colorize( '%BStarting download of new files from the remote server%n' ) );
					$howMany = count( $updated_files );

					$download_progress = \WP_CLI\Utils\make_progress_bar( "Downloading {$howMany} Files.", $howMany );
					$i                 = 1;
					$x                 = 1;
					// LOOP THROUGH THE SELECTED FILES.
					foreach ( $updated_files as $file => $info ) {
						// IF FILENAME IS NOT EMPTY
						if ( ! empty( $file ) ) {
							// SETUP URL OF FILE TO DOWNLOAD.
							// ENSURE THAT UTF-8 CHARS OR + AREN'T CONVERTED BY HTTP WHICH SCREWS UP THE REMOTE END
							$base64File = 'base64:' . base64_encode( $file );
							$ch_url     = $server_dl . '?auth_token=' . urlencode( $auth_token ) . '&type=files&file=' . $base64File;
							// SETUP DIRECTORY FOR DOWNLOADED FILE, IF IT DOES NOT EXIST.
							$file_dir       = WPEZ_UPLOADS_DIR;
							$full_file_path = $file_dir . $file;
							if ( ! file_exists( dirname( $full_file_path ) ) ) {
								mkdir( dirname( $full_file_path ), 0755, true ); }
							$ch = $sync_tools->setup_curl_get_data( $ch_url );
							if ( $ch ) {
								// THIS GRABS THE FILE DATA
								$response = curl_exec( $ch );
								$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
								$filesize = strlen( $response );

								$error = curl_error( $ch );
								curl_close( $ch );
								if ( empty( $response ) ) {
									$message  = "Empty CURL response with status {$status} for {$file}";
									$errors[] = $message;
									$sync_tools->logFile( $message );
								} else if ( $status == 200 ) {
									// COMPARE THE MD5 HASHES, TO MAKE SURE THE DOWNLOADED FILE IS THE SAME AS THE ORIGINAL ON THE REMOTE SERVER.
									$saved_md5_hash = md5( $response );
									if ( $saved_md5_hash === $info['md5'] ) {
										$success[0] = "{$x} of {$howMany} files downloaded successfully";
										++$x;
										$sync_tools->logFile( "file download -- status: {$status} -- size {$filesize} -- Success: {$file}" );
										// SAVE TO DISK LOCALLY
										$fp = fopen( $full_file_path, 'wb' );
										if ( $fp ) {
											fwrite( $fp, $response );
											fclose( $fp );
											// COPY ORIGINAL TIMESTAMP
											touch( $full_file_path, $info['timestamp'] );
										} else {
											$message  = "Cannot write to {$full_file_path} for {$file}";
											$errors[] = $message;
											$sync_tools->logFile( $message );
										}
									} else {
										// MD5 MISMATCH
										$errors_md5[] = $file;
										$sync_tools->logFile( "file download -- status: {$status} -- Error: MD5 checksum of {$file} did not match after download -- Curl Message: {$error}" );
										$sync_tools->logFile( "Error: MD5 wanted {$info['md5']}: got {$saved_md5_hash}" );
									}
								} else {
									// HTTP STATUS IS NOT 200, SO SOME TYPE OF ERROR
									$decoded  = json_decode( $response, true );
									$message  = $decoded['message'];
									$errors[] = $message;
									$sync_tools->logFile( "file download -- status: {$status} -- Error:" . $message . " -- Curl Message: {$error}" );
								}
							} else {
								$message  = "Could not start CURL download for {$file}";
								$errors[] = $message;
								$sync_tools->logFile( $message );
							}
						}
						unset( $updated_files[ $file ] ); // reduce ram usage
						$download_progress->tick();
						++$i;
					}
					$download_progress->finish();

					// WHEN LOOP IS COMPLETE REPORT SUCCESS MESSAGE AND POSSIBLE ERRORS.
					if ( ! empty( $success ) ) {
						$notify_all[] = $sync_tools->format_message( $success[0], 'success' );
						WP_CLI::success( $success[0] );
					} else {
						$notify_all[] = $sync_tools->format_message( 'No files were downloaded', 'warning' );
						WP_CLI::warning( 'No files were downloaded' );
					}
					if ( ! empty( $errors_md5 ) ) {
						$message      = 'The following files did not pass the MD5 check:';
						$notification = $sync_tools->format_message( $message . "\n\n" . implode( "\n\n", $errors_md5 ), 'error' );
						$notify_all[] = $notification;
						$notify_err[] = $notification;
						WP_CLI::error( $message . "\n" . implode( "\n", $errors_md5 ), false );
						// BECAUSE ERRORS WERE FOUND, RESETTING THE TIMESTAMP TO WHAT IT WAS BEFORE THE DOWNLOAD BEGAN.
						$sync_tools->setTimeStamp( $user_loc, $timestamp );
					}
					if ( ! empty( $errors ) ) {
						$message      = 'The following files Failed for some other reason:';
						$notification = $sync_tools->format_message( $message . "\n\n" . implode( "\n\n", $errors ), 'error' );
						$notify_all[] = $notification;
						$notify_err[] = $notification;
						WP_CLI::error( $message . "\n" . implode( "\n", $errors ), false );
						// BECAUSE ERRORS WERE FOUND, RESETTING THE TIMESTAMP TO WHAT IT WAS BEFORE THE DOWNLOAD BEGAN.
						$sync_tools->setTimeStamp( $user_loc, $timestamp );
					}
					$sync_tools->logFile( 'FINISHED: file sync' );
					$sync_tools->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" );
				} else {
					// IF THE UPDATED ARRAY IS EMPTY, REPORT THAT NO DIFFERENCES WHERE FOUND.
					$notify_all[] = $sync_tools->format_message( 'No differences were found between the remote and local versions of the site.', 'warning' );
					WP_CLI::warning( 'No differences were found between the remote and local versions of the site.' );
				}
				$done         = 'Total elapsed time was ' . $sync_tools->elapsed_time( time(), (int) $startTime );
				$notify_all[] = $sync_tools->format_message( 'Get files complete: ' . $done, 'attention' );
				//$sync_tools->notify( implode( "\n", $notify_all ), 'all' );
				if ( ! empty( $notify_err ) ) {
					$notification = $sync_tools->format_message( 'File sync errors occurred at: ' . wp_date( 'm/d/Y -- h:i:s A' ) . ' -- ' . $user_loc . ' is getting data from ' . $remote_env, 'attention' );
					array_unshift( $notify_err, $notification );
					//$sync_tools->notify( implode( "\n", $notify_err ), 'error' );
				}
				WP_CLI::line( WP_CLI::colorize( "\n" . '%YGet files complete: %n' . $done . "\n" ) );
				$ts_clear = WP_CLI::runcommand( 'ez fetch status --clear --files ' . $remote_tag, $options );
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

WP_CLI::add_command( 'ez get', __NAMESPACE__ . '\\Get' );

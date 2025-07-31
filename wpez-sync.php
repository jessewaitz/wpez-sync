<?php
/**
 * WPEZ Sync Plugin -- This plugin allows for downloading the database and files from the selected remote server.
 *
 * @package WPEZ\Sync
 * @author  WPEZtools.com
 *
 * Plugin Name: WPEZtools Sync
 * Description: Syncing plugin for downloading a copy of the live site to either hot standby or dev versions of the site.
 * Version: 1.0.1.2
 * Author: Jesse Waitz
 * Author URI: https://www.wpeztools.com
 */

namespace WPEZ\Sync;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// define globals
defined( 'ABSPATH' ) || die( 'No direct access!' );

/**
 * Current plugin version.  Update this number to increment all functions throughout the plugin
 * Don't forget to increment the number in the plugin doc block above.
 *
 * Full rewrite of plugin upgrading the following:
 * 1. namespaced and changed the plugin to use modern design practices.
 * 2. Improved Public Key/Private Key authentication schema.
 * 3. Includes gzip compression and AES encryption for all remote transactions.
 * 4. Upgraded all cUrl requests to use Rest API endpoints instead of admin-ajax.
 * 5. Improved settings page to allow for adding multiple remotes, and exporting configurations.
 * 6. Moved Github token to settings page and added it to a new keys and tokens page.
 */
define( 'WPEZ_SYNC_VERSION', '1.0.1.2' );

$wpez_sync_settings = get_option( 'wpez_sync_settings' ) ?? array();
define( 'WPEZ_SYNC_SETTINGS', $wpez_sync_settings );
define( 'WPEZ_SYNC_CACHE_BUSTER', '-'.wp_date( 'YmdHis' ) ); //! make sure to remove this cachebuster on live.
define( 'WPEZ_SYNC_PLUGIN_PATH', __DIR__ );
define( 'WPEZ_SECRET_KEY', $wpez_sync_settings['secret_key'] ?? '' );
define( 'WPEZ_SECRET_SALT', $wpez_sync_settings['secret_salt'] ?? '' );
define( 'WPEZ_UPLOADS_RELPATH', 'wp-content/uploads/' );
define( 'WPEZ_UPLOADS_DIR', ABSPATH . WPEZ_UPLOADS_RELPATH );
define( 'WPEZ_DATABASE_DIR', dirname( ABSPATH ) . '/wpezsync/databases/' );
define( 'WPEZ_FILES_DIR', dirname( ABSPATH ) . '/wpezsync/files/' );

$wpez_system_loc           = '';
$wpez_system_flg           = '';
$wpez_sync_remotes_arr     = array();
$wpez_sync_remotes_setting = $wpez_sync_settings['remotes'] ?? array();
$wpez_sync_github_access_token = $wpez_sync_settings['github_access_token'] ?? '';
if ( ! empty( $wpez_sync_remotes_setting ) && is_array( $wpez_sync_remotes_setting ) ) {
	//$wpez_sync_remotes_rows = explode( PHP_EOL, $wpez_sync_remotes_setting );
	foreach ( $wpez_sync_remotes_setting as $row ) {
		//$wpez_sync_row                             = explode( '|', $row );
		//$wpez_sync_remotes_arr[ $row[0] ] = $wpez_sync_row[1];
		$wpez_sync_remotes_arr[ $row['flag'] ] = $row['url'];
		if ( rtrim( $row['url'], '/' ) === site_url() ) {
			$wpez_system_loc = $row['location'];
			$wpez_system_flg = $row['flag'];
		}
	}
}
define( 'WPEZ_SYNC_REMOTES_SETTING', $wpez_sync_remotes_setting );
define( 'WPEZ_SYNC_REMOTES', $wpez_sync_remotes_arr );
define( 'WPEZ_SYNC_GITHUB_ACCESS_TOKEN', $wpez_sync_github_access_token );

// define globals for identifying the location of server and user running the code.
if ( function_exists( 'posix_geteuid' ) && function_exists( 'posix_getpwuid' ) ) {
	$processUserArr = posix_getpwuid( posix_geteuid() );
	$processUser    = $processUserArr['name'] ?? 'unknown';
} else {
	$processUser = get_current_user();
}
$wpez_domain_name = parse_url( site_url(), PHP_URL_HOST ) ?? 'unknown';
define( 'WPEZ_SYSTEM_USR', $processUser ?: 'unknown' );
define( 'WPEZ_SYSTEM_URL', $wpez_domain_name ?: 'unknown' );
define( 'WPEZ_SYSTEM_LOC', $wpez_system_loc ?: 'unknown' );
define( 'WPEZ_SYSTEM_FLG', $wpez_system_flg ?: 'unknown' );
// hide all PHP errors for command line runs
//error_reporting( 0 ); // phpcs:ignore

/**
 * WPEZ Sync main class -- this includes and loads all of the required assets.
 * Establishes all of the ajax handling functions, sets up the menus, and
 * creates the settings page.
 */
class Sync {
	/**
	 * Construct class, and setup ajax handlers.
	 *
	 * @return void
	 */
	public function __construct() {
		require_once __DIR__ . '/vendor/autoload.php';
		require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';

		// Include the plugin update checker to manage updates from my git repo.
		$PucFactory      = new \YahnisElsts\PluginUpdateChecker\v5\PucFactory();
		$myUpdateChecker = $PucFactory->buildUpdateChecker( 'https://github.com/jessewaitz/wpez-sync/', __FILE__, 'wpez-sync' );
		$myUpdateChecker->setBranch( 'master' );
		$myUpdateChecker->setAuthentication( WPEZ_SYNC_GITHUB_ACCESS_TOKEN );

		// Require CLI files only when WP-CLI is available
		if ( defined( 'WP_CLI' ) && class_exists( 'WP_CLI' ) ) {
			require_once __DIR__ . '/inc/cli.php';
			// Require files for the commands
			require_once __DIR__ . '/inc/commands/auth.php';
			require_once __DIR__ . '/inc/commands/fetch.php';
			require_once __DIR__ . '/inc/commands/get.php';
			require_once __DIR__ . '/inc/commands/backup.php';
			require_once __DIR__ . '/inc/commands/test.php';
		}
		
		// Always require these files
		require_once __DIR__ . '/inc/tools.php';
		require_once __DIR__ . '/inc/settings.php';

		// register rest endpoints
		add_action( 'rest_api_init', array( $this, 'wpez_sync_rest_endpoints' ) );

		// load Tools class
		$sync_tools = new Tools();
		// setup action for the db dump compress and encrypt feature
		add_action( 'do_wpez_db_export', array( $sync_tools, 'wpez_db_export' ), 10, 4 );

		// load settings class
		$sync_settings = new Settings();
		// load editor assets.
		add_action( 'admin_enqueue_scripts', array( $sync_settings, 'wpez_sync_enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $sync_settings, 'wpez_sync_enqueue_admin_scripts' ) );
		// load settings page
		add_action( 'admin_menu', array( $sync_settings, 'wpez_sync_add_settings_page' ) );
		add_action( 'admin_init', array( $sync_settings, 'wpez_sync_settings_init' ) );
		// add a "settings" action link to the plugin listing page.
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $sync_settings, 'wpez_sync_action_links' ) );
	}

	// ╔═══════════════════════════════════════════════════════════╗
	// ║  ██████  ███████ ███████ ████████      █████  ██████  ██  ║
	// ║  ██   ██ ██      ██         ██        ██   ██ ██   ██ ██  ║
	// ║  ██████  █████   ███████    ██        ███████ ██████  ██  ║
	// ║  ██   ██ ██           ██    ██        ██   ██ ██      ██  ║
	// ║  ██   ██ ███████ ███████    ██        ██   ██ ██      ██  ║
	// ╚═══════════════════════════════════════════════════════════╝
	/**
	 * This function defines the Rest API Endpoints, used within this plugin.
	 *
	 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
	 *
	 * @return void
	 */
	public function wpez_sync_rest_endpoints() {
		register_rest_route(
			'wpez/v1',
			'/auth/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wpez_sync_authorize' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'wpez/v1',
			'/fetch/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wpez_sync_fetch' ),
				'permission_callback' => array( $this, 'wpez_sync_permissions' ),
			)
		);
		register_rest_route(
			'wpez/v1',
			'/get/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wpez_sync_get' ),
				'permission_callback' => array( $this, 'wpez_sync_permissions' ),
			)
		);
		register_rest_route(
			'wpez/v1',
			'/put/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wpez_sync_put' ),
				'permission_callback' => array( $this, 'wpez_sync_permissions' ),
			)
		);
		register_rest_route(
			'wpez/v1',
			'/upload/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wpez_sync_upload' ),
				'permission_callback' => array( $this, 'wpez_sync_permissions' ),
			)
		);
		register_rest_route(
			'wpez/v1',
			'/download/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'wpez_sync_download' ),
				'permission_callback' => array( $this, 'wpez_sync_permissions' ),
			)
		);
	}

	/**
	 * This functions handles the permissions for the api call. Only allow authorized requests.
	 *
	 * @param WP_REST_Request $data Data sent with the request.
	 * @return bool|WP_Error TRUE if passes authorization. WP_Error if fails authorization.
	 */
	public function wpez_sync_permissions( WP_REST_Request $data ) {
		$sync_tools           = new Tools();
		$params               = $data->get_params();
		$wpez_auth_token      = $params['auth_token'] ?? false;
		$decrypted_auth_token = $sync_tools->decrypt( $wpez_auth_token );
		$json_auth_token      = json_decode( $decrypted_auth_token );
		$auth_token_array     = $json_auth_token ?: false;

		//$sync_tools->logFile("fetch: -- wpez_auth_token: ".print_r($wpez_auth_token, true));
		//$sync_tools->logFile("fetch: -- decrypted_auth_token: ".print_r($decrypted_auth_token, true));
		//$sync_tools->logFile("fetch: -- current nonce array: ".print_r($sync_tools->create_nonce( $auth_token_array->username ), true));

		// If request authorized, return true.
		if ( ! empty( $auth_token_array ) && $sync_tools->verify_nonce( $sync_tools->create_nonce( $auth_token_array->username ), $auth_token_array->nonce ) ) {
			return true;
		}
		// If request not authorized, return a WordPress error.
		return new \WP_Error( 'not-authorized', 'ERROR: Request not authorized.', array( 'status' => 400 ) );
	}

	// ╔═════════════════════════════════════════════════════════════════════════╗
	// ║   █████  ██    ██ ████████ ██   ██  ██████  ██████  ██ ███████ ███████  ║
	// ║  ██   ██ ██    ██    ██    ██   ██ ██    ██ ██   ██ ██    ███  ██       ║
	// ║  ███████ ██    ██    ██    ███████ ██    ██ ██████  ██   ███   █████    ║
	// ║  ██   ██ ██    ██    ██    ██   ██ ██    ██ ██   ██ ██  ███    ██       ║
	// ║  ██   ██  ██████     ██    ██   ██  ██████  ██   ██ ██ ███████ ███████  ║
	// ╚═════════════════════════════════════════════════════════════════════════╝

	/**
	 * Authorize incoming REST requests.
	 *
	 * @param WP_REST_Request $data Options for the function.
	 * @return string|null Post title for the latest, * or null if none.
	 */
	public function wpez_sync_authorize( WP_REST_Request $data ) {
		$sync_tools = new Tools();
		$params     = $data->get_params();
		$data_arr   = json_decode( $params['data'] );
		$username   = $data_arr->username;
		$passkey    = (string) $data_arr->passkey;
		$passkey    = $sync_tools->decrypt( $passkey );
		$nonce      = $sync_tools->create_nonce( $username );
		$token      = $sync_tools->encrypt( $nonce['now'] );

		//$sync_tools->logFile("auth: -- params data: ".print_r($params['data'], true));
		//$sync_tools->logFile("auth: -- data decoded: ".print_r($data_arr, true));
		//$sync_tools->logFile("auth: -- username: ".var_export($username, true));
		//$sync_tools->logFile("auth: -- passkey: ".var_export($passkey, true));
		//$sync_tools->logFile("auth: -- nonce: ".print_r($nonce, true));
		//$sync_tools->logFile("auth: -- token: ".print_r($token, true));

		if ( $username === $passkey ) {
			return new \WP_REST_Response(
				array(
					'status' => 'Success',
					'output' => $token,
				),
				200
			);
		} else {
			return new \WP_Error( 'invalid-token', 'ERROR: Token is invalid.', array( 'status' => 400 ) );
		}
	}

	// ╔════════════════════════════════════════════╗
	// ║  ███████ ███████ ████████  ██████ ██   ██  ║
	// ║  ██      ██         ██    ██      ██   ██  ║
	// ║  █████   █████      ██    ██      ███████  ║
	// ║  ██      ██         ██    ██      ██   ██  ║
	// ║  ██      ███████    ██     ██████ ██   ██  ║
	// ╚════════════════════════════════════════════╝
	/**
	 * Simple function to get nonce from target server in order to complete other transactions.
	 *
	 * @param WP_REST_Request $data Options for the function.
	 * @return string|null Post title for the latest, * or null if none.
	 */
	public function wpez_sync_fetch( WP_REST_Request $data ) {
		global $wpdb;
		$sync_tools  = new Tools();
		$output      = array();
		$wpez_output = array();
		$wpez_error  = false;
		$params      = $data->get_params(); //$sync_tools->logFile("fetch: -- params: ".print_r($params, true));
		if ( ! empty( $params ) ) {
			$wpez_type       = ( ! empty( $params['type'] ) ) ? $params['type'] : false; //phpcs:ignore
			$wpez_payload    = ( ! empty( $params['data'] ) ) ? $params['data'] : false; //phpcs:ignore
			$wpez_data       = json_decode( trim( stripslashes( $wpez_payload ), '\'"' ), true );
			$wpez_all_tables = ( ! empty( $wpez_data['all_tables'] ) ) ? $wpez_data['all_tables'] : false; //phpcs:ignore
			$wpez_name       = ( ! empty( $wpez_data['name'] ) ) ? $wpez_data['name'] : false; //phpcs:ignore
			$wpez_dir        = ( ! empty( $wpez_data['dir'] ) ) ? $wpez_data['dir'] : false; //phpcs:ignore
			$wpez_action     = ( ! empty( $wpez_data['action'] ) ) ? $wpez_data['action'] : false; //phpcs:ignore
			$wpez_target     = ( ! empty( $wpez_data['target'] ) ) ? $wpez_data['target'] : false; //phpcs:ignore
			$wpez_file_name  = ( $wpez_name === 'json' ) ? WPEZ_SYSTEM_URL . '_file_array.json' : $wpez_name; //$sync_tools->logFile("fetch: wpez_file_name: {$wpez_file_name}");
			$user_loc        = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;

			if ( $wpez_type === 'tables' ) {
				$db_from   = ' FROM ' . $wpdb->dbname;
				$db_prefix = ( ! $wpez_all_tables ) ? " LIKE '" . $wpdb->prefix . "%'" : '';
				$db_tables = $wpdb->get_results( 'SHOW TABLES' . $db_from . $db_prefix, ARRAY_N ); //phpcs:ignore
				//$sync_tools->logFile("fetch: -- Success: db_tables (wpdb): ".print_r($db_tables, true));
				// IMPORTANT -- if you get the error below "No tables found" it means that the mysql CLI tools are not installed on the remote server.
				if ( empty( $db_tables ) ) {
					$wpez_error = 'ERROR: No tables found.'; }
				foreach ( $db_tables as $file ) {
					$output[] = $file[0]; }
				$wpez_output = ( ! empty( $output ) ) ? json_encode( $output ) : json_encode( array() );
				$sync_tools->logFile( 'fetch: db_tables: ' . print_r( $wpez_output, true ) );
			}
			if ( $wpez_type === 'stored' ) {
				$db_dir   = $sync_tools->get_sync_dir( 'databases' );
				$db_files = array_diff( scandir( $db_dir ), array( '..', '.' ) );
				//$sync_tools->logFile("fetch: -- Success: db_files: ".print_r($db_files, true));
				if ( empty( $db_files ) ) {
					$wpez_error = 'ERROR: No stored tables found.';
				}
				foreach ( $db_files as $file ) {
					$table = $file;
					$table = ( strpos( $file, '.gz' ) !== false ) ? strstr( $table, '.gz', true ) : $table;
					$table = ( strpos( $file, '.sql' ) !== false ) ? strstr( $table, '.sql', true ) : $table;
					$table = ( strpos( $file, '.aes' ) !== false ) ? strstr( $table, '.aes', true ) : $table;
					if ( file_exists( $db_dir . $file ) ) {
						$output[ $table ] = array(
							'file' => $file,
							'hash' => md5_file( $db_dir . $file ),
						);
						$sync_tools->logFile("MD5 stored file: {$file} -- table: {$table} -- hash: ".md5_file($db_dir.$file));
					}
				}
				$wpez_output = ( ! empty( $output ) ) ? json_encode( $output ) : json_encode( array() );
				//$sync_tools->logFile( 'fetch: -- Success: db_files: ' . print_r( $wpez_output, true ) );
			}
			if ( $wpez_type === 'file' ) {
				$wpez_sync_dir = $sync_tools->get_sync_dir( $wpez_dir );
				if ( file_exists( $wpez_sync_dir . $wpez_file_name ) ) {
					$wpez_hash1 = md5_file( $wpez_sync_dir . $wpez_file_name );
					sleep( 1 );
					$wpez_hash2 = md5_file( $wpez_sync_dir . $wpez_file_name );
					if ( $wpez_hash1 === $wpez_hash2 ) {
						$wpez_output = $wpez_file_name . ',' . $wpez_hash2;
						$sync_tools->logFile( 'fetch: file -- Success: ' . $wpez_sync_dir . $wpez_file_name . ' -- Hash: ' . $wpez_hash2 );
					} else {
						$wpez_error = 'ERROR: ' . $wpez_file_name . ' still being created.';
						$sync_tools->logFile( "fetch: file -- {$wpez_error}" );
					}
				} else {
					$wpez_error = 'ERROR: ' . $wpez_file_name . ' not found.';
					$sync_tools->logFile( "fetch: file -- {$wpez_error}" );
				}
			}
			if ( $wpez_type === 'status' ) {
				// SETUP THE USER AND LOCATION LABEL
				$timestamp_dir = $sync_tools->get_sync_dir( 'timestamps' );
				$timefile      = $timestamp_dir . $wpez_target . '-status.txt';
				if ( $wpez_action === 'get' ) {
					if ( file_exists( $timefile ) ) {
						$time3hrsago   = strtotime( '3 hours ago' );
						$timestatus    = file_get_contents( $timefile );
						$timestatusarr = explode( '|', $timestatus );
						if ( $time3hrsago > $timestatusarr[0] ) {
							unlink( $timefile );
							$wpez_output = 'ready';
						} else {
							$wpez_output = 'failed';
							$wpez_error  = "ERROR: {$timestatus}";
						}
					} else {
						$wpez_output = 'ready';
					}
				}
				if ( $wpez_action === 'set' ) {
					$ts_set = $sync_tools->setTimeStamp( $wpez_target . '-status', time() . '|' . $user_loc . '|' . $wpez_target );
					if ( $ts_set ) {
						$wpez_output = 'timestamp set';
					} else {
						$wpez_error = 'failed to set timestamp';
					}
				}
				if ( $wpez_action === 'clear' ) {
					if ( file_exists( $timefile ) ) {
						unlink( $timefile );
						$wpez_output = 'cleared';
					} else {
						$wpez_error = 'not found';
					}
				}
			}
			if ( $wpez_type === 'env' ) {
				$wpez_output = WPEZ_SYSTEM_URL;
				//$wpez_output = ABSPATH;
				$sync_tools->logFile( "fetch: -- Success: WPEZ_SYSTEM_URL: {$wpez_output}" );
				//$sync_tools->logFile("fetch: -- server: ".print_r(__DIR__,true));
				if ( ! $wpez_output ) {
					$wpez_error = 'ERROR: ENV not found.';
				}
			}
			// if output is empty, generate an error.
			if ( empty( $wpez_output ) ) {
				$wpez_error = 'ERROR: there was no response from the remote server';
			}
			// check for errors and report
			if ( $wpez_error ) {
				return new \WP_Error( 'no-reponse', $wpez_error, array( 'status' => 400 ) );
			} else {
				return new \WP_REST_Response(
					array(
						'status' => 'Success',
						'output' => $wpez_output,
					),
					200
				);
			}
		} else {
			// if payload empty return a WordPress error.
			return new \WP_Error( 'not-authorized', 'ERROR: Request was empty.', array( 'status' => 400 ) );
		}
	}

	// ╔═════════════════════════════╗
	// ║   ██████  ███████ ████████  ║
	// ║  ██       ██         ██     ║
	// ║  ██   ███ █████      ██     ║
	// ║  ██    ██ ██         ██     ║
	// ║   ██████  ███████    ██     ║
	// ╚═════════════════════════════╝
	/**
	 * Adds Ajax API Handlers for this plugin.
	 *
	 * @param WP_REST_Request $data Options for the function.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function wpez_sync_get( WP_REST_Request $data ) {
		$sync_tools = new Tools();
		$params     = $data->get_params(); //$sync_tools->logFile("fetch: -- params: ".print_r($params, true));
		if ( ! empty( $params ) ) {
			$wpez_payload    = ( ! empty( $params['data'] ) ) ? $params['data'] : false; //phpcs:ignore
			$wpez_data    = json_decode( trim( stripslashes( $wpez_payload ), '\'"' ), true ); //$sync_tools->logFile("handlers: wpez_data: {$wpez_data}");
			$dl_type      = $wpez_data['type'];
			$dl_encrypt   = $wpez_data['encrypt'];
			$dl_gzip      = $wpez_data['gzip'];
			$user_loc     = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
			// +---------------------------------------------------------------------------------+
			// | If requested type is data. clear out old dump of table, and generate a new one. |
			// +---------------------------------------------------------------------------------+
			if ( $dl_type === 'data' ) {
				// setup variables
				$tables = $wpez_data['tables'];
				// remove the old gzips file if present
				$db_dir = $sync_tools->get_sync_dir( 'databases' );
				// Delete ALL .sql, .gz and .aes files from the db_dir directory.
				$sql_files = glob( $db_dir . '*.sql' );
				$gz_files = glob( $db_dir . '*.gz' );
				$aes_files = glob( $db_dir . '*.aes' );
				$files = array_merge( $sql_files, $gz_files, $aes_files );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
				/**
				 * Fire the new action scheduler functionality for the db dump.
				 *
				 * $timestamp (integer)(required) The Unix timestamp representing the date you want the action to run.
				 * $hook (string)(required) Name of the action hook.
				 * $args (array) Arguments to pass to callbacks when the hook triggers. Default: array().
				 * $group (string) The group to assign this job to. Default: '’.
				 * $unique (boolean) Whether the action should be unique. Default: false.
				 * $priority (integer) Lower values take precedence over higher values. Defaults to 10, with acceptable values falling in the range 0-255.)
				 */
				as_schedule_single_action( time(), 'do_wpez_db_export', array( $db_dir, $dl_encrypt, $dl_gzip, $tables ), 'wpez', true, 0 );

				// Return created database files name
				return new \WP_REST_Response(
					array(
						'status'  => 'Success',
						'message' => 'Scheduled action to export tables created successfully.',
					),
					200
				);
			}
			// +------------------------------------------------------------------------------------+
			// | If requested type is file. clear out old json file object, and generate a new one. |
			// +------------------------------------------------------------------------------------+
			if ( $dl_type === 'files' ) {
				// setup variables
				$timestamp = $wpez_data['timestamp'];
				$files_dir = $sync_tools->get_sync_dir( 'files' );
				// remove the old JSON file if present
				if ( file_exists( $files_dir . WPEZ_SYSTEM_URL . '_file_array.json' ) ) {
					unlink( $files_dir . WPEZ_SYSTEM_URL . '_file_array.json' ); }
				// starting the remote files array
				$sync_tools->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
				$sync_tools->logFile( "STARTING: JSON File Build (by {$user_loc})" );
				// generate remote_files array.
				$sync_tools->logFile( "JSON File Build (by {$user_loc}): starting remote json file creation." );
				$remote_files = $sync_tools->get_file_list( WPEZ_UPLOADS_DIR, WPEZ_SYSTEM_URL, $timestamp );
				$wpez_hash    = ( file_exists( $files_dir . WPEZ_SYSTEM_URL . '_file_array.json' ) ) ? md5_file( $files_dir . WPEZ_SYSTEM_URL . '_file_array.json' ) : false;
				$sync_tools->logFile( "JSON File Build (by {$user_loc}): remote json file created at path: {$remote_files} -- hash: {$wpez_hash}" );
				// Return created database files name
				$sync_tools->logFile( "FINISHED: JSON File Build (by {$user_loc})" );
				$sync_tools->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" );
				// Return remote files array file name and md5 hash
				return new \WP_REST_Response(
					array(
						'status'  => 'Success',
						'message' => 'remote files json -- created successfully.',
						'data'    => $remote_files,
						'hash'    => $wpez_hash,
					),
					200
				);
			}
		} else {
			// if payload empty return a WordPress error.
			return new \WP_Error( 'request-empty', 'ERROR: Request was empty.', array( 'status' => 400 ) );
		}
	}

	// ╔═════════════════════════════╗
	// ║  ██████  ██    ██ ████████  ║
	// ║  ██   ██ ██    ██    ██     ║
	// ║  ██████  ██    ██    ██     ║
	// ║  ██      ██    ██    ██     ║
	// ║  ██       ██████     ██     ║
	// ╚═════════════════════════════╝
	/**
	 * Adds Ajax API Handlers for this plugin.
	 *
	 * @param WP_REST_Request $data Options for the function.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function wpez_sync_put( WP_REST_Request $data ) {
		$sync_tools = new Tools();
		$params     = $data->get_params(); //$sync_tools->logFile("fetch: -- params: ".print_r($params, true));
		if ( ! empty( $params ) ) {
			$wpez_payload    = ( ! empty( $params['data'] ) ) ? $params['data'] : false; //phpcs:ignore
			$wpez_data    = json_decode( trim( stripslashes( $wpez_payload ), '\'"' ), true ); //$sync_tools->logFile("handlers: wpez_data: {$wpez_data}");
			$dl_type      = $wpez_data['type'];
			$dl_encrypt   = $wpez_data['encrypt'];
			$dl_gzip      = $wpez_data['gzip'];
			$user_loc     = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
			// +---------------------------------------------------------------------------------+
			// | If requested type is data. clear out old dump of table, and generate a new one. |
			// +---------------------------------------------------------------------------------+
			if ( $dl_type === 'data' ) {
				

				// Return created database files name
				return new \WP_REST_Response(
					array(
						'status'  => 'Success',
						'message' => 'Database Table Uploaded Successfully.',
					),
					200
				);
			}
			// +------------------------------------------------------------------------------------+
			// | If requested type is file. clear out old json file object, and generate a new one. |
			// +------------------------------------------------------------------------------------+
			if ( $dl_type === 'files' ) {
				
				// Return remote files array file name and md5 hash
				return new \WP_REST_Response(
					array(
						'status'  => 'Success',
						'message' => 'remote files json -- created successfully.',
					),
					200
				);
			}
		} else {
			// if payload empty return a WordPress error.
			return new \WP_Error( 'request-empty', 'ERROR: Request was empty.', array( 'status' => 400 ) );
		}
	}

	// ╔═════════════════════════════════════════════════════════════════════════════════════════╗
	// ║  ██████   ██████  ██     ██ ███    ██ ██       ██████   █████  ██████  ███████ ██████   ║
	// ║  ██   ██ ██    ██ ██     ██ ████   ██ ██      ██    ██ ██   ██ ██   ██ ██      ██   ██  ║
	// ║  ██   ██ ██    ██ ██  █  ██ ██ ██  ██ ██      ██    ██ ███████ ██   ██ █████   ██████   ║
	// ║  ██   ██ ██    ██ ██ ███ ██ ██  ██ ██ ██      ██    ██ ██   ██ ██   ██ ██      ██   ██  ║
	// ║  ██████   ██████   ███ ███  ██   ████ ███████  ██████  ██   ██ ██████  ███████ ██   ██  ║
	// ╚═════════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * Download files from remote site using this function.
	 *
	 * @param WP_REST_Request $data Options for the function.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function wpez_sync_download( WP_REST_Request $data ) {
		$sync_tools = new Tools();
		$params     = $data->get_params(); //$sync_tools->logFile("downloader: -- params: ".print_r($params, true));
		if ( ! empty( $params ) ) {
			$dl_type    = ( ! empty( $params['type'] ) ) ? $params['type'] : false; //phpcs:ignore
			$dl_file    = ( ! empty( $params['file'] ) ) ? $params['file'] : false; //phpcs:ignore
			// for file names, we need to ensure we don't get characters converted in this process
			if ( strpos( $dl_file, 'base64:' ) === 0 ) {
				$dl_file = base64_decode( substr( $dl_file, 7 ) );
			}
			// get the default WordPress upload directory
			$dl_dir = WPEZ_UPLOADS_DIR; // default
			// Set directory for data request
			if ( $dl_type === 'data' ) {
				$dl_dir = $sync_tools->get_sync_dir( 'databases' );
			}
			// Set directory for json array request
			if ( $dl_type === 'json' ) {
				$dl_dir = $sync_tools->get_sync_dir( 'files' );
			}
			// Set directory for json array request
			if ( $dl_type === 'config' ) {
				$dl_dir = $sync_tools->get_sync_dir( 'config' );
			}
			// Set directory for file request
			if ( $dl_type === 'files' ) {
				$dl_dir = WPEZ_UPLOADS_DIR;
			}
			// double check for file existence in case the filename got hosed in the HTTP transfer
			$filePath = $dl_dir . $dl_file; //$sync_tools->logFile("downloader: -- filePath: ".print_r($filePath, true)); die;
			if ( ! file_exists( $filePath ) ) {
				return new \WP_REST_Response(
					array(
						'status'  => 'FAILED',
						'message' => 'File name not found: ' . $dl_file,
					),
					400
				);
				//return new \WP_Error('file-not-found', 'ERROR: File name not found: ' . $dl_file, array( 'status' => 400 ));
			}
			// prepare file for download via ajax
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . $dl_file . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Pragma: public' );
			$filesize = (int) filesize( $filePath );
			if ( $filesize > 0 ) {
				header( 'Content-Length: ' . $filesize );
			}
			ob_clean();
			flush();
			readfile( $filePath );
			$sync_tools->logFile( "downloader: preparing file: {$dl_file} size {$filesize}" );
			exit;
		} else {
			// if payload empty return a WordPress error.
			//return new \WP_REST_Response( array( 'status' => 'FAILED', 'message' => 'ERROR: Request was empty.'), 400 );
			return new \WP_Error( 'request-empty', 'ERROR: Request was empty.', array( 'status' => 400 ) );
		}
	}

	// ╔═════════════════════════════════════════════════════════════════════╗
	// ║  ██    ██ ██████  ██       ██████   █████  ██████  ███████ ██████   ║
	// ║  ██    ██ ██   ██ ██      ██    ██ ██   ██ ██   ██ ██      ██   ██  ║
	// ║  ██    ██ ██████  ██      ██    ██ ███████ ██   ██ █████   ██████   ║
	// ║  ██    ██ ██      ██      ██    ██ ██   ██ ██   ██ ██      ██   ██  ║
	// ║   ██████  ██      ███████  ██████  ██   ██ ██████  ███████ ██   ██  ║
	// ╚═════════════════════════════════════════════════════════════════════╝
	/**
	 * Upload files from local site using this function.
	 *
	 * @param WP_REST_Request $data Options for the function.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function wpez_sync_upload( WP_REST_Request $data ) {
		$sync_tools = new Tools();
		$params     = $data->get_params(); //$sync_tools->logFile("downloader: -- params: ".print_r($params, true));
		if ( ! empty( $params ) ) {
			$ul_type    = ( ! empty( $params['type'] ) ) ? $params['type'] : false; //phpcs:ignore
			$ul_file    = ( ! empty( $params['file_name'] ) ) ? $params['file_name'] : false; //phpcs:ignore
			// for file names, we need to ensure we don't get characters converted in this process
			if ( strpos( $ul_file, 'base64:' ) === 0 ) {
				$ul_file = base64_decode( substr( $ul_file, 7 ) );
			}
			// get the default WordPress upload directory
			$ul_dir = WPEZ_UPLOADS_DIR; // default
			// Set directory for data request
			if ( $ul_type === 'data' ) {
				$ul_dir = $sync_tools->get_sync_dir( 'databases' );
			}
			// Set directory for json array request
			if ( $ul_type === 'json' ) {
				$ul_dir = $sync_tools->get_sync_dir( 'files' );
			}
			// Set directory for json array request
			if ( $ul_type === 'config' ) {
				$ul_dir = $sync_tools->get_sync_dir( 'config' );
			}
			// Set directory for file request
			if ( $ul_type === 'files' ) {
				$ul_dir = WPEZ_UPLOADS_DIR;
			}
			// decode file and prepare to save on this server.
			if (empty($_FILES['file'])) {
				return new WP_REST_Response(['error' => 'No file uploaded'], 400);
			}

			$file = $_FILES['file'];

			if ($file['error'] !== UPLOAD_ERR_OK) {
				return new WP_Error('upload-error', 'File upload error: ' . $file['error'], ['status' => 400]);
			}
			// Ensure the upload directory exists
			if ( ! file_exists( $ul_dir ) ) {
				if ( ! mkdir( $ul_dir, 0755, true ) ) {
					return new WP_Error( 'upload-error', 'Failed to create upload directory.', array( 'status' => 500 ) );
				}
			}
			// Move the uploaded file to the target directory
			$target_file = $ul_dir . basename( $ul_file );
			$sync_tools->logFile( 'target_file: ' . print_r( $target_file, true ), 'wpez-debug.log' );
			if ( ! move_uploaded_file( $file['tmp_name'], $target_file ) ) {
				return new WP_Error( 'upload-error', 'Failed to move uploaded file.', array( 'status' => 500 ) );
			}
			// Return the URL of the uploaded file
			return new WP_REST_Response([
				'success' => true,
			]);
		} else {
			// if payload empty return a WordPress error.
			//return new \WP_REST_Response( array( 'status' => 'FAILED', 'message' => 'ERROR: Request was empty.'), 400 );
			return new \WP_Error( 'request-empty', 'ERROR: Request was empty.', array( 'status' => 400 ) );
		}
	}
}

$Sync = new Sync();

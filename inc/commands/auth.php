<?php
/**
 * CLI Auth Command
 *
 * This command will run Auth function.
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
// ║   █████  ██    ██ ████████ ██   ██      ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║  ██   ██ ██    ██    ██    ██   ██     ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║  ███████ ██    ██    ██    ███████     ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║  ██   ██ ██    ██    ██    ██   ██     ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║  ██   ██  ██████     ██    ██   ██      ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Auth' ) ) {
	/**
	 * Class Auth Command
	 */
	class Auth extends WP_CLI_Command {

		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Auth Command is used to authenticate the request against the remote server.
		 * Saves auth token in wp transient for easy retrieval in subsequent requests.
		 * Function returns auth token to the requesting command for use elsewhere.
		 *
		 * ## OPTIONS
		 *
		 * --live --wp
		 * Flags for what server to pull from.  Either live or wp are defaults, but you can add additional remotes in the WPEZ Sync settings.
		 *
		 * --refresh
		 * if set this flag will force the generation of a new auth token and transient.
		 *
		 * ## EXAMPLES
		 *
		 * GET NONCE EXAMPLE:
		 * wp ez auth --live
		 *
		 * @subcommand auth
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

			// get the status of the refresh flag
			$refresh = $args_assoc['refresh'] ?? false;

			// CHECK FOR EXISTING TOKEN BEFORE PROCEEDING TO MAKE A NEW ONE
			$auth_token = get_transient( 'wpez_auth_token' ) ?: false;
			if ( ! empty( $auth_token ) && empty( $refresh ) ) {
				//$sync_tools->logFile("auth: -- auth_token (from transient): ".print_r($auth_token, true));
				echo esc_html( $auth_token );
				return;
			}

			// LOAD VARS AND ARGS
			$get_remote = $sync_tools->verify_remote( $args_assoc );
			$remote_uri = '/wp-json/wpez/v1/auth/';
			$remote_env = $get_remote['env'] ?? '';
			$remote_tag = $get_remote['tag'] ?? '';
			$remote_url = $get_remote['url'] ?? '';
			$server     = trim( $remote_url ) . $remote_uri;

			// SETUP CURL DATA OBJECT
			$ch_data                = array();
			$ch_payload             = array();
			$username               = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
			$ch_payload['username'] = $username;
			$ch_payload['passkey']  = $sync_tools->encrypt( $username );
			$ch_data['data']        = json_encode( $ch_payload );

			// SETUP CURL COMMAND TO POST TO REMOTE SERVER.
			$ch = $sync_tools->setup_curl_post( $ch_data, $server );
			if ( $ch ) {
				$response = curl_exec( $ch );
				$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
				curl_close( $ch );
				$decoded = json_decode( $response, true );
			} else {
				WP_CLI::error( 'CURL Fetch Failed.' );
			}

			// CHECK FOR ERRORS
			if ( ! empty( $decoded['status'] ) && $status == '400' && $decoded['status'] == 'Failed' ) {
				echo esc_html( $decoded['message'] );
			}
			// IF SUCCESS RETURN APPROPRIATE DATA.
			if ( ! empty( $decoded['status'] ) && $status == '200' && $decoded['status'] == 'Success' ) {
				$nonce      = $sync_tools->decrypt( $decoded['output'] );
				$json_token = json_encode(
					array(
						'username' => $username,
						'nonce'    => $nonce,
					)
				);
				$auth_token = $sync_tools->encrypt( $json_token );
				set_transient( 'wpez_auth_token', $auth_token, 3 * HOUR_IN_SECONDS );
				//$sync_tools->logFile("auth: -- auth_token (freshly made): ".print_r($auth_token, true));
				echo esc_html( $auth_token );
			}
		}
	}
}

WP_CLI::add_command( 'ez auth', __NAMESPACE__ . '\\Auth' );

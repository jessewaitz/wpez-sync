<?php
/**
 * CLI Backup Command
 *
 * This command will run Backup function.
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

// ╔═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╗
// ║  ██████   █████   ██████ ██   ██ ██    ██ ██████       ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║  ██   ██ ██   ██ ██      ██  ██  ██    ██ ██   ██     ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║  ██████  ███████ ██      █████   ██    ██ ██████      ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║  ██   ██ ██   ██ ██      ██  ██  ██    ██ ██          ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║  ██████  ██   ██  ██████ ██   ██  ██████  ██           ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Backup' ) ) {
	/**
	 * Class Backup Command
	 */
	class Backup extends WP_CLI_Command {

		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Backup Command allows admins to setup crons on remote to run backups on a regular basis.
		 * This allows the get commands to use the --stored flag to download the stored files instead of running fresh export.
		 *
		 * ## OPTIONS
		 *
		 * <sub-command>
		 * What to backup locally? -- [data]
		 *
		 * --[no-]encrypt
		 * If set the downloaded files will be encrypted with aes file encryption, before you download them. If you enter --no-encrypt then no encryption will be added. Note: You cannot encrypte and gzip the same file. Default is no encryption.
		 *
		 * --[no-]gzip
		 * If set the downloaded files will be gzipped, before you download them. If you enter --no-gzip then no zipping will happen. Note: You cannot encrypte and gzip the same file. Default is no zipping.
		 *
		 * ## EXAMPLES
		 *
		 * BACKUP EXAMPLES
		 * wp ez backup data
		 *
		 * @subcommand backup
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

			// SETUP ARGS, ASSOCIATED ARGS, AND OPTIONS
			$type = $args[0] ?? false;

			// SETUP ARGS, ASSOCIATED ARGS, AND OPTIONS
			$encrypt    = isset( $args_assoc['encrypt'] ) ? $args_assoc['encrypt'] : false; // Encrypt the files before download. Always false unless the '--[no]' flag is set.
			$gzip       = isset( $args_assoc['gzip'] ) ? $args_assoc['gzip'] : false; // GZip the files before download. Always false unless the '--[no]' flag is set.

			// SETUP GLOBAL VARIBLES.
			$tables     = array();
			$db_dir     = $sync_tools->get_sync_dir( 'databases' );
			$system_tag = WPEZ_SYSTEM_FLG;
			$user_loc   = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
			$options    = array( 'return' => true, 'parse' => false, 'launch' => false, 'exit_error' => true, ); //phpcs:ignore

			// IF DATA TYPE BACKUP THE DATA LOCALLY
			if ( $type === 'data' ) {
				// SETUP VARIABLES
				$all_tables = WP_CLI::runcommand( 'ez fetch tables --' . $system_tag, $options );
				if ( substr( $all_tables, 0, 7 ) === 'ERROR: ' ) {
					$message = str_replace( 'ERROR: ', '', $all_tables );
					//$sync_tools->notify($sync_tools->format_message( $message, 'error' ), 'error');
					WP_CLI::error( $message );
				}
				$tables = json_decode( $all_tables );
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
				as_schedule_single_action( time(), 'do_wpez_db_export', array( $db_dir, $encrypt, $gzip, $tables ), 'wpez', true, 0 );
			}
		}
	}
}

WP_CLI::add_command( 'ez backup', __NAMESPACE__ . '\\Backup' );

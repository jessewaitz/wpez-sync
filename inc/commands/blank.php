<?php
/**
 * CLI Blank Command
 *
 * This command will run blank function.
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
// ║  ██   ██ ██   ██ ██   ██ ██   ██        ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
// ║   ██ ██   ██ ██   ██ ██   ██ ██        ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
// ║    ███     ███     ███     ███         ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
// ║   ██ ██   ██ ██   ██ ██   ██ ██        ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
// ║  ██   ██ ██   ██ ██   ██ ██   ██        ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
// ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════╝
if ( ! class_exists( 'Blank' ) ) {
	/**
	 * Class Blank Command
	 */
	class Blank extends WP_CLI_Command {
		/**
		 * Constructor for cli command -- checks for memory over-usage
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Blank command, used for running blank before adding to the actual commands.
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
			echo 'blank'; 
			die;
		}
	}
}

WP_CLI::add_command( 'ez blank', __NAMESPACE__ . '\\Blank' );

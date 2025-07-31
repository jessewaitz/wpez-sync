<?php
/**
 * WPEZ Sync CLI Command Class
 *
 * This file contains the CLI command implementation for the WPEZ Sync plugin.
 *
 * @package    WPEZ Sync Plugin
 * @subpackage CLI
 * @author     WPEZtools.com
 */

namespace WPEZ\Sync;

// Only proceed if WP-CLI is available
if ( ! defined( 'WP_CLI' ) || ! class_exists( 'WP_CLI' ) ) {
	return;
}

use WP_CLI;
use WP_CLI_Command;

defined( 'ABSPATH' ) || die( 'No direct access!' );

// HIDE ALL PHP ERRORS FOR COMMAND LINE RUNS
//error_reporting(0);

/**
 * WPEZ Sync main command class - extends the WP_CLI_Command
 */
class CLI extends WP_CLI_Command {
	// ╔══════════════════════════════════════════════════════════════════════════════════════════════════════════╗
	// ║  ██     ██ ██████  ███████ ███████      ██████  ██████  ███    ███ ███    ███  █████  ███    ██ ██████   ║
	// ║  ██     ██ ██   ██ ██         ███      ██      ██    ██ ████  ████ ████  ████ ██   ██ ████   ██ ██   ██  ║
	// ║  ██  █  ██ ██████  █████     ███       ██      ██    ██ ██ ████ ██ ██ ████ ██ ███████ ██ ██  ██ ██   ██  ║
	// ║  ██ ███ ██ ██      ██       ███        ██      ██    ██ ██  ██  ██ ██  ██  ██ ██   ██ ██  ██ ██ ██   ██  ║
	// ║   ███ ███  ██      ███████ ███████      ██████  ██████  ██      ██ ██      ██ ██   ██ ██   ████ ██████   ║
	// ╚══════════════════════════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * Constructor for cli command -- checks for memory over-usage
	 */
	public function __construct() {
		parent::__construct();
		$memory_limit = (int) ini_get( 'memory_limit' );
		if ( $memory_limit > 0 && $memory_limit < 1000000000 ) {
			ini_set( 'memory_limit', '1G' ); // phpcs:ignore
		}
	}

}

WP_CLI::add_command( 'ez', __NAMESPACE__ . '\\CLI' );

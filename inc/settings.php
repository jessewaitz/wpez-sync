<?php
/**
 * WPEZ Sync Plugin Settings Class
 *
 * Handles the settings admin for the WPEZ Sync Plugin.
 *
 * @package WPEZ\Sync
 * @author  WPEZtools.com
 */

namespace WPEZ\Sync;

/**
 * This class contains the settings admin for the Sync Plugin.
 */
class Settings {

	// ╔═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╗
	// ║  ███████ ███    ██  ██████  ██    ██ ███████ ██    ██ ███████      █████  ███████ ███████ ███████ ████████ ███████  ║
	// ║  ██      ████   ██ ██    ██ ██    ██ ██      ██    ██ ██          ██   ██ ██      ██      ██         ██    ██       ║
	// ║  █████   ██ ██  ██ ██    ██ ██    ██ █████   ██    ██ █████       ███████ ███████ ███████ █████      ██    ███████  ║
	// ║  ██      ██  ██ ██ ██ ▄▄ ██ ██    ██ ██      ██    ██ ██          ██   ██      ██      ██ ██         ██         ██  ║
	// ║  ███████ ██   ████  ██████   ██████  ███████  ██████  ███████     ██   ██ ███████ ███████ ███████    ██    ███████  ║
	// ╚═════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @return void
	 */
	public function wpez_sync_enqueue_admin_styles() {
		//The Lsn_Comments_Loader will setup the relationship between the defined hooks and the functions defined in this class.
		wp_enqueue_style( 'wpez-sync-admin-css', plugin_dir_url( __DIR__ ) . 'assets/wpez-sync-admin.css', array(), WPEZ_SYNC_VERSION.WPEZ_SYNC_CACHE_BUSTER );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @return void
	 */
	public function wpez_sync_enqueue_admin_scripts() {
		// The Lsn_Comments_Loader will setup the relationship between the defined hooks and the functions defined in this class.
		wp_enqueue_script( 'ace-editor', 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.4/ace.js', array(), '1.32.4', true );
		wp_enqueue_script( 'wpez-sync-admin-js', plugin_dir_url( __DIR__ ) . 'assets/wpez-sync-admin.js', array( 'ace-editor' ), WPEZ_SYNC_VERSION.WPEZ_SYNC_CACHE_BUSTER, true );
	}

	// ╔═══════════════════════════════════════════════════════════════════╗
	// ║  ███████ ███████ ████████ ████████ ██ ███    ██  ██████  ███████  ║
	// ║  ██      ██         ██       ██    ██ ████   ██ ██       ██       ║
	// ║  ███████ █████      ██       ██    ██ ██ ██  ██ ██   ███ ███████  ║
	// ║       ██ ██         ██       ██    ██ ██  ██ ██ ██    ██      ██  ║
	// ║  ███████ ███████    ██       ██    ██ ██   ████  ██████  ███████  ║
	// ╚═══════════════════════════════════════════════════════════════════╝

	/**
	 * Register the settings link on the plugins listing for the wpez-sync settings page
	 *
	 * @param array $links The existing links for the plugin.
	 * @return array $links The modified links for the plugin.
	 */
	public function wpez_sync_action_links( $links ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=wpez_sync_settings' ) . '">' . __( 'Settings' ) . '</a>';
		return $links;
	}

	/**
	 * Create the WPEZ Settings Page Sections and fields
	 */
	public function wpez_sync_settings_init() {
		// Register our settings so that $_POST handling is done for us and our callback function just has to echo the <input>
		register_setting( 'wpez_sync_settings_fields', 'wpez_sync_settings', array( 'sanitize_callback' => array( $this, 'wpez_sync_setting_validate' ) ) );
	}

	/**
	 * Create the WPEZ Settings Page
	 */
	public function wpez_sync_add_settings_page() {
		add_options_page( 'WPEZtools Sync', 'WPEZtools Sync', 'manage_options', 'wpez_sync_settings', array( $this, 'wpez_sync_settings_form' ) );
	}

	/**
	 * Create the WPEZ Settings Page Form
	 */
	public function wpez_sync_settings_form() {
		// reference link: https://wordpress.stackexchange.com/questions/365980/wordpress-settings-api-repeatable-fields
		// reference link: https://gist.github.com/DavidWells/4653358
		$wpez_sync_settings = get_option( 'wpez_sync_settings' ); // live|https://wpeztools.com\ndev|https://dev.wpeztools.com
		//echo '<pre>'; print_r($wpez_sync_settings); echo '</pre>';
		?>
		<div>
			<form action="options.php" method="post">
				<?php settings_fields( 'wpez_sync_settings_fields' ); ?>

				<div id="tabs">
					<h2 id="nav-tab-wrapper">
						<a href="#tab-1" data-ref="tab-1" class="nav-tab">Connection</a>
						<a href="#tab-2" data-ref="tab-2" class="nav-tab">Scripts & Lists</a>
						<a href="#tab-3" data-ref="tab-3" class="nav-tab">Import/Export</a>
						<a href="#tab-4" data-ref="tab-4" class="nav-tab">Keys &amp; Tokens</a>
					</h2>
					<hr/>
					<div id="tab-1" class="tab-content">
						<h3>Connection</h3>
						<table class="form-table">
							<tbody>
								<tr valign="top">
									<th scope="row">This Location:</th>
									<td>
										<p>
											<strong>User:</strong> <?php echo esc_html( WPEZ_SYSTEM_USR ); ?> &nbsp;&bull;&nbsp;
											<strong>URL:</strong> <?php echo esc_html( WPEZ_SYSTEM_URL ); ?> &nbsp;&bull;&nbsp; 
											<strong>Flag:</strong> <?php echo esc_html( WPEZ_SYSTEM_FLG ); ?> &nbsp;&bull;&nbsp; 
											<strong>Location:</strong> <?php echo esc_html( WPEZ_SYSTEM_LOC ); ?>
										</p>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">Sync Locations:</th>
									<td>
										<table class="wpez-location-headers">
											<tr>
												<td>Flag</td>
												<td>Location Url</td>
												<td>Type</td>
											</tr>
										</table>
										<ul id="remote_list" >
											<?php
											$wpez_sync_remotes = ( ! empty( $wpez_sync_settings['remotes'] ) && is_array( $wpez_sync_settings['remotes'] ) ) ? array_values( $wpez_sync_settings['remotes'] ) : array(
												'0' => array(
													'flag' => '',
													'url'  => '',
													'location' => '',
												),
											);
											foreach ( $wpez_sync_remotes as $key => $remote ) {
												?>
												<li data-key="<?php echo esc_attr( $key ); ?>">
													<input type="text" name="wpez_sync_settings[remotes][<?php echo esc_attr( $key ); ?>][flag]" value="<?php echo esc_attr( $remote['flag'] ); ?>" placeholder="ie. live" size="6"/>
													<input type="text" name="wpez_sync_settings[remotes][<?php echo esc_attr( $key ); ?>][url]" value="<?php echo esc_attr( $remote['url'] ); ?>" placeholder="ie. https://wpeztools.com" size="30"/>
													<select name="wpez_sync_settings[remotes][<?php echo esc_attr( $key ); ?>][location]">
														<option value="">Select</option>
														<option value="remote"
														<?php
														if ( esc_attr( $remote['location'] ) === 'remote' ) {
															echo ' selected="selected"'; }
														?>
														>Remote</option>
														<option value="local"
														<?php
														if ( esc_attr( $remote['location'] ) === 'local' ) {
															echo ' selected="selected"'; }
														?>
														>Local</option>
													</select>
													<a class="dashicons dashicons-trash" onClick="remove_remote(<?php echo esc_attr( $key ); ?>)"></a>
												</li>
											<?php } ?>
										</ul>
										<input type="button" onClick="add_new_remote()" class="button button-secondary" value="add new remote">
										<br/><span style="font-size:12px; font-style:italic;">Locations should include all instances of this same web site. The live server, your <br/>local installation, and any staging servers. The flag is the short name that will be <br/>used in the wp-cli for designating what remote to connect to (ie. --live). The URL <br/>is the fully qualified URL of the location.</span>
									</td>
								</tr>
							</tbody>	
						</table>
					</div>
					<div id="tab-2" class="tab-content">
						<h3>Scripts & Lists</h3>
						<table class="form-table">
							<tbody>
								<tr valign="top">
									<th scope="row">Bash Scripts: <br/><span style="font-weight: normal;">These scripts will be run after the the sync operations are complete. Please enter one command per line.</span></th>
									<td>
										<?php
										// https://codepen.io/jesse-waitz/pen/YzgYRJv
										// https://ace.c9.io/build/kitchen-sink.html
										$wpez_sync_bash_scripts = $wpez_sync_settings['bash_scripts'] ?? '';
										echo '<textarea id="wpez_sync_setting_bash_scripts" name="wpez_sync_settings[bash_scripts]" data-editor="bash" data-gutter="1" rows="5" cols="50">' . $wpez_sync_bash_scripts . '</textarea>'; //phpcs:ignore
										?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">File Sync Exceptions: <br/><span style="font-weight: normal;">These file and folders will be ignored during any file sync processes. Please enter one file or folder name per line.</span></th>
									<td>
										<?php
										// https://codepen.io/jesse-waitz/pen/YzgYRJv
										// https://ace.c9.io/build/kitchen-sink.html
										$wpez_sync_file_exceptions = $wpez_sync_settings['file_exceptions'] ?? '';
										echo '<textarea id="wpez_sync_setting_file_exceptions" name="wpez_sync_settings[file_exceptions]" data-editor="bash" data-gutter="1" rows="5" cols="50">' . $wpez_sync_file_exceptions . '</textarea>'; //phpcs:ignore
										?>
									</td>
								</tr>
							</tbody>	
						</table>
					</div>
					<div id="tab-3" class="tab-content">
						<h3>Import/Export</h3>
						<table class="form-table">
							<tbody>
								<tr valign="top">
									<th scope="row">Import Configuration: <br/><span style="font-weight: normal;">Use this form to upload a JSON config file from another system.</span></th>
									<td>
										<?php
										// https://codepen.io/jesse-waitz/pen/YzgYRJv
										// https://ace.c9.io/build/kitchen-sink.html
										$wpez_sync_json_config = $wpez_sync_settings['json_config'] ?? '';
										echo '<textarea id="wpez_sync_setting_json_config" name="wpez_sync_settings[json_config]" data-editor="json" data-gutter="1" rows="10" cols="50">' . $wpez_sync_json_config . '</textarea>'; //phpcs:ignore
										?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">Export Configuration: <br/><span style="font-weight: normal;">Click this button to download this site's configuration file.</span></th>
									<td>
										<?php
										$sync_tools = new Tools();
										$auth_token = $sync_tools->create_local_auth_token();
										$server_dl  = home_url() . '/wp-json/wpez/v1/download/';
										$ch_url     = $server_dl . '?auth_token=' . urlencode( $auth_token ) . '&type=config&file=wpez_config.json';
										echo '<a class="button button-secondary" href="' . esc_url( $ch_url ) . '">Download Configuration</a>';
										?>
									</td>
								</tr>
							</tbody>	
						</table>
					</div>
					<div id="tab-4" class="tab-content">
						<h3>Keys &amp; Tokens</h3>
						<table class="form-table">
							<tbody>
								<tr valign="top">
									<th scope="row">Secret Key:</th>
									<td>
										<?php
										$wpez_sync_secret_key = $wpez_sync_settings['secret_key'] ?? '';
										echo "<textarea id='wpez_sync_setting_key' name='wpez_sync_settings[secret_key]' rows='3' cols='50'>" . $wpez_sync_secret_key . '</textarea>'; //phpcs:ignore
										echo '<br/><span style="font-size:12px; font-style:italic;">Keys need to be random and long. We recommend at least 64 characters<br/>long alphanumeric and should include special characters. We recommend<br/>using the wordpress salt generator: https://api.wordpress.org/secret-key/1.1/salt/</span>';
										?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">Secret Salt:</th>
									<td>
										<?php
										$wpez_sync_secret_salt = $wpez_sync_settings['secret_salt'] ?? '';
										echo "<textarea id='wpez_sync_setting_salt' name='wpez_sync_settings[secret_salt]' rows='3' cols='50'>" . $wpez_sync_secret_salt . '</textarea>'; //phpcs:ignore
										echo '<br/><span style="font-size:12px; font-style:italic;">Salts need to be random and long. We recommend at least 64 characters<br/>long alphanumeric and should include special characters. We recommend<br/>using the wordpress salt generator: https://api.wordpress.org/secret-key/1.1/salt/</span>';
										?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">GitHub Access Token:</th>
									<td>
										<?php
										$wpez_sync_github_access_token = $wpez_sync_settings['github_access_token'] ?? '';
										echo "<input type='text' id='wpez_sync_setting_github_access_token' name='wpez_sync_settings[github_access_token]' class='regular-text' value='" . esc_attr( $wpez_sync_github_access_token ) . "' />"; //phpcs:ignore
										echo '<br/><span style="font-size:12px; font-style:italic;">GitHub Access Tokens are generated at your github account at this<br/>URL: https://github.com/settings/tokens</span>';
										?>
									</td>
								</tr>
							</tbody>	
						</table>
					</div>
				</div>

				<input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" /><br/><br/>WPEZ v.<?php echo esc_html( WPEZ_SYNC_VERSION ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * This function will take the settings saved and allow us to validate the input.
	 * Also will be used to save the setting info to a json file in the config folder
	 *
	 * @param array|string $settings the save values of the settings array.
	 *
	 * @return array|string $settings the sanitized values of the input
	 */
	public function wpez_sync_setting_validate( $settings ) {
		$sync_tools = new Tools(); //$sync_tools->logFile("validate settings: input: ".print_r($settings, true));
		// if the configuration import is entered, need to validate, sanitize,
		// and overwrite the settings with the imported configuration.
		if ( ! empty( $settings['json_config'] ) ) {
			$settings                = $this->import_settings( $settings['json_config'], $settings );
			$settings['json_config'] = '';
		} else {
			$settings = map_deep( $settings, 'sanitize_textarea_field' );
		}
		// save the inputs in the json config file.
		$config_dir    = $sync_tools->get_sync_dir( 'config' );
		$settings_json = json_encode( $settings, JSON_PRETTY_PRINT );
		//$sync_tools->logFile("validate settings: input: ".print_r($settings, true));
		file_put_contents( $config_dir . 'wpez_config.json', $settings_json );
		// read the url of the remotes array, and if any of the urls have a trailing slash remove the slash.
		if ( ! empty( $settings['remotes'] ) && is_array( $settings['remotes'] ) ) {
			foreach ( $settings['remotes'] as $key => $remote ) {
				if ( ! empty( $remote['url'] ) ) {
					$settings['remotes'][ $key ]['url'] = rtrim( $remote['url'], '/' );
				}
			}
		}
		// return the validated and saved configuration.
		return $settings;
	}

	/**
	 * This function processes the imported json settings data. Function tests if the array is
	 * properly formatted, sanitizes the input, and only replaces data that is new in the import.
	 *
	 * @param string $json_data JSON data that is meant to be imported.
	 * @param array  $settings array of settings to be validated.
	 *
	 * @return array
	 */
	public function import_settings( $json_data, $settings ): array {
		$sync_tools        = new Tools();
		$imported_settings = json_decode( $json_data, true );
		if ( ! empty( $imported_settings ) ) {
			$sanitized_settings = map_deep( $imported_settings, 'sanitize_textarea_field' );

			foreach ( $sanitized_settings as $key => $value ) {
				if ( ! empty( $key ) && ! empty( $value ) ) {
					if ( $key === 'remote' ) {
						if ( ! empty( $value ) ) {
							foreach ( $value as $remote_key => $remote_val ) {
								//$sync_tools->logFile("validate remote_key: {$remote_key} -- remote_val: {$remote_val}");
								$settings['remote'][ $remote_key ] = $remote_val ?: $settings['remote'][ $remote_key ];
							}
						}
					} else {
						$settings[ $key ] = $value ?: $settings[ $key ];
					}
				}
			}
		}

		return $settings;
	}
}

<?php
/**
 * Tools class for WPEZ Sync Plugin.
 *
 * Provides utility functions for file operations, encryption, database dumps, and more.
 *
 * @package WPEZ\Sync
 * @author  WPEZtools.com
 */

namespace WPEZ\Sync;

use WP_CLI;
use WP_CLI_Command;
use Ifsnop\Mysqldump as IMysqldump;

/**
 * This class contains tools that will be used by other parts of the Sync Plugin
 */
class Tools {

	// ╔══════════════════════════════════════════════════════════════════════════════════╗
	// ║   ██████ ██    ██ ██████  ██          ███████ ███████ ████████ ██    ██ ██████   ║
	// ║  ██      ██    ██ ██   ██ ██          ██      ██         ██    ██    ██ ██   ██  ║
	// ║  ██      ██    ██ ██████  ██          ███████ █████      ██    ██    ██ ██████   ║
	// ║  ██      ██    ██ ██   ██ ██               ██ ██         ██    ██    ██ ██       ║
	// ║   ██████  ██████  ██   ██ ███████     ███████ ███████    ██     ██████  ██       ║
	// ╚══════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * Sets up the curl post parameters for communicating with the ajax handlers
	 *
	 * @param array  $ch_data -- data to be posted.
	 * @param string $server -- url of server to post to.
	 *
	 * @return \CurlHandle|resource|false
	 */
	public function setup_curl_post( $ch_data, $server ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $server );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 1000 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $ch_data );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // Skip SSL Verification
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'User-Agent: WPEZ-Sync ' . WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL ) );

		return $ch;
	}

	/**
	 * Sets up the curl get parameters for downloading files
	 *
	 * @param string $ch_url url of file to download.
	 * @param string $fp path to where to download the file to.
	 *
	 * @return \CurlHandle|resource|false
	 */
	public function setup_curl_get( $ch_url, $fp ) {
		$ch = curl_init( $ch_url );
		curl_setopt( $ch, CURLOPT_FILE, $fp );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'User-Agent: WPEZ-Sync ' . WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // Skip SSL Verification
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );

		return $ch;
	}

	/**
	 * Sets up the curl get parameters for downloading files and returns the whole file in one big string
	 *
	 * @param string $ch_url url of file to download.
	 *
	 * @return \CurlHandle|resource|false
	 */
	public function setup_curl_get_data( $ch_url ) {
		$ch = curl_init( $ch_url );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'User-Agent: WPEZ-Sync ' . WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL ) );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // Skip SSL Verification
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		return $ch;
	}


	// ╔══════════════════════════════════════════════════════════════════════════════════════════════╗
	// ║  ███    ██  ██████  ███    ██  ██████ ███████     ███████ ███████ ████████ ██    ██ ██████   ║
	// ║  ████   ██ ██    ██ ████   ██ ██      ██          ██      ██         ██    ██    ██ ██   ██  ║
	// ║  ██ ██  ██ ██    ██ ██ ██  ██ ██      █████       ███████ █████      ██    ██    ██ ██████   ║
	// ║  ██  ██ ██ ██    ██ ██  ██ ██ ██      ██               ██ ██         ██    ██    ██ ██       ║
	// ║  ██   ████  ██████  ██   ████  ██████ ███████     ███████ ███████    ██     ██████  ██       ║
	// ╚══════════════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * This function is a replacement of the wp_create_nonce.  It uses our private key and salt for randomness, has a time that expires after 12 hours
	 * and uses hash_mac to create a salted and hashed nonce that is unique to every connection.
	 *
	 * @param string $action defaults to lsn_subscribe but can be used for any string.
	 *
	 * @return array $nonces this array contains nonces for both the current and previous ticks
	 */
	public function create_nonce( $action = 'wpez_sync' ): array {
		// get session id from php or default to random string
		$private_key = ( defined( 'WPEZ_SECRET_KEY' ) && ! empty( WPEZ_SECRET_KEY ) ) ? WPEZ_SECRET_KEY : md5( $action . '|secret_key' );
		// get wp nonce salt
		$salt = ( defined( 'WPEZ_SECRET_SALT' ) && ! empty( WPEZ_SECRET_SALT ) ) ? WPEZ_SECRET_SALT : md5( $action . '|secret_salt' );
		// get the UTC unix epoch for right now.
		$now = time();
		// get tick that last for 12 hours
		$timer_now = (int) ( $now / 43200 );
		// get the tick for the previous 12 hours
		$timer_prev = $timer_now - 1;
		// create an array of the nonces for both the current and previous ticks.
		$nonces         = array();
		$nonces['now']  = substr( hash_hmac( 'md5', $action . '|' . $private_key . '|' . $timer_now, $salt ), -12, 10 );
		$nonces['prev'] = substr( hash_hmac( 'md5', $action . '|' . $private_key . '|' . $timer_prev, $salt ), -12, 10 );
		// $this->logFile("action: {$action} -- private_key: {$private_key} -- timer_now: {$timer_now} -- salt: {$salt} -- lsn_nonces: ".print_r($nonces,true));
		// return nonce array.
		return $nonces;
	}

	/**
	 * This function verifies the nonce that is generated by the create_nonce function
	 * It first checks to see if the submitted nonce matches the current tick.  If not, it checks
	 * the previous tick. Returns true if either tests passes, and false if both checks fail.
	 *
	 * @param array  $nonces The the current nonce array to verify against.
	 * @param string $check_nonce The submitted nonce that needs to be checked.
	 *
	 * @return bool
	 */
	public function verify_nonce( $nonces, $check_nonce ): bool {
		// if submitted nonce matches the current nonce return true
		if ( $nonces['now'] === $check_nonce ) {
			return true;
		}
		// if submitted nonce matches the previous nonce return true
		if ( $nonces['prev'] === $check_nonce ) {
			return true;
		}
		// if fails both checks return false
		return false;
	}

	/**
	 * This function creates an auth token that can be used for local rest api calls.
	 * Specifically if the settings UI wants to download a config file.
	 *
	 * @return string
	 */
	public function create_local_auth_token(): string {
		$username   = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
		$nonce      = $this->create_nonce( $username );
		$json_token = json_encode(
			array(
				'username' => $username,
				'nonce'    => $nonce['now'],
			)
		);
		$auth_token = $this->encrypt( $json_token );
		return $auth_token;
	}

	// ╔═════════════════════════════════════════════════════════════════════════════════════╗
	// ║  ███████ ███    ██  ██████ ██████  ██    ██ ██████  ████████ ██  ██████  ███    ██  ║
	// ║  ██      ████   ██ ██      ██   ██  ██  ██  ██   ██    ██    ██ ██    ██ ████   ██  ║
	// ║  █████   ██ ██  ██ ██      ██████    ████   ██████     ██    ██ ██    ██ ██ ██  ██  ║
	// ║  ██      ██  ██ ██ ██      ██   ██    ██    ██         ██    ██ ██    ██ ██  ██ ██  ║
	// ║  ███████ ██   ████  ██████ ██   ██    ██    ██         ██    ██  ██████  ██   ████  ║
	// ╚═════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * This function will encrypt a text string using the secret key.
	 * Refernce Link: https://dev.to/__manucodes/really-simple-encryption-in-php-3kk9
	 *
	 * @param  string $data unencrypted string of text.
	 * @return string $ciphertext encrypted token.
	 */
	public function encrypt( $data ) {
		$key            = WPEZ_SECRET_KEY;
		$plaintext      = $data;
		$cipher         = 'AES-128-CBC';
		$ivlen          = openssl_cipher_iv_length( $cipher );
		$iv             = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt( $plaintext, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv );
		$hmac           = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
		$ciphertext     = base64_encode( $iv . $hmac . $ciphertext_raw );
		return $ciphertext;
	}

	/**
	 * This function will decrypt a text string using the secret key.
	 * Refernce Link: https://dev.to/__manucodes/really-simple-encryption-in-php-3kk9
	 *
	 * @param  string $data encrypted token.
	 * @return string|bool $ciphertext decrypted string of text.
	 */
	public function decrypt( $data ) {
		$key                = WPEZ_SECRET_KEY;
		$c                  = base64_decode( $data );
		$cipher             = 'AES-128-CBC';
		$ivlen              = openssl_cipher_iv_length( $cipher );
		$iv                 = substr( $c, 0, $ivlen );
		$hmac               = substr( $c, $ivlen, $sha2len = 32 );
		$ciphertext_raw     = substr( $c, $ivlen + $sha2len );
		$original_plaintext = openssl_decrypt( $ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv );
		$calcmac            = hash_hmac( 'sha256', $ciphertext_raw, $key, $as_binary = true );
		if ( hash_equals( $hmac, $calcmac ) ) {
			return $original_plaintext;
		} else {
			return false;
		}
	}

	/**
	 * Function for encrypting a file, in small chuncks.
	 * Referenclink: https://medium.com/@antoine.lame/how-to-encrypt-files-with-php-f4adead297de
	 *
	 * @param string $file  Name of the file to be encrypted.
	 * @param string $path  Path of the file to be encrypted.
	 *
	 * @return string|bool Either the encrypted file name, or false if file failed to generate.
	 */
	public function encrypt_file( $file, $path ) {
		$new_file        = $file . '.aes';
		$key             = WPEZ_SECRET_KEY;
		$file_block_size = 1024 * 512; // 512 kb
		$cipher          = 'aes-256-cbc';
		$ivLenght        = openssl_cipher_iv_length( $cipher );
		$iv              = openssl_random_pseudo_bytes( $ivLenght );

		$fpSource = fopen( $path . $file, 'rb' );
		$fpDest   = fopen( $path . $new_file, 'w' );

		fwrite( $fpDest, $iv );

		while ( ! feof( $fpSource ) ) {
			$plaintext  = fread( $fpSource, $ivLenght * $file_block_size );
			$ciphertext = openssl_encrypt( $plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv );
			$iv         = substr( $ciphertext, 0, $ivLenght );

			fwrite( $fpDest, $ciphertext );
		}
		// close file and delete original.
		fclose( $fpSource );
		fclose( $fpDest );
		unlink( $path . $file );
		// return file name of false if not found.
		return ( file_exists( $path . $new_file ) ) ? $new_file : false;
	}

	/**
	 * Function for decrypting a file, in small chuncks.
	 * Referenclink: https://medium.com/@antoine.lame/how-to-encrypt-files-with-php-f4adead297de
	 *
	 * @param string $file  Name of the file to be decrypted.
	 * @param string $path  Path of the file to be decrypted.
	 *
	 * @return string|bool Either the encrypted file name, or false if file failed to generate.
	 */
	public function decrypt_file( $file, $path ) {
		$new_file        = str_replace( '.aes', '', $file );
		$key             = WPEZ_SECRET_KEY;
		$file_block_size = 1024 * 512; // 512 kb
		$cipher          = 'aes-256-cbc';
		$ivLenght        = openssl_cipher_iv_length( $cipher );

		$fpSource = fopen( $path . $file, 'rb' );
		$fpDest   = fopen( $path . $new_file, 'w' );

		$iv = fread( $fpSource, $ivLenght );

		while ( ! feof( $fpSource ) ) {
			$ciphertext = fread( $fpSource, $ivLenght * ( $file_block_size + 1 ) );
			$plaintext  = openssl_decrypt( $ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv );
			$iv         = substr( $plaintext, 0, $ivLenght );

			fwrite( $fpDest, $plaintext );
		}
		// close file and delete original.
		fclose( $fpSource );
		fclose( $fpDest );
		unlink( $path . $file );
		// return file name of false if not found.
		return ( file_exists( $path . $new_file ) ) ? $new_file : false;
	}

	// ╔═════════════════════════════════════════════════════════════════════════════════════════════╗
	// ║   ██████  ██████  ███    ███ ██████  ██████  ███████ ███████ ███████ ██  ██████  ███    ██  ║
	// ║  ██      ██    ██ ████  ████ ██   ██ ██   ██ ██      ██      ██      ██ ██    ██ ████   ██  ║
	// ║  ██      ██    ██ ██ ████ ██ ██████  ██████  █████   ███████ ███████ ██ ██    ██ ██ ██  ██  ║
	// ║  ██      ██    ██ ██  ██  ██ ██      ██   ██ ██           ██      ██ ██ ██    ██ ██  ██ ██  ║
	// ║   ██████  ██████  ██      ██ ██      ██   ██ ███████ ███████ ███████ ██  ██████  ██   ████  ║
	// ╚═════════════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * Function for gzipping a file, in small chuncks.
	 * Referencelink: https://www.php.net/manual/en/function.gzwrite.php
	 *
	 * @param string $file  Name of the file to be g-zipped.
	 * @param string $path  Path of the file to be g-zipped.
	 *
	 * @return string|bool Either the encrypted file name, or false if file failed to generate.
	 */
	public function gzip_file( $file, $path ) {
		$new_file    = $file . '.gz';
		$level       = 9;
		$buffer_size = 1024 * 512; // 512 kb
		// Open our files (in binary mode)
		$fpSource = fopen( $path . $file, 'rb' );
		$fpDest   = gzopen( $path . $new_file, 'wb' . $level );
		// Keep repeating until the end of the input file
		while ( ! feof( $fpSource ) ) {
			// Read buffer-size bytes
			// Both gzwrite and fread are binary-safe
			gzwrite( $fpDest, fread( $fpSource, $buffer_size ) );
		}
		// close file and delete original.
		fclose( $fpSource );
		gzclose( $fpDest );
		unlink( $path . $file );
		// return file name of false if not found.
		return ( file_exists( $path . $new_file ) ) ? $new_file : false;
	}

	/**
	 * Function for g-unzipping a file, in small chuncks.
	 * Referencelink: https://stackoverflow.com/questions/11265914/how-can-i-extract-or-uncompress-gzip-file-using-php
	 *
	 * @param string $file  Name of the file to be g-unzipped.
	 * @param string $path  Path of the file to be g-unzipped.
	 *
	 * @return string|bool Either the encrypted file name, or false if file failed to generate.
	 */
	public function gunzip_file( $file, $path ) {
		$new_file    = str_replace( '.gz', '', $file );
		$buffer_size = 1024 * 512; // 512 kb
		// Open our files (in binary mode)
		$fpSource = gzopen( $path . $file, 'rb' );
		$fpDest   = fopen( $path . $new_file, 'wb' );
		// Keep repeating until the end of the input file
		while ( ! gzeof( $fpSource ) ) {
			// Read buffer-size bytes
			// Both fwrite and gzread are binary-safe
			fwrite( $fpDest, gzread( $fpSource, $buffer_size ) );
		}
		// close file and delete original.
		gzclose( $fpSource );
		fclose( $fpDest );
		unlink( $path . $file );
		// return file name of false if not found.
		return ( file_exists( $path . $new_file ) ) ? $new_file : false;
	}

	// ╔════════════════════════════════════════════════════════════════════╗
	// ║  ██████   █████  ████████  █████  ██████   █████  ███████ ███████  ║
	// ║  ██   ██ ██   ██    ██    ██   ██ ██   ██ ██   ██ ██      ██       ║
	// ║  ██   ██ ███████    ██    ███████ ██████  ███████ ███████ █████    ║
	// ║  ██   ██ ██   ██    ██    ██   ██ ██   ██ ██   ██      ██ ██       ║
	// ║  ██████  ██   ██    ██    ██   ██ ██████  ██   ██ ███████ ███████  ║
	// ╚════════════════════════════════════════════════════════════════════╝
	/**
	 * This function will dump the local database from the configured settings given.
	 *
	 * @param string  $table table to be dumped.
	 * @param string  $file filename to use for dump file.
	 * @param string  $path path to use for dump file.
	 * @param boolean $gzip whether or not to gzip file.
	 * @return string|boolean either the filename created of false.
	 */
	public static function db_dump_file( $table, $file, $path, $gzip = false ) {
		// build dump settings array
		$dumpSettings                               = array();
		$dumpSettings['include-tables']             = array( $table );
		$dumpSettings['compress']                   = ( $gzip ) ? IMysqldump\Mysqldump::GZIP : IMysqldump\Mysqldump::NONE;
		$dumpSettings['no-data']                    = false;
		$dumpSettings['add-drop-table']             = true;
		$dumpSettings['single-transaction']         = true;
		$dumpSettings['lock-tables']                = true;
		$dumpSettings['add-locks']                  = true;
		$dumpSettings['extended-insert']            = true;
		$dumpSettings['disable-foreign-keys-check'] = true;
		$dumpSettings['skip-triggers']              = false;
		$dumpSettings['add-drop-trigger']           = true;
		$dumpSettings['databases']                  = false;
		$dumpSettings['add-drop-database']          = false;
		$dumpSettings['hex-blob']                   = true;
		$dumpSettings['no-create-db']               = true; // Suppress CREATE DATABASE and USE
		$dumpSettings['skip-comments']              = true; // Suppress comments
		// declare a new instance of the mysql dump class, and initialize with db settings.
		$dump = new IMysqldump\Mysqldump( 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, $dumpSettings );
		// dump the database to the designated file name.
		$file_name = ( $gzip ) ? $file . '.gz' : $file;
		$db_export = $dump->start( $path . $file_name );
		// return file name of false if not found.
		return ( file_exists( $path . $file_name ) ) ? $file_name : false;
	}

	/**
	 * This function will start the process of dumping, compressing and encrypting the database tables.
	 * Reference Link: https://actionscheduler.org/usage/
	 * Reference Link: https://github.com/ifsnop/mysqldump-php
	 *
	 * @param string $path path of the directory where the tables to be exported.
	 * @param bool   $encrypt whether or not to encrypt the db dump files.
	 * @param bool   $gzip whether or not to gzip the db dump files.
	 * @param array  $tables an array of tables to be exported.
	 * @return void
	 */
	public function wpez_db_export( $path, $encrypt, $gzip, $tables ) {
		$user_loc = WPEZ_SYSTEM_USR . '@' . WPEZ_SYSTEM_URL;
		// starting the database backup
		$this->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
		$this->logFile( "STARTING: DB Backup (by {$user_loc})" );
		$this->logFile( "STARTING: DB Backup -- path: {$path}" );
		// create db export files
		foreach ( $tables as $table ) {
			$file    = $table . '.sql';
			$success = true;
			$this->logFile( "STARTING: DB Backup -- table: {$table}" );
			// if file exists in this location remove it.
			if ( file_exists( $path . $file ) ) {
				unlink( $path . $file ); }
			// Dump database table to an SQL file.
			$db_dump_file = $this->db_dump_file( $table, $file, $path, $gzip );
			if ( ! $db_dump_file ) {
				$message = "DB Dump File failed for file: {$path}{$db_dump_file}";
				$this->logFile( $message );
				//$this->notify($this->format_message( $message, 'error' ), 'error');
				$success = false;
			}
			// Encrypt the g-zipped SQL file.
			if ( $encrypt ) {
				$db_encrypt = $this->encrypt_file( $db_dump_file, $path );
				if ( ! $db_encrypt ) {
					$message = 'DB Encryption failed for file: ' . $path . $file;
					$this->logFile( $message );
					//$this->notify($this->format_message( $message, 'error' ), 'error');
					$success = false;
				}
			}
			// log the MD5 of the new archive file
			if ( $success ) {
				$this->logFile( "DB Backup (by {$user_loc}): -- Success: backup file: " . $path . $file );
			}
		}
		$this->logFile( "FINISHED: DB Backup (by {$user_loc})" );
		$this->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" );
	}


	// ╔════════════════════════════════════════════════════════════════════════════════════════════╗
	// ║  ███    ██  ██████  ████████ ██ ███████ ██  ██████  █████  ████████ ██  ██████  ███    ██  ║
	// ║  ████   ██ ██    ██    ██    ██ ██      ██ ██      ██   ██    ██    ██ ██    ██ ████   ██  ║
	// ║  ██ ██  ██ ██    ██    ██    ██ █████   ██ ██      ███████    ██    ██ ██    ██ ██ ██  ██  ║
	// ║  ██  ██ ██ ██    ██    ██    ██ ██      ██ ██      ██   ██    ██    ██ ██    ██ ██  ██ ██  ║
	// ║  ██   ████  ██████     ██    ██ ██      ██  ██████ ██   ██    ██    ██  ██████  ██   ████  ║
	// ╚════════════════════════════════════════════════════════════════════════════════════════════╝
	/**
	 * Sets up the curl notification call.
	 *
	 * @param string $content content to be sent to the log.
	 * @param string $channel channel to send log.
	 *
	 * @return boolean
	 */
	public function notify( $content, $channel ) {
		return true;
		//$ch_url = 'https://hooks.slack.com/services/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';
		//$ch_url = "https://slack.com/api/chat.postMessage";
		//$data = array(
		//  "token" => "", // removed token for security reasons.
		//  "channel" => '#server-alerts', //"#mychannel",
		//  "text" => $content, //"Hello, Foo-Bar channel message.",
		//  "username" => "jesse@waitz.com",
		//);
		//
		//$ch = curl_init();
		//curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		//curl_setopt( $ch, CURLOPT_URL, $ch_url );
		//curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Content-Type: application/json", "User-Agent: WPEZ Sync ".WPEZ_SYSTEM_USR.'@'.WPEZ_SYSTEM_URL ) );
		//curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
		//curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true ); // Skip SSL Verification
		//curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 1 );
		//curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		//if ( $ch ) {
		//  $response = curl_exec( $ch ); $this->logFile( $response );
		//  $status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		//  curl_close( $ch );
		//} else {
		//  $status = 0;
		//}
		//return ($status == 200) ? true : false;
	}

	/**
	 * Sets up the curl notification call.
	 *
	 * @param string $content -- content to be formatted.
	 * @param string $type -- type of message to create.
	 *
	 * @return string
	 *
	 * examples of how to call this function
	 * $sync_tools->format_message( 'Write your message here', '' );
	 * $sync_tools->format_message( 'Write your message here', 'heading' );
	 * $sync_tools->format_message( 'Write your message here', 'success' );
	 * $sync_tools->format_message( 'Write your message here', 'error' );
	 * $sync_tools->format_message( 'Write your message here', 'warning' );
	 * $sync_tools->format_message( 'Write your message here', 'attention' );
	 */
	public function format_message( $content, $type = 'line' ) {
		// SETUP CURL COMMAND TO POST TO TEAMS SERVER.
		$message = array(
			'line'      => $content,
			'heading'   => "<strong style='color:#439de9'>" . $content . '</strong> ',
			'success'   => "<strong style='color:#00d59d'>Success:</strong> " . $content,
			'error'     => "<strong style='color:#fa665f'>Error:</strong> " . $content,
			'warning'   => "<strong style='color:#0cc3e1'>Warning:</strong> " . $content,
			'attention' => "<strong style='color:#f6f560'>Attention:</strong> " . $content,
		);
		return $message[ $type ] . "<br/><br/>\n";
	}


	// ╔══════════════════════════════════════════════════════════════╗
	// ║  ███████ ██ ██      ███████     ██      ██ ███████ ████████  ║
	// ║  ██      ██ ██      ██          ██      ██ ██         ██     ║
	// ║  █████   ██ ██      █████       ██      ██ ███████    ██     ║
	// ║  ██      ██ ██      ██          ██      ██      ██    ██     ║
	// ║  ██      ██ ███████ ███████     ███████ ██ ███████    ██     ║
	// ╚══════════════════════════════════════════════════════════════╝
	/**
	 * Function produces an array of files for a specific path
	 * used by plugin for syncing files over php, like rsync.
	 *
	 * @param string $path  path to iterate on.
	 * @param string $location  location to iterate on.
	 * @param string $timestamp  timestamp to start from.
	 *
	 * @return string|false
	 */
	public function get_file_list( $path, $location, $timestamp ) {
		if ( ! file_exists( $path ) || ! is_dir( $path ) ) {
			return false; }
		$iterator           = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$path,
				\FilesystemIterator::CURRENT_AS_FILEINFO |
				\FilesystemIterator::SKIP_DOTS
			)
		);
		$path_prefix_length = strlen( $path );
		$files              = array();
		$x                  = 1;
		$y                  = 1;

		// IGNORE THESE WHOLE FILE NAMES
		//! need to integrate this into the settings panel
		//! when the sync includes the plugin and theme dirs, the vendor directory will have to be figured out.
		$ignore_files = array( '.DS_Store', 'nginx-helper/nginx.log', 'node_modules', 'vendor', '.git' );

		// BEGIN THE LOOP TO CREATE THE FILE ARRAY.
		$this->logFile( 'get_file_list: creating the ' . $location . ' files array' );
		foreach ( $iterator as $file_info ) {
			// SETUP VARIABLES FOR THE LOOP
			$includeFile = true;
			$filepath    = $file_info->getRealPath();
			$filename    = $file_info->getFilename();
			$filesize    = $file_info->getSize();
			$filetime    = (int) $file_info->getMTime();
			$timestamp   = (int) $timestamp;

			// FILTER OUT RESULTS BY TIMESTAMP, 0 BYTE FILES, IGNORE FILES, AND RESIZED IMAGES.
			if ( $filesize == 0 ) {
				$this->logFile( "get_file_list: SKIPPED: {$filename} file was only 0 bytes." );
				$includeFile = false;
				continue;
			}
			if ( $filetime < $timestamp ) {
				$includeFile = false;
				continue;
			}
			if ( str_replace( $ignore_files, '', $filepath ) !== $filepath ) {
				$includeFile = false;
				continue;
			}
			if ( in_array( $filename, $ignore_files, true ) ) {
				$includeFile = false;
				continue;
			}

			// BUILD ARRAY OF FILES.
			if ( $includeFile ) {
				if ( $x == 5000 * $y ) {
					$this->logFile( '%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%' );
					$this->logFile( "get_file_list: {$x} files added to the array" );
					$this->logFile( "%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%\n" );
				} ++$y;
				$full_path           = str_replace( DIRECTORY_SEPARATOR, '/', substr( $filepath, $path_prefix_length ) );
				$files[ $full_path ] = array(
					'md5'       => md5_file( $path . $full_path ),
					'timestamp' => $filetime,
				);
			}
			++$x;
		}
		$this->logFile( 'get_file_list: converting the ' . $location . ' files array into a JSON object' );
		$file_array = json_encode( $files );
		$files_dir  = $this->get_sync_dir( 'files' );
		$file_name  = $location . '_file_array.json';
		$file_path  = $files_dir . $file_name;
		$fd         = fopen( $file_path, 'wb+' );
		if ( $fd ) {
			$this->logFile( 'get_file_list: saving the JSON file to disk' );
			fwrite( $fd, $file_array );
			fclose( $fd );
			return $file_name;
		}
		return false;
	}


	// ╔═════════════════════════════════════════════════════════════════════╗
	// ║   ██████ ██      ██     ████████  ██████   ██████  ██      ███████  ║
	// ║  ██      ██      ██        ██    ██    ██ ██    ██ ██      ██       ║
	// ║  ██      ██      ██        ██    ██    ██ ██    ██ ██      ███████  ║
	// ║  ██      ██      ██        ██    ██    ██ ██    ██ ██           ██  ║
	// ║   ██████ ███████ ██        ██     ██████   ██████  ███████ ███████  ║
	// ╚═════════════════════════════════════════════════════════════════════╝
	/**
	 * Function for setting timestamp if user updates files.
	 *
	 * @param string $type  server user and location.
	 * @param string $msg  timestamp to start from.
	 *
	 * @return int timestamp
	 */
	public function setTimeStamp( $type, $msg ) {
		$timestamp_dir = $this->get_sync_dir( 'timestamps' );
		$timepath      = $timestamp_dir . $type . '.txt';
		$this->logFile( "***** type: {$type} -- msg: {$msg} *****" );
		$fd = fopen( $timepath, 'wb+' );
		if ( $fd ) {
			fwrite( $fd, $msg );
			fclose( $fd );
			return $timepath;
		}
		return true;
	}

	/**
	 * Function for verifying, and selecting the remote to connect to.
	 * If the remote is not set, it will ask you to select one, if no
	 * remote is set after that it will return and error and end the request.
	 *
	 * @param array $args_assoc -- array of flagss sent to the command.
	 *
	 * @return array|void $remote_arr -- array of found remotes or exit with wp-cli error message.
	 */
	public function verify_remote( $args_assoc ) {
		$remote_arr  = array(
			'env' => '',
			'tag' => '',
		);
		$remote_keys = array();
		$remote_urls = array();
		// CHECK TO SEE IF THE REMOTE SERVERS ARE SET IN THE SETTINGS, AND THEN ASSIGN VARS BASES ON SENT FLAGS.
		if ( defined( 'WPEZ_SYNC_REMOTES' ) && ! empty( WPEZ_SYNC_REMOTES ) && is_array( WPEZ_SYNC_REMOTES ) ) {
			// loop through the remotes available
			foreach ( WPEZ_SYNC_REMOTES as $key => $url ) {
				$remote_keys[] = $key;
				$remote_urls[] = $key . ' -- ' . $url;
				// check if the remote in the loop is the same as the flag set in the command. If so, set vars.
				if ( ! empty( $args_assoc[ $key ] ) && $args_assoc[ $key ] === true ) {
					$remote_arr['env'] = $key;
					$remote_arr['tag'] = '--' . $key;
					$remote_arr['url'] = $url;
					if ( ! empty( $url ) ) {
						return $remote_arr;
					}
				}
			}
		} else {
			// if the remotes are not set, or are improperly formatted, then show error to create remotes.
			WP_CLI::error( 'There are no remotes set in your sync settings. Please login to the WP-admin go to WPEZ-Sync settings, and set your remote locations.' );
			die;
		}

		// IF NO FLAG IS SENT IN THE COMMAND OR THE FLAD DOES NOT EXIST AS A REMOTE ASK THIS QUESTION.
		if ( empty( $remote_env ) || empty( $remote_url ) ) {
			WP_CLI::warning( "A valid sync server must be set in your command.\n\nThe remote flags --" . implode( ' or --', $remote_keys ) . " are currently available,\nbut you can add additional remotes in the WPEZ Sync settings." );
			$server_ask = $this->ask( "\nWHICH SERVER DO YOU WANT TO SYNC FROM? \n\n" . implode( "\n", $remote_urls ) . "\n\nenter [" . implode( '|', $remote_keys ) . ']:' );
			if ( ! empty( $server_ask ) ) {
				$remote_arr['env'] = $server_ask;
				$remote_arr['tag'] = '--' . $server_ask;
				$remote_url        = WPEZ_SYNC_REMOTES[ $server_ask ] ?? false;
				$remote_arr['url'] = $remote_url;
				if ( ! empty( $remote_url ) ) {
					return $remote_arr;
				}
			}
		}

		// IF THE REMOTE VARS ARE STILL EMPTY SHOW ERROR AND STOP THE COMMAND BECAUSE WE CANNOT CONTINUE.
		WP_CLI::error( 'No valid sync server has be set. Please try again.' );
		die;
	}

	/**
	 * Function for asking a question in the CLI and returning an answer as a string.
	 *
	 * @param string $question -- text to turn into a STDIN question.
	 *
	 * @return string
	 */
	public function ask( $question ) {
		fwrite( STDOUT, $question . ' ' );
		return strtolower( trim( fgets( STDIN ) ) );
	}

	/**
	 * Converts a unix timestamp difference into a duration string, like
	 *   1 hour, 39 minutes, 24 seconds
	 *
	 * @param int $endTimestamp Starting timestamp; default 0.
	 * @param int $startTimestamp Ending timestamp; default 0.
	 * @param int $precision How many intervals should be printed.
	 *
	 * @return string
	 */
	public function elapsed_time( $endTimestamp = 0, $startTimestamp = 0, $precision = 5 ) {
		if ( ! $startTimestamp ) {
			$startTimestamp = time();
		}
		$elapsed      = $endTimestamp - $startTimestamp;
		$intervalList = array(
			'decade' => 315576000,
			'year'   => 31557600,
			'month'  => 2629800,
			'week'   => 604800,
			'day'    => 86400,
			'hour'   => 3600,
			'min'    => 60,
			'sec'    => 1,
		);
		$i            = 0;
		$result       = '';
		foreach ( $intervalList as $interval => $durationSeconds ) {
			$k = floor( $elapsed / $durationSeconds );
			if ( $k > 0 ) {
				++$i;
			}
			$elapsed = ( $i >= $precision ) ? 0 : $elapsed - ( $k * $durationSeconds );
			$plural  = $k > 1 ? 's' : '';
			$result .= ( ( $k > 0 ) ? $k . ' ' . $interval . $plural . ' ' : '' );
		}
		return $result === '' ? 'instantaneous' : $result;
	}

	/**
	 * Logs our stuff to the dev log DB table
	 *
	 * @param mixed $stuff Item to be logged, can be string array or object.
	 *
	 * @return bool
	 */
	public function logFileBKP( $stuff ): bool {
		if ( ! is_string( $stuff ) ) {
			$stuff = print_r( $stuff, true );
		}
		$dir = $this->get_sync_dir( 'logs' );
		if ( ! file_exists( $dir ) ) {
			mkdir( $dir, 0755, true ); }
		$file = $dir . 'wpez_sync_log.txt';
		$when = wp_date( 'Y-m-d H:i:s' );
		$fd   = fopen( $file, 'ab' );
		if ( $fd ) {
			fwrite( $fd, "{$when} | " . WPEZ_SYSTEM_LOC . " | {$stuff}\n" );
			fclose( $fd );

			return true;
		}

		return false;
	}
	/**
	 * Capture given user input to a log file, default named 'debug-YYYY-MM-DD.txt'.
	 *
	 * @param mixed  $stuff The data to log. Can be a string or an array.
	 * @param string $logFile File name of where to output the log. Default is debug-YYYY-MM-DD.txt.
	 * @return void
	 */
	function logFile( mixed $stuff, string $logFile = 'wpez_sync.log' ): void {
		$logDir = $this->get_sync_dir( 'logs' );
		$logFile  = str_replace( 'DATE', wp_date( 'Y-m-d' ), $logFile );
		$fullPath = "{$logDir}/{$logFile}";
		$fd       = @fopen( $fullPath, 'ab' );
		if ( $fd ) {
			fwrite( $fd, date_i18n( 'Y-m-d H:i:s' ) . ' | ' );
			// get the IP
			if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
				$ip = $_SERVER['HTTP_CF_CONNECTING_IP']; // phpcs:ignore
			} elseif ( isset( $_SERVER['HTTP_CF_CONNECTING_IPV6'] ) ) {
				$ip = $_SERVER['HTTP_CF_CONNECTING_IPV6']; // phpcs:ignore
			} elseif ( isset( $_SERVER['HTTP_CF_PSEUDO_IPV4'] ) ) {
				$ip = $_SERVER['HTTP_CF_PSEUDO_IPV4']; // phpcs:ignore
			} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$ip = $_SERVER['REMOTE_ADDR']; // phpcs:ignore
			} else {
				$ip = '127.0.0.1'; // dunno
			}
			$request_method = $_SERVER['REQUEST_METHOD'] ?? 'dunno'; // phpcs:ignore
			//fwrite( $fd, $ip . ' | ' . $request_method . ' | ' );
			if ( ! is_string( $stuff ) ) {
				$stuff = print_r( $stuff, true );
			}
			fwrite( $fd, $stuff . "\n" );
			fflush( $fd );
			fclose( $fd );
		}
	}

	/**
	 * Gets the server path to the WPEZ Sync temporary directory, with a trailing slash
	 *
	 * @param string $suffix Set the directory to use. Defaults to tmp.
	 *
	 * @return string
	 */
	public function get_sync_dir( $suffix = 'tmp' ): string {
		$dir = dirname( ABSPATH ) . '/wpezsync/' . $suffix . '/';
		$this->maybe_create_sync_dir( $dir );
		return $dir;
	}

	/**
	 * Creates the WPEZ Sync temporary directory is not already present
	 *
	 * @param string $dir Optional directory to create; defaults to WPEZ Sync temporary directory.
	 * @see get_temp_dir()
	 *
	 * @return void
	 */
	public function maybe_create_sync_dir( $dir = '' ): void {
		if ( $dir === '' ) {
			$dir = $this->get_sync_dir(); }
		if ( ! file_exists( $dir ) ) {
			mkdir( $dir, 0755, true ); }
	}

	/**
	 * Flushes redis cache.
	 *
	 * @param string $user_loc  server user and location.
	 *
	 * @return array
	 */
	public function clear_cache( $user_loc ) {
		// create arrays for success and error message
		$output  = array();
		$message = array();
		// create the database file.
		$command = 'redis-cli flushdb 2>&1';
		exec( $command, $output, $result );
		$output_str = ( is_array( $output ) ) ? implode( ' | ', $output ) : 'No Errors';
		// add error checking here for not 0. if error end process and return error.
		if ( $result > 0 ) {
			$this->logFile( "Redis Cache Flush (by {$user_loc}): Error: Operation Failed: " . $output_str );
			$message = array(
				'status'  => 'FAILED',
				'message' => $output_str,
			);
		} else {
			$this->logFile( "Redis Cache Flush (by {$user_loc}): Success: Redis Cached Flushed: " . $output_str );
			$message = array(
				'status'  => 'success',
				'message' => 'Redis cached was flushed',
			);
		}
		// return messages array of results of the dump and archive operation.
		return $message;
	}
}

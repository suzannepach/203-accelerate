<?php
/** File Cacher class
 *
 * @category Class
 * @package SG_File_Cacher
 * @author SiteGround
 */

namespace SiteGround_Optimizer\File_Cacher;

use SiteGround_Optimizer\Helper\Helper;
use SiteGround_Optimizer\Helper\File_Cacher_Trait;
/**
 * SG File Cacher main class
 */
class Cache {
	use File_Cacher_Trait;
	/**
	 * The file configuration.
	 *
	 * @since 7.0.0
	 *
	 * @var $config
	 */
	private $config;

	/**
	 * The constructor
	 *
	 * @since 7.0.0
	 *
	 * @param string $config_path Path to the config file.
	 */
	public function __construct( $config_path ) {
		$this->parse_config( $config_path );
	}

	/**
	 * Parse the config
	 *
	 * @since  7.0.0
	 *
	 * @param  string $path The path to the config.
	 */
	public function parse_config( $path ) {

		if ( ! file_exists( $path ) ) {
			return;
		}

		include $path;

		foreach ( $config as $setting => $entry_value ) {
			$this->$setting = $entry_value;
		}
	}

	/**
	 * Checks if the cache path exists.
	 *
	 * @since  7.0.0
	 *
	 * @return bool True if the path exists, false otherwise.
	 */
	public function cache_exists() {
		return file_exists( $this->get_cache_path() );
	}

	/**
	 * Get the cache path.
	 *
	 * @since  7.0.0
	 *
	 * @return string The cache path.
	 */
	public function get_cache_path( $url = '', $include_user = true ) {
		// Get the current url if the url params is missing.
		$url = empty( $url ) ? self::get_current_url() : $url;

		// Parse the url.
		$parsed_url = parse_url( $url );

		// Prepare the path.
		$path = $parsed_url['host'];

		if (
			true === $include_user &&
			$this->is_logged_in() &&
			$this->logged_in_cache
		) {
			$path .= '-' . $this->get_user_login();
		}

		$path .= '-' . $this->cache_secret_key;

		$path .= $parsed_url['path']; // phpcs:ignore

		return $this->output_dir . $path;
	}

	/**
	 * Check if user is logged in.
	 *
	 * @since  7.0.0
	 *
	 * @return boolean True if the user is logged in, false otherwise.
	 */
	public function is_logged_in() {
		// Bail, if user not logged in.
		if ( ! in_array( $this->logged_in_cookie, array_keys( $_COOKIE ), true ) ) {
			return false;
		}

		// Include class, since we are performing those checks before the plugin is loaded.
		require_once dirname( __DIR__ ) . '/Helper/Helper.php';

		// Get the active plugins.
		$active_plugins = Helper::sg_get_db_entry( 'options', 'option_value', 'option_name', 'active_plugins' );

		// Bail if we do not fetch the active plugins.
		if ( empty( $active_plugins ) ) {
			return true;
		}

		// Bail if the SG-Security is not enabled.
		if( ! preg_match( '~sg-security\/sg-security.php~', $active_plugins[0]['option_value'] ) ) {
			return true;
		}

		$sg_2fa_option = Helper::sg_get_db_entry( 'options', 'option_value', 'option_name', 'sg_security_sg2fa' );

		// Check if 2fa option is enabled.
		if ( empty( $sg_2fa_option ) ) {
			return true;
		}

		// Bail if the option is disabled.
		if ( 0 === (int) $sg_2fa_option[0]['option_value'] ) {
			return true;
		}

		// Get the user data.
		$user_data = Helper::sg_get_db_entry( 'users', 'ID', 'user_login', $this->get_user_login() );

		// Bail if no user data is found.
		if ( empty( $user_data ) ) {
			return false;
		}

		// Get the user ID.
		$user_id = $user_data[0]['ID'];

		// Get all 2fa users' ids.
		$users_with_2fa = Helper::sg_get_2fa_users();

		// Check if current user's ID is in the array.
		if ( ! in_array( $user_id, array_column( $users_with_2fa, 'ID' ), true ) ) {
			return true;
		}

		// Get the cookie name for this user.
		$cookie = 'sg_security_2fa_' . $user_id . '_cookie';

		// Bail if cookie is not set.
		if ( ! isset( $_COOKIE[ $cookie ] ) ) {
			return false;
		}

		// Get the 2FA cookie data.
		$token = Helper::sg_get_user_meta( $user_id, 'sg_security_2fa_secret' );

		// Bail, if secret is not set.
		if ( false === $token ) {
			return false;
		}

		// Require the Helper class so we can use the sgs_decrypt from the SG-Security plugin.
		require_once preg_replace( '~sg-cachepress~', 'sg-security', dirname( __DIR__ ) . '/Helper/Helper.php' );

		// Decrypt the user's 2fa cookie.
		$data = \SG_Security\Helper\Helper::sgs_decrypt( $_COOKIE[ $cookie ], md5( $token ) ); // phpcs:ignore

		// Return true if the cookie is valid.
		if ( $data[0] === $this->get_user_login() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the user login from the cookie.
	 *
	 * @since  7.0.0
	 *
	 * @return string The user login.
	 */
	public function get_user_login() {
		$logged_in_cookie_parsed = explode( '|', $_COOKIE[ $this->logged_in_cookie ] ); // phpcs:ignore

		return $logged_in_cookie_parsed[0];
	}

	/**
	 * Unserilizes the content and checks the cache timestamp
	 *
	 * @since 7.0.0
	 *
	 * @return string|bool     Returns the HTML in a string format, if cache expired or invalid - returns false
	 */
	public function get_cache() {
		$should_send_miss = true;

		if (
			( @file_exists( '/etc/yum.repos.d/baseos.repo' ) && @file_exists( '/Z' ) ) &&
			empty( $_COOKIE[ 'wordpress_logged_in_' . "COOKIEHASH" ] )
		) {
			$should_send_miss = false;
		}
		// Bail if the page is excluded from the cache.
		if ( ! $this->is_cacheable() ) {
			header( 'SG-F-Cache: BYPASS' );
			return;
		}

		$cache_file = $this->get_cache_path() . $this->get_filename( $this->ignored_query_params );

		if ( ! file_exists( $cache_file ) ) {
			if ( $should_send_miss ) {
				header( 'SG-F-Cache: MISS' );
			}
			return;
		}

		$content = file_get_contents( $cache_file );

		// Check for non-existing data or non-existing file.
		if ( empty( $content ) ) {
			if ( $should_send_miss ) {
				header( 'SG-F-Cache: MISS' );
			}
			return false;
		}

		// Bail if the cache is stale.
		if ( filemtime( $cache_file ) < ( time() - WEEK_IN_SECONDS ) ) {
			if ( $should_send_miss ) {
				header( 'SG-F-Cache: MISS' );
			}
			return false;
		}

		header( 'SG-F-Cache: HIT' );

		echo $content; // phpcs:ignore
		exit;
	}
}

<?php
namespace SG_Security\Sg_2fa;

use SG_Security\Helper\Helper;
use PHPGangsta_GoogleAuthenticator;
use SG_Security;

use PragmaRX\Recovery\Recovery;

/**
 * Class that manages 2FA related services.
 */
class Sg_2fa {
	/**
	 * The singleton instance.
	 *
	 * @since 1.1.1
	 *
	 * @var \Sg_2fa The singleton instance.
	 */
	public static $instance;

	/**
	 * Roles that should be forced to use 2FA.
	 *
	 * @var array
	 */
	public $roles = array(
		'editor',
		'administrator',
	);

	/**
	 * The constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->google_authenticator = new PHPGangsta_GoogleAuthenticator();
		$this->recovery             = new Recovery();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.1.1
	 *
	 * @return \Sg_2fa The singleton instance.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the roles that apply for 2FA.
	 *
	 * @since 1.2.0
	 *
	 * @return array The roles, that have 2FA enabled.
	 */
	public function get_2fa_user_roles() {
		// Get the custom user roles set by the user.
		$custom_user_roles = get_option( 'sg_security_2fa_roles', array() );

		// Merge and return the default and custom user roles.
		return array_merge( $this->roles, $custom_user_roles );
	}

	/**
	 * Generate QR code for specific user.
	 *
	 * @since  1.0.0
	 *
	 * @param  object $user The WP_USER object.
	 *
	 * @return string       The QR code URL.
	 */
	public function generate_qr_code( $user ) {
		// Build the title for the authenticator.
		$title = get_home_url() . ' (' . $user->user_email . ')';

		// Get the user secret code.
		$secret = get_user_meta( $user->ID, 'sg_security_2fa_secret', true ); // phpcs:ignore

		// Return the URL.
		return $this->google_authenticator->getQRCodeGoogleUrl( $title, $secret );
	}

	/**
	 * Verify the authenticaion code.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $code    One time code from the authenticator app.
	 * @param  int    $user_id The user ID.
	 *
	 * @return bool            True if the code is valid, false otherwise.
	 */
	public function check_authentication_code( $code, $user_id ) {
		// Get the user secret.
		$secret = get_user_meta( $user_id, 'sg_security_2fa_secret', true ); // phpcs:ignore

		// Verify the code.
		return $this->google_authenticator->verifyCode( $secret, $code, 2 );
	}

	/**
	 * Enable 2FA.
	 *
	 * @since  1.0.0
	 *
	 * @return bool  True on success, false on failure.
	 */
	public function enable_2fa() {
		$users = get_users(
			array(
				'role__in' => $this->get_2fa_user_roles(),
			)
		);

		if ( empty( $users ) ) {
			return true;
		}

		foreach ( $users as $user ) {
			// Get the user by the user id.
			$user = get_userdata( $user->data->ID );

			if ( empty( array_intersect( $this->get_2fa_user_roles(), $user->roles ) ) ) {
				continue;
			}

			$this->enable_2fa_for_user( $user->data->ID );
		}

		return true;
	}

	/**
	 * Enable the 2FA for an user.
	 *
	 * @since  1.1.1
	 *
	 * @param  int $user_id The user ID.
	 */
	public function enable_2fa_for_user( $user_id ) {
		$this->generate_user_secret( $user_id );
		$this->generate_user_qr( $user_id );
		$this->generate_user_backup_codes( $user_id );
	}

	/**
	 * Handle 2FA option change.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 */
	public function handle_option_change( $old_value, $new_value ) {
		if ( 1 == $new_value ) {
			$this->enable_2fa();
		}
	}

	/**
	 * Generate the user secret.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $user_id WordPress user ID.
	 *
	 * @return mixed        True on success, false on failure, user ID if the secret exists.
	 */
	public function generate_user_secret( $user_id ) {
		// Check if the user has secret code.
		$secret = get_user_meta( $user_id, 'sg_security_2fa_secret', true ); // phpcs:ignore

		// Bail if the user already has a secret code.
		if ( ! empty( $secret ) ) {
			return $user_id;
		}

		// Add the user secret meta.
		return update_user_meta( // phpcs:ignore
			$user_id,
			'sg_security_2fa_secret',
			$this->google_authenticator->createSecret() // Generate the secret code.
		);
	}

	/**
	 * Generate the user QR code link.
	 *
	 * @since  1.1.1
	 *
	 * @param  int   $user_id WordPress user ID.
	 *
	 * @return mixed          True on success, false on failure, user ID if the QR exists.
	 */
	public function generate_user_qr( $user_id ) {
		// Check if the user has a QR code.
		$qr = get_user_meta( $user_id, 'sg_security_2fa_qr', true ); // phpcs:ignore

		// Get the user by ID.
		$user = get_user_by( 'ID', $user_id );

		// Bail if the user already has a QR code.
		if ( ! empty( $qr ) ) {
			return $user_id;
		}

		// Add the user QR.
		return update_user_meta( // phpcs:ignore
			$user_id,
			'sg_security_2fa_qr',
			$this->generate_qr_code( $user ) // Generate the QR code.
		);
	}

	/**
	 * Generate the user backup codes.
	 *
	 * @since  1.1.0
	 *
	 * @param  int $user_id WordPress user ID.
	 *
	 * @return mixed        True on success, false on failure, user ID if the backup codes exists.
	 */
	public function generate_user_backup_codes( $user_id ) {
		// Check if the user has backup codes.
		$backup_codes = get_user_meta( $user_id, 'sg_security_2fa_backup_codes', true ); // phpcs:ignore

		// Bail if the user already has a backup codes.
		if ( ! empty( $backup_codes ) ) {
			return $user_id;
		}

		// Add the user backup_codes meta.
		return update_user_meta( // phpcs:ignore
			$user_id,
			'sg_security_2fa_backup_codes',
			$this->recovery->numeric()->setCount( 8 )->setBlocks( 1 )->setChars( 8 )->toArray() // Generate the backup codes.
		);
	}

	/**
	 * Validate the backup codes 2Fa login.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $code The backup login code.
	 * @param  int    $user The user id.
	 *
	 * @return bool         True if the code is correct, false on failure.
	 */
	public function validate_backup_login( $code, $user ) {
		$codes = get_user_meta( $user, 'sg_security_2fa_backup_codes', true ); // phpcs:ignore

		// Bail if the user doesn't have backup codes.
		if ( empty( $codes ) ) {
			return false;
		}

		$key = array_search( $code, $codes );

		// Bail if the code doesn't exists in the user backup codes.
		if ( false === $key ) {
			return false;
		}

		// Remove the used key.
		unset( $codes[ $key ] );

		// Add additional backup codes to the user meta, if the user has used 4 or more backup codes.
		$this->maybe_add_additional_backup_codes( $codes, $user );

		return true;
	}

	/**
	 * Adds additional backup codes to the user meta, if the user has used 4 or more backup codes.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $codes   Existing user backup codes.
	 * @param  int   $user_id The user ID.
	 */
	public function maybe_add_additional_backup_codes( $codes, $user_id ) {
		// Add additional backup codes to the user meta.
		if ( 5 > count( $codes ) ) {
			$codes = array_merge(
				$codes, // The existing codes.
				$this->recovery->numeric()->setCount( 4 )->setBlocks( 1 )->setChars( 8 )->toArray() // Generate 4 new backup codes.
			);

			// Set a flag that additional codes have been generated, so we can show an admin notice to the user.
			update_user_meta( $user_id, 'sgs_additional_codes_added', 1 ); // phpcs:ignore
		}

		update_user_meta( // phpcs:ignore
			$user_id,
			'sg_security_2fa_backup_codes',
			$codes
		);
	}

	/**
	 * Show the Security by SiteGround Section in the user profile
	 *
	 * @since 1.1.2
	 *
	 * @param object $user WP_User object.
	 */
	public function show_profile_security( $user ) {
		// Get the users backup codes.
		$backup_codes = get_user_meta( $user->ID, 'sg_security_2fa_backup_codes', true ); // phpcs:ignore

		// Get the user secret code.
		$secret = get_user_meta( $user->ID, 'sg_security_2fa_secret', true ); // phpcs:ignore

		// Get the user QR code.
		$qr = get_user_meta( $user->ID, 'sg_security_2fa_qr', true ); // phpcs:ignore

		// Bail if we do not have backup codes and secret.
		if ( empty( $backup_codes ) && empty( $secret ) ) {
			return;
		}

		// Include the Security by SiteGround heading.
		include_once SG_Security\DIR . '/templates/2fa-user-profile-section.php';
	}

	/**
	 * Displays an admin notice that additional backup codes have been generated.
	 *
	 * @since  1.1.0
	 */
	public function show_backup_codes_notice() {
		$current_user = wp_get_current_user();

		if ( empty( get_user_meta( $current_user->data->ID, 'sgs_additional_codes_added', true ) ) ) { // phpcs:ignore
			return;
		}
		?>
		<div class="notice notice-success" style="position: relative">
			<p>
				<?php _e( 'There are new 2FA backup codes available. Visit <a href="' . get_edit_profile_url( $current_user->data->ID ) . '">your profile page</a> to view them.', 'sg-security' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss notice handle.
	 *
	 * @since  1.1.0
	 */
	public function dismiss_backup_codes_notice() {
		$current_user = wp_get_current_user();

		delete_user_meta( $current_user->data->ID, 'sgs_additional_codes_added' ); // phpcs:ignore
	}

	/**
	 * Display the two factor authentication forms.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $args Additional args.
	 */
	public function load_form( $args ) {
		// Bail if template is not provided.
		if ( empty( $args['template'] ) ) {
			return;
		}

		// Path to the form template.
		$path = SG_Security\DIR . '/templates/' . $args['template'];

		// Bail if there is no such file.
		if ( ! file_exists( $path ) ) {
			return;
		}

		$args = $this->get_args_for_template( $args );

		// Include the login header if the function doesn't exists.
		if ( ! function_exists( 'login_header' ) ) {
			include_once ABSPATH . 'wp-login.php';
		}

		// Include the template.php if the function doesn't exists.
		if ( ! function_exists( 'submit_button' ) ) {
			require_once ABSPATH . '/wp-admin/includes/template.php';
		}

		login_header();

		// Include the template.
		include_once $path;

		login_footer();
		exit;
	}

	/**
	 * Reset the 2FA for specific user ID.
	 *
	 * @since  1.1.1
	 *
	 * @param  int   $user_id  WordPress user ID.
	 *
	 * @return array $response Responce to react app.
	 */
	public function reset_user_2fa( $user_id ) {
		// User meta used by 2FA setup.
		$clear_meta = array(
			'configured',
			'secret',
			'qr',
			'backup_codes',
		);

		// Bail if there is no such user.
		if ( false === get_user_by( 'ID', $user_id ) ) {
			return false;
		}

		// Delete the 2FA user meta and reset the 2FA configuration setting.
		foreach ( $clear_meta as $meta ) {
			delete_user_meta( $user_id, 'sg_security_2fa_' . $meta ); // phpcs:ignore
		}

		// Add the required 2fa user metas.
		$this->enable_2fa_for_user( $user_id );

		return array(
			'message' => __( 'User 2FA reset!', 'sg-security' ),
			'result'  => 1,
		);
	}

	/**
	 * Default arguments passed to the form.
	 *
	 * @since  1.1.1
	 *
	 * @param  array $args arguments passed.
	 *
	 * @return array arguments merged with the default ones.
	 */
	public function get_args_for_template( $args ) {
		return array_merge(
			$args,
			array(
				'interim_login' => ( isset( $_REQUEST['interim-login'] ) ) ? filter_var( wp_unslash( $_REQUEST['interim-login'] ), FILTER_VALIDATE_BOOLEAN ) : false,
				'redirect_to'   => isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url(),
				'rememberme'    => ( ! empty( $_REQUEST['rememberme'] ) ) ? true : false,
			)
		);
	}

	/**
	 * Show the backup codes form to the user if this is the initial 2fa setup.
	 *
	 * @since 1.1.1
	 *
	 * @param object $user WP_USER object.
	 */
	public function show_backup_codes( $user ) {
		$this->load_form(
			array(
				'template'     => 'backup-codes.php',
				'backup_codes' => get_user_meta( $user->ID, 'sg_security_2fa_backup_codes', true ), // phpcs:ignore
				'redirect_to'  => ! empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : get_admin_url(), // phpcs:ignore
			)
		);
	}

	/**
	 * Show QR code to the user if backup code is used.
	 *
	 * @since  1.1.1
	 *
	 * @param object $user WP_USER object.
	 */
	public function show_qr_backup_code_used( $user ) {
		$this->load_form(
			array(
				'template'    => 'backup-code-used.php',
				'qr'          => get_user_meta( $user->ID, 'sg_security_2fa_qr', true ), // phpcs:ignore
				'secret'      => get_user_meta( $user->ID, 'sg_security_2fa_secret', true ), // phpcs:ignore
				'redirect_to' => ! empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : get_admin_url(), // phpcs:ignore
			)
		);
	}

	/**
	 * Interim WordPress login.
	 *
	 * @since 1.1.1
	 */
	public function interim_check() {
		global $interim_login;
		$interim_login = ( isset( $_REQUEST['interim-login'] ) ) ? filter_var( $_REQUEST['interim-login'], FILTER_VALIDATE_BOOLEAN ) : false; // phpcs:ignore

		// Bail if $interim_login is false.
		if ( false === $interim_login ) {
			return;
		}

		$interim_login = 'success'; // WPCS: override ok.
		login_header( '', '<p class="message">' . __( 'You have logged in successfully.', 'sg-security' ) . '</p>' );
		?>
		</div>
		<?php do_action( 'login_footer' ); ?>
		</body></html>
		<?php
		exit;
	}

	/**
	 * Validate backup codes login.
	 *
	 * @since  1.1.0
	 */
	public function validate_2fabc_login() {
		// Bail if there is no backup code.
		if ( ! isset( $_POST['sgc2fabackupcode'] ) ) {
			return;
		}

		// Get the user object from authentication cookie.
		$user = $this->get_auth_user();

		// Bail if user is not authenticated.
		if ( false === $user ) {
			return;
		}

		// Check the result of the authtication.
		if ( false === $this->validate_backup_login( wp_unslash( $_POST['sgc2fabackupcode'] ), $user->ID ) ) {
			$this->load_form(
				array(
					'template' => '2fa-login-backup-code.php',
					'action'   => esc_url( add_query_arg( 'action', 'sgs2fabc', wp_login_url() ) ),
					'error'    => esc_html__( 'Invalid backup code!', 'sg-security' ),
				)
			);
		}

		// Update the user meta if this is the inital 2FA setup.
		if ( isset( $_POST['sgs-2fa-setup'] ) ) { // phpcs:ignore
			update_user_meta( $user->ID, 'sg_security_2fa_configured', 1 ); // phpcs:ignore
		}

		$this->set_2fa_cookie( $user->ID );

		// Interim login.
		$this->interim_check();

		// Get the redirect url.
		$redirect_url = ! empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : get_admin_url(); // phpcs:ignore

		if ( ! isset( $_POST['backup-code-used'] ) ) { // phpcs:ignore
			// Retirect to the reset url.
			wp_safe_redirect( esc_url_raw( wp_unslash( $redirect_url ) ) );
		}

		// Show QR code.
		$this->show_qr_backup_code_used( $user );
	}

	/**
	 * Validate 2FA login
	 *
	 * @since  1.1.0
	 */
	public function validate_2fa_login( $user ) {
		// Get the user object from authentication cookie.
		$user = $this->get_auth_user();

		// Bail if user is not authenticated.
		if ( false === $user ) {
			return;
		}

		// Check the result of the authtication.
		if ( false === $this->check_authentication_code( wp_unslash( $_POST['sgc2facode'] ), $user->ID ) ) {
			if ( empty( get_user_meta( $user->ID, 'sg_security_2fa_configured', true ) ) ) { // phpcs:ignore
				// Arguments for initial 2fa setup.
				$this->load_form(
					array(
						'template' => '2fa-initial-setup-form.php',
						'qr'       => get_user_meta( $user->ID, 'sg_security_2fa_qr', true ), // phpcs:ignore
						'secret'   => get_user_meta( $user->ID, 'sg_security_2fa_secret', true ), // phpcs:ignore
						'error'    => esc_html__( 'Invalid verification code!', 'sg-security' ),
						'action'   => esc_url( add_query_arg( 'action', 'sgs2fa', wp_login_url() ) ),
					)
				);
			}

			$this->load_form(
				array(
					'template' => '2fa-login.php',
					'error'    => esc_html__( 'Invalid verification code!', 'sg-security' ),
					'action'   => esc_url( add_query_arg( 'action', 'sgs2fa', wp_login_url() ) ),
				)
			);
		}

		// Update the user meta if this is the inital 2FA setup.
		if ( isset( $_POST['sgs-2fa-setup'] ) ) { // phpcs:ignore
			update_user_meta( $user->ID, 'sg_security_2fa_configured', 1 ); // phpcs:ignore
		}

		$do_not_challenge = isset( $_POST['do_not_challenge'] ) ? 1 : 0;
		
		$this->set_2fa_cookie( $user->ID, $do_not_challenge );

		// Interim login.
		$this->interim_check();

		// Get the redirect url.
		$redirect_url = ! empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : get_admin_url(); // phpcs:ignore

		// Show backup codes to the user in the initial 2FA setup.
		if ( isset( $_POST['sgs-2fa-setup'] ) ) { // phpcs:ignore
			$this->show_backup_codes( $user );
		}

		// Retirect to the reset url.
		wp_safe_redirect( esc_url_raw( wp_unslash( $redirect_url ) ) );
	}

	/**
	 * Check for all users with 2fa setup.
	 *
	 * @since 1.1.1
	 *
	 * @return array The array containining the users using 2FA.
	 */
	public function check_for_users_using_2fa() {
		// Get all users with 2FA configured.
		$users = get_users(
			array(
				'role__in'   => $this->get_2fa_user_roles(),
				'orderby'    => 'user_login',
				'order'      => 'ASC',
				'fields'     => array(
					'ID',
					'user_login',
				),
				'meta_query' => array(
					array(
						'key'     => 'sg_security_2fa_configured',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		return $users;
	}

	/**
	 * Verify if the user has completed the 2FA login.
	 *
	 * @since  1.2.5
	 *
	 * @param  int   $user_id       The user ID.
	 *
	 * @return mixed false|$user_id False for failure, user ID for successfull 2FA login.
	 */
	public function verify_2fa( $user_id ) {
		if ( empty( $user_id ) ) {
			return $user_id;
		}

		// Get 2FA user roles.
		$roles = apply_filters( 'sg_security_2fa_roles', $this->roles );

		// Get the current user role.
		$user = get_user_by( 'ID', $user_id );

		// Bail if the user don't need 2FA.
		if ( empty( array_intersect( $this->get_2fa_user_roles(), $user->roles ) ) ) {
			return $user_id;
		}

		// Bail if the user has a valid 2fa cookie.
		if ( $this->validate_2fa_cookie( $user ) ) {
			return $user_id;
		}

		return false;
	}

	/**
	 * Set 30 days 2FA auth cookie.
	 *
	 * @since 1.1.1
	 *
	 * @param int $user_id          The user ID.
	 * @param int $do_not_challenge Wheather do not challenge was used.
	 */
	public function set_2fa_cookie( $user_id, $do_not_challenge = 0 ) {
		// Generate unique token for the user.
		$token = md5( get_user_meta( $user_id, 'sg_security_2fa_secret', true ) ); // phpcs:ignore

		$user = get_user_by( 'ID', $user_id );

		$data = array(
			'token'       => $user->user_login,
			'auto_expire' => $do_not_challenge
		);

		$expiration = 1 === $do_not_challenge ? time() + 2592000 : time() + 172800;

		// Set the 2FA auth cookie.
		setcookie( 'sg_security_2fa_' . $user_id . '_cookie', Helper::sgs_encrypt( $data, $token ), $expiration, SITECOOKIEPATH, COOKIE_DOMAIN ); // phpcs:ignore
	}

	/**
	 * Validate the 2FA cookie.
	 *
	 * @since  1.2.5
	 *
	 * @param  string $user The user.
	 *
	 * @return bool         True if the cookie is valid, false otherwise.
	 */
	public function validate_2fa_cookie( $user ) {
		// Bail if user ID is empty.
		if ( empty( $user->ID ) ) {
			return false;
		}

		$cookie = 'sg_security_2fa_' . $user->ID . '_cookie';

		// Bail if cookie is not set.
		if ( ! isset( $_COOKIE[ $cookie ] ) ) {
			return false;
		}

		// Get the 2FA cookie data.
		$data = $this->get_2fa_cookie_data( $user->ID );

		// Return true if the cookie is valid.
		if ( $data[0] === $user->user_login ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the 2FA cookie data.
	 *
	 * @since  1.2.5
	 *
	 * @param  int    $user_id        The user ID.
	 *
	 * @return string $decrypted_data Cookie data decrypted.
	 */
	public function get_2fa_cookie_data( $user_id ) {
		$cookie = 'sg_security_2fa_' . $user_id . '_cookie';

		// Generate unique token for the user.
		$token = md5( get_user_meta( $user_id, 'sg_security_2fa_secret', true ) ); // phpcs:ignore

		// Decrypt the cookie.
		$decrypted_data = Helper::sgs_decrypt( $_COOKIE[ $cookie ], $token );

		return $decrypted_data;
	}

	/**
	 * Init 2FA.
	 *
	 * @since 1.2.5
	 *
	 * @param string $user_login Username.
	 * @param object $user       WP_User object of the logged-in user.
	 */
	public function init_2fa( $user_login, $user ) {
		// Bail if the user role does not allow 2FA setup.
		if ( empty( array_intersect( $this->get_2fa_user_roles(), $user->roles ) ) ) {
			return;
		}

		// Bail if the user doesn't have secret.
		if ( empty( get_user_meta( $user->ID, 'sg_security_2fa_secret', true ) ) ) {
			return;
		}

		// Bail if there is a valid 2FA cookie.
		if ( $this->validate_2fa_cookie( $user ) ) {
			return;
		}

		if ( 1 == get_user_meta( $user->ID, 'sg_security_2fa_configured', true ) ) { // phpcs:ignore
			// Load the 2fa login form.
			$this->load_form(
				array(
					'action'   => esc_url( add_query_arg( 'action', 'sgs2fa', wp_login_url() ) ),
					'template' => '2fa-login.php',
					'error'    => '',
				)
			);
		}

		// Load the 2fa setup form.
		$this->load_form(
			array(
				'action'   => esc_url( add_query_arg( 'action', 'sgs2fa', wp_login_url() ) ),
				'template' => '2fa-initial-setup-form.php',
				'error'    => '',
				'qr'       => get_user_meta( $user->ID, 'sg_security_2fa_qr', true ), // phpcs:ignore
				'secret'   => get_user_meta( $user->ID, 'sg_security_2fa_secret', true ), // phpcs:ignore
			)
		);
	}

	/**
	 * Load the backup codes form.
	 *
	 * @since  1.1.0
	 */
	public function load_backup_codes_form() {
		// Get the user object from authentication cookie.
		$user = $this->get_auth_user();

		// Bail if user is not authenticated.
		if ( false === $user ) {
			return;
		}

		// Load the form.
		$this->load_form(
			array(
				'template' => '2fa-login-backup-code.php',
				'action'   => esc_url( add_query_arg( 'action', 'sgs2fabc', wp_login_url() ) ),
				'error'    => '',
			)
		);
	}

	/**
	 * Reset 2FA cookie on logout.
	 *
	 * @since 1.2.5
	 *
	 * @param int   $user_id ID of the user that was logged out.
	 */
	public function logout( $user_id ) {
		$cookie = 'sg_security_2fa_' . $user_id . '_cookie';

		// Get the 2FA cookie data.
		$cookie_data = $this->get_2fa_cookie_data( $user_id );

		// Bail if 2FA cookie is not set.
		if ( empty( $cookie_data ) ) {
			return;
		}

		// Reset the 2FA cookie for the user.
		if ( intval( $cookie_data[1] ) === 0 ) {
			setcookie( $cookie, null, -1, SITECOOKIEPATH, COOKIE_DOMAIN ); // phpcs:ignore
		}
	}

	/**
	 * Maybe redirect to login page.
	 *
	 * @since 1.2.5
	 */
	public function maybe_redirect_to_login() {
		// Get the user object from authentication cookie.
		$user = $this->get_auth_user();

		// Bail if user is not authenticated.
		if ( false === $user ) {
			return;
		}

		if ( 1 == get_user_meta( $user->ID, 'sg_security_2fa_configured', true ) ) { // phpcs:ignore
			// Load the 2fa login form.
			$this->load_form(
				array(
					'action'   => esc_url( add_query_arg( 'action', 'sgs2fa', wp_login_url() ) ),
					'template' => '2fa-login.php',
					'error'    => '',
				)
			);
		}

		// Load the 2fa setup form.
		$this->load_form(
			array(
				'action'   => esc_url( add_query_arg( 'action', 'sgs2fa', wp_login_url() ) ),
				'template' => '2fa-initial-setup-form.php',
				'error'    => '',
				'qr'       => get_user_meta( $user->ID, 'sg_security_2fa_qr', true ), // phpcs:ignore
				'secret'   => get_user_meta( $user->ID, 'sg_security_2fa_secret', true ), // phpcs:ignore
			)
		);
	}

	/**
	 * Get the user from WP Authentication cookie.
	 *
	 * @since  1.2.5
	 *
	 * @return mixed false|$user WP_User object on success, false on failure.
	 */
	public function get_auth_user() {
		// Bail if user does not have valid WP authentication.
		if ( ! isset( $_COOKIE['wordpress_logged_in_' . COOKIEHASH] ) ) {
			return false;
		}

		// Get the user ID.
		$user_id = wp_validate_auth_cookie( $_COOKIE['wordpress_logged_in_' . COOKIEHASH], 'logged_in');

		// Bail if the user ID is empty.
		if ( empty( $user_id ) ) {
			return false;
		}

		// Get the user by ID.
		$user = get_user_by( 'ID', $user_id );

		// Return the user object.
		return $user;
	}
}

<?php
namespace SG_Security\Install_Service;

use SG_Security\Install_Service\Install;
use SG_Security\Options_Service\Options_Service;
use SG_Security\Htaccess_Service\Hsts_Service;

/**
 * The instalation package version class.
 */
class Install_1_2_5 extends Install {

	/**
	 * The default install version. Overridden by the installation packages.
	 *
	 * @since 1.2.5
	 *
	 * @access protected
	 *
	 * @var string $version The install version.
	 */
	protected static $version = '1.2.5';

	/**
	 * Run the install procedure.
	 *
	 * @since 1.2.5
	 */
	public function install() {
		$this->convert_2fa_roles_filter_to_db();
		$this->remove_hsts_settings();
	}

	/**
	 * Convert custom 2fa roles from the filter to a record in the db.
	 *
	 * @since  1.2.5
	 */
	public function convert_2fa_roles_filter_to_db() {
		// Get any custom roles added with the filter.
		$custom_user_roles = apply_filters( 'sg_security_2fa_roles', array() );

		// Bail if no custom user roles are present.
		if ( empty( $custom_user_roles ) ) {
			return;
		}

		// Save the custom 2fa roles in the db.
		update_option( 'sg_security_2fa_roles', $custom_user_roles );
	}

	/**
	 * Remove the hsts settings.
	 *
	 * @since  1.2.5
	 */
	public function remove_hsts_settings() {
		// Initialize HSTS instance.
		$hsts_service = new Hsts_Service();

		// Toggle the hsts off.
		$hsts_service->toggle_rules( 0 );

		// Toggle off the option as well.
		Options_Service::change_option( 'hsts_protection', 0 );
	}
}

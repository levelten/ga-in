<?php
/**
 * Removes all plugin data when the plugin is deleted.
 *
 * @package GAinWP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Like gainwp.php, this file must parse on old PHP. The plugin never ran
// below 7.4, so there is nothing of 6.x to clean up there.

// @phpstan-ignore-next-line if.alwaysFalse
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Upgrade.php';

/**
 * Deletes the plugin's options for the current site, including 5.x leftovers.
 *
 * @return void
 */
function gainwp_uninstall_site() {
	delete_option( \GAinWP\Options::OPTION );
	delete_option( \GAinWP\Upgrade::VERSION_OPTION );
	delete_option( \GAinWP\Upgrade::LEGACY_BACKUP_OPTION );
	delete_option( \GAinWP\Upgrade::WARNINGS_OPTION );
	\GAinWP\Upgrade::delete_legacy_options();
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $gainwp_site_id ) {
		switch_to_blog( $gainwp_site_id );
		gainwp_uninstall_site();
		restore_current_blog();
	}
	delete_site_option( 'gainwp_network_options' );
} else {
	gainwp_uninstall_site();
}

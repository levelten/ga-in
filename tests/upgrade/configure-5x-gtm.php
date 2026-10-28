<?php
/**
 * Configures GAinWP 5.4.6 as a Google Tag Manager site.
 *
 * @package GAinWP
 */

require __DIR__ . '/lib.php';

gainwp_check( '5.4.6 is installed', GAINWP_CURRENT_VERSION, '5.4.6' );

$gainwp_config = GAINWP()->config;
foreach (
	array(
		'tracking_type'   => 'tagmanager',
		'web_containerid' => 'GTM-ABC1234',
		'tm_author_var'   => 1,
		'tm_user_var'     => 1,
	) as $gainwp_key => $gainwp_value
) {
	$gainwp_config->options[ $gainwp_key ] = $gainwp_value;
}
$gainwp_config->set_plugin_options();

gainwp_check( '5.x settings stored', json_decode( get_option( 'gainwp_options' ), true )['web_containerid'], 'GTM-ABC1234' );

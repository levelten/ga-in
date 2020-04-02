<?php
/**
 * Copyright 2013 Alin Marcu
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

final class GAINWP_Settings {

	private static function update_options( $who, $validation_error = 0 ) {
		$gainwp = GAINWP();
		$network_settings = false;
		$options = $gainwp->config->options; // Get current options
		if ( isset( $_POST['options']['gainwp_hidden'] ) && isset( $_POST['options'] ) && ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) && !$validation_error && 'Reset' != $who ) {
			$new_options = $_POST['options'];
			if ( 'tracking' == $who ) {
				$options['ga_anonymize_ip'] = 0;
				$options['ga_optout'] = 0;
				$options['ga_dnt_optout'] = 0;
				$options['ga_event_tracking'] = 0;
				$options['ga_enhanced_links'] = 0;
				$options['ga_event_precision'] = 0;
				$options['ga_remarketing'] = 0;
				$options['ga_event_bouncerate'] = 0;
				$options['ga_crossdomain_tracking'] = 0;
				$options['ga_aff_tracking'] = 0;
				$options['ga_hash_tracking'] = 0;
				$options['ga_formsubmit_tracking'] = 0;
				$options['ga_force_ssl'] = 0;
				$options['ga_pagescrolldepth_tracking'] = 0;
				$options['tm_pagescrolldepth_tracking'] = 0;
				$options['tm_optout'] = 0;
				$options['tm_dnt_optout'] = 0;
				$options['amp_tracking_analytics'] = 0;
				$options['amp_tracking_clientidapi'] = 0;
				$options['amp_tracking_tagmanager'] = 0;
				$options['optimize_pagehiding'] = 0;
				$options['optimize_tracking'] = 0;
				$options['trackingcode_infooter'] = 0;
				$options['trackingevents_infooter'] = 0;
				$options['ga_with_gtag'] = 0;
				if ( isset( $_POST['options']['ga_tracking_code'] ) ) {
					$new_options['ga_tracking_code'] = trim( $new_options['ga_tracking_code'], "\t" );
				}
				if ( empty( $new_options['track_exclude'] ) ) {
					$new_options['track_exclude'] = array();
				}
			} elseif ( 'reporting' == $who ) {
				$options['switch_profile'] = 0;
				$options['backend_item_reports'] = 0;
				$options['dashboard_widget'] = 0;
				$options['backend_realtime_report'] = 0;
				if ( empty( $new_options['access_back'] ) ) {
					$new_options['access_back'][] = 'administrator';
				}
				$options['frontend_item_reports'] = 0;
				if ( empty( $new_options['access_front'] ) ) {
					$new_options['access_front'][] = 'administrator';
				}
			} elseif ( 'general' == $who ) {
				$options['user_api'] = 0;
				if ( ! is_multisite() ) {
					$options['automatic_updates_minorversion'] = 0;
				}
			} elseif ( 'network' == $who ) {
				$options['user_api'] = 0;
				$options['network_mode'] = 0;
				$options['superadmin_tracking'] = 0;
				$options['automatic_updates_minorversion'] = 0;
				$network_settings = true;
			}
			$options = array_merge( $options, $new_options );
			$gainwp->config->options = $options;
			$gainwp->config->set_plugin_options( $network_settings );
		}
		return $options;
	}

	private static function navigation_tabs( $tabs ) {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $name ) {
			echo "<a class='nav-tab' id='tab-$tab' href='#top#gainwp-$tab'>$name</a>";
		}
		echo '</h2>';
	}

	private static function global_notices( $who, &$validation_error = 0, $options = array() ) {
	  $gainwp = GAINWP();
	  $message = '';

	  if ( isset( $_POST['options']['gainwp_hidden'] ) ) {
	    if ( ! ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) ) {
        $message .= "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
        $validation_error = 1;
      }
      if ( ! $validation_error && empty($options['disable_settings_saved_msgs'])) {
        $message .= "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Settings saved.", 'ga-in' ) . "</p></div>";
      }
      elseif ( empty($options['disable_settings_saved_msgs']) ) {
        $message .= "<div class='error' id='gainwp-autodismiss'><p>" . __( "Settings not saved.", 'ga-in' ) . "</p></div>";
      }
    }

		if ( ! $gainwp->config->options['tableid_jail'] && ! $gainwp->config->options['tracking_id']) {
			$message = sprintf( '<div class="error"><p>%s</p></div>', sprintf( __( 'Something went wrong, check %1$s or %2$s.', 'ga-in' ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_errors_debugging', false ), __( 'Errors & Debug', 'ga-in' ) ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_general_settings', false ), __( 'authorize the plugin', 'ga-in' ) ) ) );
		}
		return $message;
	}

	public static function tracking_settings() {
		$gainwp = GAINWP();

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$message = '';
    $validation_error = 0;

    if (!empty($_POST['options']['tracking_id']) && !preg_match('/^ua-\d{4,9}-\d{1,4}$/i', $_POST['options']['tracking_id'])) {
      $validation_error = 1;
      $message = sprintf( '<div class="error"><p>%s</p></div>', __( 'Tracking ID must be in the format of UA-12345678-9.', 'ga-in' ) );
    }

		$message .= self::global_notices( 'tracking',  $validation_error);
		$options = self::update_options( 'tracking', $validation_error );

		/*
		if ( isset( $_POST['options']['gainwp_hidden'] ) ) {
			$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Settings saved.", 'ga-in' ) . "</p></div>";
			if ( ! ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) ) {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( ! $gainwp->config->options['tableid_jail'] ) {
			$message = sprintf( '<div class="error"><p>%s</p></div>', sprintf( __( 'Something went wrong, check %1$s or %2$s.', 'ga-in' ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_errors_debugging', false ), __( 'Errors & Debug', 'ga-in' ) ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_settings', false ), __( 'authorize the plugin', 'ga-in' ) ) ) );
		}
		*/
		?>
<form name="gainwp_form" method="post" action="<?php  esc_url($_SERVER['REQUEST_URI']); ?>">
	<div class="wrap">
			<?php echo "<h2>" . __( "Google Analytics Tracking Settings", 'ga-in' ) . "</h2>"; ?>
	</div>
	<div id="poststuff" class="gainwp">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div class="settings-wrapper">
					<div class="inside">
						<?php if ( 'universal' == $options['tracking_type'] ) :?>
						<?php $tabs = array( 'basic' => __( "Basic Settings", 'ga-in' ), 'events' => __( "Events Tracking", 'ga-in' ), 'custom' => __( "Custom Definitions", 'ga-in' ), 'exclude' => __( "Exclude Tracking", 'ga-in' ), 'advanced' => __( "Advanced Settings", 'ga-in' ), 'integration' => __( "Integration", 'ga-in' ) );?>
						<?php elseif ( 'tagmanager' == $options['tracking_type'] ) :?>
						<?php $tabs = array( 'basic' => __( "Basic Settings", 'ga-in' ), 'tmdatalayervars' => __( "DataLayer Variables", 'ga-in' ), 'exclude' => __( "Exclude Tracking", 'ga-in' ), 'tmadvanced' =>  __( "Advanced Settings", 'ga-in' ), 'tmintegration' => __( "Integration", 'ga-in' ) );?>
						<?php else :?>
						<?php $tabs = array( 'basic' => __( "Basic Settings", 'ga-in' ) );?>
						<?php endif; ?>
						<?php self::navigation_tabs( $tabs ); ?>
						<?php if ( isset( $message ) ) : ?>
							<?php echo $message; ?>
						<?php endif; ?>
						<div id="gainwp-basic">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Tracking Settings", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tracking_type"><?php _e("Tracking Type:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tracking_type" name="options[tracking_type]" onchange="this.form.submit()">
											<option value="universal" <?php selected( $options['tracking_type'], 'universal' ); ?>><?php _e("Analytics", 'ga-in');?></option>
											<option value="tagmanager" <?php selected( $options['tracking_type'], 'tagmanager' ); ?>><?php _e("Tag Manager", 'ga-in');?></option>
											<option value="disabled" <?php selected( $options['tracking_type'], 'disabled' ); ?>><?php _e("Disabled", 'ga-in');?></option>
										</select>
									</td>
								</tr>
								<?php if ( 'universal' == $options['tracking_type'] ) : ?>
								<tr>
									<td class="gainwp-settings-title"></td>
									<td>
										<?php $profile_info = GAINWP_Tools::get_selected_profile($gainwp->config->options['ga_profiles_list'], $gainwp->config->options['tableid_jail']); ?>
										<?php if (!empty($profile_info[2])) : ?>
										  <?php echo '<pre>' . __("View Name:", 'ga-in') . "\t" . esc_html($profile_info[0]) . "<br />" . __("Tracking ID:", 'ga-in') . "\t" . esc_html($profile_info[2]) . "<br />" . __("Default URL:", 'ga-in') . "\t" . esc_html($profile_info[3]) . "<br />" . __("Time Zone:", 'ga-in') . "\t" . esc_html($profile_info[5]) . '</pre>';?>
									  <?php else : ?>
									  <tr>
                      <td class="gainwp-settings-title">
                        <label for="tracking_id"><?php _e("Tracking ID:", 'ga-in' ); ?>
                        </label>
                      </td>
                      <td>
                        <input type="text" name="options[tracking_id]" value="<?php echo esc_attr($options['tracking_id']); ?>" size="15">
                      </td>
                    </tr>
									  <?php endif; ?>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_with_gtag]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_with_gtag" <?php checked( $options['ga_with_gtag'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_with_gtag">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("use global site tag gtag.js (not recommended)", 'ga-in' );?></div>
									</td>
								</tr>
								<?php elseif ( 'tagmanager' == $options['tracking_type'] ) : ?>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tracking_type"><?php _e("Web Container ID:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<input type="text" name="options[web_containerid]" value="<?php echo esc_attr($options['web_containerid']); ?>" size="15">
									</td>
								</tr>
								<?php endif; ?>
								<tr>
									<td class="gainwp-settings-title">
										<label for="trackingcode_infooter"><?php _e("Code Placement:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="trackingcode_infooter" name="options[trackingcode_infooter]">
											<option value="0" <?php selected( $options['trackingcode_infooter'], 0 ); ?>><?php _e("HTML Head", 'ga-in');?></option>
											<option value="1" <?php selected( $options['trackingcode_infooter'], 1 ); ?>><?php _e("HTML Body", 'ga-in');?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-events">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Events Tracking", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_event_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_event_tracking" <?php checked( $options['ga_event_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_event_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("track downloads, mailto, telephone and outbound links", 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_aff_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_aff_tracking" <?php checked( $options['ga_aff_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_aff_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("track affiliate links", 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_hash_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_hash_tracking" <?php checked( $options['ga_hash_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_hash_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("track fragment identifiers, hashmarks (#) in URI links", 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_formsubmit_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_formsubmit_tracking" <?php checked( $options['ga_formsubmit_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_formsubmit_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("track form submit actions", 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_pagescrolldepth_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_pagescrolldepth_tracking" <?php checked( $options['ga_pagescrolldepth_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_pagescrolldepth_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("track page scrolling depth", 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_event_downloads"><?php _e("Downloads Regex:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="text" id="ga_event_downloads" name="options[ga_event_downloads]" value="<?php echo esc_attr($options['ga_event_downloads']); ?>" size="50">
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_event_affiliates"><?php _e("Affiliates Regex:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="text" id="ga_event_affiliates" name="options[ga_event_affiliates]" value="<?php echo esc_attr($options['ga_event_affiliates']); ?>" size="50">
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="trackingevents_infooter"><?php _e("Code Placement:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="trackingevents_infooter" name="options[trackingevents_infooter]">
											<option value="0" <?php selected( $options['trackingevents_infooter'], 0 ); ?>><?php _e("HTML Head", 'ga-in');?></option>
											<option value="1" <?php selected( $options['trackingevents_infooter'], 1 ); ?>><?php _e("HTML Body", 'ga-in');?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-custom">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Custom Dimensions", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_author_dimindex"><?php _e("Authors (display name):", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_author_dimindex" name="options[ga_author_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
											<option value="<?php echo $i;?>" <?php selected( $options['ga_author_dimindex'], $i ); ?>><?php echo 0 == $i ?'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_author_dimindex"><?php _e("Authors (user login):", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_author_dimindex" name="options[ga_author_login_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
											<option value="<?php echo $i;?>" <?php selected( $options['ga_author_login_dimindex'], $i ); ?>><?php echo 0 == $i ?'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_pubyear_dimindex"><?php _e("Publication Year:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_pubyear_dimindex" name="options[ga_pubyear_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
											<option value="<?php echo $i;?>" <?php selected( $options['ga_pubyear_dimindex'], $i ); ?>><?php echo 0 == $i ?'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_pubyearmonth_dimindex"><?php _e("Publication Month:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_pubyearmonth_dimindex" name="options[ga_pubyearmonth_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
											<option value="<?php echo $i;?>" <?php selected( $options['ga_pubyearmonth_dimindex'], $i ); ?>><?php echo 0 == $i ?'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_category_dimindex"><?php _e("Categories:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_category_dimindex" name="options[ga_category_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
											<option value="<?php echo $i;?>" <?php selected( $options['ga_category_dimindex'], $i ); ?>><?php echo 0 == $i ? 'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_user_dimindex"><?php _e("User Type:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_user_dimindex" name="options[ga_user_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
											<option value="<?php echo $i;?>" <?php selected( $options['ga_user_dimindex'], $i ); ?>><?php echo 0 == $i ? 'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_tag_dimindex"><?php _e("Tags:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ga_tag_dimindex" name="options[ga_tag_dimindex]">
										<?php for ($i=0;$i<21;$i++) : ?>
										<option value="<?php echo $i;?>" <?php selected( $options['ga_tag_dimindex'], $i ); ?>><?php echo 0 == $i ? 'Disabled':'dimension '.$i; ?></option>
										<?php endfor; ?>
										</select>
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-tmdatalayervars">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Main Variables", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_author_var"><?php _e("Authors (display name):", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_author_var" name="options[tm_author_var]">
											<option value="1" <?php selected( $options['tm_author_var'], 1 ); ?>>gainwpAuthor</option>
											<option value="0" <?php selected( $options['tm_author_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_author_var"><?php _e("Authors (user login):", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_author_var" name="options[tm_author_login_var]">
											<option value="1" <?php selected( $options['tm_author_login_var'], 1 ); ?>>gainwpAuthor</option>
											<option value="0" <?php selected( $options['tm_author_login_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_pubyear_var"><?php _e("Publication Year:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_pubyear_var" name="options[tm_pubyear_var]">
											<option value="1" <?php selected( $options['tm_pubyear_var'], 1 ); ?>>gainwpPublicationYear</option>
											<option value="0" <?php selected( $options['tm_pubyear_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_pubyearmonth_var"><?php _e("Publication Month:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_pubyearmonth_var" name="options[tm_pubyearmonth_var]">
											<option value="1" <?php selected( $options['tm_pubyearmonth_var'], 1 ); ?>>gainwpPublicationYearMonth</option>
											<option value="0" <?php selected( $options['tm_pubyearmonth_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_category_var"><?php _e("Categories:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_category_var" name="options[tm_category_var]">
											<option value="1" <?php selected( $options['tm_category_var'], 1 ); ?>>gainwpCategory</option>
											<option value="0" <?php selected( $options['tm_category_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_user_var"><?php _e("User Type:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_user_var" name="options[tm_user_var]">
											<option value="1" <?php selected( $options['tm_user_var'], 1 ); ?>>gainwpUser</option>
											<option value="0" <?php selected( $options['tm_user_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tm_tag_var"><?php _e("Tags:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="tm_tag_var" name="options[tm_tag_var]">
											<option value="1" <?php selected( $options['tm_tag_var'], 1 ); ?>>gainwpTag</option>
											<option value="0" <?php selected( $options['tm_tag_var'], 0 ); ?>><?php _e( "Disabled", 'ga-in' ); ?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-advanced">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Advanced Tracking", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_speed_samplerate"><?php _e("Speed Sample Rate:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="number" id="ga_speed_samplerate" name="options[ga_speed_samplerate]" value="<?php echo (int)($options['ga_speed_samplerate']); ?>" max="100" min="1">
										%
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_user_samplerate"><?php _e("User Sample Rate:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="number" id="ga_user_samplerate" name="options[ga_user_samplerate]" value="<?php echo (int)($options['ga_user_samplerate']); ?>" max="100" min="1">
										%
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_anonymize_ip]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_anonymize_ip" <?php checked( $options['ga_anonymize_ip'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_anonymize_ip">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("anonymize IPs while tracking", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_optout]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_optout" <?php checked( $options['ga_optout'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_optout">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable support for user opt-out", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_dnt_optout]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_dnt_optout" <?php checked( $options['ga_dnt_optout'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_dnt_optout">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"> <?php _e( 'exclude tracking for users sending Do Not Track header', 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_remarketing]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_remarketing" <?php checked( $options['ga_remarketing'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_remarketing">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable remarketing, demographics and interests reports", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_event_bouncerate]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_event_bouncerate" <?php checked( $options['ga_event_bouncerate'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_event_bouncerate">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("exclude events from bounce-rate and time on page calculation", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_enhanced_links]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_enhanced_links" <?php checked( $options['ga_enhanced_links'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_enhanced_links">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable enhanced link attribution", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_event_precision]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_event_precision" <?php checked( $options['ga_event_precision'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_event_precision">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("use hitCallback to increase event tracking accuracy", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_force_ssl]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_force_ssl" <?php checked( $options['ga_force_ssl'] || $options['ga_with_gtag'], 1 ); ?>  <?php disabled( $options['ga_with_gtag'], true );?>>
											<label class="gainwp-settings-switchoo-label" for="ga_force_ssl">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable Force SSL", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Cross-domain Tracking", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[ga_crossdomain_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="ga_crossdomain_tracking" <?php checked( $options['ga_crossdomain_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="ga_crossdomain_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable cross domain tracking", 'ga-in' ); ?></div>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_crossdomain_list"><?php _e("Cross Domains:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="text" id="ga_crossdomain_list" name="options[ga_crossdomain_list]" value="<?php echo esc_attr($options['ga_crossdomain_list']); ?>" size="50">
									</td>
								</tr>
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Cookie Customization", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_cookiedomain"><?php _e("Cookie Domain:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="text" id="ga_cookiedomain" name="options[ga_cookiedomain]" value="<?php echo esc_attr($options['ga_cookiedomain']); ?>" size="50">
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_cookiename"><?php _e("Cookie Name:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="text" id="ga_cookiename" name="options[ga_cookiename]" value="<?php echo esc_attr($options['ga_cookiename']); ?>" size="50">
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="ga_cookieexpires"><?php _e("Cookie Expires:", 'ga-in'); ?>
										</label>
									</td>
									<td>
										<input type="text" id="ga_cookieexpires" name="options[ga_cookieexpires]" value="<?php echo esc_attr($options['ga_cookieexpires']); ?>" size="10">
										<?php _e("seconds", 'ga-in' ); ?>
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-integration">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Accelerated Mobile Pages (AMP)", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[amp_tracking_analytics]" value="1" class="gainwp-settings-switchoo-checkbox" id="amp_tracking_analytics" <?php checked( $options['amp_tracking_analytics'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="amp_tracking_analytics">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable tracking for Accelerated Mobile Pages (AMP)", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[amp_tracking_clientidapi]" value="1" class="gainwp-settings-switchoo-checkbox" id="amp_tracking_clientidapi" <?php checked( $options['amp_tracking_clientidapi'] && !$options['ga_with_gtag'], 1 ); ?> <?php disabled( $options['ga_with_gtag'], true );?>>
											<label class="gainwp-settings-switchoo-label" for="amp_tracking_clientidapi">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable Google AMP Client Id API", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Ecommerce", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tracking_type"><?php _e("Ecommerce Tracking:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<select id="ecommerce_mode" name="options[ecommerce_mode]" <?php disabled( $options['ga_with_gtag'], true );?>>
											<option value="disabled" <?php selected( $options['ecommerce_mode'], 'disabled' ); ?>><?php _e("Disabled", 'ga-in');?></option>
											<option value="standard" <?php selected( $options['ecommerce_mode'], 'standard' ); ?>><?php _e("Ecommerce Plugin", 'ga-in');?></option>
											<option value="enhanced" <?php selected( $options['ecommerce_mode'], 'enhanced' ); selected( $options['ga_with_gtag'], true );?>><?php _e("Enhanced Ecommerce Plugin", 'ga-in');?></option>
										</select>
									</td>
								</tr>
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Optimize", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[optimize_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="optimize_tracking" <?php checked( $options['optimize_tracking'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="optimize_tracking">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable Optimize tracking", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[optimize_pagehiding]" value="1" class="gainwp-settings-switchoo-checkbox" id="optimize_pagehiding" <?php checked( $options['optimize_pagehiding'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="optimize_pagehiding">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable Page Hiding support", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tracking_type"><?php _e("Container ID:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<input type="text" name="options[optimize_containerid]" value="<?php echo esc_attr($options['optimize_containerid']); ?>" size="15">
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-tmintegration">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Accelerated Mobile Pages (AMP)", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td colspan="2" class="gainwp-settings-title">
										<div class="button-primary gainwp-settings-switchoo">
											<input type="checkbox" name="options[amp_tracking_tagmanager]" value="1" class="gainwp-settings-switchoo-checkbox" id="amp_tracking_tagmanager" <?php checked( $options['amp_tracking_tagmanager'], 1 ); ?>>
											<label class="gainwp-settings-switchoo-label" for="amp_tracking_tagmanager">
												<div class="gainwp-settings-switchoo-inner"></div>
												<div class="gainwp-settings-switchoo-switch"></div>
											</label>
										</div>
										<div class="switch-desc"><?php echo " ".__("enable tracking for Accelerated Mobile Pages (AMP)", 'ga-in' );?></div>
									</td>
								</tr>
								<tr>
									<td class="gainwp-settings-title">
										<label for="tracking_type"><?php _e("AMP Container ID:", 'ga-in' ); ?>
										</label>
									</td>
									<td>
										<input type="text" name="options[amp_containerid]" value="<?php echo esc_attr($options['amp_containerid']); ?>" size="15">
									</td>
								</tr>
							</table>
						</div>
						<div id="gainwp-exclude">
							<table class="gainwp-settings-options">
								<tr>
									<td colspan="2"><?php echo "<h2>" . __( "Exclude Tracking", 'ga-in' ) . "</h2>"; ?></td>
								</tr>
								<tr>
									<td class="roles gainwp-settings-title">
										<label for="track_exclude"><?php _e("Exclude tracking for:", 'ga-in' ); ?></label>
									</td>
									<td class="gainwp-settings-roles">
										<table>
											<tr>
										<?php if ( ! isset( $wp_roles ) ) : ?>
											<?php $wp_roles = new WP_Roles(); ?>
										<?php endif; ?>
										<?php $i = 0; ?>
										<?php foreach ( $wp_roles->role_names as $role => $name ) : ?>
											<?php if ( 'subscriber' != $role ) : ?>
												<?php $i++; ?>
											<td>
													<label>
														<input type="checkbox" name="options[track_exclude][]" value="<?php echo $role; ?>" <?php if (in_array($role,$options['track_exclude'])) echo 'checked="checked"'; ?> /> <?php echo $name; ?>
											</label>
												</td>
											<?php endif; ?>
											<?php if ( 0 == $i % 4 ) : ?>
										 	</tr>
											<tr>
											<?php endif; ?>
										<?php endforeach; ?>
										</table>
									</td>
								</tr>
							</table>
						</div>
						<table class="gainwp-settings-options">
							<tr>
								<td colspan="2">
									<hr>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="submit">
									<input type="submit" name="Submit" class="button button-primary" value="<?php _e('Save Changes', 'ga-in' ) ?>" />
								</td>
							</tr>
						</table>
						<input type="hidden" name="options[gainwp_hidden]" value="Y">
						<?php wp_nonce_field('gainwp_form','gainwp_security'); ?>
          </form>
<?php
		self::output_sidebar();
	}

	public static function errors_debugging() {

		$gainwp = GAINWP();

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$anonim = GAINWP_Tools::anonymize_options( $gainwp->config->options );

		$options = self::update_options( 'frontend' );
		$message = self::global_notices( 'frontend' );
		/*
		if ( ! $gainwp->config->options['tableid_jail'] || ! $gainwp->config->options['token'] ) {
			$message = sprintf( '<div class="error"><p>%s</p></div>', sprintf( __( 'Something went wrong, check %1$s or %2$s.', 'ga-in' ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_errors_debugging', false ), __( 'Errors & Debug', 'ga-in' ) ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_settings', false ), __( 'authorize the plugin', 'ga-in' ) ) ) );
		}
		*/
		?>
<div class="wrap">
		<?php echo "<h2>" . __( "Google Analytics Errors & Debugging", 'ga-in' ) . "</h2>"; ?>
</div>
<div id="poststuff" class="gainwp">
	<div id="post-body" class="metabox-holder columns-2">
		<div id="post-body-content">
			<div class="settings-wrapper">
				<div class="inside">
						<?php if (isset($message)) echo $message; ?>
						<?php $tabs = array( 'errors' => __( "Errors & Details", 'ga-in' ), 'config' => __( "Plugin Settings", 'ga-in' ), 'sysinfo' => __( "System", 'ga-in' ) ); ?>
						<?php self::navigation_tabs( $tabs ); ?>
						<div id="gainwp-errors">
						<table class="gainwp-settings-logdata">
							<tr>
								<td>
									<?php echo "<h2>" . __( "Error Details", 'ga-in' ) . "</h2>"; ?>
								</td>
							</tr>
							<tr>
								<td>
									<?php $errors_count = GAINWP_Tools::get_cache( 'errors_count' ); ?>
									<pre class="gainwp-settings-logdata"><?php echo '<span>' . __("Count: ", 'ga-in') . '</span>' . (int)$errors_count;?></pre>
									<?php $errors = print_r( GAINWP_Tools::get_cache( 'last_error' ), true ) ? esc_html( print_r( GAINWP_Tools::get_cache( 'last_error' ), true ) ) : ''; ?>
									<?php $errors = str_replace( 'Deconfc_', 'Google_', $errors); ?>
									<pre class="gainwp-settings-logdata"><?php echo '<span>' . __("Last Error: ", 'ga-in') . '</span>' . "\n" . $errors;?></pre>
									<pre class="gainwp-settings-logdata"><?php echo '<span>' . __("GAPI Error: ", 'ga-in') . '</span>'; echo "\n" . esc_html( print_r( GAINWP_Tools::get_cache( 'gapi_errors' ), true ) ) ?></pre>
									<br />
									<hr>
								</td>
							</tr>
							<tr>
								<td>
									<?php echo "<h2>" . __( "Sampled Data", 'ga-in' ) . "</h2>"; ?>
								</td>
							</tr>
							<tr>
								<td>
									<?php $sampling = GAINWP_TOOLS::get_cache( 'sampleddata' ); ?>
									<?php if ( $sampling ) :?>
									<?php printf( __( "Last Detected on %s.", 'ga-in' ), '<strong>'. $sampling['date'] . '</strong>' );?>
									<br />
									<?php printf( __( "The report was based on %s of sessions.", 'ga-in' ), '<strong>'. $sampling['percent'] . '</strong>' );?>
									<br />
									<?php printf( __( "Sessions ratio: %s.", 'ga-in' ), '<strong>'. $sampling['sessions'] . '</strong>' ); ?>
									<?php else :?>
									<?php _e( "None", 'ga-in' ); ?>
									<?php endif;?>
								</td>
							</tr>
						</table>
					</div>
					<div id="gainwp-config">
						<table class="gainwp-settings-options">
							<tr>
								<td><?php echo "<h2>" . __( "Plugin Configuration", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<tr>
								<td>
									<pre class="gainwp-settings-logdata"><?php echo esc_html(print_r($anonim, true));?></pre>
									<br />
									<hr>
								</td>
							</tr>
						</table>
					</div>
					<div id="gainwp-sysinfo">
						<table class="gainwp-settings-options">
							<tr>
								<td><?php echo "<h2>" . __( "System Information", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<tr>
								<td>
									<pre class="gainwp-settings-logdata"><?php echo esc_html(GAINWP_Tools::system_info());?></pre>
									<br />
									<hr>
								</td>
							</tr>
						</table>
					</div>
	<?php
		self::output_sidebar();
	}

	public static function general_settings() {
		$gainwp = GAINWP();

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = self::update_options( 'general' );
		printf( '<div id="gapi-warning" class="updated"><p>%1$s <a href="https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_config&utm_medium=link&utm_content=general_screen&utm_campaign=gainwp">%2$s</a></p></div>', __( 'Loading the required libraries. If this results in a blank screen or a fatal error, try this solution:', 'ga-in' ), __( 'Library conflicts between WordPress plugins', 'ga-in' ) );
		if ( null === $gainwp->gapi_controller ) {
			$gainwp->gapi_controller = new GAINWP_GAPI_Controller();
		}
		echo '<script type="text/javascript">jQuery("#gapi-warning").hide()</script>';
// TEMP TESTING
/*
$token = json_decode($gainwp->config->options['token'], 1);
intel_d($token);
intel_d(time());
intel_d(time() - $token['created']);

if ( $gainwp->config->options['token'] && $gainwp->gapi_controller->client->getAccessToken() ) {
  $profiles = $gainwp->gapi_controller->refresh_profiles();
intel_d($profiles);
  if ( is_array ( $profiles ) && ! empty( $profiles ) ) {
    $gainwp->config->options['ga_profiles_list'] = $profiles;
    if ( ! $gainwp->config->options['tableid_jail'] ) {
      $profile = GAINWP_Tools::guess_default_domain( $profiles );
      $gainwp->config->options['tableid_jail'] = $profile;
    }
    $gainwp->config->set_plugin_options();
    $options = self::update_options( 'general' );
  }
}
*/
// END TEMP TESTING
		if ( isset( $_POST['gainwp_access_code'] ) ) {
			if ( 1 == ! stripos( 'x' . $_POST['gainwp_access_code'], 'UA-', 1 ) && $_POST['gainwp_access_code'] != get_option( 'gainwp_redeemed_code' ) ) {
				try {
					$gainwp_access_code = $_POST['gainwp_access_code'];
					update_option( 'gainwp_redeemed_code', $gainwp_access_code );
					GAINWP_Tools::delete_cache( 'gapi_errors' );
					GAINWP_Tools::delete_cache( 'last_error' );

					$gainwp->gapi_controller->client->authenticate( $_POST['gainwp_access_code'] );
					$gainwp->config->options['token'] = $gainwp->gapi_controller->client->getAccessToken();
					$gainwp->config->options['automatic_updates_minorversion'] = 1;
					$gainwp->config->set_plugin_options();
					$options = self::update_options( 'general' );
					$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Plugin authorization succeeded.", 'ga-in' ) . "</p></div>";
					if ( $gainwp->config->options['token'] && $gainwp->gapi_controller->client->getAccessToken() ) {
						$profiles = $gainwp->gapi_controller->refresh_profiles();
						if ( is_array ( $profiles ) && ! empty( $profiles ) ) {
							$gainwp->config->options['ga_profiles_list'] = $profiles;
							if ( ! $gainwp->config->options['tableid_jail'] ) {
								$profile = GAINWP_Tools::guess_default_domain( $profiles );
								$gainwp->config->options['tableid_jail'] = $profile;
							}
							$gainwp->config->set_plugin_options();
							$options = self::update_options( 'general' );
						}
					}
				} catch ( Deconfc_IO_Exception $e ) {
					$timeout = $gainwp->gapi_controller->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( $e, $timeout );
				} catch ( Deconfc_Service_Exception $e ) {
					$timeout = $gainwp->gapi_controller->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( $e, $timeout );
				} catch ( Exception $e ) {
					$timeout = $gainwp->gapi_controller->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( $e, $timeout );
					$gainwp->gapi_controller->reset_token();
				}
			} else {
				if ( 1 == stripos( 'x' . $_POST['gainwp_access_code'], 'UA-', 1 ) ) {
					$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "The access code is <strong>not</strong> your <strong>Tracking ID</strong> (UA-XXXXX-X) <strong>nor</strong> your <strong>email address</strong>!", 'ga-in' ) . ".</p></div>";
				} else {
					$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "You can only use the access code <strong>once</strong>, please generate a <strong>new access</strong> code following the instructions!", 'ga-in' ) . ".</p></div>";
				}
			}
		}
		if ( isset( $_POST['Clear'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				GAINWP_Tools::clear_cache();
				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Cleared Cache.", 'ga-in' ) . "</p></div>";
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['Reset'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				$gainwp->gapi_controller->reset_token( TRUE );
				GAINWP_Tools::clear_cache();

				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Token Reseted and Revoked.", 'ga-in' ) . "</p></div>";
				$options = self::update_options( 'Reset' );
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['Reset_Err'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {

				if ( GAINWP_Tools::get_cache( 'gapi_errors' ) || GAINWP_Tools::get_cache( 'last_error' ) ) {

					$info = GAINWP_Tools::system_info();
					$info .= 'GAINWP Version: ' . GAINWP_CURRENT_VERSION;

					$sep = "\n---------------------------\n";
					$error_report = GAINWP_Tools::get_cache( 'last_error' );
					$error_report .= $sep . print_r( GAINWP_Tools::get_cache( 'gapi_errors' ), true );
					$error_report .= $sep . GAINWP_Tools::get_cache( 'errors_count' );
					$error_report .= $sep . $info;

					$error_report = urldecode( $error_report );

					$url = GAINWP_ENDPOINT_URL . 'gainwp-report.php';
					/* @formatter:off */
					$response = wp_remote_post( $url, array(
							'method' => 'POST',
							'timeout' => 45,
							'redirection' => 5,
							'httpversion' => '1.0',
							'blocking' => true,
							'headers' => array(),
							'body' => array( 'error_report' => $error_report ),
							'cookies' => array()
						)
					);
				}

				/* @formatter:on */
				GAINWP_Tools::delete_cache( 'last_error' );
				GAINWP_Tools::delete_cache( 'gapi_errors' );
				delete_option( 'gainwp_got_updated' );
				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "All errors reseted.", 'ga-in' ) . "</p></div>";
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['options']['gainwp_hidden'] ) && !empty( $_POST['Submit'] ) && ! isset( $_POST['Clear'] ) && ! isset( $_POST['Reset'] ) && ! isset( $_POST['Reset_Err'] ) ) {
			$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Settings saved.", 'ga-in' ) . "</p></div>";
			if ( ! ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) ) {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['Hide'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				$message = "<div class='updated' id='gainwp-action'><p>" . __( "All other domains/properties were removed.", 'ga-in' ) . "</p></div>";
				$lock_profile = GAINWP_Tools::get_selected_profile( $gainwp->config->options['ga_profiles_list'], $gainwp->config->options['tableid_jail'] );
				$gainwp->config->options['ga_profiles_list'] = array( $lock_profile );
				$options = self::update_options( 'general' );
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}

    if (!empty( $_POST['Submit'] ) && isset( $_POST['setup_mode'] ) ) {
      if ($_POST['setup_mode'] == '') {
        if ($options['tracking_type'] == 'disabled') {
          $gainwp->config->options['tracking_type'] = 'universal';
          self::update_options( 'tracking' );
        }
      }
      elseif ($_POST['setup_mode'] == 'reporting_only') {
        if ($options['tracking_type'] != 'disabled') {
          $gainwp->config->options['tracking_type'] = 'disabled';
          self::update_options( 'tracking' );
        }
      }
    }
		if ( !isset($_POST['setup_mode']) ) {
      $_POST['setup_mode'] = '';
      if ($options['token'] || isset($_POST['Reset'])) {
        $_POST['setup_mode'] = ($options['tracking_type'] == 'disabled') ? 'reporting_only' : '';
      }
      if (isset($_GET['setup_mode'])) {
        $_POST['setup_mode'] = $_GET['setup_mode'];
      }
		}
		?>
	<div class="wrap">
	<?php echo "<h2>" . __( "Google Analytics General Settings", 'ga-in' ) . "</h2>"; ?>
					<hr>
					</div>
					<div id="poststuff" class="gainwp">
						<div id="post-body" class="metabox-holder columns-2">
							<div id="post-body-content">
								<div class="settings-wrapper">
									<div class="inside">
										<?php if ( $gainwp->gapi_controller->gapi_errors_handler() || GAINWP_Tools::get_cache( 'last_error' ) ) : ?>
													<?php $message = sprintf( '<div class="error"><p>%s</p></div>', sprintf( __( 'Something went wrong, check %1$s or %2$s.', 'ga-in' ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_errors_debugging', false ), __( 'Errors & Debug', 'ga-in' ) ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_settings', false ), __( 'authorize the plugin', 'ga-in' ) ) ) );?>
										<?php endif;?>
										<?php if ( isset( $_POST['Authorize'] ) ) : ?>
											<?php GAINWP_Tools::clear_cache(); ?>
											<?php $gainwp->gapi_controller->token_request(); ?>
											<div class="updated">
											<p><?php _e( "Use the red link (see below) to generate and get your access code! You need to generate a new code each time you authorize!", 'ga-in' )?></p>
										</div>
										<?php else : ?>
										<?php if ( isset( $message ) ) :?>
											<?php echo $message;?>
										<?php endif; ?>
										<form name="gainwp_form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
											<input type="hidden" name="options[gainwp_hidden]" value="Y">
											<?php wp_nonce_field('gainwp_form','gainwp_security'); ?>
											<table class="gainwp-settings-options">
												<tr>
													<td colspan="2">
														<?php echo "<h2>" . __( "Google Analytics Setup", 'ga-in' ) . "</h2>";?>
													</td>
												</tr>
												<tr>
                          <td class="gainwp-settings-title">
                            <label for="setup_mode"><?php _e("Setup Mode:", 'ga-in' ); ?>
                            </label>
                          </td>
                          <td>
                            <select id="setup_mode" name="setup_mode" onchange="this.form.submit()"<?php if ($options['token']) { echo ' xdisabled="disabled"'; } ?>>
                              <option value="" <?php selected( $_POST['setup_mode'], '' ); ?>><?php _e("Tracking & Reporting", 'ga-in');?></option>
                              <option value="reporting_only" <?php selected( $_POST['setup_mode'], 'reporting_only' ); ?>><?php _e("Reporting only", 'ga-in');?></option>
                              <option value="tracking_only" <?php selected( $_POST['setup_mode'], 'tracking_only' ); ?><?php if ($options['token']) { echo ' disabled="disabled"'; } ?>><?php _e("Tracking only", 'ga-in');?></option>

                            </select>
                          </td>
                        </tr>
                        <?php if ($_POST['setup_mode'] == 'tracking_only') : ?>
                          <tr>
                            <td class="gainwp-settings-title">
                              <label for="tracking_id"><?php _e("Tracking ID:", 'ga-in' ); ?>
                              </label>
                            </td>
                            <td>
                              <input type="text" name="options[tracking_id]" value="<?php echo esc_attr($options['tracking_id']); ?>" size="15">
                            </td>
                          </tr>
                        <?php else : ?>
                          <?php if ( $options['token'] ) : ?>
                            <tr>
                              <td class="gainwp-settings-title">
                                <label for="tableid_jail"><?php _e("Tracking ID / View:", 'ga-in' ); ?></label>
                              </td>
                              <td>
                                <select id="tableid_jail" <?php disabled(empty($options['ga_profiles_list']) || 1 == count($options['ga_profiles_list']), true); ?> name="options[tableid_jail]">
                                  <?php if ( ! empty( $options['ga_profiles_list'] ) ) : ?>
                                    <?php foreach ( $options['ga_profiles_list'] as $items ) : ?>
                                      <?php if ( $items[3] ) : ?>
                                        <option value="<?php echo esc_attr( $items[1] ); ?>" <?php selected( $items[1], $options['tableid_jail'] ); ?> title="<?php _e( "View Name:", 'ga-in' ); ?> <?php echo esc_attr( $items[0] ); ?>">
                                          <?php echo esc_html( GAINWP_Tools::strip_protocol( $items[3] ) )?> (<?php echo esc_html( $items[2] )?>) &#8658; <?php echo esc_attr( $items[0] ); ?>
                                        </option>
                                      <?php endif; ?>
                                    <?php endforeach; ?>
                                  <?php else : ?>
                                    <option value=""><?php _e( "Property not found", 'ga-in' ); ?></option>
                                  <?php endif; ?>
                                </select>
                                <?php if ( count( $options['ga_profiles_list'] ) > 1 ) : ?>
                                  &nbsp;<input type="submit" name="Hide" class="button button-secondary" value="<?php _e( "Lock Selection", 'ga-in' ); ?>" />
                                <?php endif; ?>
                               </td>
                            </tr>
                          <?php endif; // END if ( $options['token'] ) ?>
                          <?php if ( $options['tableid_jail'] ) :	?>
                            <tr>
                              <td class="gainwp-settings-title"></td>
                              <td>
                              <?php $profile_info = GAINWP_Tools::get_selected_profile( $gainwp->config->options['ga_profiles_list'], $gainwp->config->options['tableid_jail'] ); ?>
                                <pre><?php echo __( "View Name:", 'ga-in' ) . "\t" . esc_html( $profile_info[0] ) . "<br />" . __( "Tracking ID:", 'ga-in' ) . "\t" . esc_html( $profile_info[2] ) . "<br />" . __( "Default URL:", 'ga-in' ) . "\t" . esc_html( $profile_info[3] ) . "<br />" . __( "Time Zone:", 'ga-in' ) . "\t" . esc_html( $profile_info[5] );?></pre>
                              </td>
                            </tr>
                          <?php endif; // END if $options['tableid_jail'] ?>
                          <tr>
                            <td colspan="2">
                              <?php echo "<h3>" . __( "Google Analytics API Authorization", 'ga-in' ) . "</h3>";?>
                            </td>
                          </tr>
                          <php if (0): // TODO: create doc ?>
                          <tr>
                            <td colspan="2" class="gainwp-settings-info">
                              <?php printf(__('You need to create a %1$s and watch this %2$s before proceeding to authorization.', 'ga-in'), sprintf('<a href="%1$s" target="_blank">%2$s</a>', 'https://intelligencewp.com/creating-a-google-analytics-account/?utm_source=gainwp_config&utm_medium=link&utm_content=top_tutorial&utm_campaign=gainwp', __("free analytics account", 'ga-in')), sprintf('<a href="%1$s" target="_blank">%2$s</a>', 'https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_config&utm_medium=link&utm_content=top_video&utm_campaign=gainwp', __("video tutorial", 'ga-in')));?>
                            </td>
                          </tr>
                          <php endif; ?>
                          <?php if (! $options['token'] || ($options['user_api']  && ! $options['network_mode'])) : ?>
                            <tr>
                              <td colspan="2" class="gainwp-settings-info">
                                <input name="options[user_api]" type="checkbox" id="user_api" value="1" <?php checked( $options['user_api'], 1 ); ?> onchange="this.form.submit()" <?php echo ($options['network_mode'])?'disabled="disabled"':''; ?> /><?php echo " ".__("developer mode (requires advanced API knowledge)", 'ga-in' );?>
                              </td>
                            </tr>
                          <?php endif; ?>
                          <?php if ($options['user_api']  && ! $options['network_mode']) : ?>
                            <tr>
                              <td class="gainwp-settings-title">
                                <label for="options[client_id]"><?php _e("Client ID:", 'ga-in'); ?></label>
                              </td>
                              <td>
                                <input type="text" name="options[client_id]" value="<?php echo esc_attr($options['client_id']); ?>" size="40" required="required">
                              </td>
                            </tr>
                            <tr>
                              <td class="gainwp-settings-title">
                                <label for="options[client_secret]"><?php _e("Client Secret:", 'ga-in'); ?></label>
                              </td>
                              <td>
                                <input type="text" name="options[client_secret]" value="<?php echo esc_attr($options['client_secret']); ?>" size="40" required="required">
                                <input type="hidden" name="options[gainwp_hidden]" value="Y">
                                <?php wp_nonce_field('gainwp_form','gainwp_security'); ?>
                              </td>
                            </tr>
                          <?php endif; ?>
                          <?php if ( $options['token'] ) : ?>
                            <tr>
                              <td colspan="2">
                                <input type="submit" name="Reset" class="button button-secondary" value="<?php _e( "Clear Authorization", 'ga-in' ); ?>" <?php echo $options['network_mode']?'disabled="disabled"':''; ?> />
                                <input type="submit" name="Clear" class="button button-secondary" value="<?php _e( "Clear Cache", 'ga-in' ); ?>" />
                                <input type="submit" name="Reset_Err" class="button button-secondary" value="<?php _e( "Report & Reset Errors", 'ga-in' ); ?>" />
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <hr>
                              </td>
                            </tr>
                          <?php else : // end if $options['token'] ?>
                            <tr>
                              <td colspan="2">
                                <hr>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <input type="submit" name="Authorize" class="button button-secondary" id="authorize" value="<?php _e( "Authorize Plugin", 'ga-in' ); ?>" <?php echo $options['network_mode']?'disabled="disabled"':''; ?> />
                                <input type="submit" name="Clear" class="button button-secondary" value="<?php _e( "Clear Cache", 'ga-in' ); ?>" />
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <hr>
                              </td>
                            </tr>
                          <?php endif; // END $options['token'] ?>
                        <?php endif; // END if $_POST['setup_mode] == '' ?>
                        <?php if ($options['token'] || ($_POST['setup_mode'] == 'tracking_only' && $options['tracking_id'])) : ?>
                          <tr>
                            <td colspan="2"><?php echo "<h2>" . __( "Theme", 'ga-in' ) . "</h2>"; ?></td>
                          </tr>
                          <tr>
                            <td class="gainwp-settings-title">
                              <label for="theme_color"><?php _e("Theme Color:", 'ga-in' ); ?></label>
                            </td>
                            <td>
                              <input type="text" id="theme_color" class="theme_color" name="options[theme_color]" value="<?php echo esc_attr($options['theme_color']); ?>" size="10">
                            </td>
                          </tr>
                          <tr>
                            <td colspan="2">
                              <hr>
                            </td>
                          </tr>
                          <?php if ( !is_multisite()) :?>
                            <tr>
                              <td colspan="2"><?php echo "<h2>" . __( "Automatic Updates", 'ga-in' ) . "</h2>"; ?></td>
                            </tr>
                            <tr>
                              <td colspan="2" class="gainwp-settings-title">
                                <div class="button-primary gainwp-settings-switchoo">
                                  <input type="checkbox" name="options[automatic_updates_minorversion]" value="1" class="gainwp-settings-switchoo-checkbox" id="automatic_updates_minorversion" <?php checked( $options['automatic_updates_minorversion'], 1 ); ?>>
                                  <label class="gainwp-settings-switchoo-label" for="automatic_updates_minorversion">
                                    <div class="gainwp-settings-switchoo-inner"></div>
                                    <div class="gainwp-settings-switchoo-switch"></div>
                                  </label>
                                </div>
                                <div class="switch-desc"><?php echo " ".__( "automatic updates for minor versions (security and maintenance releases only)", 'ga-in' );?></div>
                              </td>
                            </tr>
                            <tr>
                              <td colspan="2">
                                <hr>
                              </td>
                            </tr>
                          <?php endif; // END if is_multisite ?>
                        <?php endif; // END if ($options['token'] || $options['tracking_id]) ?>
                        <?php if ($options['token'] || ( 'tracking_only' == $_POST['setup_mode'])) : ?>
                          <tr>
                            <td colspan="2" class="submit">
                              <input type="submit" name="Submit" class="button button-primary" value="<?php _e('Save Changes', 'ga-in' ) ?>" />
                            </td>
                          </tr>
                        <?php endif; // END if ($options['token'] || ( '' == $_POST['setup_mode'])) ?>
											</table>
										</form>
				<?php self::output_sidebar(); ?>
				<?php return; ?>
											</table>
										</form>
			<?php endif; ?>
			<?php

		self::output_sidebar();
	}

	// Network Settings
	public static function general_settings_network() {
		$gainwp = GAINWP();

		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$options = self::update_options( 'network' );
		/*
		 * Include GAPI
		 */
		echo '<div id="gapi-warning" class="updated"><p>' . __( 'Loading the required libraries. If this results in a blank screen or a fatal error, try this solution:', 'ga-in' ) . ' <a href="https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_config&utm_medium=link&utm_content=general_screen&utm_campaign=gainwp">Library conflicts between WordPress plugins</a></p></div>';

		if ( null === $gainwp->gapi_controller ) {
			$gainwp->gapi_controller = new GAINWP_GAPI_Controller();
		}

		echo '<script type="text/javascript">jQuery("#gapi-warning").hide()</script>';
		if ( isset( $_POST['gainwp_access_code'] ) ) {
			if ( 1 == ! stripos( 'x' . $_POST['gainwp_access_code'], 'UA-', 1 ) && $_POST['gainwp_access_code'] != get_option( 'gainwp_redeemed_code' ) ) {
				try {
					$gainwp_access_code = $_POST['gainwp_access_code'];
					update_option( 'gainwp_redeemed_code', $gainwp_access_code );
					$gainwp->gapi_controller->client->authenticate( $_POST['gainwp_access_code'] );
					$gainwp->config->options['token'] = $gainwp->gapi_controller->client->getAccessToken();
					$gainwp->config->options['automatic_updates_minorversion'] = 1;
					$gainwp->config->set_plugin_options( true );
					$options = self::update_options( 'network' );
					$message = "<div class='updated' id='gainwp-action'><p>" . __( "Plugin authorization succeeded.", 'ga-in' ) . "</p></div>";
					if ( is_multisite() ) { // Cleanup errors on the entire network
						foreach ( GAINWP_Tools::get_sites( array( 'number' => apply_filters( 'gainwp_sites_limit', 100 ) ) ) as $blog ) {
							switch_to_blog( $blog['blog_id'] );
							GAINWP_Tools::delete_cache( 'last_error' );
							GAINWP_Tools::delete_cache( 'gapi_errors' );
							restore_current_blog();
						}
					} else {
						GAINWP_Tools::delete_cache( 'last_error' );
						GAINWP_Tools::delete_cache( 'gapi_errors' );
					}
					if ( $gainwp->config->options['token'] && $gainwp->gapi_controller->client->getAccessToken() ) {
						$profiles = $gainwp->gapi_controller->refresh_profiles();
						if ( is_array ( $profiles ) && ! empty( $profiles ) ) {
							$gainwp->config->options['ga_profiles_list'] = $profiles;
							if ( isset( $gainwp->config->options['tableid_jail'] ) && ! $gainwp->config->options['tableid_jail'] ) {
								$profile = GAINWP_Tools::guess_default_domain( $profiles );
								$gainwp->config->options['tableid_jail'] = $profile;
							}
							$gainwp->config->set_plugin_options( true );
							$options = self::update_options( 'network' );
						}
					}
				} catch ( Deconfc_IO_Exception $e ) {
					$timeout = $gainwp->gapi_controller->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( $e, $timeout );
				} catch ( Deconfc_Service_Exception $e ) {
					$timeout = $gainwp->gapi_controller->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( $e, $timeout );
				} catch ( Exception $e ) {
					$timeout = $gainwp->gapi_controller->get_timeouts( 'midnight' );
					GAINWP_Tools::set_error( $e, $timeout );
					$gainwp->gapi_controller->reset_token();
				}
			} else {
				if ( 1 == stripos( 'x' . $_POST['gainwp_access_code'], 'UA-', 1 ) ) {
					$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "The access code is <strong>not</strong> your <strong>Tracking ID</strong> (UA-XXXXX-X) <strong>nor</strong> your <strong>email address</strong>!", 'ga-in' ) . ".</p></div>";
				} else {
					$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "You can only use the access code <strong>once</strong>, please generate a <strong>new access code</strong> using the red link", 'ga-in' ) . "!</p></div>";
				}
			}
		}
		if ( isset( $_POST['Refresh'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				$gainwp->config->options['ga_profiles_list'] = array();
				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Properties refreshed.", 'ga-in' ) . "</p></div>";
				$options = self::update_options( 'network' );
				if ( $gainwp->config->options['token'] && $gainwp->gapi_controller->client->getAccessToken() ) {
					if ( ! empty( $gainwp->config->options['ga_profiles_list'] ) ) {
						$profiles = $gainwp->config->options['ga_profiles_list'];
					} else {
						$profiles = $gainwp->gapi_controller->refresh_profiles();
					}
					if ( $profiles ) {
						$gainwp->config->options['ga_profiles_list'] = $profiles;
						if ( isset( $gainwp->config->options['tableid_jail'] ) && ! $gainwp->config->options['tableid_jail'] ) {
							$profile = GAINWP_Tools::guess_default_domain( $profiles );
							$gainwp->config->options['tableid_jail'] = $profile;
						}
						$gainwp->config->set_plugin_options( true );
						$options = self::update_options( 'network' );
					}
				}
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['Clear'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				GAINWP_Tools::clear_cache();
				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Cleared Cache.", 'ga-in' ) . "</p></div>";
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['Reset'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				$gainwp->gapi_controller->reset_token();
				GAINWP_Tools::clear_cache();
				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Token Reseted and Revoked.", 'ga-in' ) . "</p></div>";
				$options = self::update_options( 'Reset' );
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['options']['gainwp_hidden'] ) && ! isset( $_POST['Clear'] ) && ! isset( $_POST['Reset'] ) && ! isset( $_POST['Refresh'] ) ) {
			$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Settings saved.", 'ga-in' ) . "</p></div>";
			if ( ! ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) ) {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( isset( $_POST['Hide'] ) ) {
			if ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) {
				$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "All other domains/properties were removed.", 'ga-in' ) . "</p></div>";
				$lock_profile = GAINWP_Tools::get_selected_profile( $gainwp->config->options['ga_profiles_list'], $gainwp->config->options['tableid_jail'] );
				$gainwp->config->options['ga_profiles_list'] = array( $lock_profile );
				$options = self::update_options( 'network' );
			} else {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		?>
<div class="wrap">
											<h2><?php _e( "Google Analytics Settings", 'ga-in' );?></h2>
											<hr>
										</div>
										<div id="poststuff" class="gainwp">
											<div id="post-body" class="metabox-holder columns-2">
												<div id="post-body-content">
													<div class="settings-wrapper">
														<div class="inside">
					<?php if ( $gainwp->gapi_controller->gapi_errors_handler() || GAINWP_Tools::get_cache( 'last_error' ) ) : ?>
						<?php $message = sprintf( '<div class="error"><p>%s</p></div>', sprintf( __( 'Something went wrong, check %1$s or %2$s.', 'ga-in' ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_errors_debugging', false ), __( 'Errors & Debug', 'ga-in' ) ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_settings', false ), __( 'authorize the plugin', 'ga-in' ) ) ) );?>
					<?php endif; ?>
					<?php if ( isset( $_POST['Authorize'] ) ) : ?>
						<?php GAINWP_Tools::clear_cache();?>
						<?php $gainwp->gapi_controller->token_request();?>
					<div class="updated">
																<p><?php _e( "Use the red link (see below) to generate and get your access code! You need to generate a new code each time you authorize!", 'ga-in' );?></p>
															</div>
					<?php else : ?>
						<?php if ( isset( $message ) ) : ?>
							<?php echo $message; ?>
						<?php endif; ?>
					<form name="gainwp_form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
																<input type="hidden" name="options[gainwp_hidden]" value="Y">
						<?php wp_nonce_field('gainwp_form','gainwp_security'); ?>
						<table class="gainwp-settings-options">
							<tr>
								<td colspan="2">
						  		<?php echo "<h2>" . __( "Network Setup", 'ga-in' ) . "</h2>"; ?>
								</td>
							</tr>
              <tr>
                <td colspan="2" class="gainwp-settings-title">
                  <div class="button-primary gainwp-settings-switchoo">
                    <input type="checkbox" name="options[network_mode]" value="1" class="gainwp-settings-switchoo-checkbox" id="network_mode" <?php checked( $options['network_mode'], 1); ?> onchange="this.form.submit()">
                    <label class="gainwp-settings-switchoo-label" for="network_mode">
                      <div class="gainwp-settings-switchoo-inner"></div>
                      <div class="gainwp-settings-switchoo-switch"></div>
                    </label>
                  </div>
                  <div class="switch-desc"><?php echo " ".__("use a single Google Analytics account for the entire network", 'ga-in' );?></div>
                </td>
              </tr>
							<?php if ($options['network_mode']) : ?>
							<tr>
                <td colspan="2">
                  <hr>
                </td>
              </tr>
              <tr>
                <td colspan="2"><?php echo "<h2>" . __( "Plugin Authorization", 'ga-in' ) . "</h2>"; ?></td>
              </tr>
              <?php if (0) : // TODO: create doc ?>
              <tr>
							  <td colspan="2" class="gainwp-settings-info">
								  <?php printf(__('You need to create a %1$s and watch this %2$s before proceeding to authorization.', 'ga-in'), sprintf('<a href="%1$s" target="_blank">%2$s</a>', 'https://intelligencewp.com/creating-a-google-analytics-account/?utm_source=gainwp_config&utm_medium=link&utm_content=top_tutorial&utm_campaign=gainwp', __("free analytics account", 'ga-in')), sprintf('<a href="%1$s" target="_blank">%2$s</a>', 'https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_config&utm_medium=link&utm_content=top_video&utm_campaign=gainwp', __("video tutorial", 'ga-in')));?>
								</td>
							</tr>
							<?php endif; ?>
							<?php if ( ! $options['token'] || $options['user_api'] ) : ?>
                <tr>
                  <td colspan="2" class="gainwp-settings-info">
                    <input name="options[user_api]" type="checkbox" id="user_api" value="1" <?php checked( $options['user_api'], 1 ); ?> onchange="this.form.submit()" /><?php echo " ".__("developer mode (requires advanced API knowledge)", 'ga-in' );?>
                  </td>
                </tr>
							<?php endif; ?>
							<?php if ( $options['user_api'] ) : ?>
                <tr>
                  <td class="gainwp-settings-title">
                    <label for="options[client_id]"><?php _e("Client ID:", 'ga-in'); ?>
                    </label>
                  </td>
                  <td>
                    <input type="text" name="options[client_id]" value="<?php echo esc_attr($options['client_id']); ?>" size="40" required="required">
                  </td>
                </tr>
                <tr>
                  <td class="gainwp-settings-title">
                    <label for="options[client_secret]"><?php _e("Client Secret:", 'ga-in'); ?>
                    </label>
                  </td>
                  <td>
                    <input type="text" name="options[client_secret]" value="<?php echo esc_attr($options['client_secret']); ?>" size="40" required="required">
                    <input type="hidden" name="options[gainwp_hidden]" value="Y">
                    <?php wp_nonce_field('gainwp_form','gainwp_security'); ?>
                  </td>
                </tr>
							<?php endif; ?>
							<?php if ( $options['token'] ) : ?>
							<tr>
                <td colspan="2">
                  <input type="submit" name="Reset" class="button button-secondary" value="<?php _e( "Clear Authorization", 'ga-in' ); ?>" />
                  <input type="submit" name="Clear" class="button button-secondary" value="<?php _e( "Clear Cache", 'ga-in' ); ?>" />
                  <input type="submit" name="Refresh" class="button button-secondary" value="<?php _e( "Refresh Properties", 'ga-in' ); ?>" />
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <hr>
                </td>
              </tr>
              <tr>
                <td colspan="2">
								<?php echo "<h2>" . __( "Properties/Views Settings", 'ga-in' ) . "</h2>"; ?>
								</td>
							</tr>
							<?php if ( isset( $options['network_tableid'] ) ) : ?>
								<?php $options['network_tableid'] = json_decode( json_encode( $options['network_tableid'] ), false ); ?>
							  <?php endif; ?>
							  <?php foreach ( GAINWP_Tools::get_sites( array( 'number' => apply_filters( 'gainwp_sites_limit', 100 ) ) ) as $blog ) : ?>
							    <tr>
                    <td class="gainwp-settings-title-s">
                      <label for="network_tableid"><?php echo '<strong>'.$blog['domain'].$blog['path'].'</strong>: ';?></label>
                    </td>
                    <td>
                      <select id="network_tableid" <?php disabled(!empty($options['ga_profiles_list']),false);?> name="options[network_tableid][<?php echo $blog['blog_id'];?>]">
                        <?php if ( ! empty( $options['ga_profiles_list'] ) ) : ?>
                          <?php foreach ( $options['ga_profiles_list'] as $items ) : ?>
                            <?php if ( $items[3] ) : ?>
                              <?php $temp_id = $blog['blog_id']; ?>
                              <option value="<?php echo esc_attr( $items[1] );?>" <?php selected( $items[1], isset( $options['network_tableid']->$temp_id ) ? $options['network_tableid']->$temp_id : '');?> title="<?php echo __( "View Name:", 'ga-in' ) . ' ' . esc_attr( $items[0] );?>">
                                 <?php echo esc_html( GAINWP_Tools::strip_protocol( $items[3] ) );?> &#8658; <?php echo esc_attr( $items[0] );?>
                              </option>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        <?php else : ?>
                          <option value="">
                            <?php _e( "Property not found", 'ga-in' );?>
                          </option>
									      <?php endif; ?>
									    </select>
                      <br />
                    </td>
                  </tr>
							  <?php endforeach; ?>
							  <tr>
                  <td colspan="2">
                    <h2><?php echo _e( "Automatic Updates", 'ga-in' );?></h2>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="gainwp-settings-title">
                    <div class="button-primary gainwp-settings-switchoo">
                      <input type="checkbox" name="options[automatic_updates_minorversion]" value="1" class="gainwp-settings-switchoo-checkbox" id="automatic_updates_minorversion" <?php checked( $options['automatic_updates_minorversion'], 1 ); ?>>
                      <label class="gainwp-settings-switchoo-label" for="automatic_updates_minorversion">
                        <div class="gainwp-settings-switchoo-inner"></div>
                        <div class="gainwp-settings-switchoo-switch"></div>
                      </label>
                    </div>
                    <div class="switch-desc"><?php echo " ".__( "automatic updates for minor versions (security and maintenance releases only)", 'ga-in' );?></div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <hr><?php echo "<h2>" . __( "Exclude Tracking", 'ga-in' ) . "</h2>"; ?></td>
                </tr>
                <tr>
                  <td colspan="2" class="gainwp-settings-title">
                    <div class="button-primary gainwp-settings-switchoo">
                      <input type="checkbox" name="options[superadmin_tracking]" value="1" class="gainwp-settings-switchoo-checkbox" id="superadmin_tracking"<?php checked( $options['superadmin_tracking'], 1); ?>">
                      <label class="gainwp-settings-switchoo-label" for="superadmin_tracking">
                        <div class="gainwp-settings-switchoo-inner"></div>
                        <div class="gainwp-settings-switchoo-switch"></div>
                      </label>
                    </div>
                    <div class="switch-desc"><?php echo " ".__("exclude Super Admin tracking for the entire network", 'ga-in' );?></div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <hr>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="submit">
                    <input type="submit" name="Submit" class="button button-primary" value="<?php _e('Save Changes', 'ga-in' ) ?>" />
                  </td>
                </tr>
							<?php else : ?>
							<tr>
                <td colspan="2">
                  <hr>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <input type="submit" name="Authorize" class="button button-secondary" id="authorize" value="<?php _e( "Authorize Plugin", 'ga-in' ); ?>" />
                  <input type="submit" name="Clear" class="button button-secondary" value="<?php _e( "Clear Cache", 'ga-in' ); ?>" />
                </td>
              </tr>
							<?php endif; ?>
							<tr>
                <td colspan="2">
                  <hr>
                </td>
              </tr>
            </table>
          </form>
		<?php self::output_sidebar(); ?>
				<?php return; ?>
			<?php endif;?>
						</table>
					</form>
		<?php endif; ?>
		<?php

		self::output_sidebar();
	}

	public static function reporting_settings() {
		$gainwp = GAINWP();
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$message = '';
    $validation_error = 0;

		$message .= self::global_notices( 'reporting',  $validation_error);
		$options = self::update_options( 'reporting', $validation_error );
		/*
		if ( isset( $_POST['options']['gainwp_hidden'] ) ) {
			$message = "<div class='updated' id='gainwp-autodismiss'><p>" . __( "Settings saved.", 'ga-in' ) . "</p></div>";
			if ( ! ( isset( $_POST['gainwp_security'] ) && wp_verify_nonce( $_POST['gainwp_security'], 'gainwp_form' ) ) ) {
				$message = "<div class='error' id='gainwp-autodismiss'><p>" . __( "Cheating Huh?", 'ga-in' ) . "</p></div>";
			}
		}
		if ( ! $gainwp->config->options['tableid_jail'] || ! $gainwp->config->options['token'] ) {
			$message = sprintf( '<div class="error"><p>%s</p></div>', sprintf( __( 'Something went wrong, check %1$s or %2$s.', 'ga-in' ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_errors_debugging', false ), __( 'Errors & Debug', 'ga-in' ) ), sprintf( '<a href="%1$s">%2$s</a>', menu_page_url( 'gainwp_settings', false ), __( 'authorize the plugin', 'ga-in' ) ) ) );
		}
		*/
		?>
<form name="gainwp_form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
	<div class="wrap">
			<?php echo "<h2>" . __( "Google Analytics Reporting Settings", 'ga-in' ) . "</h2>"; ?><hr>
	</div>
	<div id="poststuff" class="gainwp">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div class="settings-wrapper">
					<div class="inside">
					<?php if (isset($message)) echo $message; ?>
						<table class="gainwp-settings-options">
							<tr>
								<td colspan="2"><?php echo "<h2>" . __( "Backend Reports", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<tr>
								<td class="roles gainwp-settings-title">
									<label for="access_back"><?php _e("Show stats to:", 'ga-in' ); ?>
									</label>
								</td>
								<td class="gainwp-settings-roles">
									<table>
										<tr>
										<?php if ( ! isset( $wp_roles ) ) : ?>
											<?php $wp_roles = new WP_Roles(); ?>
										<?php endif; ?>
										<?php $i = 0; ?>
										<?php foreach ( $wp_roles->role_names as $role => $name ) : ?>
											<?php if ( 'subscriber' != $role ) : ?>
												<?php $i++; ?>
											<td>
												<label>
													<input type="checkbox" name="options[access_back][]" value="<?php echo $role; ?>" <?php if ( in_array($role,$options['access_back']) || 'administrator' == $role ) echo 'checked="checked"'; if ( 'administrator' == $role ) echo 'disabled="disabled"';?> /> <?php echo $name; ?>
												</label>
											</td>
											<?php endif; ?>
											<?php if ( 0 == $i % 4 ) : ?>
										</tr>
										<tr>
											<?php endif; ?>
										<?php endforeach; ?>






									</table>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<div class="button-primary gainwp-settings-switchoo">
										<input type="checkbox" name="options[switch_profile]" value="1" class="gainwp-settings-switchoo-checkbox" id="switch_profile" <?php checked( $options['switch_profile'], 1 ); ?>>
										<label class="gainwp-settings-switchoo-label" for="switch_profile">
											<div class="gainwp-settings-switchoo-inner"></div>
											<div class="gainwp-settings-switchoo-switch"></div>
										</label>
									</div>
									<div class="switch-desc"><?php _e ( "enable Switch View functionality", 'ga-in' );?></div>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<div class="button-primary gainwp-settings-switchoo">
										<input type="checkbox" name="options[backend_item_reports]" value="1" class="gainwp-settings-switchoo-checkbox" id="backend_item_reports" <?php checked( $options['backend_item_reports'], 1 ); ?>>
										<label class="gainwp-settings-switchoo-label" for="backend_item_reports">
											<div class="gainwp-settings-switchoo-inner"></div>
											<div class="gainwp-settings-switchoo-switch"></div>
										</label>
									</div>
									<div class="switch-desc"><?php _e ( "enable reports on Posts List and Pages List", 'ga-in' );?></div>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<div class="button-primary gainwp-settings-switchoo">
										<input type="checkbox" name="options[dashboard_widget]" value="1" class="gainwp-settings-switchoo-checkbox" id="dashboard_widget" <?php checked( $options['dashboard_widget'], 1 ); ?>>
										<label class="gainwp-settings-switchoo-label" for="dashboard_widget">
											<div class="gainwp-settings-switchoo-inner"></div>
											<div class="gainwp-settings-switchoo-switch"></div>
										</label>
									</div>
									<div class="switch-desc"><?php _e ( "enable the main Dashboard Widget", 'ga-in' );?></div>
								</td>
							</tr>

							<tr>
								<td colspan="2"><?php echo "<h2>" . __( "Frontend Reports", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<tr>
								<td class="roles gainwp-settings-title">
									<label for="access_front"><?php _e("Show stats to:", 'ga-in' ); ?>
									</label>
								</td>
								<td class="gainwp-settings-roles">
									<table>
										<tr>
										<?php if ( ! isset( $wp_roles ) ) : ?>
											<?php $wp_roles = new WP_Roles(); ?>
										<?php endif; ?>
										<?php $i = 0; ?>
										<?php foreach ( $wp_roles->role_names as $role => $name ) : ?>
											<?php if ( 'subscriber' != $role ) : ?>
												<?php $i++; ?>
												<td>
												<label>
													<input type="checkbox" name="options[access_front][]" value="<?php echo $role; ?>" <?php if ( in_array($role,$options['access_front']) || 'administrator' == $role ) echo 'checked="checked"'; if ( 'administrator' == $role ) echo 'disabled="disabled"';?> /><?php echo $name; ?>
												  </label>
											</td>
											<?php endif; ?>
											<?php if ( 0 == $i % 4 ) : ?>
										 </tr>
										<tr>
											<?php endif; ?>
										<?php endforeach; ?>
									</table>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<div class="button-primary gainwp-settings-switchoo">
										<input type="checkbox" name="options[frontend_item_reports]" value="1" class="gainwp-settings-switchoo-checkbox" id="frontend_item_reports" <?php checked( $options['frontend_item_reports'], 1 ); ?>>
										<label class="gainwp-settings-switchoo-label" for="frontend_item_reports">
											<div class="gainwp-settings-switchoo-inner"></div>
											<div class="gainwp-settings-switchoo-switch"></div>
										</label>
									</div>
									<div class="switch-desc"><?php echo " ".__("enable web page reports on frontend", 'ga-in' );?></div>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<hr>
								</td>
							</tr>


							<tr>
								<td colspan="2">
									<hr><?php echo "<h2>" . __( "Real-Time Settings", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<?php if ( $options['user_api'] ) : ?>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<div class="button-primary gainwp-settings-switchoo">
										<input type="checkbox" name="options[backend_realtime_report]" value="1" class="gainwp-settings-switchoo-checkbox" id="backend_realtime_report" <?php checked( $options['backend_realtime_report'], 1 ); ?>>
										<label class="gainwp-settings-switchoo-label" for="backend_realtime_report">
											<div class="gainwp-settings-switchoo-inner"></div>
											<div class="gainwp-settings-switchoo-switch"></div>
										</label>
									</div>
									<div class="switch-desc"><?php _e ( "enable Real-Time report (requires access to Real-Time Reporting API)", 'ga-in' );?></div>
								</td>
							</tr>
							<?php endif; ?>
							<tr>
								<td colspan="2" class="gainwp-settings-title"> <?php _e("Maximum number of pages to display on real-time tab:", 'ga-in'); ?>
									<input type="number" name="options[ga_realtime_pages]" id="ga_realtime_pages" value="<?php echo (int)$options['ga_realtime_pages']; ?>" size="3">
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<hr><?php echo "<h2>" . __( "Location Settings", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<?php echo __("Target Geo Map to country:", 'ga-in'); ?>
									<input type="text" style="text-align: center;" name="options[ga_target_geomap]" value="<?php echo esc_attr($options['ga_target_geomap']); ?>" size="3">
								</td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<?php echo __("Maps API Key:", 'ga-in'); ?>
									<input type="text" style="text-align: center;" name="options[maps_api_key]" value="<?php echo esc_attr($options['maps_api_key']); ?>" size="50">
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<hr><?php echo "<h2>" . __( "404 Errors Report", 'ga-in' ) . "</h2>"; ?></td>
							</tr>
							<tr>
								<td colspan="2" class="gainwp-settings-title">
									<?php echo __("404 Page Title contains:", 'ga-in'); ?>
									<input type="text" style="text-align: center;" name="options[pagetitle_404]" value="<?php echo esc_attr($options['pagetitle_404']); ?>" size="20">
								</td>
							</tr>
							<tr>
								<td colspan="2">
									<hr>
								</td>
							</tr>
							<tr>
								<td colspan="2" class="submit">
									<input type="submit" name="Submit" class="button button-primary" value="<?php _e('Save Changes', 'ga-in' ) ?>" />
								</td>
							</tr>
						</table>
						<input type="hidden" name="options[gainwp_hidden]" value="Y">
						<?php wp_nonce_field('gainwp_form','gainwp_security'); ?>
</form>
<?php
		self::output_sidebar();
	}

	public static function output_sidebar() {
		global $wp_version;

		$gainwp = GAINWP();
		?>
		    </div>
      </div>
    </div>
    <div id="postbox-container-1" class="postbox-container">
      <div class="meta-box-sortables">
        <div class="postbox">
          <h3>
            <span><?php _e("Setup Tutorial & Demo",'ga-in') ?></span>
          </h3>
          <div class="inside">
            <a href="https://intelligencewp.com/google-analytics-in-wordpress/?utm_source=gainwp_config&utm_medium=link&utm_content=video&utm_campaign=gainwp" target="_blank"><img src="<?php echo plugins_url( 'images/ga-in.png' , __FILE__ );?>" width="100%" alt="" /></a>
          </div>
        </div>
        <div class="postbox">
          <h3>
            <span><?php _e("Tools",'ga-in')?></span>
          </h3>
          <div class="inside">
            <div class="gainwp-title">
              <a href="https://ga-dev-tools.appspot.com/campaign-url-builder/"><span class="dashicons dashicons-chart-pie" style="font-size: 2.0em; text-decoration: none;"></span></a>
            </div>
            <div class="gainwp-desc">
              <?php printf(__('%s - creates URLs for custom campaign tracking.', 'ga-in'), sprintf('<a href="https://ga-dev-tools.appspot.com/campaign-url-builder/">%s</a>', __('Campaign URL Builder', 'ga-in')));?>
            </div>
            <br />
            <div class="gainwp-title">
              <a href="https://chrome.google.com/webstore/detail/google-analytics-debugger/jnkmfdileelhofjcijamephohjechhna"><span class="dashicons dashicons-admin-tools" style="font-size: 2.0em; text-decoration: none;"></span></a>
            </div>
            <div class="gainwp-desc">
              <?php printf(__('%s - Chrome extension enables you to view and troubleshoot tracking data.', 'ga-in'), sprintf('<a href="https://chrome.google.com/webstore/detail/google-analytics-debugger/jnkmfdileelhofjcijamephohjechhna">%s</a>', __('Google Analytics Debugger', 'ga-in')));?>
            </div>
            <br />
            <div class="gainwp-title">
              <a href="https://wordpress.org/plugins/intelligence/"><span class="dashicons dashicons-analytics" style="font-size: 2.0em; text-decoration: none;"></span></a>
            </div>
            <div class="gainwp-desc">
              <?php printf(__('%s - Enhance Google Analytics for content marketers.', 'ga-in'), sprintf('<a href="https://wordpress.org/plugins/intelligence/">%s</a>', __('Intelligence plugin', 'ga-in')));?>
            </div>
          </div>
        </div>
        <div class="postbox">
          <h3>
            <span><?php _e("Further Reading",'ga-in')?></span>
          </h3>
          <div class="inside">
            <div class="gainwp-title">
              <a href="https://analytics.google.com/analytics/academy/"><span class="dashicons dashicons-welcome-learn-more" style="font-size: 2.0em; text-decoration: none;"></span></a>
            </div>
            <div class="gainwp-desc">
              <?php printf(__('%s - Learn analytics with free online courses.', 'ga-in'), sprintf('<a href="https://analytics.google.com/analytics/academy/">%s</a>', __('Google Analytics Academy', 'ga-in')));?>
            </div>
            <br />
            <div class="gainwp-title">
              <a href="https://analytics.googleblog.com/"><span class="dashicons dashicons-admin-post" style="font-size: 2.0em; text-decoration: none;"></span></a>
            </div>
            <div class="gainwp-desc">
              <?php printf(__('%s - Timely updates for getting the most out of GA.', 'ga-in'), sprintf('<a href="https://analytics.googleblog.com/">%s</a>', __('Google Analytics Blog', 'ga-in')));?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
		// Dismiss the admin update notice
		if ( version_compare( $wp_version, '4.2', '<' ) && current_user_can( 'manage_options' ) ) {
			delete_option( 'gainwp_got_updated' );
		}
	}
}

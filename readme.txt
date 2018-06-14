=== GAinWP Google Analytics Integration for WordPress ===
Contributors: tomdude,deconf
Tags: analytics,google analytics,google analytics code,google analytics dashboard,google analytics plugin,google analytics tracking code,google analytics widget,gtag
Requires at least: 3.5
Tested up to: 4.9.6
Stable tag: 5.4.0
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connects Google Analytics with your WordPress site.

== Description ==
The GAinWP Google Analytics Integration for WordPress plugin easily integrates Google Analytics tracking and dashboard reporting into your website in just minutes.

It was created from the excellent Google Analytics Dashboard for WordPress (GADWP) plugin to maintain a robust, direct Google Analytics integration.

In addition to a set of general Google Analytics stats, in-depth page reports and in-depth post reports allow further segmentation of your analytics data, providing performance details for each post or page from your website.

The Google Analytics tracking code is fully customizable through options and hooks, allowing advanced data collection like custom dimensions and events.    

= Google Analytics Real-Time Stats =

Google Analytics reports, in real-time, in your dashboard screen:

- Real-time number of visitors
- Real-time acquisition channels
- Real-time traffic sources details 

= Google Analytics Reports =

The Google Analytics reports you need, on your dashboard, in your All Posts and All Pages screens, and on site's frontend:  

- Sessions, organic searches, page views, bounce rate analytics stats
- Locations, pages, referrers, keywords, 404 errors analytics stats
- Traffic channels, social networks, traffic mediums, search engines analytics stats
- Device categories, browsers, operating systems, screen resolutions, mobile brands analytics stats

In addition, you can control who can view specific Google Analytics reports by setting permissions based on user roles.

= Google Analytics Tracking =

Installs the latest Google Analytics tracking code and allows full code customization:

- Universal Google Analytics (analytics.js) tracking code
- Global Site Tag (gtag.js) tracking code
- Enhanced link attribution
- Remarketing, demographics and interests tracking
- Page Speed sampling rate control
- User sampling rate control
- Cross domain tracking
- Exclude user roles from tracking
- Accelerated Mobile Pages (AMP) support for Google Analytics
- Ecommerce support for Google Analytics

User privacy oriented features:

- IP address anonymization
- option to follow Do Not Track (DNT) sent by browsers
- support for user tracking opt-out

GAinWP enables you to easily track events like:
 
- Downloads
- Emails 
- Outbound links
- Affiliate links
- Fragment identifiers
- Telephone
- Page Scrolling Depth
- Custom event categories, actions and labels using annotated HTML elements

With GAinWP you can use custom dimensions to track:

- Authors
- Publication year
- Publication month
- Categories
- Tags
- User engagement

Actions and filters are available for further Google Analytics tracking code customization.

= Google Tag Manager Tracking =

As an alternative to Google Analytics tracking code, you can use Google Tag Manager for tracking:

- Google Tag Manager code
- Data Layer variables: authors, publication year, publication month, categories, tags, user type
- Exclude user roles from tracking
- Accelerated Mobile Pages (AMP) support for Google Tag Manager

= Accelerated Mobile Pages (AMP) features =

- Google Tag Manager basic tracking
- Google Analytics basic tracking 
- Automatically removes <em>amp/</em> from Google Analytics tracking page URL
- Scrolling depth tracking
- Custom dimensions tracking
- User sampling rate control
- Form submit tracking
- File downloads tracking
- Affiliate links tracking
- Hashmarks, outbound links, telephones and e-mails tracking
- Custom event categories, actions and labels using annotated HTML elements

= GAinWP on Multisite =

This plugin is fully compatible with multisite network installs, allowing three setup modes:

- Mode 1: network activated using multiple Google Analytics accounts
- Mode 2: network activated using a single Google Analytics account
- Mode 3: network deactivated using multiple Google Analytics accounts

> <strong>GAinWP on GitHub</strong><br>
> You can submit feature requests or bugs on [GAinWP](https://github.com/levelten/ga-in) repository.

== Why this Plugin ==

The [IntelligenceWP](https://wordpress.org/plugins/intelligence) project leverages the GADWP plugin for core Google Analytics integrations.

After the Google Analytics Dashboard for WordPress changed maintainers, users expressed concern over the new auth process proving 3rd party access to Google API keys and analytics data.

GAinWP uses a direct auth process where API keys only reside in your WordPress site so data access is not shared with any 3rd parties.

== Credits ==

This plugin was originally created by [Alin Marcu](https://deconf.com) as the Google Analytics Dashboard for WordPress (GADWP)

== Installation ==

1. Upload the full ga-in directory into your wp-content/plugins directory.
2. In WordPress select Plugins from your sidebar menu and activate the GAINWP - Google Analytics Integration for WordPress plugin.
3. Open the plugin configuration page, which is located under Google Analytics menu.
4. Authorize the plugin to connect to Google Analytics using the Authorize Plugin button.
5. Go back to the plugin configuration page, which is located under Google Analytics menu to update/set your settings.
6. Go to Google Analytics -> Tracking Code to configure/enable/disable tracking.

== Frequently Asked Questions == 

= Do I have to insert the Google Analytics tracking code manually? =

No, once the plugin is authorized and a default domain is selected the Google Analytics tracking code is automatically inserted in all webpages.

= Some settings are missing in the video tutorial =

We are constantly improving GAinWP, sometimes the video tutorial may be a little outdated.

= How can I suggest a new feature, contribute or report a bug? =

You can submit pull requests, feature requests and bug reports on [our GitHub repository](https://github.com/levelten/ga-in).

= Documentation, Tutorials and FAQ =

For documentation, tutorials, FAQ and videos check out: [GAinWP documentation](https://intelligencewp.com/google-analytics-in-wordpress/).

== Screenshots ==

1. Google Analytics Dashboard Sessions Report
2. Google Analytics Dashboard Real-Time Report
3. Google Analytics Posts/Pages Report
4. Google Analytics Dashboard Geo Map Report
5. Google Analytics Dashboard Pages Report
6. Google Analytics Dashboard Traffic Details Report
7. Google Analytics Frontend Report overlay

== Localization ==

You can translate GAinWP on [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/ga-in).

== License ==

GAinWP it's released under the GPLv2, you can use it free of charge on your personal or commercial website.

== Upgrade Notice ==

== Changelog ==

= 5.4 =
* Enable direct Google API auth process to prevent access keys and Google Analytics data sharing with 3rd parties
* Enabled Google Analytics tracking without requiring GAPI authentication
* Consolidate settings

= 5.3.2 =
* Bug Fixes:	
	* fixes for user opt-out feature 
* Enhancements: 
	* use <em>gainwp_useroptout</em> shortcode to easily generate opt-out buttons and links
	* adding <em>gainwp_gtag_commands</em> and <em>gainwp_gtag_script_path</em> hooks to allow further gtag (Global Site Tag) code customization
	* adds opt-out and DNT support for Google Tag Manager	
	
= 5.3.1.1 =
* Bug Fixes:	
	* avoid tracking issues by not clearing the profiles list on automatic token resets

= 5.3.1 =
* Bug Fixes:	
	* frontend_item_reports PHP notice when upgrading from a version lower than v4.8.0.1   

= 5.3 =
* Enhancements: 
	* adds full support for Global Site Tag (gtag.js)
	* remove Scroll Depth functionality, since this is now available as a trigger on Google Tag Manager
	* adds custom dimensions support for AMP pages with Google Tag Manager tracking
	* adds support for button submits
* Bug Fixes:	
	* form submit events were not following the non-interaction settings   
	
= 5.2.3.1 =
* Bug Fixes:	
	* fixing a small reporting issue 
	
= 5.2.3 =
* Enhancements:
	* add Google Analytics user opt-out support
	* add option to exclude tracking for users sending the <em>Do Not Track</em> header
	* add System tab to Errors & Debug screen
	* check to avoid using a redeemed access code
* Bug Fixes:	
	* remove a debugging message
	* cURL options were overwritten during regular API calls	

= 5.2.2 =
* Enhancements:  
	* more informative alerts and suggestions on the authorization screen
	* disable autocomplete for the access code input field to avoid reuse of the same unique authorization code
	* GAINWP Endpoint improvements
	* Error reporting improvements
	* introducing the gainwp_maps_api_key filter
* Bug Fixes:	
	* use the theme color palette for the frontend widget 	 

= 5.2.1 =
* Enhancements:  
	* avoid submitting empty error reports
* Bug Fixes:	
	* fixes a bug for custom PHP cURL options 
	
= 5.2 =
* Enhancements:  
	* improvements on exponential backoff system
	* introduces a new authentication method with endpoints
	* multiple updates of plugin's options
	* code cleanup
	* improvements on error reporting system
	* option to report errors to developer
	* move the upgrade notice from the Dashboard to plugin's settings page
	* enable PHP cURL proxy support using WordPress settings, props by [Joe Hobson](https://github.com/joehobson)
	* hide unusable options based on plugin's settings 
* Bug Fixes:	
	* some thrown errors were not displayed on Errors & Debug screen
	* analytics icon disappears from post list after quick edit, props by [karex](https://github.com/karex)
	* fix for inline SVG links, props by [Andrew Minion](https://github.com/macbookandrew)
	* fixes a bug on affiliate events tracking
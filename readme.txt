=== GAinWP Google Analytics Integration for WP ===
Contributors: tomdude
Tags: google analytics, ga4, google tag manager, consent mode, analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 6.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add Google Analytics 4 or Google Tag Manager to WordPress, with Consent Mode v2, privacy controls, page attributes and extra engagement events.

== Description ==

GAinWP adds the Google Analytics 4 tag (or a Google Tag Manager container) to every page of your site. It is lightweight, has no dashboards or upsells, and sends your data straight to Google: nothing passes through a third party.

= Google Analytics 4 =

* Paste your Measurement ID (G-XXXXXXXXXX) and you're done.
* Page attributes sent with every page view: author, category, tags, publication year and month, and whether the visitor is logged in.
* Engagement events that GA4 enhanced measurement doesn't cover: email and phone link clicks, same-page anchor clicks, affiliate link clicks, and scroll depth at 25, 50, 75 and 100%.
* Cross-domain measurement, cookie domain and cookie lifetime settings.

= Google Tag Manager =

* Adds your container, including the no-JavaScript fallback.
* Pushes page attributes to the data layer (`gainwpAuthor`, `gainwpCategory`, `gainwpTag`, `gainwpPublicationYear`, `gainwpPublicationYearMonth`, `gainwpUser`).
* Optional AMP container for AMP plugin Reader mode pages.

= Privacy =

* Google Consent Mode v2 defaults: leave them to your consent banner, deny in the EEA, UK and Switzerland only, or deny everywhere.
* Optionally skip tracking for visitors who send Do Not Track or Global Privacy Control.
* Visitor opt-out link or button with the `[gainwp_useroptout]` shortcode.
* Exclude logged-in user roles (and network super admins) from tracking. Post previews and the Customizer are never tracked.

> **Bug reports and feature requests:** [GAinWP on GitHub](https://github.com/levelten/ga-in).

== Upgrading from 5.x ==

Version 6.0 is a rewrite for Google Analytics 4. Google stopped processing Universal Analytics data in July 2023, so 5.x has not been collecting anything since then.

**After updating, go to Settings &rarr; Google Analytics and enter your GA4 Measurement ID.** Until you do, GAinWP adds no tag and shows a reminder on the Dashboard and Plugins screens.

Carried over automatically:

* Tracking method, including Tag Manager containers. Tag Manager sites keep working with no changes; data layer variable names are the same as before.
* Custom dimension and data layer choices, which become page attributes.
* Email, phone, anchor, affiliate and scroll tracking, Do Not Track, opt-out, excluded roles, cross-domain domains, cookie domain and lifetime.
* Affiliate links are now matched by plain URL fragments such as `/out/` instead of a regular expression. Simple patterns convert automatically; if yours can't, the settings screen tells you and affiliate events stay off until you update it.

Removed:

* Dashboard reports, real-time stats, post reports and the front-end widget. They relied on Google APIs that were shut down. Use Google Analytics, or Site Kit by Google if you want reports in WordPress.
* Download, outbound link and form submit events: GA4 enhanced measurement records these itself.
* Google Optimize (discontinued by Google), Analytics on AMP pages, Universal Analytics ecommerce, sample rates, enhanced link attribution and the custom cookie name.
* IP anonymization (GA4 never stores IP addresses) and the remarketing switch (manage Google signals in Google Analytics instead).
* Custom event categories, actions and labels set with `data-vars-ga-*` attributes. GA4 has no categories or labels, so tracked links send the standard event names.
* Your stored Google sign-in and cached report data are deleted.

Opt-outs recorded by 5.x are still honored.

== Installation ==

1. Install and activate GAinWP from Plugins &rarr; Add New.
2. Go to Settings &rarr; Google Analytics.
3. Choose Google Analytics 4 and enter your Measurement ID, or choose Google Tag Manager and enter your container ID.
4. Save. Open your site in a private browser window and check Reports &rarr; Realtime in Google Analytics.

== Frequently Asked Questions ==

= Where do I find my Measurement ID? =

In Google Analytics, go to Admin &rarr; Data streams and open your web stream. The Measurement ID starts with G-.

= Why don't page attributes show up in my reports? =

GA4 only reports parameters you register. In Google Analytics, go to Admin &rarr; Custom definitions and create an event-scoped custom dimension for each parameter: `post_author`, `post_category`, `post_tags`, `publish_year`, `publish_month` and `user_type`.

= Does it work with cookie consent banners? =

Yes. Most banner plugins set Google Consent Mode themselves, so leave "Consent mode default" set to "Not set". Choose a denied option only if your banner sends consent updates but doesn't set defaults.

= How do I check that it's working? =

View the source of any page while logged out and look for the `GAinWP` comment followed by the Google tag. Settings &rarr; Google Analytics also shows what is being sent. Logged-in users in excluded roles are not tracked, so test from a private browser window.

= Can I use it alongside Site Kit or another analytics plugin? =

Use only one plugin to add the Google tag, or page views are counted twice. Site Kit can show reports without adding the tag if you turn off its snippet.

= How can I customize the output? =

Developers can use these hooks:

* `gainwp_gtag_config` (filter): parameters of the `gtag('config')` command.
* `gainwp_page_data` (filter): page attributes before they are sent.
* `gainwp_exclude_tracking` (filter): return true to skip tracking for the current request. It runs after the query is parsed, so conditional tags such as `is_page()` work.
* `gainwp_consent_regions` (filter): regions where consent defaults to denied.
* `gainwp_scroll_thresholds` (filter): scroll depth percentages.
* `gainwp_gtag_script_path` (filter): gtag.js URL, for example to use a proxy.
* `gainwp_tagmanager_datalayer` (action): change data layer variables with the tracker's `get()` and `set()` methods.

`gainwp_gtag_commands` still runs but is deprecated in favor of `gainwp_gtag_config`.

== External services ==

GAinWP connects your site to Google so you can measure visits. It contacts no other service.

* **Google Analytics 4 mode** loads the Google tag (gtag.js) from googletagmanager.com on every page. The visitor's browser then sends page views, the page attributes and events you enable, and standard browser data (such as page URL, referrer, screen size and language) to Google Analytics, under the Measurement ID you enter.
* **Google Tag Manager mode** loads your container from googletagmanager.com on every page. What it sends, and to whom, depends on the tags you configure in Tag Manager.

Nothing is sent to Google from your server, and nothing is sent before you enter an ID. Google's [Terms of Service](https://policies.google.com/terms), [Privacy Policy](https://policies.google.com/privacy) and [Google Analytics Terms](https://marketingplatform.google.com/about/analytics/terms/us/) apply.

== Changelog ==

= 6.0.0 =
* Rewritten for Google Analytics 4 and modern WordPress (PHP 7.4+, WordPress 6.0+).
* New: Google Consent Mode v2 defaults, Global Privacy Control support, scroll depth for GA4, opt-out for Tag Manager sites.
* Settings move to Settings &rarr; Google Analytics, with a status summary.
* Settings from 5.x are migrated automatically. See "Upgrading from 5.x".
* Removed Universal Analytics, dashboard reports and real-time stats, Google sign-in, and features Google has discontinued.

= 5.4.6 =
* Last Universal Analytics release.

== Upgrade Notice ==

= 6.0.0 =
Major update for Google Analytics 4. Universal Analytics is retired: after updating, enter your GA4 Measurement ID under Settings > Google Analytics. Dashboard reports have been removed.

=== GAinWP Google Analytics Integration for WP ===
Contributors: tomdude
Tags: google analytics, ga4, google tag manager, consent mode, analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 6.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Analytics 4 for WordPress that tracks results: form leads, sign-ups and goal values, with GA4's own event names and a setup checklist.

== Description ==

GAinWP adds Google Analytics 4 (or a Google Tag Manager container) to your site and sends the events and dimensions that show what your site achieves, not just how many people visit. It uses GA4's own event names, so results work in GA4 reports, Google Ads and anything else that reads GA4. It has no dashboards or upsells, and your data goes straight to Google.

= Track results, not just traffic =

* **Form leads:** an event for every successful submission in Gravity Forms, Contact Form 7 and WPForms, whether the form uses AJAX, reloads the page or redirects to a thank-you page. Failed validation and spam don't count. Set the forms that bring you business to Lead and they send GA4's `generate_lead`; a sign-up form can send `sign_up`. Other forms send `form_complete`, so a newsletter or search form never counts as a lead. Field values are never sent.
* **Goals with values:** mark the results that matter as goals and say what each is worth, for example $100 for a lead. Goals send their value to GA4 and, through it, Google Ads; per-form values override the default.
* **WordPress events:** logins and registrations as GA4's `login` and `sign_up`, and comments as `comment_submit`.
* **Engagement events** that GA4 enhanced measurement doesn't cover: email and phone link clicks, affiliate link clicks (including `rel="sponsored"` links), same-page anchor clicks, and scroll depth at 25, 50 and 75%.

= Page attributes =

Sent with every event, under GA4-style names: content type and ID (built into GA4), author, primary category (Yoast SEO and Rank Math's primary category supported), tags, publication month, content group, and whether the visitor is logged in.

= GA4 setup checklist =

A GA4 setup tab lists exactly what to do in Google Analytics for your settings: which custom dimensions to register, which events to mark as key events, and which GA4 settings to change (currency, data retention, enhanced measurement, data redaction). It also warns when Site Kit by Google adds the same tag or events, which would count them twice.

= Google Analytics 4 and Tag Manager =

* Paste your Measurement ID (G-XXXXXXXXXX), or your Tag Manager container ID.
* In Tag Manager mode, page attributes go to the data layer (under their GA4 names and GAinWP 5.x's `gainwp*` names) and events are pushed as `{ event, intelligence: { ... } }`.
* Cross-domain measurement, cookie domain and cookie lifetime settings, and an optional AMP container for AMP plugin Reader mode pages.

= Privacy =

* Google Consent Mode v2 defaults: leave them to your consent banner, deny in the EEA, UK and Switzerland only, or deny everywhere.
* Optionally skip tracking for visitors who send Do Not Track or Global Privacy Control.
* Visitor opt-out link or button with the `[gainwp_useroptout]` shortcode. A visitor's opt-out is honored even if the site later turns the shortcode off.
* No personal data in events: email addresses and phone numbers are removed from link text and URLs, and authors are sent by display name only.
* Exclude logged-in user roles (and network super admins) from tracking. Post previews and the Customizer are never tracked.

> **Bug reports and feature requests:** [GAinWP on GitHub](https://github.com/levelten/ga-in).

== Upgrading from 6.0 ==

* **Form tracking starts on.** Successful submissions send `form_complete`, which nothing else sends, so leads your site already tracks another way aren't counted twice. Set the forms that bring you business to Lead under Settings &rarr; Google Analytics &rarr; Forms, and remove any other lead tracking for them. Logins, sign-ups and comments are off until you turn them on. A notice lists what's new.
* **Page attribute parameters have new names**, matching GA4's style: `post_author` is now `content_author`, `post_category` is `content_category`, `post_tags` is `content_tags`, `publish_month` is `content_publish_month` and `user_type` is `user_status`. `publish_year` was removed: the month starts with the year. If you registered the old names as custom dimensions, register the new ones (the GA4 setup tab lists them). Tag Manager's `gainwp*` data layer names are unchanged.
* Page attributes are now sent with `gtag('config')`, as Google recommends, and apply to every event.
* The "Username" author format was removed; authors are sent by display name.
* Scroll depth stops at 75%: GA4's own scroll event covers 90%.
* Engagement events now work in Tag Manager mode too.

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

= Why don't page attributes or form names show up in my reports? =

GA4 only reports custom parameters you register. The GA4 setup tab under Settings &rarr; Google Analytics lists the ones your settings send, such as `content_author`, `content_category` and `component_name`. In Google Analytics, go to Admin &rarr; Data display &rarr; Custom definitions and create an event-scoped custom dimension for each. Content type, content ID and content group are built into GA4.

= How do I count form submissions as conversions? =

Under Settings &rarr; Google Analytics &rarr; Forms, set the forms that bring you business to Lead (every form sends `form_complete` until you do). In Google Analytics, mark `generate_lead` (and `sign_up`, if you use it) as a key event. Mark `generate_lead`, not GA4's own `form_submit`, which counts every submit attempt. Give leads a value under Goals to see the value they bring in GA4 and Google Ads.

= Which form plugins are supported? =

Gravity Forms, Contact Form 7 and WPForms (Lite and Pro). Contact Form 7 counts a submission once its email has been sent, which is when it reports success. Other plugins and themes can send their own events with `gainwp.track()` or the PHP recorder (see below).

= Does it work with cookie consent banners? =

Yes. Most banner plugins set Google Consent Mode themselves, so leave "Consent mode default" set to "Not set". Choose a denied option only if your banner sends consent updates but doesn't set defaults.

= How do I check that it's working? =

View the source of any page while logged out and look for the `GAinWP` comment followed by the Google tag. Settings &rarr; Google Analytics also shows what is being sent. Logged-in users in excluded roles are not tracked, so test from a private browser window.

= Can I use it alongside Site Kit or another analytics plugin? =

Use only one plugin to add the Google tag, or page views are counted twice. Site Kit can show reports without adding the tag if you turn off its "Place Google Analytics code" setting. If Site Kit's plugin conversion tracking is on, also turn it off when GAinWP tracks your forms, or leads count twice. GAinWP warns you when it sees either overlap.

= How can I customize the output? =

Developers can use these hooks:

* `gainwp_gtag_config` (filter): parameters of the `gtag('config')` command.
* `gainwp_page_data` (filter): page attributes before they are sent, keyed by parameter name (`content_author`, ...). Keys from 6.0 (`author`, ...) are still mapped.
* `gainwp_exclude_tracking` (filter): return true to skip tracking for the current request. For page output it runs after the query is parsed, so conditional tags such as `is_page()` work; it also runs when an event is recorded on the server, such as a login or form submission, with a second argument `'event'` (page output passes `'page'`).
* `gainwp_event` (filter): change an event recorded on the server, or return null to drop it.
* `gainwp_event_recorded` (action): runs when an event recorded on the server is queued, for example to react to a lead.
* `gainwp_integrations` (filter): add or remove integrations (classes implementing `GAinWP\Integrations\Integration`, or `FormSource` for form plugins). An integration's `ajax_events()` names the browser events, such as a form plugin's success event, after which events recorded during an AJAX request are sent.
* `gainwp_consent_regions` (filter): regions where consent defaults to denied.
* `gainwp_scroll_thresholds` (filter): scroll depth percentages.
* `gainwp_gtag_script_path` (filter): gtag.js URL, for example to use a proxy.
* `gainwp_tagmanager_datalayer` (action): change data layer variables with the tracker's `get()` and `set()` methods.

`gainwp_gtag_commands` still runs but is deprecated in favor of `gainwp_gtag_config`.

To send your own events from JavaScript, call `gainwp.track( 'event_name', { parameter: 'value' } )`; it works in both tracking modes and honors opt-outs. From PHP, call `\GAinWP\Plugin::instance()->recorder()->record( 'event_name', array( 'parameter' => 'value' ) )`; the event is sent from the visitor's browser on their next page view, or right away after an AJAX submission in a supported form plugin.

== Screenshots ==

1. Choose Google Analytics 4 or Google Tag Manager and paste your ID. The status line shows what GAinWP is sending.
2. Goals: the results that matter on your site, each with a value in your currency.
3. Forms from Gravity Forms, Contact Form 7 and WPForms, each sending a lead, a sign-up or another event, with an optional value of its own.
4. WordPress and engagement events, and the page attributes sent with every event.
5. The GA4 setup tab: the custom dimensions to register, the key events to mark and the GA4 settings to check.
6. Privacy settings (Consent Mode v2 defaults, Do Not Track and Global Privacy Control, visitor opt-out) and role exclusions.

== External services ==

GAinWP connects your site to Google so you can measure visits. It contacts no other service.

* **Google Analytics 4 mode** loads the Google tag (gtag.js) from googletagmanager.com on every page. The visitor's browser then sends page views, the page attributes and events you enable, and standard browser data (such as page URL, referrer, screen size and language) to Google Analytics, under the Measurement ID you enter.
* **Google Tag Manager mode** loads your container from googletagmanager.com on every page. What it sends, and to whom, depends on the tags you configure in Tag Manager.

Nothing is sent to Google from your server, and nothing is sent before you enter an ID. Events that happen on the server (a login, a form submission) are passed to the visitor's browser in a short-lived first-party cookie, `gainwp_events_` followed by random characters, which holds only the pending event and is deleted when the event is sent or after a day. Visitors who opt out get a `gainwp_optout` cookie. Google's [Terms of Service](https://policies.google.com/terms), [Privacy Policy](https://policies.google.com/privacy) and [Google Analytics Terms](https://marketingplatform.google.com/about/analytics/terms/us/) apply.

== Changelog ==

= 6.1.0 =
* New: form tracking for Gravity Forms, Contact Form 7 and WPForms, on by default. Successful submissions send `form_complete`, or `generate_lead` or `sign_up` for forms you set to Lead or Sign-up, with the form's ID and name, including AJAX forms and forms that redirect.
* New: goals with values. Mark results as goals, give them a value and a currency, and set per-form values.
* New: GA4's `login` and `sign_up` events for WordPress logins and registrations, and `comment_submit` for comments.
* New: GA4 setup tab listing the custom dimensions, key events and GA4 settings your configuration needs, with warnings when Site Kit adds the same tag or events.
* New: page attributes for content type and ID, content group, and the primary category from Yoast SEO or Rank Math.
* New: `gainwp.track()` in JavaScript and a PHP recorder for sending your own events; `gainwp_event`, `gainwp_event_recorded` and `gainwp_integrations` hooks.
* Changed: page attribute parameters renamed to GA4-style names (`content_author`, `content_category`, `content_tags`, `content_publish_month`, `user_status`); `publish_year` removed. See "Upgrading from 6.0".
* Changed: page attributes are sent with `gtag('config')` instead of `gtag('set')`, so they reliably apply to every event.
* Changed: engagement events now work in Tag Manager mode, and links marked `rel="sponsored"` count as affiliate links.
* Changed: scroll depth stops at 75%; GA4's enhanced measurement covers 90%.
* Changed: authors are always sent by display name; the username option was removed.
* Fixed: a visitor's opt-out is honored even after the site turns the opt-out shortcode off.
* Fixed: link click events no longer send the Universal Analytics `transport_type` parameter.

= 6.0.0 =
* Rewritten for Google Analytics 4 and modern WordPress (PHP 7.4+, WordPress 6.0+).
* New: Google Consent Mode v2 defaults, Global Privacy Control support, scroll depth for GA4, opt-out for Tag Manager sites.
* Settings move to Settings &rarr; Google Analytics, with a status summary.
* Settings from 5.x are migrated automatically. See "Upgrading from 5.x".
* Removed Universal Analytics, dashboard reports and real-time stats, Google sign-in, and features Google has discontinued.

= 5.4.6 =
* Last Universal Analytics release.

== Upgrade Notice ==

= 6.1.0 =
Adds form tracking for Gravity Forms, Contact Form 7 and WPForms (on by default: set your lead forms to Lead), goals with values, and a GA4 setup checklist. Page attribute parameters have new names: register them again in GA4 if you used the 6.0 names.

= 6.0.0 =
Major update for Google Analytics 4. Universal Analytics is retired: after updating, enter your GA4 Measurement ID under Settings > Google Analytics. Dashboard reports have been removed.

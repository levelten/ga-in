# GAinWP

Google Analytics 4 and Google Tag Manager for WordPress. This is the development repository; the user-facing description is in [readme.txt](readme.txt) and the plugin is published at [wordpress.org/plugins/ga-in](https://wordpress.org/plugins/ga-in/).

## Requirements

- PHP 7.4+ and WordPress 6.0+ to run the plugin.
- Composer and Node 22.13+ for development, plus Docker for the local WordPress site.

## Setup and checks

```sh
composer install
npm install

composer test      # PHP unit tests (PHPUnit + Brain Monkey, no WordPress needed)
composer lint      # WordPress Coding Standards + PHP 7.4 compatibility
composer analyse   # PHPStan level 8
composer check     # all three
npm test           # behavior tests for assets/js/*.js (jsdom)
npm run test:upgrade  # update a real 5.4.6 install to this build (needs Docker)
```

CI runs all of these on every push and pull request, across PHP 7.4 to 8.4, plus the wordpress.org [Plugin Check](https://github.com/WordPress/plugin-check-action) against a release build.

### Upgrade test

`npm run test:upgrade` starts a separate wp-env site (port 8897), installs GAinWP 5.4.6 from wordpress.org, configures it with 5.4.6's own code as a Universal Analytics site and as a Tag Manager site, then updates it to a release build of this repository. It checks that the plugin stays active, settings migrate, credentials and cached reports are removed, the front end outputs the right tag, no PHP errors are logged and uninstall removes everything. CI runs it on PHP 7.4 and 8.3. Set `WP_ENV_PHP_VERSION` to try another PHP version and `KEEP_ENV=1` to keep the site running afterwards.

## Local WordPress site

[wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) runs the latest WordPress in Docker with this plugin active and debug output on:

```sh
npm run env:start          # http://localhost:8895 (admin / password)
npm run env:cli -- option get gainwp_settings
npm run env:stop
```

To try another PHP version without changing the shared config, create `.wp-env.override.json` (git-ignored) with `{ "phpVersion": "7.4" }` and run `npx wp-env start --update`.

## Layout

| Path | Purpose |
| --- | --- |
| `gainwp.php` | Bootstrap: PHP version guard, constants, autoloader. Keep the file name: renaming it deactivates the plugin on update. |
| `src/Options.php` | Settings schema, defaults and sanitizing. Every read goes through `sanitize()`. |
| `src/Upgrade.php` | Settings migrations (5.x and 6.0) and recommended settings for new installs. |
| `src/Tracking/` | Front-end output: `Gtag` (GA4), `TagManager`, consent, opt-out, page attributes. |
| `src/Events/` | Server-side events: `Recorder` (exclusions, goal values, hooks) and `Queue` (one short-lived cookie per request, read by `events.js`). |
| `src/Integrations/` | What gets recorded: `WordPress` (login, sign-up, comments) and the form plugins, each a small class behind `Registry` and the `gainwp_integrations` filter. |
| `src/Admin/` | Settings screen (Settings API), GA4 setup checklist, Site Kit overlap detection and admin notices. |
| `assets/js/events.js` | `gainwp.track()`, engagement events and delivery of server events (no build step, no dependencies). |
| `uninstall.php` | Deletes all plugin data, including 5.x leftovers. |
| `.wordpress-org/` | Banners and icons for the wordpress.org SVN `assets` folder. |

## Compatibility promises

Existing sites update automatically, so these must not change without a migration:

- Plugin slug, main file (`ga-in/gainwp.php`) and text domain (`ga-in`).
- Tag Manager data layer variable names (`gainwp*`, `content_*`, the `intelligence` event object), which users reference in their containers.
- GA4 parameter and event names (`content_author`, `generate_lead`, `email_link_click`, ...), which users register as custom dimensions and mark as key events.
- Public hooks listed in readme.txt.

6.1 renamed 6.0's page attribute parameters once (`post_author` to `content_author` and so on), a few days after 6.0 shipped. Settings migrate automatically, the `gainwp_page_data` filter maps 6.0 keys, and the changelog tells sites to register the new names. Names are fixed from 6.1 on.

## Known gaps from 5.x

Deliberately left out of 6.0; revisit if users ask.

- **Custom event labels from HTML attributes.** 5.x let `data-vars-ga-category`, `data-vars-ga-action` and `data-vars-ga-label` on a tracked link override its UA event category, action and label. GA4 has no category/action/label, so 6.0 ignores them and sends the default event names. If needed: in `assets/js/events.js`, send `data-vars-ga-label` as `link_text` on links GAinWP already tracks, and add a `data-gainwp-event` attribute to rename the event (with tests in `tests/js/events.test.mjs`).

## Releasing

1. Set the version in `gainwp.php` (header and `GAINWP_VERSION`) and `Stable tag` in readme.txt, and add a changelog entry.
2. Merge to `master`.
3. Publish a GitHub release tagged with the version (for example `6.0.0`). The Deploy workflow checks that the versions match and pushes to wordpress.org SVN. It needs the `SVN_USERNAME` and `SVN_PASSWORD` repository secrets.

A weekly workflow opens a pull request to bump "Tested up to" when a new WordPress version ships, after Plugin Check passes against it.

## License

GPL-2.0-or-later. Originally based on Google Analytics Dashboard for WordPress (GADWP) by Alin Marcu; 6.0 replaced that code.

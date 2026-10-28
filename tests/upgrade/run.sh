#!/usr/bin/env bash
#
# Upgrade test: installs GAinWP 5.4.6 from wordpress.org on a clean wp-env
# site, configures it with 5.4.6's own code, updates it to a release build of
# this repository the way WordPress replaces a plugin, and checks migration,
# front-end output, PHP errors and uninstall.
#
# Usage: npm run test:upgrade
# Env:   WP_ENV_PHP_VERSION=7.4  run on another PHP version
#        KEEP_ENV=1              leave the site running at http://localhost:8897

set -Eeuo pipefail

# wp-env resolves the config's mappings from the repository root.
cd "$(dirname "$0")/../.."
CONFIG=tests/upgrade/.wp-env.json
URL=http://localhost:8897
BUILD=build/upgrade
ENV=(npx wp-env --config "$CONFIG")
FAILS=0
STARTED=0

finish() {
	local status=$?
	if [ "$STARTED" = 1 ] && [ "${KEEP_ENV:-}" != "1" ]; then
		"${ENV[@]}" stop >/dev/null 2>&1 || true
	fi
	printf '\n== Result\n'
	if [ "$status" -ne 0 ] && [ "$FAILS" -eq 0 ]; then
		echo "Aborted: a step failed (see the error above)"
	elif [ "$FAILS" -gt 0 ]; then
		echo "$FAILS check(s) failed"
		status=1
	else
		echo "All checks passed"
	fi
	exit "$status"
}
trap finish EXIT
trap 'echo "ERROR: step failed (line $LINENO): $BASH_COMMAND" >&2' ERR

# Hides wp-env's own status lines but keeps real errors visible.
quiet() { grep -vE '^(ℹ|✔) ' >&2 || true; }
wp() { "${ENV[@]}" run cli wp "$@" 2> >(quiet); }
container() { "${ENV[@]}" run cli bash -c "$1" 2> >(quiet); }
section() { printf '\n== %s\n' "$1"; }

# Runs a wp-env command, printing its log only if it fails.
wp_env() {
	local log="$BUILD/wp-env.log"
	"${ENV[@]}" "$@" >"$log" 2>&1 || { cat "$log" >&2; return 1; }
}

# check LABEL ACTUAL EXPECTED
check() {
	if [ "$2" = "$3" ]; then
		echo "PASS $1"
	else
		echo "FAIL $1 (expected '$3', got '$2')"
		FAILS=$((FAILS + 1))
	fi
}

# Runs a PHP check script in WordPress and counts its FAIL lines.
php_checks() {
	local out
	out=$(wp eval-file "wp-content/gainwp-upgrade-test/$1")
	echo "$out"
	FAILS=$((FAILS + $(grep -c '^FAIL' <<<"$out" || true)))
}

# Fetches a page, failing on HTTP errors, and checks it rendered completely,
# so "tag absent" checks can't pass on an error page.
page() {
	local html
	html=$(curl -fsSL "$URL$1")
	if ! grep -q '</html>' <<<"$html" || grep -qi 'critical error' <<<"$html"; then
		echo "ERROR: $1 did not render a complete page" >&2
		return 1
	fi
	printf '%s' "$html"
}
contains() { grep -c -- "$2" <<<"$1" || true; }
has() { grep -q -- "$2" <<<"$1" && echo yes || echo no; }

install_546() {
	wp_env clean development
	wp plugin install ga-in --version=5.4.6 --force --activate >/dev/null
	check "5.4.6 installed from wordpress.org" "$(wp plugin get ga-in --field=version)" "5.4.6"
}

update_to_build() {
	wp plugin install "wp-content/gainwp-build/ga-in.zip" --force >/dev/null
	check "still active after the update" "$(wp plugin is-active ga-in && echo yes || echo no)" "yes"
	check "updated to the build" "$(wp plugin get ga-in --field=version)" "$VERSION"
	container 'rm -f wp-content/debug.log'
}

# Fails on any PHP notice, warning or error from the updated plugin.
check_php_log() {
	local count
	count=$(container 'if [ -f wp-content/debug.log ]; then grep -c "plugins/ga-in/" wp-content/debug.log || true; else echo 0; fi')
	check "$1: no PHP errors from GAinWP" "$count" "0"
}

# Fails if an inline GAinWP script isn't valid JavaScript.
check_js() {
	local html=$1 id=$2 file="$BUILD/$2.js"
	python3 -I -c 'import re,sys; m=re.search(r"<script id=\"%s\">(.*?)</script>" % sys.argv[1], sys.stdin.read(), re.S); open(sys.argv[2], "w").write(m.group(1) if m else "")' "$id" "$file" <<<"$html"
	check "$id script is valid JavaScript" "$([ -s "$file" ] && node --check "$file" 2>/dev/null && echo yes || echo no)" "yes"
}

section "Build"
# The release is built from git, so a file that exists here but isn't
# tracked would pass this test and still be missing from the release.
UNTRACKED=$(git ls-files --others --exclude-standard -- src assets gainwp.php uninstall.php readme.txt)
if [ -n "$UNTRACKED" ]; then
	printf 'Release files not tracked by git (git add them first):\n%s\n' "$UNTRACKED" >&2
	exit 1
fi
rm -rf "$BUILD"
mkdir -p "$BUILD/stage"
rsync -a --exclude-from=.distignore ./ "$BUILD/stage/ga-in/"
(cd "$BUILD/stage" && zip -qr ../ga-in.zip ga-in)
rm -rf "$BUILD/stage"
VERSION=$(sed -nE 's/^ \* Version: +(.+)$/\1/p' gainwp.php)
echo "ga-in $VERSION -> $BUILD/ga-in.zip"

section "Start WordPress (PHP ${WP_ENV_PHP_VERSION:-8.3})"
STARTED=1
wp_env start
echo "WordPress $(wp core version) at $URL"

section "Universal Analytics site"
install_546
php_checks configure-5x-gtag.php
check "5.x outputs its UA tag" "$(has "$(page /)" 'UA-12345-1')" "yes"
update_to_build
HOME_HTML=$(page /)
check "no tag until a GA4 ID is entered" "$(contains "$HOME_HTML" '<!-- GAinWP')" "0"
check "UA tag gone" "$(contains "$HOME_HTML" 'UA-12345-1')" "0"
php_checks check-migrated-gtag.php
wp option patch update gainwp_settings measurement_id G-UPGRADE1 >/dev/null
POST_HTML=$(page '/?p=1')
check "GA4 tag after entering the ID" "$(contains "$POST_HTML" 'gtag("config", "G-UPGRADE1")')" "1"
check "page attributes sent" "$(contains "$POST_HTML" '"post_author":"admin"')" "1"
check "gtag.js waits for the opt-out check" "$(contains "$POST_HTML" 'if (!window.gainwpOptOut)')" "1"
check "opt-out handler loaded" "$(contains "$POST_HTML" 'assets/js/optout.js')" "1"
check "events configured" "$(contains "$POST_HTML" '"affiliates":\["/go/","/recommends/"\]')" "1"
check_js "$POST_HTML" gainwp-gtag
check_php_log "Universal Analytics site"

section "Uninstall"
wp plugin uninstall ga-in --deactivate >/dev/null
php_checks check-uninstalled.php

section "Tag Manager site"
install_546
php_checks configure-5x-gtm.php
check "5.x outputs the container" "$(has "$(page /)" 'GTM-ABC1234')" "yes"
update_to_build
POST_HTML=$(page '/?p=1')
check "container still loads with no action needed" "$(contains "$POST_HTML" '"GTM-ABC1234"')" "1"
check "5.x data layer names kept" "$(contains "$POST_HTML" '{"gainwpAuthor":"admin","gainwpUser":"guest"}')" "1"
check_js "$POST_HTML" gainwp-gtm
check_php_log "Tag Manager site"

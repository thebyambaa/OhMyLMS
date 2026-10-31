#!/usr/bin/env bash
# Installs a throwaway WordPress for the PHP integration suites (CI job "php-integration").
# The plugin is copied (not symlinked: PHP resolves symlinks in __DIR__) to <root>/wp-content/plugins/ohmylms,
# so `dirname( __DIR__, 5 )` in tests/php/*.php is the WordPress root.
#
# Usage: tools/install-test-wordpress.sh <wordpress-root>
# Environment (defaults in brackets):
#   WP_CLI [wp]   PHP [php]   WP_VERSION [latest]   OHMYLMS_TEST_HOST [127.0.0.1:8080]
#   OHMYLMS_TEST_DB_HOST [127.0.0.1]   OHMYLMS_TEST_DB_PORT [3306]   OHMYLMS_TEST_DB_NAME [ohmylms_ci]
#   OHMYLMS_TEST_DB_USER [root]   OHMYLMS_TEST_DB_PASSWORD [root]
set -euo pipefail
ROOT="${1:?Usage: tools/install-test-wordpress.sh <wordpress-root>}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
read -r -a WP <<<"${WP_CLI:-wp}"
read -r -a PHP_CMD <<<"${PHP:-php}"
HOST="${OHMYLMS_TEST_HOST:-127.0.0.1:8080}"
DB_HOST="${OHMYLMS_TEST_DB_HOST:-127.0.0.1}"
DB_PORT="${OHMYLMS_TEST_DB_PORT:-3306}"
DB_NAME="${OHMYLMS_TEST_DB_NAME:-ohmylms_ci}"
DB_USER="${OHMYLMS_TEST_DB_USER:-root}"
DB_PASSWORD="${OHMYLMS_TEST_DB_PASSWORD:-root}"
case "$DB_NAME" in local | wordpress | wp) echo "Refusing to install into database '$DB_NAME'." >&2; exit 1 ;; esac
wp() { "${WP[@]}" --path="$ROOT" "$@"; }

mkdir -p "$ROOT"
# With content: the learner templates and shortcodes render inside the default theme.
[ -f "$ROOT/wp-load.php" ] || wp core download --version="${WP_VERSION:-latest}" --force

PLUGIN="$ROOT/wp-content/plugins/ohmylms"
if [ "$(cd "$REPO/.." && pwd)" != "$(mkdir -p "$ROOT/wp-content/plugins" && cd "$ROOT/wp-content/plugins" && pwd)" ]; then
  rm -rf "$PLUGIN"
  mkdir -p "$PLUGIN"
  # Tracked and new files of the working tree; ignored files (node_modules, build output) stay behind.
  (cd "$REPO" && git ls-files -z --cached --others --exclude-standard | xargs -0 cp --parents -t "$PLUGIN" 2>/dev/null || true)
  [ -f "$PLUGIN/ohmylms.php" ] || { echo "Copying the plugin to $PLUGIN failed." >&2; exit 1; }
fi

DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_USER="$DB_USER" DB_PASSWORD="$DB_PASSWORD" DB_NAME="$DB_NAME" "${PHP_CMD[@]}" -r '
$db = new mysqli(getenv("DB_HOST"), getenv("DB_USER"), getenv("DB_PASSWORD"), "", (int) getenv("DB_PORT"));
$db->query("CREATE DATABASE IF NOT EXISTS `" . getenv("DB_NAME") . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
'

wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="$DB_HOST:$DB_PORT" \
  --dbcharset=utf8mb4 --skip-check --force
# Like the Local site the suites were written against: WP_DEBUG off leaves display_errors to php.ini, so a
# fatal error reaches the runner's output instead of wp-content/debug.log.
wp config set WP_ENVIRONMENT_TYPE local
wp config set WP_DEBUG false --raw
wp config set AUTOMATIC_UPDATER_DISABLED true --raw
# Private exam-import storage outside the web root, as on the Local site (exams, figure proposals). The site
# serves the published assets/dist bundles: build/ is not in the repository, so OHMYLMS_SOURCE_ASSETS stays off.
PRIVATE="$(cd "$ROOT/.." && pwd)/$(basename "$ROOT")-private"
mkdir -p "$PRIVATE"
wp config set OHMYLMS_IMPORT_PRIVATE_DIR "$PRIVATE"

wp core is-installed 2>/dev/null || wp core install --url="http://$HOST" --title="OhMyLMS CI" --admin_user=admin \
  --admin_password=admin --admin_email=admin@example.test --skip-email
wp option update home "http://$HOST"
wp option update siteurl "http://$HOST"
wp rewrite structure '/%postname%/'
wp plugin activate ohmylms
# integrations-rest-integration expects the MCP integration switched on, as on the Local site.
wp eval '$s = (array) get_option( "ohmylms_integrations", array() ); $s["mcp"] = array_merge( (array) ( $s["mcp"] ?? array() ), array( "is_enable" => 1 ) ); update_option( "ohmylms_integrations", $s );'
wp plugin list --status=active --field=name

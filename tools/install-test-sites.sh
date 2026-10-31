#!/usr/bin/env bash
# Installs the disposable WordPress sites that the credentials, migration and streak suites need (CI job
# "php-integration"), like tools/create-test-site.py but from a clean install instead of the Local site:
#   <base>/source      database ohmylms_source_test                      http://127.0.0.1:8099
#   <base>/migration   database ohmylms_gutenberg_migration_20261020     http://127.0.0.1:8098
#   <base>/streak      database ohmylms_streak_test                      http://127.0.0.1:8097
# Each one is installed by tools/install-test-wordpress.sh, then gets OHMYLMS_TEST_SITE, DISABLE_WP_CRON, the
# isolation mu-plugin (tests/fixtures/isolation.php: no outbound HTTP or mail), the administrator
# ohmylms-test-admin and Mathematics as the default subject profile; the source site also enables the
# examples module. <base>/test-credentials.json is the file for OHMYLMS_TEST_CREDENTIALS:
#   { "username", "password", "site": <source>, "sites": { <database>: <root>, ... } }
# tools/run-php-tests.mjs runs each suite against the site whose database it checks.
#
# Usage: tools/install-test-sites.sh <base-dir>
# Environment: as tools/install-test-wordpress.sh, except that OHMYLMS_TEST_DB_PORT defaults to 10005, the port of
# the disposable sites' MySQL (curriculum-integration opens a second connection on it). Refuses a database that
# exists before its site folder does, and a site folder whose wp-config.php uses another database.
set -euo pipefail
BASE="${1:?Usage: tools/install-test-sites.sh <base-dir>}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
read -r -a WP <<<"${WP_CLI:-wp}"
read -r -a PHP_CMD <<<"${PHP:-php}"
export OHMYLMS_TEST_DB_PORT="${OHMYLMS_TEST_DB_PORT:-10005}"
mkdir -p "$BASE"
BASE="$(cd "$BASE" && pwd)"
# Paths in the JSON are read by PHP; on Git Bash for Windows that needs C:/... rather than /c/...
native() { if command -v cygpath >/dev/null 2>&1; then cygpath -m "$1"; else printf '%s' "$1"; fi; }

CREDENTIALS="$BASE/test-credentials.json"
PASSWORD="$("${PHP_CMD[@]}" -r 'echo bin2hex( random_bytes( 24 ) );')"
SITES=()
for site in source:ohmylms_source_test:8099 migration:ohmylms_gutenberg_migration_20261020:8098 streak:ohmylms_streak_test:8097; do
  IFS=: read -r name database port <<<"$site"
  root="$BASE/$name"
  if [ -f "$root/wp-config.php" ] && ! grep -q "'DB_NAME', '$database'" "$root/wp-config.php"; then
    echo "Refusing to reuse $root: its wp-config.php does not use the database $database." >&2
    exit 1
  fi
  # A database this script did not create (no site folder yet) may belong to tools/create-test-site.py.
  if [ ! -f "$root/wp-config.php" ] && DB_NAME="$database" "${PHP_CMD[@]}" -r '
$db = new mysqli( getenv( "OHMYLMS_TEST_DB_HOST" ) ?: "127.0.0.1", getenv( "OHMYLMS_TEST_DB_USER" ) ?: "root", getenv( "OHMYLMS_TEST_DB_PASSWORD" ) ?: "root", "", (int) getenv( "OHMYLMS_TEST_DB_PORT" ) );
exit( $db->query( "SHOW DATABASES LIKE \"" . $db->real_escape_string( getenv( "DB_NAME" ) ) . "\"" )->num_rows ? 0 : 1 );
'; then
    echo "Refusing to install into the existing database $database on port $OHMYLMS_TEST_DB_PORT; drop it or use another server." >&2
    exit 1
  fi
  OHMYLMS_TEST_DB_NAME="$database" OHMYLMS_TEST_HOST="127.0.0.1:$port" bash "$REPO/tools/install-test-wordpress.sh" "$root"
  wp() { "${WP[@]}" --path="$root" "$@"; }
  wp config set OHMYLMS_TEST_SITE true --raw
  wp config set DISABLE_WP_CRON true --raw
  # extensions-integration registers the example question, lesson, checkout and membership extensions.
  [ "$name" != source ] || wp config set OHMYLMS_ENABLED_MODULES "array( 'examples' )" --raw
  # Mathematics is the default subject profile on the Local site, whose shared skills are mathematics; on a clean
  # install it is Generic, which has no skill domains (guidance, skill-map).
  wp eval '$s = (array) get_option( "ohmylms_subject_profiles", array() ); $s["default"] = "mathematics"; update_option( "ohmylms_subject_profiles", $s );'
  mkdir -p "$root/wp-content/mu-plugins"
  cp "$REPO/tests/fixtures/isolation.php" "$root/wp-content/mu-plugins/isolation.php"
  if wp user get ohmylms-test-admin --field=ID >/dev/null 2>&1; then
    wp user update ohmylms-test-admin --user_pass="$PASSWORD" --role=administrator --skip-email >/dev/null
  else
    wp user create ohmylms-test-admin ohmylms-test-admin@example.test --role=administrator --user_pass="$PASSWORD" >/dev/null
  fi
  SITES+=("$database" "$(native "$root")")
done

OHMYLMS_TEST_ADMIN_PASSWORD="$PASSWORD" "${PHP_CMD[@]}" -r '
$args  = array_slice( $argv, 1 );
$file  = array_shift( $args );
$sites = array();
for ( $i = 0; $i < count( $args ); $i += 2 ) {
	$sites[ $args[ $i ] ] = $args[ $i + 1 ];
}
$json = array( "username" => "ohmylms-test-admin", "password" => getenv( "OHMYLMS_TEST_ADMIN_PASSWORD" ), "site" => $sites["ohmylms_source_test"], "sites" => $sites );
file_put_contents( $file, json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
' -- "$(native "$CREDENTIALS")" "${SITES[@]}"
chmod 600 "$CREDENTIALS"
echo "Disposable sites in $BASE; OHMYLMS_TEST_CREDENTIALS=$(native "$CREDENTIALS")"

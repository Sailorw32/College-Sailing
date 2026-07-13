#!/usr/bin/env bash
# Sets up a local WordPress instance for previewing the icsa theme, using
# SQLite instead of MySQL so no database server is required. Safe to re-run.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

WP_VERSION="latest"
PORT="${PORT:-8080}"

echo "==> Fetching WP-CLI"
if [ ! -f bin/wp-cli.phar ]; then
  curl -sSL -o bin/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod +x bin/wp-cli.phar
fi
WP="php bin/wp-cli.phar --path=$ROOT_DIR --allow-root"

echo "==> Fetching WordPress core ($WP_VERSION)"
if [ ! -f wp-load.php ]; then
  $WP core download --version="$WP_VERSION" --skip-content
fi

echo "==> Fetching the SQLite Database Integration plugin"
# Extracted directly with unzip rather than `wp plugin install`, since that
# command needs WordPress to already bootstrap a DB connection — which is
# exactly what this plugin's drop-in is here to provide.
mkdir -p wp-content/plugins wp-content/mu-plugins
if [ ! -d wp-content/plugins/sqlite-database-integration ]; then
  curl -sSL -o /tmp/sqlite-plugin.zip \
    https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
  unzip -q -o /tmp/sqlite-plugin.zip -d wp-content/plugins/
fi
# The plugin's own db.php drop-in switches WP to SQLite automatically.
cp -f wp-content/plugins/sqlite-database-integration/db.copy wp-content/db.php

echo "==> Writing wp-config.php"
if [ ! -f wp-config.php ]; then
  $WP config create \
    --dbname=icsa --dbuser=icsa --dbpass=icsa \
    --skip-check --extra-php <<'PHP'
define( 'WP_HOME', 'http://localhost:PORT_PLACEHOLDER' );
define( 'WP_SITEURL', 'http://localhost:PORT_PLACEHOLDER' );
PHP
  sed -i "s/PORT_PLACEHOLDER/${PORT}/g" wp-config.php
fi

echo "==> Installing WordPress"
if ! $WP core is-installed 2>/dev/null; then
  $WP core install \
    --url="http://localhost:${PORT}" \
    --title="ICSA (dev preview)" \
    --admin_user=admin --admin_password=admin --admin_email=dev@example.com \
    --skip-email
fi

echo "==> Activating the icsa theme"
$WP theme activate icsa

echo "==> Removing the default 'Hello world!' sample post"
HELLO_ID=$($WP post list --post_type=post --name=hello-world --field=ID 2>/dev/null || true)
if [ -n "$HELLO_ID" ]; then
  $WP post delete "$HELLO_ID" --force
fi

echo "==> Seeding sample News posts for template preview"
declare -A NEWS_POSTS=(
  ["2026-icsa-all-america-teams-announced"]="2026 ICSA All-America Teams Announced|<p>The Inter-Collegiate Sailing Association is proud to announce the 2026 All-America sailing teams, recognizing the nation's top collegiate sailors across fleet and team racing.</p>"
  ["maisa-fall-championship-results"]="MAISA Fall Championship Results|<p>Results are in from the MAISA Fall Championship, hosted this year with strong winds and a competitive fleet.</p>"
  ["new-umpire-certification-videos-posted"]="New Umpire Certification Videos Posted|<p>A new set of umpire training videos is now available in Resources, covering the 2026 rule changes for team racing.</p>"
)
for slug in "${!NEWS_POSTS[@]}"; do
  IFS='|' read -r title content <<< "${NEWS_POSTS[$slug]}"
  if [ "$($WP post list --post_type=post --name="$slug" --format=count)" = "0" ]; then
    $WP post create --post_type=post --post_status=publish \
      --post_title="$title" --post_name="$slug" --post_content="$content"
  fi
done

echo "==> Seeding placeholder pages for the primary navigation"
declare -A NAV_PAGES=(
  ["Home"]="home"
  ["Racing"]="racing"
  ["Recruits & Families"]="recruits-families"
  ["Hall of Fame"]="hall-of-fame"
  ["News"]="news"
  ["Resources"]="resources"
  ["About"]="about"
)
for title in "${!NAV_PAGES[@]}"; do
  slug="${NAV_PAGES[$title]}"
  if [ "$($WP post list --post_type=page --name="$slug" --format=count)" = "0" ]; then
    $WP post create --post_type=page --post_status=publish \
      --post_title="$title" --post_name="$slug" \
      --post_content="<!-- wp:paragraph --><p>Placeholder page for ${title}. Real content migration from the current site is a later pass — see docs/audit.md.</p><!-- /wp:paragraph -->"
  fi
done

HOME_ID=$($WP post list --post_type=page --name=home --field=ID)
NEWS_ID=$($WP post list --post_type=page --name=news --field=ID)
$WP option update show_on_front page
$WP option update page_on_front "$HOME_ID"
$WP option update page_for_posts "$NEWS_ID"
$WP rewrite structure '/%postname%/'
$WP rewrite flush --hard

echo "==> Starting PHP built-in server on http://localhost:${PORT}"
php -S "localhost:${PORT}" "$ROOT_DIR/router.php"

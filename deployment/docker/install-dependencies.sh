#!/bin/sh
set -eu
# Composer may remove its vendor directory during installation. Never install
# directly into a Docker mount point. Keep Windows vendor isolated.
cd /app
export COMPOSER_ALLOW_SUPERUSER=1
unset COMPOSER_VENDOR_DIR
key="$(sha256sum composer.json composer.lock | sha256sum | cut -d ' ' -f 1)"
if [ -f vendor/autoload.php ] && [ -f vendor/.aydra-lock ] && [ "$(cat vendor/.aydra-lock)" = "$key" ]; then
    composer check-platform-reqs --no-dev --no-interaction
    exit 0
fi
# Require the dedicated writable mount, never the host vendor directory.
awk '$5 == "/app/vendor" && $6 ~ /(^|,)rw(,|$)/ {found=1} END {exit !found}' /proc/self/mountinfo || { echo 'Volume vendor Linux inscriptible absent.' >&2; exit 1; }
stage="$(mktemp -d /tmp/aydra-composer.XXXXXX)"
trap 'rm -rf -- "$stage"' EXIT
cp composer.json composer.lock "$stage/"
(
    cd "$stage"
    composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-plugins --no-autoloader -v
)
# Only replace contents of the dedicated, uninitialized Linux volume.
# The revision-specific volume is not used by any running worker until ready.
test -f "$stage/vendor/composer/installed.json"
find /app/vendor -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
cp -a "$stage/vendor/." /app/vendor/
# Generate paths and class maps using the real /app project, not the staging directory.
composer dump-autoload --no-dev --no-interaction --no-scripts --no-plugins
composer check-platform-reqs --no-dev --no-interaction
php -r 'require "/app/vendor/autoload.php"; if (!class_exists("Illuminate\\Foundation\\Application")) exit(1);'
printf '%s\n' "$key" > vendor/.aydra-lock

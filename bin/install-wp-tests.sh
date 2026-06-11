#!/usr/bin/env bash
# install-wp-tests.sh
# Downloads WordPress core and the WP test suite for local integration testing.
#
# Usage:
#   bash bin/install-wp-tests.sh [db_name] [db_user] [db_pass] [db_host] [wp_version]
#
# Defaults:
#   db_name    = wordpress_test
#   db_user    = root
#   db_pass    = root
#   db_host    = localhost
#   wp_version = latest
#
# After running this script, execute integration tests with:
#   vendor/bin/phpunit --configuration phpunit-integration.xml

set -e

DB_NAME="${1:-wordpress_test}"
DB_USER="${2:-root}"
DB_PASS="${3:-root}"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

download() {
    if [ "$(which curl)" ]; then
        curl -s "$1" > "$2"
    elif [ "$(which wget)" ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION == 'latest' ]]; then
    WP_VERSION=$(
        download "https://api.wordpress.org/core/version-check/1.7/" - |
        grep -o '"version":"[^"]*"' | head -1 | sed 's/"version":"//;s/"//'
    )
fi

WP_TESTS_TAG="tags/$WP_VERSION"

# Install WP test suite
if [ ! -d "$WP_TESTS_DIR" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn co --quiet \
        "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" \
        "$WP_TESTS_DIR/includes"
    svn co --quiet \
        "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" \
        "$WP_TESTS_DIR/data"
fi

# Install WP core
if [ ! -d "$WP_CORE_DIR" ]; then
    mkdir -p "$WP_CORE_DIR"
    download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" /tmp/wordpress.tar.gz
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
    rm /tmp/wordpress.tar.gz
fi

# Create wp-tests-config.php
if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    download \
        "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" \
        "$WP_TESTS_DIR/wp-tests-config.php"

    sed -i "s|dirname( __FILE__ ) . '/src/'|'$WP_CORE_DIR/'|" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourusernamehere/$DB_USER/"        "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s/yourpasswordhere/$DB_PASS/"        "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|localhost|$DB_HOST|"               "$WP_TESTS_DIR/wp-tests-config.php"
fi

# Create test database
mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" \
    --host="$DB_HOST" 2>/dev/null || true

echo ""
echo "WP test suite installed at: $WP_TESTS_DIR"
echo "Run integration tests with:"
echo "  vendor/bin/phpunit --configuration phpunit-integration.xml"

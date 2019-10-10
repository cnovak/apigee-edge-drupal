#!/usr/bin/env bash

set -e
set -x

# Register shared variables.
export THREADS=${THREADS:-4}
export MODULE_PATH="/opt/drupal-module"
export WEB_ROOT="/var/www/html/build"
export WEB_ROOT_PARENT="/var/www/html"
export TEST_ROOT=${TEST_ROOT:-modules/custom}
export TESTRUNNER="/var/www/html/testrunner"

if [[ ! -f ${TESTRUNNER} ]]; then
  echo "Preparing test environment..."
  /opt/drupal-module/.travis/prepare-test-env.sh
fi

PHPUNIT="${WEB_ROOT_PARENT}/vendor/bin/phpunit -c ${WEB_ROOT}/core"

# Use \Drupal\Tests\Listeners\HtmlOutputPrinter when running tests.
if [[ -n "${ENABLE_HTML_OUTPUT_PRINTER}" ]]; then
  PHPUNIT="${PHPUNIT}" . " --printer \Drupal\Tests\Listeners\HtmlOutputPrinter"
fi

# Do not exit if any PHPUnit test fails.
set +e

# If no argument passed start the testrunner and start running ALL tests
# concurrently, otherwise pass them directly to PHPUnit.
if [[ $# -eq 0 ]]; then
  sudo -u root -E sudo -u www-data -E ${TESTRUNNER} -verbose -threads="${THREADS}" -root="${WEB_ROOT}"/"${TEST_ROOT}" -command="$PHPUNIT"
else
  sudo -u root -E sudo -u www-data -E "${PHPUNIT}" "${@}"
fi

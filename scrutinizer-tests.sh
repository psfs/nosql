#!/bin/sh
set -eu

mkdir -p cache/coverage
vendor/bin/phpunit \
    --configuration src/NOSQL/phpunit.xml.dist \
    --colors=never \
    --coverage-clover cache/coverage/coverage.xml

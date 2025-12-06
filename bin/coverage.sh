#!/usr/bin/env bash

BINDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

PHPUNIT="${BINDIR}/run.sh vendor/bin/phpunit"

if [ $# -eq 0 ]; then
    echo "Running full test suite with default coverage..."
    $PHPUNIT
else
    TARGET="$1"
    echo "Running tests with coverage ONLY for: $TARGET"
    $PHPUNIT \
        --filter "$TARGET" \
        --coverage-filter "$TARGET"
fi

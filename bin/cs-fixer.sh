#!/usr/bin/env bash

APPDIR=/srv/www/app
BINDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

${BINDIR}/run.sh vendor/bin/php-cs-fixer fix $@

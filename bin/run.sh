#!/bin/bash

# Save command-line APP_ENV if set
CMD_APP_ENV="${APP_ENV}"

. $(dirname $0)/../.env

# Restore command-line APP_ENV if it was set
if [ -n "${CMD_APP_ENV}" ]; then
  APP_ENV="${CMD_APP_ENV}"
fi

# Check if we're running inside a Docker container
if [ -f /.dockerenv ]; then
  # We're inside the container, execute command directly
  exec "$@"
fi

# Check if we're in production environment without Docker
# Production is detected by APP_ENV=prod/production or by absence of Docker
if [ "${APP_ENV}" = "prod" ] || [ "${APP_ENV}" = "production" ] || ! command -v docker &> /dev/null; then
  # In production without Docker, execute commands directly using system PHP
  exec "$@"
fi

mkdir -p -m o+rwX \
  $(dirname $0)/../var/cache

if [ -z "${1}" ]; then
  trap "exit" INT TERM ERR
  trap "$(dirname $0)/docker-compose.sh stop" EXIT
  $(dirname $0)/docker-compose.sh up
elif [ "${1}" == "-d" ]; then
  $(dirname $0)/docker-compose.sh up $@
elif [ "${1}" == "stop" ] || [ "${1}" == "down" ]; then
  $(dirname $0)/docker-compose.sh down
else
  $(dirname $0)/docker-compose.sh run --rm --entrypoint "" app $@
fi

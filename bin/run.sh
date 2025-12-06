#!/bin/bash

. $(dirname $0)/../.env

# Check if we're running inside a Docker container
if [ -f /.dockerenv ]; then
  # We're inside the container, execute command directly
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

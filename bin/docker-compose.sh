#!/bin/sh

cd $(dirname ${0})/..

. ./.env


export DEV_PROJECT_NAME

export DEV_IMAGE_TAG=${USER}-$(find docker/image-app/* -type f -printf '%TY%Tm%Td%TH%TM%TS\n' | sort -r | head -n 1)
export U_ID=$(id -u)

# Check if docker-compose or docker compose is available
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE_CMD="docker-compose"
elif docker compose version &> /dev/null; then
    DOCKER_COMPOSE_CMD="docker compose"
else
    echo "Error: Neither docker-compose nor docker compose found"
    exit 127
fi

$DOCKER_COMPOSE_CMD \
  --env-file ./config.dev.env \
  --env-file ./.env \
  --file docker/docker-compose.yml \
  --file docker/docker-compose.dev.yml \
  --project-name ${DEV_PROJECT_NAME} \
  "$@"

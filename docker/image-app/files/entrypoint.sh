#!/bin/bash

PCOV_INI_FILE_NAME="docker-php-ext-pcov.ini"

set -e

if [ "${ENABLE_PCOV}" == "1" ] && [ -f ${PHP_INI_DIR}/conf.d/${PCOV_INI_FILE_NAME}.disabled ]; then
  mv ${PHP_INI_DIR}/conf.d/${PCOV_INI_FILE_NAME}.disabled ${PHP_INI_DIR}/conf.d/${PCOV_INI_FILE_NAME}
fi

if [ "${ENABLE_PCOV}" == "0" ] && [ -f ${PHP_INI_DIR}/conf.d/${PCOV_INI_FILE_NAME} ]; then
  mv ${PHP_INI_DIR}/conf.d/${PCOV_INI_FILE_NAME} ${PHP_INI_DIR}/conf.d/${PCOV_INI_FILE_NAME}.disabled
fi

case "${RUN_APP}" in
  "apache")
    exec /usr/local/bin/docker-php-entrypoint apache2-foreground
    ;;
  *)
    if [ $# == 0 ]; then
      exec /usr/local/bin/docker-php-entrypoint apache2-foreground
    else
      exec /usr/local/bin/docker-php-entrypoint "$@"
    fi
    ;;
esac

FROM php:8.4-apache AS base

# Use bash and stop on errors
SHELL ["/bin/bash", "-eo", "pipefail", "-c"]

ARG DOCKERFILE_DIR="docker/image-app"
ENV TZ="Europe/Warsaw" \
    COMPOSER_CACHE_DIR="/var/cache/composer" \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /srv/www/app
ENTRYPOINT [ "/entrypoint.sh" ]

ENV PHPUNIT_VERSION="12.3.6"

RUN \
  --mount=type=bind,source=${DOCKERFILE_DIR}/build-files,target=/build-files \
<<EOF
    apt-get update
    apt-get install --no-install-recommends -y $(cat /build-files/requirements.apt.txt | grep -v "^#")
    apt-get clean
    rm -rf /var/lib/apt/lists/*
    # timezone, locale
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
    sed -i 's/^# *\(pl_PL.UTF-8\)/\1/' /etc/locale.gen
    locale-gen pl_PL.UTF-8
    update-locale LANG=pl_PL.UTF-8 LANGUAGE=pl_PL.UTF-8 LC_ALL=pl_PL.UTF-8
    # phpunit
    curl -Lo /usr/local/bin/phpunit https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar
    chmod +x /usr/local/bin/phpunit
    # apache config
    a2enmod rewrite
    rm /etc/apache2/sites-enabled/*
    # others
    mkdir -p $COMPOSER_CACHE_DIR
EOF

ENV LANG="pl_PL.utf8" \
    LC_CTYPE="pl_PL.utf8" \
    LC_COLLATE="pl_PL.utf8" \
    LANGUAGE="pl_PL"

ENV DOCKER_PHP_EXTENSION_INSTALLER_VERSION="2.9.6" \
    COMPOSER_VERSION="2.9.2"

# php extensions:
# https://github.com/mlocati/docker-php-extension-installer?tab=readme-ov-file#supported-php-extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/${DOCKER_PHP_EXTENSION_INSTALLER_VERSION}/install-php-extensions /usr/local/bin/
RUN \
  --mount=type=bind,source=${DOCKERFILE_DIR}/build-files,target=/build-files \
<<EOF
    install-php-extensions @composer-${COMPOSER_VERSION} $(cat /build-files/requirements.php.txt | grep -v "^#")
EOF

COPY ${DOCKERFILE_DIR}/files/ /

ARG U_ID=1000
RUN <<EOF
    # dedicated user
    addgroup default
    adduser --shell /bin/bash --uid ${U_ID} --ingroup default --home /home/default --disabled-password default
    chown -R default:default $COMPOSER_CACHE_DIR /srv/www/app
    chown -R :default ${PHP_INI_DIR}/conf.d
    chmod -R g+w ${PHP_INI_DIR}/conf.d
EOF
USER default

# ----------------------------

FROM base AS dev

# Use bash and stop on errors
SHELL ["/bin/bash", "-eo", "pipefail", "-c"]

FROM base AS test

# Use bash and stop on errors
SHELL ["/bin/bash", "-eo", "pipefail", "-c"]

ENTRYPOINT [ "" ]
HEALTHCHECK NONE

COPY --chown=default:default composer.* ./

ARG COMPOSER_AUTH
RUN \
  --mount=type=cache,uid=${U_ID},target=${COMPOSER_CACHE_DIR} \
<<EOF
  composer install --no-scripts --no-autoloader
EOF

COPY --chown=default:default . ./

RUN <<EOF
  composer dump-autoload --optimize
  touch .env
EOF

FROM base AS release

# Use bash and stop on errors
SHELL ["/bin/bash", "-eo", "pipefail", "-c"]

ENTRYPOINT [ "/entrypoint-release.sh" ]

COPY --chown=default:default composer.* ./

ARG COMPOSER_AUTH
RUN \
  --mount=type=cache,uid=${U_ID},target=${COMPOSER_CACHE_DIR} \
<<EOF
  composer install --no-scripts --no-autoloader
EOF

COPY --chown=default:default . ./

RUN <<EOF
  touch .env
  mkdir -p var/cache var/log
  composer dump-autoload --optimize
  composer run-script post-install-cmd
EOF

ARG APP_ENV
ARG BUILD_VERSION
ENV IMAGE_VERSION="${BUILD_VERSION}" \
  APP_ENV=${APP_ENV} \
  SYMFONY_ENV=${APP_ENV}
LABEL version="${IMAGE_VERSION}" \
  registry="${IMAGE_REGISTRY}"

ARG PHP_VERSION

FROM php:${PHP_VERSION}

RUN apk add --no-cache \
    git \
    tini \
;

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /tmp

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN install-php-extensions @composer imagick gd

CMD ["php", "-v"]

#!/usr/bin/env sh

if [ ! -f "$COMPOSER_HOME/keys.dev.pub" ] || [ ! -f "$COMPOSER_HOME/keys.tags.pub" ]; then
    if [ $(stat -c %u:%g $COMPOSER_HOME) != $(id -u):$(id -g) ]; then
        printf "Your $COMPOSER_HOME directory owned by another user.\n"
        printf "Please run \"sudo chown -R $(id -u):$(id -g) $COMPOSER_HOME\" on the host system before you proceed.\n"

        exit 1
    fi

    cp --recursive /tmp/* "$COMPOSER_HOME"
    composer diagnose
fi

# check if the first argument passed in looks like a flag
if [ "$(printf %c "$1")" = '-' ]; then
  set -- php "$@"
fi

exec tini -- "$@"

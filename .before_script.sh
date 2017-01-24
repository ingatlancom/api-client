#!/bin/bash

VERSION="$(php -v | head -1)"

case "$VERSION" in
    *HipHop*)
        echo "Skip"
        exit 0
    ;;
    *7.*)
        echo "Skip"
        exit 0
    ;;
esac

printf "\n" | pecl install imagick-3.3.0
echo "extension=imagick.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

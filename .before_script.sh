#!/bin/bash

echo -e "\e[4mSetting up PHP Extensions for $TRAVIS_PHP_VERSION\e[0m"

if [[ "$TRAVIS_PHP_VERSION" =~ "5." ]]; then
    printf "\n" | pecl install imagick-3.3.0
fi

if [[ "$TRAVIS_PHP_VERSION" =~ "7." ]]; then
    printf "\n" | pecl install imagick-3.4.4
fi

echo "extension=imagick.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

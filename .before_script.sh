#!/bin/bash

echo -e "\e[4mSetting up PHP Extensions for $TRAVIS_PHP_VERSION\e[0m"

if [[ "$TRAVIS_PHP_VERSION" =~ "hhvm" ]] || [[ "$TRAVIS_PHP_VERSION" =~ "7." ]]; then
    echo "Skipping"
    exit 0
fi

printf "\n" | pecl install imagick-3.3.0
echo "extension=imagick.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

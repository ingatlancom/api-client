#!/bin/bash

echo -e "\e[4mSetting up PHP Extensions for $TRAVIS_PHP_VERSION\e[0m"

printf "\n" | pecl install imagick-3.4.4
echo "extension=imagick.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
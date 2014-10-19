#!/bin/sh
# install composer to get dependencies
set -ex
[ ! -f composer.phar ] && curl -sS https://getcomposer.org/installer | php
php composer.phar install

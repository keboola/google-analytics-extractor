#!/bin/sh
composer install \
&& ./vendor/bin/phpcs --standard=psr2 -n --ignore=vendor --extensions=php . \
&& ./vendor/bin/phpunit

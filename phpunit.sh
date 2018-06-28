#!/bin/sh
composer install \
&& ./vendor/bin/phpstan analyse src tests \
&& ./vendor/bin/phpcs --standard=psr2 -n --ignore=vendor --extensions=php . \
&& ./vendor/bin/phpunit --verbose --debug

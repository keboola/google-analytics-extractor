FROM keboola/base-php56
MAINTAINER Miro Cillik <miro@keboola.com>

ADD . /code
WORKDIR /code

RUN composer install --no-interaction

ENTRYPOINT php ./src/run.php --data=/data

# we can use the BUILDPLATFORM amd64 for all platforms, as pie is a php phar
FROM --platform=$BUILDPLATFORM ghcr.io/php/pie:bin AS pie 

FROM kimbtechnologies/php_nginx:latest 

# php redis support
RUN --mount=type=bind,from=pie,source=/pie,target=/usr/local/bin/pie \
    apk add --update --no-cache $PHPIZE_DEPS \
    && pie install phpredis/phpredis:dev-develop
  
# SVG -> PNG convert
RUN apk add --update --no-cache rsvg-convert

# copy php files, nginx conf and startup scripts
COPY --chown=www-data:www-data ./php/ /php-code/
COPY ./utils/nginx.conf /etc/nginx/more-server-conf.conf 
COPY ./utils/startup.php /utils/startup-before.sh ./utils/cron.php  /

# backup default data dir
RUN mkdir /data-dir-default/ \
	&& cp -r /php-code/data/* /data-dir-default \
	&& mkdir /media-dir-default/ \
	&& cp -r /php-code/media/* /media-dir-default

ENV DOCKER_MODE=true
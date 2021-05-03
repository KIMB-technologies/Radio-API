FROM kimbtechnologies/php_nginx:8-latest 

# php redis support
RUN apk add --update --no-cache $PHPIZE_DEPS \
	&& pecl install redis \
	&& docker-php-ext-enable redis

# copy php files, nginx conf and startup scripts
COPY --chown=www-data:www-data ./php/ /php-code/
COPY ./nginx.conf /etc/nginx/more-server-conf.conf 
COPY ./startup.php /startup-before.sh ./cron.php  /

# backup default data dir
RUN mkdir /data-dir-default/ \
	&& cp -r /php-code/data/* /data-dir-default
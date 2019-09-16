FROM kimbtechnologies/php_nginx:latest 

COPY --chown=www-data:www-data ./php/ /php-code/
COPY ./nginx.conf /etc/nginx/more-server-conf.conf 

RUN mkdir /data-dir-default/ \
	&& cp -r /php-code/data/* /data-dir-default \
	&& echo $'#!/bin/sh \n\
	if [ ! -d /php-code/data/cache ]; then \n\
		mv /data-dir-default/* /php-code/data/ \n\
		chown -R www-data:www-data /php-code/data/ \n\
	fi; \n\
	if [ -f /php-code/data/podcasts.json ]; then \n\
		mv /php-code/data/podcasts.json /php-code/data/podcasts_1.json \n\
		chown www-data:www-data /php-code/data/podcasts_1.json \n\
	fi; \n\
	if [ -f /php-code/data/radios.json ]; then \n\
		mv /php-code/data/radios.json /php-code/data/radios_1.json \n\
		chown www-data:www-data /php-code/data/radios_1.json \n\
	fi; ' > /startup-before.sh
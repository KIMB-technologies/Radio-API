FROM kimbtechnologies/php_nginx:latest 

COPY --chown=www-data:www-data ./php/ /php-code/
COPY ./nginx.conf /etc/nginx/more-server-conf.conf 

RUN mkdir /data-dir-default/ \
	&& cp -r /php-code/data/* /data-dir-default \
	&& echo $'#!/bin/sh \n\
	if [ ! -d /php-code/data/cache ]; then \n\
		mv /data-dir-default/* /php-code/data/ \n\
		chown -R www-data:www-data /php-code/data/ \n\
	fi ' > /setup-data.sh

CMD ["sh", "-c", "sh /setup-data.sh && sh /startup.sh"]
#!/bin/sh 
# file to setup docker container and update data to newer version

#	migrate old podcast from single user to first user
if [ -f /php-code/data/podcasts.json ]; then
	mv /php-code/data/podcasts.json /php-code/data/podcasts_1.json
	chown www-data:www-data /php-code/data/podcasts_1.json
fi;
#	migrate old radio from single user to first user
if [ -f /php-code/data/radios.json ]; then
	mv /php-code/data/radios.json /php-code/data/radios_1.json
	chown www-data:www-data /php-code/data/radios_1.json
fi;

# 	create default data dir, if does not exist
if [ ! -f /php-code/data/radios_1.json ]; then 
	mv /data-dir-default/* /php-code/data/ 
	chown -R www-data:www-data /php-code/data/ 
fi;

#	init redis with env vars
php /startup.php 

#	file permissions
chown -R www-data:www-data /php-code/data/

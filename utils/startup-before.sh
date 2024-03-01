#!/bin/sh 
# file to setup docker container and update data to newer version

# migrations (currently none)

# 	create default data dir, if does not exist
if [ ! -f /php-code/data/radios_1.json ]; then 
	mv /data-dir-default/* /php-code/data/ 
fi;

# 	create default media dir, if does not exist
if [ ! -f /php-code/media/default.png ]; then 
	mv /media-dir-default/* /php-code/media/ 
fi;

#	init redis with env vars
php /startup.php 

#	file permissions
chown -R www-data:www-data /php-code/data/
chown -R www-data:www-data /php-code/media/

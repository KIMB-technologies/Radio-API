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

# assure a self-signed ssl certificate exists for newer JSON radios 
if [ ! -f /php-code/data/ssl_key.pem ] || [ ! -f /php-code/data/ssl_crt.pem ]; then 
	# create a cert, we used 100 years and a wildcard for the sf domain
	openssl req -x509 -newkey rsa:4096 -sha256 -days 36500 -nodes \
		-subj "/CN=*.wifiradiofrontier.com" \
		-keyout /php-code/data/ssl_key.pem -out /php-code/data/ssl_crt.pem
fi;

#	init redis with env vars
php /startup.php 

#	file permissions
chown -R www-data:www-data /php-code/data/
chown -R www-data:www-data /php-code/media/

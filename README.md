# Radio-API

> Frontier-Silicon Internet Radio alternative API

see https://github.com/kimbtech/WiFi-RadioAPI for informations about the Frontier-Silicon API  
see https://hub.docker.com/r/kimbtechnologies/radio_api for the Docker Image

## About

This is an alternative API for Frontier-Silicon internet Radios, it can be placed on a server
and will host the list of internet radio stations, podcasts etc. which then can be found in the
radios menu.

The main idea is to redirect the http request of the radio to another server, where own stations and podcasts
can be added. This redirect is possible by manipulating the DNS queries.

## Usage
- After setting up the Docker Image and changing the DNS resolver, start the radio and open `Internet Radio`.
- There should be a list providing:
	- **Radio**
		- This is a list of internet radio stations.
		- The list of stations can be changed using the GUI.
		- A radio station should be URL to some stream like MP3, M3U etc.
	- **Podcasts**
		- This is a list of podcasts.
		- The list of podcasts can be changed using the GUI.
		- The list of episodes for each podcast is cached for `CONF_CACHE_EXPIRE` seconds.
		- The URL of a podcast can be an Atom RSS link or a link to a NextCloud fileshare.
		- NextCloud fileshare:
    			- The system can fetch and stream audiofiles from nextcloud fileshares.
    			- The link of the share needs a to look like `<mycloud.expample.com>/s/<token>/`. 
			- All files in the shared folder will be show in the radio as epoisode.
    			- It is only possible to share a folder filled with files.
    			- The share must not have a password.
    			- There is no support for subfolders in shares.
	- **Stream**
		- This is a list of custom streams.
		- The list is fetched from a custom url, provided in the Docker Image setup.
		- The list is cached for `CONF_CACHE_EXPIRE` seconds.
		- The radio streams each item like a radio station.
		- The Streams are all the same on one system.

## Notice
- This is a private project and has no connections to Frontier-Silicon.
- There is a limit of 1000 items per list; 1000 Radio stations, 1000 Streams, 1000 Podcasts.
- The GUI at `radio.example.com/gui/` provides a preview of the radios menu. 
- Nobody should host a pubic DNS resolver resolving wrong IPs, some type of access control is useful.

## Setup

The entire API is bundled in a [Docker Image](https://hub.docker.com/r/kimbtechnologies/radio_api).

1. Redirect the HTTP request of the radio to your server.
	- This is done by altering the DNS queries.
	- There is a [Docker Container](https://hub.docker.com/r/kimbtechnologies/radio_dns)
      	which provides a DNS Server altering all requests to `*.wifiradiofrontier.com`.
		- It has a feature to define an `ALLOWED_DOMAIN`, only requests from the corresponding IP address will be answered.
		- Use a DynDNS hostname as your `ALLOWED_DOMAIN`.
		- If hosted in the local network `ALLOWED_DOMAIN` can be `all`.
	- *Not everybody has to setup a own DNS resolver, some routers provide such features.
      	The radio just has to send its http request to the server where this repositories
		Docker Image is running.*
	- **Change the DNS server in the configuration of your radio to the IP of your DNS resolver.**
2. Run the Docker Image.
	- See [docker-compose.yml](https://github.com/KIMB-technologies/Radio-API/blob/master/docker-compose.yml)
		for Docker configuration and [nginx.conf](#nginx-load-balancer) for reverse proxy setup.
	- It is recommended to save the folder `/php-code/data/` als volume, because all stations and podcasts
		are stored there.
	- Configure the image
      	- `CONF_DOMAIN` The domain where the system is hosted (will be reached via HTTP).
      	- `CONF_ALLOWED_DOMAIN` Like `ALLOWED_DOMAIN` in the DNS Image, only requests from the corresponding
			IP address will be answered. Use DynDNS. You may give a list of multiple allowed
			hostnames, divided by `,`. *The API would be public useable, if `CONF_ALLOWED_DOMAIN`
			is set to `all`. If hosted in a local network using `all` is recommended.*
      	- `CONF_CACHE_EXPIRE` Time in seconds for cache of ips, podcasts to expire.
		- `CONF_REDIS_HOST` The API needs Redis to cache station lists, give the redis hostname here.
      	- *`CONF_REDIS_PORT` (optional)* Change the redis port to a none default one.
      	- *`CONF_REDIS_PASS`(optional)* Activate Redis Authentication by giving password.
      	- `CONF_OWN_STREAM` Fetch a list of own streams `true/ false`.
      	- `CONF_OWN_STREAM_JSON` URL where the list of own stream can be fetched.
			JSON like `{ "key" : { name : "Test 1" }, ... }`
      	- `CONF_OWN_STREAM_URL` URL where each audiofile can be accessed, the `key` will be appended
      	- `CONF_PROXY_OWN_STREAM` Use the builtin HTTP proxy for own streams `true/ false`.
	- There are to ways to store, which episodes of a podcasts were already played (new ones are marked by `*`)
		- Use the Redis-Data-Volume (Redis will load its dump files on container startup)
		- Create a CronJob to `/cron.php` (this will dump the already played episodes to a JSON-File and load the file on container startup)
4. Done
    - Start the radio and open `Internet Radio`
    - There should be a list with three points `Radio, Podcasts, (Streams)` and a GUI-Code.
    - The GUI to define the list of stations and podcasts can be found at `CONF_DOMAIN/gui`. 
    - You will need the Code shown in the menu to log into the gui. 
    - Each connected radio has is own list of radio stations and podcasts, the own streams are global.


> The API can be placed outside of the local network as well as inside.


### Nginx Load Balancer

An example file to use the image with an nginx load balancer.

```nginx

server {
	server_name radio.example.com .wifiradiofrontier.com;

	location / {
		set $url "${scheme}${request_uri}";
		if ( $url ~* "^http/gui.*$" ){ # rewite gui to ssl
			return 301 https://radio.example.com$request_uri;
		}

		proxy_pass http://127.0.0.1:8080/;
		proxy_set_header X-Real-IP $remote_addr;
		proxy_http_version 1.1;
		proxy_read_timeout 3m;
		proxy_send_timeout 3m;
		proxy_set_header Host $host;
		proxy_set_header X-Forwarded-Proto $scheme;
	}

	listen 80; # needed by radio
	listen [::]:80;	

	listen [::]:443 ssl; # add for gui
	listen 443 ssl;
	# more ssl setup ....
}

```

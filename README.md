# Radio-API

> Silicone-Frontier Radio alternative Web-Radio API

see https://github.com/kimbtech/WiFi-RadioAPI for informations about the original API

## About

This is an alternative API for Silicone-Frontier internet Radios, it can be placed on a server
and will host the list of internet radio stations, podcasts etc. which then can be found in the
radios menu.

The main idea is to redirect the http request of the radio to another server, where own stations and podcasts
can be added. This redirect is possible by manipulating the DNS queries.

## Setup

The entire API is bundled in a Docker Image.

1. Redirect the HTTP request of the radio to your server.
    - This is done by altering the DNS queries.
    - There is a [Docker Container](https://hub.docker.com/r/kimbtechnologies/radio_dns)
      which provides a DNS Server altering all requests to `*.wifiradiofrontier.com`.
        - It has a feature to define an `ALLOWED_DOMAIN`, only requests from the corresponding IP address will be answered.
	  - Use a DynDNS hostname as your `ALLOWED_DOMAIN`.
	  - If hosted in the local network `ALLOWED_DOMAIN` can be `all`.
    - *Not everybody has to setup a own DNS resolver, some routers provide such features.
      The radio just has to send its http request to the server where this repositories Docker-Image is running.*
    - Change the DNS server in the configuration of your radio to the IP of your DNS resolver.
2. Run this Docker-Image.
    - See [docker-compose.yml](https://github.com/KIMB-technologies/Radio-API/blob/master/docker-compose.yml) and [nginx.conf](#nginx-load-balancer)
    - It is recommended to save the folder `/php-code/data/` als volume, because all stations and podcasts are stored there
    - Configure the Image
        - `CONF_DOMAIN` The domain where the system is hosted, add `/` at the end!
        - `CONF_ALLOWED_DOMAIN` Like `ALLOWED_DOMAIN` in the DNS Image, only requests from the corresponding IP address will be answered. Use DynDNS.
        - `CONF_CACHE_EXPIRE` Time in seconds for cache of ips, podcasts to expire
        - `CONF_NEXTCLOUD` The system can load audiofiles from nextcloud shares. This is the url to the nextcloud instance.
            The urls of the share have to look like `CONF_NEXTCLOUD/s/<token>/`. All files in the shared folder will be show in the radio.
            It is not possible to share only files or folders of folders or to set a password.
        - `CONF_OWN_STREAM` Fetch a list of own streams `true/ false`.
        - `CONF_own_stream_json` URL where the list of own stream can be fetched. JSON like `{ "key" : { name : "Test 1" }, ... }`
        - `CONF_own_stream_url` URL where each audiofile can be found, the `key` will be appended
4. Done
    - Start the radio and open `Internet Radio`
    - There should be a list with three points `Radio, Podcasts, (Streams)`
    - The GUI to define the list of stations and podcasts can be found at `CONF_DOMAIN/gui`. 
>
> The API can be placed outside of the local network as well as inside.
>

### Nginx Load Balancer

An example file to use the image with an nginx load balancer.

```nginx

server {
	server_name radio.example.com hama.wifiradiofrontier.com;

	location / {
		set $url "${scheme}${request_uri}";
		if ( $url ~* "^http/gui.*$" ){ # reqwite gui to ssl
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

	location /cloudproxy/ {
		proxy_pass https://cloud.example.com/;
	}

	listen 80; # needed by radio
	listen [::]:80;	

	listen [::]:443 ssl; # add for gui
	listen 443 ssl;
	# more ssl setup ....
}

```

## Usage
- Start the radio and open `Internet Radio`
- There should be a list with three points `Radio, Stream, Podcasts`

### Radio 
- This is a list of internet radio stations.
- The list can be changed using the GUI at `radio.example.com/gui/`.
- A radio station should be URL to some MP3, M3U.

### Podcasts
- This is a list of podcasts.
- The list can be changed using the GUI at `radio.example.com/gui/`.
- The URL can be an Atom RSS link or a link of a nextcloud fileshare.
    - The link of the share has to start with `CONF_NEXTCLOUD`
    - The share must not have a password.
    - There is no support for subfolders in shares.
- The list of episodes is cached for `CONF_CACHE_EXPIRE` minutes.

### Stream
- This is a list of custom streams.
- They are using the given URL.

## Other
- This is a private project and has no connections to Silicone-Frontier
- Nonbody should host a pubic DNS resolver resolving wrong IPs, some type of access control is useful.
- There is a limit of 1000 items per list; 1000 Radio stations, 1000 Streams, 1000 Podcasts.
- The GUI at `radio.example.com/gui/` has a preview of the lists. 

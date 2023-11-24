# Setup

Radio-API can be run using Docker (recommend way) [&darr;](#setup-using-docker).
It is also possible to run Radio-API on a simple webserver with PHP [&darr;](#manual-setup).

## Setup using Docker
The entire API is bundled in a [Docker Image](https://hub.docker.com/r/kimbtechnologies/radio_api).

1. Redirect the HTTP request of the radio to your server (the *Radio-API*).
	- This is done by altering the DNS queries.
	- There is a [Docker Image](https://hub.docker.com/r/kimbtechnologies/radio_dns) which provides a DNS server altering all requests to `*.wifiradiofrontier.com`.
		- It has a feature to define an `ALLOWED_DOMAIN`, only requests from the corresponding IP address will be answered.
		- Use a DynDNS hostname as your `ALLOWED_DOMAIN`.
		- If hosted in the local network `ALLOWED_DOMAIN` can be `all`.
	- *Not everybody has to setup a own DNS resolver, some routers provide such features.
		The radio just has to send its HTTP request to the server where *Radio-API* (this repositories Docker Container) is running.*
	- **Change the DNS server in the configuration of your radio to the IP of your DNS resolver.**
		(This may be done via the web interface of the radio, accessible at the local IP address of the radio.)
2. Run the Docker Container of *Radio-API*.
	- See [docker-compose.yml](https://github.com/KIMB-technologies/Radio-API/blob/master/docker-compose.yml)
		for Docker configuration and [below](#nginx-load-balancer) for reverse proxy setup.
	- It is recommended to save the folder `/php-code/data/` als volume, because all stations and podcasts
		are stored there.
	- Configure the image
		- `CONF_DOMAIN` The domain where the system is hosted (will be reached via HTTP).
		- `CONF_ALLOWED_DOMAIN` Like `ALLOWED_DOMAIN` in the DNS image, only requests from the corresponding IP address will be answered.
			Use, e.g., the dynamic DNS host name of you local home router.
			You may give a list of multiple allowed host names, divided by `,`.
			*The API would be public useable, if `CONF_ALLOWED_DOMAIN` is set to `all`.*
			*If hosted in a local network using `all` is recommended.*
		- `CONF_STREAM_JSON` Url to a JSON list of streams or `false` to disable (see [Own Streams](#own-streams))
		- There are some more options, see defaults in [docker-compose.yml](https://github.com/KIMB-technologies/Radio-API/blob/master/docker-compose.yml).
	- There are two ways to store which episodes of podcasts have already been listened to (new ones are marked by `*`)
		- Create a cron job to `/cron.php`, e.g., `docker exec --user www-data radio_api php /cron.php`. (This will dump the already played episodes to a JSON file in `./data/` and *Radio-API* will load the file into redis on container startup).
		- Use the data volume of Redis. (Redis will (re-)load its dump files on container startup.)
3. Done
	- Start the radio and open `Internet Radio`.
	- You will see the entries described at [Usage](./Readme.md#usage).
	- Use the GUI to define the list of stations and podcasts. It can be accessed with a browser at `CONF_DOMAIN/gui`. 
	- You will need the code shown by the radio to log into the GUI. 
	- Each connected radio has is own list of user defined radio stations and podcasts, the *own streams* are global.

> The API can be placed outside of the local network as well as inside.

### Platforms
The [Docker Image](https://hub.docker.com/r/kimbtechnologies/radio_api) of *Radio-API* is available for `linux/amd64`, `linux/arm/v6`, `linux/arm/v7`, `linux/arm64/v8`, and `linux/arm64` and thus also for, e.g., Raspberry Pis.
The image of [Radio DNS](https://hub.docker.com/r/kimbtechnologies/radio_dns) is available for `linux/amd64`, `linux/arm/v7`, `linux/arm64/v8`, and `linux/arm64`.

## Manual Setup 
> We recommend the Docker-based setup as the manual setup might be a bit fiddly and is less tested.  
> **This is a *beta* feature.** -- You are welcome to file bug reports as issues or open pull requests!

1. Redirect the HTTP request of the radio to your server (the *Radio-API*).
	- This is the same as with the Docker based setup (see [here](#setup-using-docker)).
2. Run the *Radio-API* on your webserver.
	- Preface:
		- The manual setup does not rely on *Redis* (which is replaced by a file-based caching).
		- The only requirement a current version of PHP (code analysis shows compatibility with PHP > 8.0, code is tested with 8.2 and 8.3).
		- You do not need a cron job, all data is stored in `./data/` and the cache files in `./data/cache/`.
		- The proxy feature is provided by PHP, but might be less stable than the NGINX proxy.
		- The EndURL feature uses the cURL extension of PHP (else it will error!).
		- Assure, that PHP/ the webserver can write to `./data/`!
	- Download the lastest source of the *Radio-API* [here](https://github.com/KIMB-technologies/Radio-API/releases/latest).
	- Extract the zip and place the folder `php` in the web-root of our server (this is our  `./`, other files are not needed).
	- Configure *Radio-API* in `./data/env.json` (The config values are the same as for the Docker-based mode, always use strings for the values!):
		- `CONF_DOMAIN` The domain where the system is hosted (will be reached via HTTP).
		- `CONF_ALLOWED_DOMAIN` You may give a list of multiple allowed host names, divided by `,`.
			*The API would be public useable, if `CONF_ALLOWED_DOMAIN` is set to `all`.*
			*If hosted in a local network using `all` is recommended.*
		- `CONF_SHUFFLE_MUSIC` Randomly shuffle music in Nextcloud radio stations
		- `CONF_CACHE_EXPIRE` Cache duration of ips, podcasts,  RadioBrowser requests, ...
		- `CONF_STREAM_JSON` Url to a JSON list of streams or `false` to disable (see [Own Streams](#own-streams)).
	- Make sure, that *Radio-API* is available at port `80` for requests with the hostname `*.wifiradiofrontier.com` and `CONF_DOMAIN`.
	- Block HTTP access to `./data/` (and `./classes/`) for security reasons (might be omitted in a local network installation).
	- Rewrite requests to PHP:
		- All requests which do not point to an existing file need to be redirected to `./index.php`.
		- E.g. `http://radio.example.com/setupapp/iden/asp/BrowseXML/loginXML.asp?token=0` must call `./index.php`.
		- `./index.php` checks the get parameter `uri` for the path value, e.g., `"/setupapp/iden/asp/BrowseXML/loginXML.asp"`.
			If this fails, `$_SERVER['REQUEST_URI']` is checked and the part before the first `?` is taken as path value.
		- It is important, that the path value starts with `/` and contains the full path, but without get parameters starting at `?`.
		- See the example for NGINX below. The built in webserver of PHP may be used for development with the `router.php` in the repository's root.
3. Done
	- Start the radio and open `Internet Radio`.
	- You will see the entries described above at [Usage](./Readme.md#usage)..

### Rewrite with NGINX 

```nginx 
# error pages (optional)
error_page 404 /gui/index.php?err=404;
error_page 403 /gui/index.php?err=403;

# handle file requests
location / {
	try_files $uri $uri/ @nofile;
}

# block access to configuration
location ~ ^/(data|classes){
	deny all;
	return 403;
}

# do the path value rewrite to get parameter "uri"
location @nofile {
	rewrite ^(.*)$ /index.php?uri=$1 last;
}
```

## General Information
### Troubleshooting 
- A log file of (unknown) request received by the Radio-API is created at `./data/log.txt`.  
- Errors with the RadioBrowser API are logged at `./data/log_radiobrowser.txt`.
- If the Radio-API is unable to parse a JSON-file in `./data/`, it will initialize a new one, while the old one is renamed to `*.error.json`.
- PHP error messages are disabled by default, set `DEV=dev` in the environment to enable them.
- Erase the data folder/ volume of redis and restart Radio-API.
- Check the outputs from the Docker Container `docker-compose logs`
	- Make sure, that your radio sends the requests to Radio-API (i.e., the DNS setup works)
- Test the Radio-API with your browser
	1. `http://radio.example.com/setupapp/iden/asp/BrowseXML/loginXML.asp?token=0` (returns `<EncryptedToken>3a3f5ac48a1dab4e</EncryptedToken>`)
	2. `http://radio.example.com/setupapp/iden/asp/BrowseXML/loginXML.asp?gofile=&mac=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa&dlang=eng&fver=4&ven=iden00` (returns `<?xml version="1.0" encoding="UTF-8" standalone="yes"?> <ListOfItems> ... </ListOfItems>`) 
	3. Get the GUI-Code from the preceding response and try to used it to access the GUI at `http://radio.example.com/gui/`

### Nginx Load Balancer
An example file to use *Radio-API* behind a nginx load balancer as reverse proxy.

```nginx
server {
	server_name radio.example.com .wifiradiofrontier.com;

	location / {
		set $url "${scheme}${request_uri}";
		if ( $url ~* "^http/gui.*$" ){ # rewite GUI to ssl
			return 301 https://radio.example.com$request_uri;
		}

		proxy_pass http://127.0.0.1:8080/; # the port of the running Docker Container with *Radio-API*
		proxy_set_header X-Real-IP $remote_addr; # needed to detect allowed domains 
		proxy_http_version 1.1;
		proxy_read_timeout 3m;
		proxy_send_timeout 3m;
		proxy_set_header Host $host;
		proxy_set_header X-Forwarded-Proto $scheme;
	}

	listen 80; # needed by radio
	listen [::]:80;	

	listen [::]:443 ssl; # add for GUI
	listen 443 ssl;
	# more ssl setup ....
}
```

### Own Streams
By setting `CONF_STREAM_JSON` to a url pointing to a JSON resource, the *Own Streams* can be enabled.
(`CONF_STREAM_JSON=false` disables the feature completely.)
*Own Streams* are server specific and shared across all users of one Radio-API server.

The idea of *Own Streams* is to be able to automatically add files for streaming to the Radio when these files are available and created outside of Radio-API.
E.g. some external service creates audio files by cutting the audio tracks from videos, then this service may provide a `CONF_STREAM_JSON` url to pass the files to Radio-API.
This makes it easy to listen via the radio to the audio files created.

The JSON resource at `CONF_STREAM_JSON` should look like this:
```json
[
	{
		"name": "Name A",
		"url": "https://stream.example.com/file.mp3",
		"live": false,
		"proxy": true
	},
	{
		"name": "Name B",
		"url": "http://stream.example.com/live.m3u",
		"live": true,
		"proxy": false
	}
]
```

JSON list of objects with the following keys each:
- `name` Contains the name of the file to stream as shown by the radio.
- `url` Contains the url of the stream (either a file, .e.g., mp3, or a streamable ressource, e.g., m3u).
- `live` (optional, default `true`) Live streams can not be paused or fast forwarded, for non live streams the entire file needs to be available from the start.
- `proxy` (optional, default `false`) Use the internal proxy to allow https urls.
# Radio-API

> Silicon Frontier, Frontier Silicon, or Frontier Nuvola (Smart) Internet Radio alternative API

see https://github.com/kimbtech/WiFi-RadioAPI for information about the API used by the radios  
see https://hub.docker.com/r/kimbtechnologies/radio_api for the Docker Image

## About
This is an alternative API for Frontier Nuvola (Frontier Silicon) internet radios, it can be placed on a server and will host the list of internet radio stations, podcasts etc. which then can be found in the radio's menu.

The main idea is to redirect the HTTP request of the radio to another server, where own stations and podcasts can be added.
This redirect is possible by manipulating the DNS queries.

> This *Radio-API* uses the [RadioBrowser](https://www.radio-browser.info/) to provide a list of radio stations.
> In addition, it allows each user to define their *own list of radio stations and podcasts*.
> Audio streams from Nextcloud shares are supported, too. 

&rarr; [Have a look at **screenshots**](./screenshots/Readme.md)

## Usage
- First [set up](#setup) the Docker Container of this *Radio-API* and change the DNS resolver of the radio (e.g., as described there).
- Afterwards start the radio and open "Internet Radio".
- The *Radio-API* should provide a list of:
	- **Podcast**
		- This is the user defined list of podcasts.
		- The list available of podcasts can be changed using the GUI.
		- The list of episodes for each podcast is cached for `CONF_CACHE_EXPIRE` seconds.
		- The URL of a podcast can be an Atom RSS link or a link to a Nextcloud share.
		- Nextcloud share:
			- The system can fetch and stream audiofiles from Nextcloud shares.
			- The link of the share needs a to look like `mycloud.example.com/s/<token>/`. 
			- All files in the shared folder will be shown in the radio as episode.
			- The share must not have a password.
			- There is no support for sub folders in shares, only the files in the share are shown.
		- Episodes get a `*` in front of their name if they have not yet been listened to.
	- **Radio**
		- This is the user defined list of internet radio stations.
		- The list of stations can be changed in the GUI.
		- A radio station should be an URL to some stream like MP3, M3U etc.
	- **Radio-Browser**
		- This allows to browse the radio stations in [RadioBrowser](https://www.radio-browser.info/).
		- Stations can be browsed by country (and state), language, tags, clicks and votes.
		- The stations recently opened via the radio are shown in *My Last*.
		- *Using Radio-Browser will send http requests and such usage data to the [RadioBrowser API](https://api.radio-browser.info/)!*
		- In the GUI it is also possible to search for stations in RadioBrowser and add them to the user defined stations. Stations from *My Last* are shown in the GUI, too.
	- **Stream** (if enabled in `docker-compose.yml`)
		- This is a list of server specific streams.
		- The list is fetched from a custom url, provided in the Docker Container setup.
		- The list is cached for `CONF_CACHE_EXPIRE` seconds.
		- The radio streams each item as a radio station.
		- The Streams are shared across all radios using the same *Radio-API* setup.
	- **GUI-Code**
		- This code is like a password to access the GUI for this radio and edit the radio stations and streams.
- Gui:
	- The GUI can be opened via a webbrowser at `radio.example.com/gui/`.
	- The GUI provides the editable lists of radio stations and podcasts.
	- A preview of the items shown by the radio is provided by the GUI, too.
		- The preview is also shown when opening `radio.example.com` in an browser and this browser has already logged into the GUI.
		- The `*` to mark new episodes can be toggled by the &check;/ &cross; in the preview.
	- Additional information texts describe the options chooseable for radio stations and podcasts.

### Notes
- This is a private project and has no connections to Frontier Nuvola/ Frontier Silicon.
- There is a limit of 1000 items per list: 1000 radio stations, 1000 streams, 1000 podcasts.
	Items from RadioBrowser do not count against this limit.
	Adding more than 200 user defined radio stations or podcasts is not recommended.
- Nobody should host a public DNS resolver resolving wrong IPs. Some type of access control is recommended.

## Setup
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
		- `CONF_OWN_STREAM` Fetch a list of own streams `true/ false`.
		- `CONF_OWN_STREAM_JSON` URL where the list of own stream can be fetched.
			Should return JSON like `{ "key1" : { name : "Test 1" }, "key2" : { name : "Test 2" }, ... }`.
		- `CONF_OWN_STREAM_URL` URL where each audio file can be accessed, the `key` will be appended, i.e, `CONF_OWN_STREAM_URL+"key1"` for `Test 1`.
		- `CONF_PROXY_OWN_STREAM` Use the builtin HTTP proxy for own streams `true/ false`.
	- There are two ways to store which episodes of podcasts have already been listened to (new ones are marked by `*`)
		- Create a cron job to `/cron.php`, e.g., `docker exec --user www-data radio_api php /cron.php`. (This will dump the already played episodes to a JSON file in `./data/` and *Radio-API* will load the file into redis on container startup).
		- Use the data volume of Redis. (Redis will (re-)load its dump files on container startup.)
3. Done
	- Start the radio and open `Internet Radio`.
	- You will see the entries described above at [Usage](#usage).
	- Use the GUI to define the list of stations and podcasts. It can be accessed with a browser at `CONF_DOMAIN/gui`. 
	- You will need the code shown by the radio to log into the GUI. 
	- Each connected radio has is own list of user defined radio stations and podcasts, the *own streams* are global.

> The API can be placed outside of the local network as well as inside.

### Platforms
The [Docker Image](https://hub.docker.com/r/kimbtechnologies/radio_api) of *Radio-API* is available for `linux/amd64`, `linux/arm/v6`, `linux/arm/v7`, `linux/arm64/v8`, and `linux/arm64` and thus also for, e.g., Raspberry Pis.
The image of [Radio DNS](https://hub.docker.com/r/kimbtechnologies/radio_dns) is available for `linux/amd64`, `linux/arm/v7`, `linux/arm64/v8`, and `linux/arm64`.

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
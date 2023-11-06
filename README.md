# Radio-API

> Silicon Frontier, Frontier Silicone, or Frontier Nuvola (Smart) Internet Radio alternative API

see https://github.com/kimbtech/WiFi-RadioAPI for information about the API used by the radios  
see https://hub.docker.com/r/kimbtechnologies/radio_api for the Docker Image

## About
This is an alternative API for Frontier Nuvola (Frontier Silicone) internet radios, it can be placed on a server and will host the list of internet radio stations, podcasts etc. which then can be found in the radio's menu.

The main idea is to redirect the HTTP request of the radio to another server, where own stations and podcasts can be added.
This redirect is possible by manipulating the DNS queries.

> This *Radio-API* does not come with a predefined list of stations. 
> Instead is allows each user to define their own list of radio stations, podcasts and audio streams from Nextcloud shares.

## Usage
- First [set up](#setup) the Docker Container of this *Radio-API* and change the DNS resolver of the radio (e.g. as described in set up, too).
- Afterwards start the radio and open "Internet Radio".
- The *Radio-API* should provide a list of:
	- **Radio**
		- This is a list of internet radio stations.
		- The list of stations can be changed in the GUI.
		- A radio station should be an URL to some stream like MP3, M3U etc.
	- **Podcasts**
		- This is a list of podcasts.
		- The list of podcasts can be changed using the GUI.
		- The list of episodes for each podcast is cached for `CONF_CACHE_EXPIRE` seconds.
		- The URL of a podcast can be an Atom RSS link or a link to a Nextcloud share.
		- Nextcloud share:
    			- The system can fetch and stream audiofiles from Nextcloud shares.
    			- The link of the share needs a to look like `<mycloud.expample.com>/s/<token>/`. 
			- All files in the shared folder will be shown in the radio as episode.
    			- The share must not have a password.
    			- There is no support for sub folders in shares, only the files in the share are shown.
		- Episodes get a `*` in front of their name if they have not yet been listened to.
	- **Stream**
		- This is a list of custom streams.
		- The list is fetched from a custom url, provided in the Docker Container setup.
		- The list is cached for `CONF_CACHE_EXPIRE` seconds.
		- The radio streams each item as a radio station.
		- The Streams are shared across all radios using the same *Radio-API* setup.
	- **GUI-Code**
		- This code is like a password to access the GUI for this radio and edit the radio stations and streams.
- Gui:
	- The gui can be opened via a webbrowser at `radio.expample.com/gui/`.
	- The gui provides the editable lists of radio stations and podcasts.
	- A preview of the items shown by the radio is provided by the gui, too.
		- The preview is also shown when opening `radio.example.com` in an browser and this browser has already logged into the GUI.
		- The `*` to mark new episodes can be toggled by the &check;/ &cross; in the preview.
	- Additional information texts describe the options chooseable for radio stations and podcasts.

### Notes
- This is a private project and has no connections to Frontier Nuvola/ Frontier Silicone.
- There is a limit of 1000 items per list: 1000 radio stations, 1000 streams, 1000 podcasts.
- Nobody should host a pubic DNS resolver resolving wrong IPs. Some type of access control is recommended.

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
		- Use the data volume of Redis. (Redis will (re-)load its dump files on container startup.)
		- Create a cron job to `/cron.php` (this will dump the already played episodes to a JSON file and *Radio-API* will load the file on container startup.)
4. Done
    - Start the radio and open `Internet Radio`
    - There should be a list with three items `Radio, Podcasts, (Streams)` and a GUI-Code.
    - Use the gui to define the list of stations and podcasts. It can be accessed with a browser at `CONF_DOMAIN/gui`. 
    - You will need the Code shown by the radio to log into the gui. 
    - Each connected radio has is own list of radio stations and podcasts, the *own streams* are global.

> The API can be placed outside of the local network as well as inside.

### Requests Logfile
The *Radio-API* creates a log file in the `data`-directory named `log.txt`. 

### Non AMD64 hosts
Currently the [Docker Image](https://hub.docker.com/r/kimbtechnologies/radio_api) of *Radio-API* is only build for `linux/amd64`.
There is an open issue to build multi platform images [#13](https://github.com/KIMB-technologies/Radio-API/issues/13), e.g., for Raspberry Pis.

In the mean time, one might build the image on its own, e.g., see issue [#10](https://github.com/KIMB-technologies/Radio-API/issues/10#issuecomment-1792708498) and comments.  

### Nginx Load Balancer
An example file to use *Radio-API* behind a nginx load balancer as reverse proxy.

```nginx
server {
	server_name radio.example.com .wifiradiofrontier.com;

	location / {
		set $url "${scheme}${request_uri}";
		if ( $url ~* "^http/gui.*$" ){ # rewite gui to ssl
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

	listen [::]:443 ssl; # add for gui
	listen 443 ssl;
	# more ssl setup ....
}

```
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
- First [set up](./Setup.md) the Docker Container of this *Radio-API* and change the DNS resolver of the radio (e.g., as described there).
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
	- **Stream** (if enabled in `docker-compose.yml`, see [Own Streams](#own-streams))
		- This is a list of server specific streams.
		- The list is fetched from a custom url, provided in the Docker Container setup.
		- The Streams are shared across all radios using the same *Radio-API* setup.
	- **GUI-Code**
		- This code is like a password to access the GUI for this radio and edit the radio stations and streams.
- GUI:
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
See [Setup.md](./Setup.md)!
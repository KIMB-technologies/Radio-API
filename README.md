# Radio-API

> Silicone-Frontier Radio alternative Web-Radio API

see https://github.com/kimbtech/WiFi-RadioAPI for informations about the original API

## About

This is an alternative API for Silicone-Frontier internet Radios, it can be placed on a server
and will host the list of internet radio stations, podcasts etc. which then can be found in the
radios menu.

The main idea it to redirect the http request of the radio to another server, where own stations and podcasts
can be added. This redirect is possible by manipulating the DNS queries.

## Setup

The alternative API has to be placed on an webserver with PHP support. It must be possible to connect
without SSL.  
Also the server has to answer requests for the hostname `hama.wifiradiofrontier.com` (`hama` is the vendor of my
radio, for other models this may be another one hostname).

1. Redirect the HTTP request of the radio to your server.
    - Setup a DNS resolver, e.g. bind9
    - Point the IP of `hama.wifiradiofrontier.com` to your server
    - Bing9 config files:
      [named.conf.local](https://github.com/KIMB-technologies/Radio-API/blob/master/data/config/named.conf.local),
      [db.hama.wifiradiofrontier.com](https://github.com/KIMB-technologies/Radio-API/blob/master/data/config/db.hama.wifiradiofrontier.com)
    - Change the DNS Server in the configuration of your radio to your DNS resolver
    - *Not everybody has to setup a own DNS resolver, some routers provide such features.
      The radio just has to send its http request to the server with this API.*
2. Copy this repository to a server with PHP support, the server the radio queries.
    - Setup some type of URL rewrite
        - URL shall be readable in `$_GET['uri']`, other get paramters have to work as usual.
        - Nginx config file: [nginx.conf](https://github.com/KIMB-technologies/Radio-API/blob/master/data/config/nginx.conf)
    - The directory `/data/cache` and the files `/data/podcasts.json`, `/data/radios.json` should be writeable by PHP.
3. Change the configuration file `/data/Config.php`
    - Set `DOMAIN` to the real server domain, could be `hama.wifiradiofrontier.com`, but a domain
      under your control would be better e.g. `radio.example.com`
    - If you would like to use the Nextcloud share podcasts, then `NEXTCLOUD` has to be a prefix of a domain of
      a nextcloud instance. This can be a reverse proxy, to allow connections without SSL e.g. `radio.example.com/cloudproxy/`
    - Change the function `checkAccess()`, it will be always called before the contents are saved. If it ends the script, no
      access is granted.
    - Change `getMyStreamsList()` and `myStreamsListGetURL()` to add custom streams, the first has to give an
      array of streams, the second translates the key of the stream to a domain, where the audio file is. 
4. Done
    - Start the radio and open `Internet Radio`
    - There should be a list with three points `Radio, Stream, Podcasts`
>
> The API can be placed outside of the local network as well as inside it.
>

## Usage
- Start the radio and open `Internet Radio`
- There should be a list with three points `Radio, Stream, Podcasts`

### Radio 
- This is a list of internet radio stations.
- The list can be changed using the GUI at `radio.example.com/gui/`
- A radio station should be a MP3, M3U link

### Stream
- This is a list of custom streams.
- They are defined using `getMyStreamsList()` and `myStreamsListGetURL()` in `/data/Config.php`

### Podcasts
- This is a list of podcasts
- The list can be changed using the GUI at `radio.example.com/gui/`
- The URL can be an Atom RSS link or a link of a nexcloud fileshare.
    - The link of the share has to start with `NEXTCLOUD` in `/data/Config.php`
    - The share must not have a password
    - There is no support for subfolders in shares


## Other
- This is a private project and has no connections to Silicone-Frontier
- Nonbody should host a pubic DNS resolver resolving wrong IPs
- There is a limit of 1000 items per list; 1000 Radio stations, 1000 Streams, 1000 Podcasts

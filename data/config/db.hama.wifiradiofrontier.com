; hama.wifiradiofrontier.com
$TTL 3600
hama.wifiradiofrontier.com. IN     SOA    localhost. server@example.com. (
				3           ; Serial
				3H          ; refresh after 3 hours
				1H          ; retry after 1 hour
				1W          ; expire after 1 week
				1D)         ; minimum TTL of 1 day

	; Name Server
	IN	NS	server.example.com.	; Only personal use

hama.wifiradiofrontier.com.			IN A		127.0.0.1
www			IN CNAME		127.0.0.1

; EOF

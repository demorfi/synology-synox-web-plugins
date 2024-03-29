# Synology Synox Web Plugins

Download Station and Audio Station plugins.
Uses [Synox Web](https://github.com/demorfi/synox-web) as data source.

## Required
* DSM >= 6.0
* [Synox Web](https://github.com/demorfi/synox-web)

## Includes
* Audio Station Module
* Download Station Search Module
* Download Station Host Module

## HOWTO Use
1. Build modules to get tar.gz files (.aum, .dlm, .host)
2. Set synox web url (example: **``http://synox.synology.loc/``**)
3. *Optional: Set debug mode and profile id
4. Login to you Synology with admin privileges
5. Open Download Station or Audio Station package
6. Go to Settings area

###### For Audio Station
1. Go to Plugins Text, found on top hand side
2. Click add and locate **builds/*.aum**
3. Move plugins in the list to change their priority use

Location of the log file when debugging mode is enabled ``/var/packages/AudioStation/etc/lyricsPlugIn/plugins/au-synox-web/debug.log``

###### For Download Station Search Module
1. Go to File Search, found on left hand side
2. Click add and locate required **builds/*.dlm** file
3. Once done click edit and add your account details

To enable debug mode or set synox web url can be set fields the "Password" or "Username" to **test** or **synox web url**.
It's also possible can be set the following JSON string ``{"debug": true, "url": "synox web url", "profile": "profile-id"}`` to fields "Password" or "Username".
This equal options in INFO file.

Location of the log file when debugging mode is enabled ``/var/packages/DownloadStation/etc/download/userplugins/bt-synox-web/debug.log``

###### For Download Station Host Module
1. Go to File Hosting, found on left hand side
2. Click add and locate required **builds/*.host** file
3. Once done click edit and add your account details

To enable debug mode or set synox web url can be set fields the "Password" or "Username" to **test** or **synox web url**.
It's also possible can be set the following JSON string ``{"debug": true, "url": "synox web url"}`` to fields "Password" or "Username".
This equal options in INFO file.

Location of the log file when debugging mode is enabled ``/var/packages/DownloadStation/etc/download/userhosts/ht-synox-web/debug.log``

## Build
```shell
cd ~ && git clone https://github.com/demorfi/synology-synox-web-plugins && cd synology-synox-web-plugins
./make && ls builds -lX

# rebuild
./make clean && ./make && ls builds -lX
```
or use tar gz command in directory src.

## Tests
Use the included self-diagnosis utility **tests**

### Required
* PHP >= 5.6

###### Search files
```shell
./tests --command bt --query "search query string" [--url "http://synox.synology.loc/"] [--profile "profile-id"] [--debug]
```

###### Download file
```shell
./tests --command ht --query "search result link" [--url "http://synox.synology.loc/"] [--debug]
```

###### Search texts
```shell
./tests --command au --query "artist song/title song" [--url "http://synox.synology.loc/"] [--profile "profile-id"] [--debug]
```

###### Download text
```shell
./tests --command hu --query "search result link" [--url "http://synox.synology.loc/"] [--debug]
```

###### Other commands
```shell
./tests --help
```

## Reporting issues
If you have any issues with the application please open an issue on [GitHub](https://github.com/demorfi/synology-synox-web-plugins/issues).

## License
The plugins are licensed under the [MIT License](http://www.opensource.org/licenses/mit-license.php).
# LineageOTA
A simple OTA REST Server for LineageOS OTA Updater System Application

## Support

Got a question? Not sure where it should be made? See [CONTRIBUTING](CONTRIBUTING.md).

## Contents
* [Requirements](#requirements)
* [How to use](#how-to-use)
* [Local Hosting](#local-hosting)
* [Github Hosting](#github-hosting)
* [Disabling Local/Github Hosting](#disabling-localgithub-hosting)
* [Limiting Github Releases](#limiting-github-releases)
* [Caching](#caching)
* [Web Root Templates](#web-root-templates)
* [REST Server Unit Testing](#rest-server-unit-testing)
* [ROM Integration](#rom-integration)
* [Changelog](#changelog)

## Requirements

- Apache mod_rewrite enabled
- PHP >= 8.2
- PHP ZIP Extension
- Composer ( if installing via CLI )

## How to use

### Composer

```shell
$ cd /var/www/html # Default Apache WWW directory, feel free to choose your own
$ composer create-project julianxhokaxhiu/lineage-ota LineageOTA
```

then finally visit http://localhost/LineageOTA to see the REST Server up and running. Please note that this is only for a quick test, when you plan to use that type of setup for production (your users), make sure to also provide HTTPS support.

> If you get anything else then a list of files, contained inside the `builds` directory, this means something is wrong in your environment. Double check it, before creating an issue report here.

### Docker

```shell
$ docker run \
    --restart=always \
    -d \
    -p 80:80 \
    -v "/home/user/builds:/var/www/html/builds/full" \
    julianxhokaxhiu/lineageota
```

then finally visit http://localhost/ to see the REST Server up and running.

The root URL (used to generate ROM URLs in the `/api` endpoint) can be set using the `LINEAGEOTA_BASE_PATH` variable.

## Local Hosting

- Full builds should be uploaded into `builds/full` directory.
- Delta builds should be uploaded into `builds/delta` directory.

### ONLY for LineageOS 15.x and newer

If you are willing to use this project on top of your LineageOS 15.x ( or newer ) ROM builds, you may have noticed that the file named `build.prop` have been removed inside your ZIP file, and has been instead integrated within your `system.new.dat` file, which is basically an ext4 image ( you can find out more here: https://source.android.com/devices/tech/ota/block ).

In order to make use of this Server from now on, you **MAY** copy the `build.prop` file from your build directory ( where your ROM is being built ), inside the same directory of your ZIP and name it like your ZIP file name + the `.prop` extension.

For example, feel free to check this structure:

```shell
$ cd builds/full
$ tree
.
├── lineage-15.0-20171030-NIGHTLY-gts210vewifi.zip # the full ROM zip file
└── lineage-15.0-20171030-NIGHTLY-gts210vewifi.zip.prop # the ROM build.prop file
```

### What happens if no build.prop file is found

The Server is able to serve the ZIP file via the API, also when a `build.prop` file is not given, by fetching those missing informations elsewhere ( related always to that ZIP file ). Although, as it's a trial way, it may be incorrect so don't rely too much on it.

I am not sure how much this may help anyway, but this must be used as an extreme fallback scenario where you are not able to provide a `build.prop` for any reason. Instead, please always consider to find a way to obtain the prop file, in order to deliver a proper API response.

## Github Hosting

If you want to host your roms on Github you can put your repository names inside the [`github.json`](github.json) file, like this example below:
```json
[
  {
    "name": "ADeadTrousers/android_device_Unihertz_Atom_XL_EEA",
    "name": "ADeadTrousers/android_device_Unihertz_Atom_XL_TEE"
  }
]
```

Each line should point to a repository for a single device and have Github releases with attached files.  At a minimum there should be the following files in each release:

* build.prop
* OTA release zip
* .md5sum list

The md5sum file contains a list of hash values for the the OTA zip as well as any other files you included in the release that need them.  Each line of the md5sum should be of the format:

```
HASHVALUE	FILENAME
```

The filename should not contain any directory information.

You may also include a changelog file in html format.  Note, any html file included in the release file list will be included as a changelog.

## Disabling Local/Github Hosting

Both local and Github hosting features can be disable if they are not being used via the configuration file, in the root directory, called lineageota.json:

```json
[
        {
                "DisableLocalBuilds": false,
                "DisableGithubBuilds": false,
        }
]

```

Setting either of these to true will disable the related hosting option.

## Limiting Github Releases

With Github you may end up having many more releases than the updater really needs to know about, as such there are two options in the config file to let you control the number of releases that are returned:

```json
[
    {
        "MaxGithubReleasesPerRepo": 0,
        "OldestGithubRelease": "",
    }
]
```

MaxGithubReleaesPerRepo will limit the number of releases used on a per repo basis.  Setting this to 0 or leaving it out of the config file will use all available releases in each repo.

OldestGithubRelease will exclude any released older than a given date from being available in the updater.  This string value can be blank for all releases, or any [```strtotime()```](https://www.php.net/manual/en/datetime.formats.php) compatible string, like "2021-01-01" or "60 days ago".

## Caching

Both local builds and Github based builds can be cached to reduce disk and network traffic.  By default, local caching is disabled and Github caching is enabled.

The default cache timeout is set to one day (86400 seconds).

You can change this via the configuration file, in the root directory, called lineageota.json:

```json
[
        {
                "EnableLocalCache": false,
                "EnableGithubCache": true,
                "LocalCacheTimeout": 86400,
                "GithubCacheTimeout": 86400
        }
]
```

This requires the webserver to have write access to the root directory.  If you wish to force a refresh of the releases, simply delete the appropriate cache.json file.

## Web Root Templates

In version 2.9 and prior, if a use visited the web root of the OTA server, they would be redirected to the builds folder.  With the introduction of Github hosting, this is no longer a particularly useful destination as they may see no builds hosted locally, or incorrect ones if local hosting has been disabled and the local builds folder has not been cleaned up.

Releases after 2.9 now use a simple templating system to present a list of builds.

Four templates are included by default (ota-list-simple, ota-list-tables, ota-list-columns, ota-list-javascript) but you can create your own in the "views" folder to match your branding as required.

There are several configuration settings for temples as follows:
```json
        "OTAListTemplate": "ota-list-tables",
        "BrandName": "",
        "LocalHomeURL": "",
        "GithubHomeURL": "",
        "DeviceNames": {
            "kebab": "8T",
            "lemonade": "9"
        },
        "DeviceVendors": {
            "kebab": "Oneplus",
            "lemonade": "Oneplus"
        }
```

* OTAListTemplate: the name of the template to use, do not include the file extension.
* BrandName: the name of your ROM, if left empty brand name will be used from the OTA filename.
* LocalHomeURL: Homepage URL for local builds, used in the template file.  If left empty https://otaserver/builds URL will be used.
* GithubHomeURL: Homepage URL for Github builds, used in the template file.  If left empty the organization URL from any Github repos that are defined will be used.
* DeviceNames: A mapping array between device code names and their proper titles.  Values: array( codename => title, ... )
* DeviceVendors: A mapping array between device code names and their vendor names.  Values: array( codename => vendor, ... )

Included Templates:

* ota-list-simple: a simple header and list of files names, no additional details or links provided.
* ota-list-table: a page containing a seires of tables, one per device, that list in date order all builds for that device.  Includes jump lists to find devices, links to local/github pages, dates, versions, md5sums, etc.

Twig is used as the templating language, see their [documentation](https://twig.symfony.com/doc/3.x/) for more details.

The following variables are available for templates:

* builds: An array of builds available, each entry is an array that contains; incremental, api_level, url, timestamp, md5sum, changes, cahnnel, filename, romtype, datetime, version, id, size
* sortedBuilds: The builds array sorted by device name (array key is the device name, each value is as in the builds array)
* parsedFilenames: An array of filenames that have been parsed in to the following tokens; type, version, date, channel, code, model, signed
* deviceNames: An array of device names, each key is the code name, the value is the device name (ie ```array( "kebab" => "8T")```)
* vendorNames: An array of device names, each key is the code name, the value is the vendor name (ie ```array( "kebab" => "Oneplus")```)
* devicesByVendor: A two dimensional array of devices by vendor (ie ```array( "Oneplus" => array( "kebab", "lemonade"))```)
* branding: An array of branding info for the updates, contains; name, GithubURL, LocalURL
* formatedFileSizes: An array of human friendly file sizes for each release file, keyed on filenames (from the builds array), values as strings like "1.1 GB"

## REST Server Unit Testing

Feel free to use this [simple script](https://github.com/julianxhokaxhiu/LineageOTAUnitTest) made with NodeJS. Instructions are included.

## ROM Integration

In order to integrate this REST Server within your ROM you have two possibilities: you can make use of the `build.prop` ( highly suggested ), or you can patch directly the `android_packages_apps_CMUpdater` package ( not suggested ).

> Before integrating, make sure your OTA Server answers from a public URL. Also, make sure to know which is your path.
>
> For eg. if your URL is http://my.ota.uri/LineageOTA, then your API URL will be http://my.ota.uri/LineageOTA/api

### Build.prop

#### CyanogenMod / LineageOS ( <= 14.x )

In order to integrate this in your CyanogenMod based ROM, you need to add the `cm.updater.uri` property ( for [CyanogenMod](https://github.com/CyanogenMod/android_packages_apps_CMUpdater/blob/cm-14.1/src/com/cyanogenmod/updater/service/UpdateCheckService.java#L206) or [Lineage](https://github.com/LineageOS/android_packages_apps_Updater/blob/cm-14.1/src/org/lineageos/updater/misc/Constants.java#L39) ) in your `build.prop` file. See this example:

```properties
# ...
cm.updater.uri=http://my.ota.uri/api/v1/{device}/{type}/{incr}
# ...
```

> As of [e930cf7](https://github.com/LineageOS/android_packages_apps_Updater/commit/e930cf7f67d10afcd933dec75879426126d8579a):
> Optional placeholders replaced at runtime:
>   {device} - Device name
>   {type} - Build type
>   {incr} - Incremental version

#### LineageOS ( >= 15.x)

In order to integrate this in your LineageOS based ROM, you need to add the [`lineage.updater.uri`](https://github.com/LineageOS/android_packages_apps_Updater/blob/lineage-15.0/src/org/lineageos/updater/misc/Constants.java#L39) property in your `build.prop` file. See this example:

```properties
# ...
lineage.updater.uri=https://my.ota.uri/api/v1/{device}/{type}/{incr}
# ...
```

Make always sure to provide a HTTPS based uri, otherwise the updater will reject to connect with your server! This is caused by the security policies newer versions of Android (at least 10+) include, as any app wanting to use non-secured connections must explicitly enable this during the compilation. The LineageOS Updater does not support that.

> Since https://review.lineageos.org/#/c/191274/ is merged, the property `cm.updater.uri` is renamed to `lineage.updater.uri`. Make sure to update your entry.

> As of [5252d60](https://github.com/LineageOS/android_packages_apps_Updater/commit/5252d606716c3f8d81617babc1293c122359a94d):
> Optional placeholders replaced at runtime:
>   {device} - Device name
>   {type} - Build type
>   {incr} - Incremental version


### android_packages_apps_CMUpdater

In order to integrate this in your [CyanogenMod](https://github.com/lineageos/android_packages_apps_CMUpdater/blob/cm-14.1/res/values/config.xml#L12) or [LineageOS](https://github.com/LineageOS/android_packages_apps_Updater/blob/cm-14.1/res/values/strings.xml#L29) based ROM, you can patch the relative line inside the package.

> Although this works ( and the position may change from release to release ), I personally do not suggest to use this practice as it will always require to override this through the manifest, or maintain the commits from the official repo to your fork.
>
> Using the `build.prop` instead offers an easy and smooth integration, which could potentially be used even in local builds that make use fully of the official repos, but only updates through a local OTA REST Server. For example, by using the [docker-lineage-cicd](https://github.com/julianxhokaxhiu/docker-lineage-cicd) project.

## Changelog

### v?.?.?
- Added template system for web root ( thanks to @toolstack )
- Added config option to limit the number/age of github releases ( thanks to @toolstack )
- Fixed Github returning only the first 100 releases ( thanks to @toolstack )
- Fixed handling of Github releases that contain multiple zip files ( thanks to @toolstack )
- Added config option to disable build types ( thanks to @toolstack )
- Added config file for caching support ( thanks to @toolstack )
- Added local caching support ( thanks to @toolstack )
- Fixed duplicate build retrievals ( thanks to @toolstack )
- Added Github caching support ( thanks to @toolstack )
- Include github as a source repository ( thanks to @ADeadTrousers )
- Accept LINEAGEOTA_BASE_PATH from environment to set the root URL ( thanks to @CyberShadow )
- Read channel from build.prop ro.lineage.releasetype ( thanks to @tduboys )
- fix loading prop file from alternate location ( thanks to @bananer )
- Support device names with underscores in name extraction ( thanks to @bylaws )
- Fix finding numbers on rom names (thanks to @erfanoabdi )
- Fix loading prop file

### v2.9.0
- Add PHP 7.4 compatibility: Prevent null array access on `isValid()` ( thanks to @McNutnut )
- Update RegEx pattern to match more roms than just CM/LineageOS ( thanks to @toolstack )
- Use Forwarded HTTP Extension to determine protocol and host ( thanks to @TpmKranz )
- Add detection of HTTP_X_FORWARDED_* headers ( thanks to @ionphractal )

### v2.8.0

- Use md5sum files if available ( thanks to @jplitza )
- Abort commandExists early if shell_exec is disabled ( thanks to @timschumi )
- Update docs to match new uri formatting ( thanks to @twouters )
- Add size field to JSON ( thanks to @syphyr )

### v2.7.0

- Add support for missing `build.prop` file in LineageOS 15.x builds ( see #36 )
- Provide a proper fallback for values if `build.prop` is missing, making the JSON response acting similar [as if it was there](https://github.com/julianxhokaxhiu/LineageOTA/issues/36#issuecomment-343601224)

### v2.6.0

- Add support for the new filename that UNOFFICIAL builds of LineageOS may get from now ( eg. `lineage-14.1-20171024_123000-nightly-hammerhead-signed.zip`) ( thanks to @brianjmurrell )

### v2.5.0

- Add support for the new Lineage namespace within build.prop ( see https://review.lineageos.org/#/c/191274/ )

### v2.4.0
- Add support for the new **id** field for LineageOS ( see #32 )
- Mention the need of the PHP ZIP extension in the README in order to run correctly this software ( see #27 )

### v2.3.1
- Fix for "Fix for the timestamp value. Now it inherits the one from the ROM". The order to read this value was before the OTA server was aware of the content of the build.prop. ( thanks to @syphyr )

### v2.3.0
- Added support for latest LineageOS ROMs that are using the version field ( see #29 )
- Fix for the timestamp value. Now it inherits the one from the ROM ( see #30 )

### v2.2.0
- Honor ro.build.ota.url if present ( thanks to @ontherunvaro )
- Add support for recursive subdirectories for full builds ( thanks to @corna )
- Fix changelog URL generation ( thanks to @corna )
- Add support for HTTPS OTA Url ( thanks to @corna )
- Fix tutorial URL inside the README.md ( thanks to @visi0nary )

### v2.1.1
- Extend the legacy updater channel support to any Lineage ROM < 14.1

### v2.1.0
- Add support for LineageOS unofficial keyword on API requests
- Drop memcached in favor of APCu. Nothing to configure, it just works :)

### v2.0.9
- Removing XDelta3 logic for Delta creation ( see https://forum.xda-developers.com/showthread.php?p=69760632#post69760632 for a described correct process )
- Prevent crash of the OTA system if a file is being accessed meanwhile it is being uploaded

### v2.0.8
- Adding support for LineageOS CMUpdater ( this should not break current CM ROMs support, if yes please create an issue! )

### v2.0.7
- Renamed the whole project from CyanogenMod to LineageOS
- Added support for LineageOS ( and kept support for current CyanogenMod ROMs, until they will transition to LineageOS)

### v2.0.6
- Loop only between .ZIP files! Before even .TXT files were "parsed" which wasted some memory. Avoid this and make the REST server memory friendly :)
- HTML Changelogs! If you will now create a changelog file next to your ZIP file with an HTML extension ( eg. `lineage-14.0-20161230-NIGHTLY-hammerhead.html` ) this will be preferred over .TXT ones! Otherwise fallback to the classic TXT extension ( eg. `lineage-14.0-20161230-NIGHTLY-hammerhead.txt` )

### v2.0.5
- Fix the parsing of SNAPSHOT builds

### v2.0.4
- Final Fix for TXT and ZIP files in the same directory
- Automatic URL detection for basePath ( no real need to touch it again )
- Delta builds array is now returned correctly

### v2.0.3
- Memcached support
- UNOFFICIAL builds support ( they will be set as channel = NIGHTLY )
- Fix Delta Builds path
- Fix internal crash when *.txt files were present inside /builds/full path

### v2.0.2
- Fix some breaking changes that will not enable the REST server to work correctly.

### v2.0.1
- Excluded hiddens files and autogenerated ones by the OS (for example `.something` or `Thumbs.db`).

### v2.0
- Refactored the whole code.
- Now everything is PSR4 compliant.
- Introduced composer.json to make easier the installation of the project.

## License
See [LICENSE](https://github.com/julianxhokaxhiu/LineageOTA/blob/2.0/LICENSE).

Enjoy :)

# LineageOTA
A simple OTA REST Server for LineageOS OTA Updater System Application

## Requirements

- Apache mod_rewrite enabled
- PHP >= 5.3.0
- PHP ZIP Extension
- Composer ( if installing via CLI )

## How to use

### Composer

```shell
$ cd /var/www/html # Default Apache WWW directory, feel free to choose your own
$ composer create-project julianxhokaxhiu/lineage-ota LineageOTA
```

then finally visit http://localhost/LineageOTA to see the REST Server up and running.

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

## Where to move built ROM ZIPs

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

## REST Server Unit Testing

Feel free to use this [simple script](https://github.com/julianxhokaxhiu/LineageOTAUnitTest) made with NodeJS. Instructions are included.

## How to integrate within your ROM

In order to integrate this REST Server within your ROM you have two possibilities: you can make use of the `build.prop` ( highly suggested ), or you can patch directly the `android_packages_apps_CMUpdater` package ( not suggested ).

> Before integrating, make sure your OTA Server answers from a public URL. Also, make sure to know which is your path.
>
> For eg. if your URL is http://my.ota.uri/LineageOTA, then your API URL will be http://my.ota.uri/LineageOTA/api

### Build.prop

#### CyanogenMod / LineageOS ( <= 14.x )

In order to integrate this in your CyanogenMod based ROM, you need to add the `cm.updater.uri` property ( for [CyanogenMod](https://github.com/CyanogenMod/android_packages_apps_CMUpdater/blob/cm-14.1/src/com/cyanogenmod/updater/service/UpdateCheckService.java#L206) or [Lineage](https://github.com/LineageOS/android_packages_apps_Updater/blob/cm-14.1/src/org/lineageos/updater/misc/Constants.java#L39) ) in your `build.prop` file. See this example:

```properties
# ...
cm.updater.uri=http://my.ota.uri/api
# ...
```

#### LineageOS ( >= 15.x)

In order to integrate this in your LineageOS based ROM, you need to add the [`cm.updater.uri`](https://github.com/LineageOS/android_packages_apps_Updater/blob/lineage-15.0/src/org/lineageos/updater/misc/Constants.java#L38) property in your `build.prop` file. See this example:

```properties
# ...
cm.updater.uri=http://my.ota.uri/api
# ...
```

> Once https://review.lineageos.org/#/c/191274/ will be merged, the property `cm.updater.uri` will be renamed to `lineage.updater.uri`. Make sure to update your entry as soon as it is merged. This documentation will be updated as soon as it will happen.

### android_packages_apps_CMUpdater

In order to integrate this in your [CyanogenMod](https://github.com/lineageos/android_packages_apps_CMUpdater/blob/cm-14.1/res/values/config.xml#L12) or [LineageOS](https://github.com/LineageOS/android_packages_apps_Updater/blob/cm-14.1/res/values/strings.xml#L29) based ROM, you can patch the relative line inside the package.

> Although this works ( and the position may change from release to release ), I personally do not suggest to use this practice as it will always require to override this through the manifest, or maintain the commits from the official repo to your fork.
>
> Using the `build.prop` instead offers an easy and smooth integration, which could potentially be used even in local builds that make use fully of the official repos, but only updates through a local OTA REST Server. For example, by using the [docker-lineage-cicd](https://github.com/julianxhokaxhiu/docker-lineage-cicd) project.

## Changelog

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
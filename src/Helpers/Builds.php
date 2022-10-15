<?php
    /*
        The MIT License (MIT)

        Copyright (c) 2022 Julian Xhokaxhiu
        Copyright (c) 2022 Matthias Leitl

        Permission is hereby granted, free of charge, to any person obtaining a copy of
        this software and associated documentation files (the "Software"), to deal in
        the Software without restriction, including without limitation the rights to
        use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
        the Software, and to permit persons to whom the Software is furnished to do so,
        subject to the following conditions:

        The above copyright notice and this permission notice shall be included in all
        copies or substantial portions of the Software.

        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
        FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
        COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
        IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
        CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
    */

    namespace JX\CmOta\Helpers;

    use \Flight;
    use \JX\CmOta\Helpers\BuildLocal;
    use \JX\CmOta\Helpers\BuildGithub;
    use \JX\CmOta\Helpers\CurlRequest;

    class Builds {

        // This will contain the build list based on the current request
    	private $builds = array();

        private $postData = array();

        /**
         * Constructor of the Builds class.
         */
    	public function __construct() {
            // Set required paths for properly builds Urls later
            Flight::cfg()->set( 'buildsPath', Flight::cfg()->get( 'basePath' ) . '/builds/full' );
            Flight::cfg()->set( 'deltasPath', Flight::cfg()->get( 'basePath' ) . '/builds/delta' );

            // Get the current POST request data
            $this->postData = Flight::request()->data;
    	}

        /**
         * Return a valid response list of builds available based on the current request
         * @return array An array preformatted with builds
         */
    	public function get() {
            // Time to get the builds.
            $this->builds = array();
            $this->getBuildsLocal();
            $this->getBuildsGithub();

    		$ret = array();

            foreach( $this->builds as $build ) {
                array_push( $ret,
                            array(
                                // CyanogenMod
                                'incremental'   => $build->getIncremental(),
                                'api_level'     => $build->getApiLevel(),
                                'url'           => $build->getUrl(),
                                'timestamp'     => $build->getTimestamp(),
                                'md5sum'        => $build->getMD5(),
                                'changes'       => $build->getChangelogUrl(),
                                'channel'       => $build->getChannel(),
                                'filename'      => $build->getFilename(),
                                // LineageOS
                                'romtype'       => $build->getChannel(),
                                'datetime'      => $build->getTimestamp(),
                                'version'       => $build->getVersion(),
                                'id'            => $build->getUid(),
                                'size'          => $build->getSize(),
                            )
                        );
            }

            return $ret;
    	}

        /**
         * Set a custom set of POST data. Useful to hack the flow in case the data doesn't come within the body of the HTTP request
         * @param array An array structured as POST data
         * @return void
         */
        public function setPostData( $customData ) {
            $this->postData = $customData;
        }

        /**
         * Return a valid response of the delta build (if available) based on the current request
         * @return array An array preformatted with the delta build
         */
    	public function getDelta() {
            $ret = false;

            $source = $this->postData['source_incremental'];
            $target = $this->postData['target_incremental'];

            if( $source != $target ) {
                $sourceToken = null;
                foreach( $this->builds as $build ) {
                    if( $build->getIncremental() == $target ) {
                        $delta = $sourceToken->getDelta( $build );
                        $ret = array(
                            'date_created_unix' => $delta['timestamp'],
                            'filename'          => $delta['filename'],
                            'download_url'      => $delta['url'],
                            'api_level'         => $delta['api_level'],
                            'md5sum'            => $delta['md5'],
                            'incremental'       => $delta['incremental']
                        );
                    } elseif( $build->getIncremental() == $source ) {
                        $sourceToken = $build;
                    }
                }
            }

    		return $ret;
    	}

        /* Utility / Internal */

    	private function getBuildsLocal() {
            // Check to see if local builds are disabled in the config file.
            if( Flight::cfg()->get('DisableLocalBuilds') == true ) {
                return;
            }

            // Check to see if we have a cached version of the local builds that is less than a day old
            $cacheFilename = Flight::cfg()->get('realBasePath') . '/local.cache.json';
            $cacheEnabled = Flight::cfg()->get('EnableLocalCache') == true ? true : false;
            $cacheTimeout = Flight::cfg()->get('LocalCacheTimeout');

            if( $cacheTimeout < 1 ) { $cacheTimout = 86400; }

            if( $cacheEnabled && file_exists( $cacheFilename ) && filesize( $cacheFilename ) > 0 && ( time() - filemtime( $cacheFilename ) < $cacheTimeout ) ) {
                $data_set = json_decode( file_get_contents( $cacheFilename ) , true );

                foreach( $data_set as $build_data ) {
                    $build = new BuildLocal( '', '', $build_data );

                    if( $build->isValid( $this->postData['params'] ) ) {
                        array_push( $this->builds, $build );
                    }
                }
            } else {
                // Get physical paths of where the files resides
                $path = Flight::cfg()->get( 'realBasePath' ) . '/builds/full';

                // Get subdirs
                $dirs = glob( $path . '/*' , GLOB_ONLYDIR );
                array_push( $dirs, $path );

                // Setup a cache array so we can store the local releases separately from the other release types
                $localBuilds = array();

                foreach( $dirs as $dir )  {
                    // Get the file list and parse it
                    $files = scandir( $dir );

                    if( count( $files ) > 0  ) {
                        foreach( $files as $file ) {
                            $extension = pathinfo( $file, PATHINFO_EXTENSION );

                            if( $extension == 'zip' ) {
                                $build = null;

                                // If APC is enabled
                                if( extension_loaded( 'apcu' ) && ini_get( 'apc.enabled' ) ) {
                                    $build = apcu_fetch( $file );

                                    // If not found there, we have to find it with the old fashion method...
                                    if( $build === FALSE ) {
                                        $build = new BuildLocal( $file, $dir );
                                        // ...and then save it for 72h until it expires again
                                        apcu_store( $file, $build, 72*60*60 );
                                    }
                                } else
                                    $build = new BuildLocal( $file, $dir );

                                // Store this build to the cache
                                if( $cacheEnabled ) {
                                    array_push( $localBuilds, $build->exportData() );
                                }

                                if ( $build->isValid( $this->postData['params'] ) ) {
                                    array_push( $this->builds , $build );
                                }
                            }
                        }
                    }
                }

                // Store the local releases to the cache file
                if( $cacheEnabled ) {
                    file_put_contents( $cacheFilename, json_encode( $localBuilds, JSON_PRETTY_PRINT ) );
                }
            }
    	}

    	private function getBuildsGithub() {
            // Check to see if Github builds are disabled in the config file.
            if( Flight::cfg()->get( 'DisableGithubBuilds' ) == true ) {
                return;
            }

            $cacheFilename = Flight::cfg()->get( 'realBasePath' ) . '/github.cache.json';
            $cacheEnabled = Flight::cfg()->get( 'EnableGithubCache' ) == false ? false : true;
            $cacheTimeout = Flight::cfg()->get( 'GithubCacheTimeout' );

            if( $cacheTimeout < 1 ) { $cacheTimout = 86400; }

            // Check to see if caching is enabled and we have a cached version of the Github builds that is less than a day old
            if( $cacheEnabled && file_exists( $cacheFilename ) && filesize( $cacheFilename ) > 0 && ( time() - filemtime( $cacheFilename ) < $cacheTimeout ) ) {
                $data_set = json_decode( file_get_contents( $cacheFilename ) , true );

                foreach( $data_set as $build_data ) {
                    $build = new BuildGithub( array(), $build_data );

                    if ( $build->isValid( $this->postData['params'] ) ) {
                        array_push( $this->builds, $build );
                    }
                }
            } else {
                // Get Repos with potential OTA releases
                $repos = Flight::cfg()->get( 'githubRepos' );

                // Setup a cache array so we can store the Github releases separately from the other release types
                $githubBuilds = array();

                // Get the max releases per repo from the config
                $maxReleases = Flight::cfg()->get( 'MaxGithubReleasesPerRepo' );

                // If maxReleases wasn't set, or set to 0, use a really big number for our maximum releases
                if( $maxReleases < 1 ) { $maxReleases = PHP_INT_MAX; }

                // Get the max age for releases from the config
                $maxAge = strtotime( Flight::cfg()->get( 'OldestGithubRelease' ) );

                foreach( $repos as $repo )  {
                    // The Github API limits results to 100 at a time, so we may have to go through multiple pages to get
                    // all of the releases, so setup a page counter before we begin.
                    $pageCount = 1;
                    $releaseCount = 0;

                    while( $pageCount != false ) {
                        $request = new CurlRequest( 'https://api.github.com/repos/' . $repo['name'] . '/releases?per_page=100&page=' . $pageCount );
                        $request->addHeader( 'Accept: application/vnd.github.v3+json' );

                        if( $request->executeRequest() ) {
                            $releases = json_decode( $request->getResponse(), true );

                            // If we received less than 100 results, there are no more pages so we can exit the loop,
                            // otherwise increase out page count and get some more releases.
                            if( count( $releases ) < 100 ) { $pageCount = false; } else { $pageCount++; }

                            foreach( $releases as $release )  {
                                // Bump our release counter for this repo
                                $releaseCount++;

                                // Check to see if we're reached out maximum release count yet, if so we can exit the
                                // loop and not get any more results.
                                if( $releaseCount > $maxReleases ) {
                                    $pageCount = false;

                                    break 1;
                                }

                                // Check to see if this release is older than our max release age, if so we can skip it.
                                if( strtotime( $release['published_at'] ) >= $maxAge ) {
                                    $build = new BuildGithub( $release );

                                    // Store this build to the cache
                                    if( $cacheEnabled ) {
                                        array_push( $githubBuilds, $build->exportData() );
                                    }

                                    if ( $build->isValid( $this->postData['params'] ) ) {
                                        array_push( $this->builds, $build );
                                    }
                                }
                            }
                        }
                    }
                }

                // Store the Github releases to the cache file
                if( $cacheEnabled ) {
                    file_put_contents( $cacheFilename, json_encode( $githubBuilds, JSON_PRETTY_PRINT ) );
                }
            }
    	}
    }

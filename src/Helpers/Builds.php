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
            Flight::cfg()->set( 'buildsPath', Flight::cfg()->get('basePath') . '/builds/full' );
            Flight::cfg()->set( 'deltasPath', Flight::cfg()->get('basePath') . '/builds/delta' );

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

            foreach ( $this->builds as $build ) {
                array_push( $ret, array(
                    // CyanogenMod
                    'incremental' => $build->getIncremental(),
                    'api_level' => $build->getApiLevel(),
                    'url' => $build->getUrl(),
                    'timestamp' => $build->getTimestamp(),
                    'md5sum' => $build->getMD5(),
                    'changes' => $build->getChangelogUrl(),
                    'channel' => $build->getChannel(),
                    'filename' => $build->getFilename(),
                    // LineageOS
                    'romtype' => $build->getChannel(),
                    'datetime' => $build->getTimestamp(),
                    'version' => $build->getVersion(),
                    'id' => $build->getUid(),
                    'size' => $build->getSize(),
                ));
            }

            return $ret;
    	}

        /**
         * Set a custom set of POST data. Useful to hack the flow in case the data doesn't come within the body of the HTTP request
         * @param array An array structured as POST data
         * @return void
         */
        public function setPostData( $customData ){
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
            if ( $source != $target ) {
                $sourceToken = null;
                foreach ($this->builds as $build) {
                    if ( $build->getIncremental() == $target ) {
                        $delta = $sourceToken->getDelta($build);
                        $ret = array(
                            'date_created_unix' => $delta['timestamp'],
                            'filename' => $delta['filename'],
                            'download_url' => $delta['url'],
                            'api_level' => $delta['api_level'],
                            'md5sum' => $delta['md5'],
                            'incremental' => $delta['incremental']
                        );
                    } else if ( $build->getIncremental() == $source ) {
                        $sourceToken = $build;
                    }
                }
            }

    		return $ret;
    	}

        /* Utility / Internal */

    	private function getBuildsLocal() {
            // Get physical paths of where the files resides
            $path = Flight::cfg()->get('realBasePath') . '/builds/full';
            // Get subdirs
            $dirs = glob( $path . '/*' , GLOB_ONLYDIR );
            array_push( $dirs, $path );
            foreach ( $dirs as $dir )  {
                // Get the file list and parse it
                $files = scandir( $dir );
                if ( count( $files ) > 0  ) {
                    foreach ( $files as $file ) {
                        $extension = pathinfo($file, PATHINFO_EXTENSION);

                        if ( $extension == 'zip' ) {
                            $build = null;

                            // If APC is enabled
                            if( extension_loaded('apcu') && ini_get('apc.enabled') ) {
                                $build = apcu_fetch( $file );

                                // If not found there, we have to find it with the old fashion method...
                                if ( $build === FALSE ) {
                                    $build = new BuildLocal( $file, $dir );
                                    // ...and then save it for 72h until it expires again
                                    apcu_store( $file, $build, 72*60*60 );
                                }
                            } else
                                $build = new BuildLocal( $file, $dir );

                            if ( $build->isValid( $this->postData['params'] ) ) {
                                array_push( $this->builds , $build );
                            }
                        }
                    }
                }
            }
    	}
    	
    	private function getBuildsGithub() {
            // Check to see if we have a cached version of the Github builds that is less than a day old
            $cacheFilename = Flight::cfg()->get('realBasePath') . '/github.cache.json';

            if( file_exists($cacheFilename) && filesize( $cacheFilename ) > 0 && ( time() - filemtime($cacheFilename) < 86400 ) ) {
                $data_set = json_decode( file_get_contents( $cacheFilename ) , true );

                foreach( $data_set as $build_data ) {
                    $build = new BuildGithub(array(), $build_data);

                    if ( $build->isValid( $this->postData['params'] ) ) {
                        array_push( $this->builds, $build );
                    }
                }
            } else {
                // Get Repos with potential OTA releases
                $repos = Flight::cfg()->get('githubRepos');

                // Setup a cache array so we can store the Github releases separately from the other release types
                $githubBuilds = array();

                foreach ( $repos as $repo )  {
                    $request = new CurlRequest('https://api.github.com/repos/' . $repo['name'] . '/releases');
                    $request->addHeader('Accept: application/vnd.github.v3+json');

                    if ($request->executeRequest()) {
                        $releases = json_decode($request->getResponse(),true);

                        foreach ( $releases as $release )  {
                            $build = new BuildGithub( $release );

                            // Store this build to the cache
                            array_push( $githubBuilds, $build->exportData() );

                            if ( $build->isValid( $this->postData['params'] ) ) {
                                array_push( $this->builds, $build );
                            }
                        }
                    }
                }

                // Store the Github releases to the cache file
                file_put_contents($cacheFilename, json_encode( $githubBuilds, JSON_PRETTY_PRINT ) );
            }
    	}
    }

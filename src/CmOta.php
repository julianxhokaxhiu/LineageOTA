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

    namespace JX\CmOta;

    use \DotNotation;
    use \Flight;

    use \JX\CmOta\Helpers\CurlRequest;

    class CmOta {
        private $builds = NULL;

        /**
         * Constructor of the CmOta class.
         * @param array $options Various options that can be configured
         */
        public function __construct() {
            // Internal Initialization routines
            $this->initConfig();
            $this->initRouting();
            $this->initBuilds();
        }

        /**
         * Get the global configuration
         * @return array The whole configuration until this moment
         */
        public function getConfig() {
            return Flight::cfg()->get();
        }

        /**
         * Set a configuration option based on a key
         * @param type $key The key of your configuration
         * @param type $value The value that you want to set
         * @return class Return always itself, so it can be chained within calls
         */
        public function setConfig( $key, $value ) {
            Flight::cfg()->set( $key, $value );

            return $this;
        }

        /**
         * Set a configuration option based on a JSON file
         * @param type $key The key of your configuration
         * @param type $value The file which contents you want to set
         * @return class Return always itself, so it can be chained within calls
         */
        public function setConfigJSON( $key, $file ) {
            Flight::cfg()->set( $key, json_decode( file_get_contents( Flight::cfg()->get( 'realBasePath' ) . '/' . $file ) , true ) );

            return $this;
        }

        /**
         * Set a configuration option based on a JSON file
         * @param type $key The key of your configuration
         * @param type $value The file which contents you want to set
         * @return class Return always itself, so it can be chained within calls
         */
        public function loadConfigJSON( $file ) {
            $settingsFile = Flight::cfg()->get( 'realBasePath' ) . '/' . $file;

            if( file_exists( $settingsFile ) ) {
                $settings = json_decode( file_get_contents( $settingsFile ), true );

                if( is_array( $settings ) ) {
                    foreach( $settings[0] as $key => $value ) {
                        Flight::cfg()->set( $key, $value );
                    }
                }
            }
            return $this;
        }

        /**
         * This initialize the REST API Server
         * @return class Return always itself, so it can be chained within calls
         */
        public function run() {
            $loader = new \Twig\Loader\FilesystemLoader( Flight::cfg()->get( 'realBasePath' ) . '/views' );

            $twigConfig = array();

            Flight::register( 'twig', '\Twig\Environment', array( $loader, array() ), function ($twig) {
                // Nothing to do here
            });

            Flight::start();

            return $this;
        }

        /* Utility / Internal */

        // Used to compare timestamps in the build ksort call inside of initRouting for the "/" route
        private function compareByTimeStamp( $a, $b ) {
          return $a['timestamp'] - $b['timestamp'];
        }

        // Format a file size string nicely
        private function formatFileSize( $bytes, $dec = 2 ) {
            $size   = array( ' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB' );
            $factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

            return sprintf( "%.{$dec}f", $bytes / pow( 1024, $factor ) ) . @$size[$factor];
        }

        // Setup Flight's routing information
        private function initRouting() {
            // Just list the builds folder for now
            Flight::route('/', function() {
                // Get the template name we're going to use and tack on .twig
                $templateName = Flight::cfg()->get( 'OTAListTemplate' ) . '.twig';

                // Make sure the template exists, otherwise fall back to our default
                if( ! file_exists( 'views/' . $templateName ) ) { $templateName = 'ota-list-tables.twig'; }

                // Time to setup some variables for use later.
                $builds = Flight::builds()->get();
                $buildsToSort = array();
                $output = '';
                $model = 'Unknown';
                $deviceNames = Flight::cfg()->get( 'DeviceNames' );
                $vendorNames = Flight::cfg()->get( 'DeviceVendors' );
                $devicesByVendor = array();
                $parsedFilenames = array();
                $formatedFileSizes = array();
                $githubURL = '';

                if( ! is_array( $deviceNames ) ) { $deviceNames = array(); }

                // Loop through the builds to do some setup work
                foreach( $builds as $build ) {
                    // Split the filename using the parser in the build class to get some details
                    $filenameParts = Flight::build()->parseFilenameFull( $build['filename'] );

                    // Same the parsed filesnames for later use in the template
                    $parsedFilenames[$build['filename']] = $filenameParts;

                    // In case no Github URL was configured, see if we can get it from an existing Github repo
                    if( $githubURL == '' && strstr( $build['url'], 'github.com' ) ) {
                        $path = parse_url( $build['url'], PHP_URL_PATH );
                        $pathParts = explode( '/', $path );
                        $githubURL = 'https://github.com/' . $pathParts[1];
                    }

                    $formatedFileSizes[$build['filename']] = $this->formatFileSize( $build['size'], 0 );

                    // Check to see if the formated size is less than 5 characters, aka 3 for the postfix and
                    // one for the actual size, if so, let's add some decimal places to it.  We want to avoid files
                    // are close to a single digit size reporting too little info, a 1400 MB file would round down
                    // to 1 GB, so instead display 1.4 GB.
                    if( strlen( $formatedFileSizes[$build['filename']] ) < 5 ) {
                        $formatedFileSizes[$build['filename']] = $this->formatFileSize( $build['size'], 1 );
                    }

                    // Add the build to a list based on model names
                    $buildsToSort[$filenameParts['model']][] = $build;
                }

                // Sort the array based on model name
                ksort( $buildsToSort );

                // Sort the entries in each model based on time/date
                foreach( $buildsToSort as $model => $sort ) {
                    usort( $sort, array( $this, 'compareByTimeStamp' ) );
                }

                // Create a list of vendors and the devices that belong to them
                foreach( $vendorNames as $model => $vendor ) {
                    $devicesByVendor[$vendor][] = $model;
                }

                // Sort the vendor names
                ksort( $devicesByVendor );

                // Sort the devices for each vendor
                foreach( $devicesByVendor as $vendor => $devices ) {
                    sort( $devices );
                }

                // Setup branding information for the template
                $branding = array(  'name'      => Flight::cfg()->get( 'BrandName' ),
                                    'GithubURL' => Flight::cfg()->get( 'GithubHomeURL' ),
                                    'LocalURL'  => Flight::cfg()->get( 'LocalHomeURL' )
                                 );

                // Sanity check the branding, use some reasonable deductions if anything is missing
                if( $branding['name'] == '' && is_array( $parsedFilenames ) ) { $branding['name'] = reset( $parsedFilenames )['type']; }
                if( $branding['GithubURL'] == '' ) { $branding['GithubURL'] = $githubURL; }
                if( $branding['LocalURL'] == '' ) { $branding['LocalURL'] = Flight::cfg()->get( 'basePath' ) . '/builds'; }

                // Render the template with Twig
                Flight::twig()->display( $templateName,
                                array(  'builds'            => $builds,
                                        'sortedBuilds'      => $buildsToSort,
                                        'parsedFilenames'   => $parsedFilenames,
                                        'deviceNames'       => $deviceNames,
                                        'vendorNames'       => $vendorNames,
                                        'devicesByVendor'   => $devicesByVendor,
                                        'branding'          => $branding,
                                        'formatedFileSizes' => $formatedFileSizes,
                                     )
                              );
            });

            // Main call
            Flight::route( '/api', function() {
                $ret = array(
                    'id' => null,
                    'result' => Flight::builds()->get(),
                    'error' => null
                );

                Flight::json( $ret );
            });

            // Delta updates call
            Flight::route( '/api/v1/build/get_delta', function() {
                $ret = array();

                $delta = Flight::builds()->getDelta();

                if ( $delta === false ) {
                    $ret['errors'] = array(
                        'message' => 'Unable to find delta'
                    );
                } else {
                    $ret = array_merge( $ret, $delta );
                }

                Flight::json($ret);
            });

            // LineageOS new API
            Flight::route( '/api/v1/@deviceType(/@romType(/@incrementalVersion))', function ( $deviceType, $romType, $incrementalVersion ) {
                Flight::builds()->setPostData(
                    array(
                        'params' => array(
                            'device' => $deviceType,
                            'channels' => array(
                                $romType,
                            ),
                            'source_incremental' => $incrementalVersion,
                        ),
                    )
                );

                $ret = array(
                    'id' => null,
                    'response' => Flight::builds()->get(),
                    'error' => null
                );

                Flight::json( $ret );
            });
        }

        /**
         * Register the config array within Flight
         */
        private function initConfig() {
            Flight::register( 'cfg', '\DotNotation', array(), function( $cfg ) {
                $cfg->set( 'basePath', '' );
                $cfg->set( 'realBasePath', realpath( __DIR__ . '/..' ) );
            });
        }

        /**
         * Register the build class within Flight
         */
        private function initBuilds() {
            Flight::register( 'builds', '\JX\CmOta\Helpers\Builds', array(), function( $builds ) {
                // Do nothing for now
            });

            Flight::register( 'build', '\JX\CmOta\Helpers\Build', array(), function( $build ) {
                // Do nothing for now
            });
        }
    }

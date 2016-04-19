<?php
    /*
        The MIT License (MIT)

        Copyright (c) 2016 Julian Xhokaxhiu

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

    class CmOta {
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
         * Enable memcached feature
         * @return class Return always itself, so it can be chained within calls
         */
        public function enableMemcached() {
            Flight::register( 'mc', 'Memcached', array(), function( $mc ) {
                $mc->addServer( Flight::cfg()->get( 'memcached.host' ) , Flight::cfg()->get( 'memcached.port' ) );
                Flight::cfg()->set( 'memcached.enabled', true );
            });

            return $this;
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
         * This initialize the REST API Server
         * @return class Return always itself, so it can be chained within calls
         */
        public function run() {
            Flight::start();

            return $this;
        }

        /* Utility / Internal */

        private function initRouting() {
            // Just list the builds folder for now
            Flight::route('/', function() {
                Flight::redirect( '/builds');
            });

            // Main call
            Flight::route('/api', function(){
                $ret = array(
                    'id' => null,
                    'result' => Flight::builds()->get(),
                    'error' => null
                );

                Flight::json($ret);
            });

            // Delta updates call
            Flight::route('/api/v1/build/get_delta', function(){
                $ret = array();

                $delta = Flight::builds()->getDelta();
                if ( $delta === false ) {
                    $ret['errors'] = array(
                        'message' => 'Unable to find delta'
                    );
                } else {
                    $ret = array_merge($ret, $delta);
                }

                Flight::json($ret);
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
        }
    }
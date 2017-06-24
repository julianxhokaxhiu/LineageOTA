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

    namespace JX\CmOta\Helpers;

    use \Flight;

    class Build {

    	private $apiLevel = -1;
        private $channel = '';
        private $model = '';
        private $filename = '';
        private $url = '';
        private $changelogUrl = '';
        private $timestamp = '';
        private $incremental = '';
        private $filePath = '';
        private $buildProp = '';

        /**
         * Constructor of the Build class.
         * Here all the information about the current build will be collected
         * and make them available for the next time.
         * @param type $fileName The current filename of the build
         * @param type $physicalPath The current path where the build lives
         */
    	public function __construct($fileName, $physicalPath) {
    		/*
				$tokens Schema:

                array(
                    1 => [TYPE] (ex. cm, lineage, etc.)
                    2 => [VERSION] (ex. 10.1.x, 10.2, 11, etc.)
                    3 => [DATE OF BUILD] (ex. 20140130)
                    4 => [CHANNEL OF THE BUILD] (ex. RC, RC2, NIGHTLY, etc.)
                    5 => [SNAPSHOT CODE] ( ex. ZNH0EAO2O0, etc. )
                    6 => [MODEL] (ex. i9100, i9300, etc.)
                )
            */
            preg_match_all( '/(cm|lineage)-([0-9\.]+)-(\d+)?-([\w+]+)?([-A-Za-z0-9]+)?-([\w+]+)/', $fileName, $tokens );
            $tokens = $this->removeTrailingDashes( $tokens );

            $this->filePath = $physicalPath . '/' . $fileName;
            $this->channel = $this->_getChannel( str_replace( range( 0 , 9 ), '', $tokens[4] ), $tokens[1], $tokens[2] );
            $this->filename = $fileName;
            $this->buildProp = explode( "\n", @file_get_contents('zip://'.$this->filePath.'#system/build.prop') );
            $this->timestamp = $this->getBuildPropValue( 'ro.build.date.utc' );
            $this->incremental = $this->getBuildPropValue( 'ro.build.version.incremental' );
            $this->apiLevel = $this->getBuildPropValue( 'ro.build.version.sdk' );
            $this->model = $this->getBuildPropValue( 'ro.cm.device' );
            $this->version = $tokens[2];

            $position = strrpos( $physicalPath, '/builds/full' );
            if ( $position === FALSE )
                $this->url = $this->_getUrl( '', Flight::cfg()->get('buildsPath') );
            else
                $this->url = $this->_getUrl( '', Flight::cfg()->get('basePath') . substr( $physicalPath, $position ) );

            $this->changelogUrl = $this->_getChangelogUrl();
        }

        /**
         * Check if the current build is valid within the current request
         * @param type $params The params dictionary inside the current POST request
         * @return boolean True if valid, False if not.
         */
    	public function isValid($params){
            $ret = false;

            if ( $params['device'] == $this->model ) {
                if ( count($params['channels']) > 0 ) {
                    foreach ( $params['channels'] as $channel ) {
                        if ( strtolower($channel) == $this->channel ) $ret = true;
                    }
                }
            }

            return $ret;
        }

        /**
         * Create a delta build based from the current build to the target build.
         * @param type $targetToken The target build from where to build the Delta
         * @return array/boolean Return an array performatted with the correct data inside, otherwise false if not possible to be created
         */
        public function getDelta($targetToken){
            $ret = false;

            $deltaFile = $this->incremental . '-' . $targetToken->incremental . '.zip';
            $deltaFilePath = Flight::cfg()->get('realBasePath') . '/builds/delta/' . $deltaFile;

            if ( file_exists( $deltaFilePath ) )
              $ret = array(
                'filename' => $deltaFile,
                'timestamp' => filemtime( $deltaFilePath ),
                'md5' => $this->getMD5( $deltaFilePath ),
                'url' => $this->_getUrl( $deltaFile, Flight::cfg()->get('deltasPath') ),
                'api_level' => $this->apiLevel,
                'incremental' => $targetToken->incremental
              );

            return $ret;
        }

        /**
         * Return the MD5 value of the current build
         * @param string $path The path of the file
         * @return string The MD5 hash
         */
        public function getMD5($path = ''){
            $ret = '';

            if ( empty($path) ) $path = $this->filePath;
            // Pretty much faster if it is available
            if ( $this->commandExists( 'md5sum' ) ) {
                $tmp = explode("  ", exec( 'md5sum ' . $path));
                $ret = $tmp[0];
            } else {
                $ret = md5_file($path);
            }

            return $ret;
        }

        /* Getters */

        /**
         * Get the Incremental value of the current build
         * @return string The incremental value
         */
        public function getIncremental() {
        	return $this->incremental;
        }

        /**
         * Get the API Level of the current build.
         * @return string The API Level value
         */
        public function getApiLevel() {
        	return $this->apiLevel;
        }

        /**
         * Get the Url of the current build
         * @return string The Url value
         */
        public function getUrl() {
        	return $this->url;
        }

        /**
         * Get the timestamp of the current build
         * @return string The timestamp value
         */
        public function getTimestamp() {
        	return $this->timestamp;
        }

        /**
         * Get the changelog Url of the current build
         * @return string The changelog Url value
         */
        public function getChangelogUrl() {
        	return $this->changelogUrl;
        }

        /**
         * Get the channel of the current build
         * @return string The channel value
         */
        public function getChannel() {
        	return $this->channel;
        }

        /**
         * Get the filename of the current build
         * @return string The filename value
         */
        public function getFilename() {
        	return $this->filename;
        }

        /**
         * Get the version of the current build
         * @return string the version value
         */
        public function getVersion() {
            return $this->version;
        }

    	/* Utility / Internal */

        /**
         * Remove trailing dashes
         * @param type $token The string where to do the operation
         * @return string The string without trailing dashes
         */
        private function removeTrailingDashes($token){
            foreach ( $token as $key => $value ) {
                $token[$key] = trim( $value[0], '-' );
            }
            return $token;
        }

        /**
         * Get the current channel of the build based on the current token
         * @param string $token The channel obtained from build.prop
         * @param string $type The ROM type from filename
         * @param string $version The ROM version from filename
         * @return string The correct channel to be returned
         */
        private function _getChannel($token, $type, $version){
            $ret = 'stable';

            $token = strtolower( $token );
            if ( $token > '' ) {
                $ret = $token;
                if ( $token == 'experimental' && ( $type == 'cm' || version_compare ( $version, '14.1', '<' ) ) ) $ret = 'snapshot';
                if ( $token == 'unofficial' && ( $type == 'cm' || version_compare ( $version, '14.1', '<' ) ) ) $ret = 'nightly';
            }

            return $ret;
        }

        /**
         * Get the correct URL for the build
         * @param string $fileName The name of the file
         * @return string The absolute URL for the file to be downloaded
         */
        private function _getUrl($fileName = '', $basePath){
            $prop = $this->getBuildPropValue( 'ro.build.ota.url' );
            if ( !empty($prop) )
                return $prop;

            if ( empty($fileName) ) $fileName = $this->filename;
            return $basePath . '/' . $fileName;
        }

        /**
         * Get the changelog URL for the current build
         * @return string The changelog URL
         */
        private function _getChangelogUrl(){
            if ( file_exists( str_replace('.zip', '.txt', $this->filePath) ) )
                $ret = str_replace('.zip', '.txt', $this->url);
            elseif ( file_exists( str_replace('.zip', '.html', $this->filePath) ) )
                $ret = str_replace('.zip', '.html', $this->url);
            else
                $ret = '';

            return $ret;
        }

        /**
         * Get a property value based on the $key value.
         * It does it by searching inside the file build.prop of the current build.
         * @param string $key The key for the wanted value
         * @return string The value for the specified key
         */
        private function getBuildPropValue($key){
            $ret = '';

            foreach ($this->buildProp as $line) {
                if ( strpos($line, $key) !== false ) {
                    $tmp = explode('=', $line);
                    $ret = $tmp[1];
                    break;
                }
            }

            return $ret;
        }

        /**
         * Checks if a command is available on the current server
         * @param string $cmd The current command to execute
         * @return boolean Return True if available, False if not
         */
        private function commandExists($cmd){
            $returnVal = shell_exec("which $cmd");
            return (empty($returnVal) ? false : true);
        }
    }

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

    class Build {

        protected $apiLevel = -1;
        protected $channel = '';
        protected $model = '';
        protected $filename = '';
        protected $url = '';
        protected $changelogUrl = '';
        protected $timestamp = '';
        protected $incremental = '';
        protected $filePath = '';
        protected $buildProp = '';
        protected $uid = null;
        protected $size = '';
        protected $md5 = '';
        protected $version = '';

        /**
         * Check if the current build is valid within the current request
         * @param type $params The params dictionary inside the current POST request
         * @return boolean True if valid, False if not.
         */
    	public function isValid( $params ){
            if( $params === NULL ) return true;  // Assume valid if no parameters

            $ret = false;

            if( $params['device'] == $this->model ) {
                if( count($params['channels']) > 0 ) {
                    foreach( $params['channels'] as $channel ) {
                        if( strtolower($channel) == $this->channel ) $ret = true;
                    }
                }
            }

            return $ret;
        }

        /* Getters */

        /**
         * Return the MD5 value of the current build
         * @return string The MD5 hash
         */
        public function getMD5(){
            return $this->md5;
        }

        /**
         * Get filesize of the current build
         * @return string filesize in bytes
         */
        public function getSize() {
            return $this->size;
        }

        /**
         * Get a unique id of the current build
         * @return string A unique id
         */
        public function getUid() {
            return $this->uid;
        }

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

        /**
         * Export a JSON representation of the object values
         * @return string the JSON data
         */
        public function exportData() {
            return get_object_vars( $this );
        }

        /**
         * Import a JSON representation of the object values
         * @param string $data The data to import
         * @return object return ourselves
         */
        public function importData( $data ) {
            if( is_array( $data ) ) {
                foreach( $data as $key => $value ) {
                    if( property_exists( $this, $key ) ) {
                        $this->$key = $value;
                    }
                }
            }

            return $this;
        }
        /**
         * Parse a string for the tokens of lineage/cm release archive
         * @param type $fileName The filename to be parsed
         * @return array The tokens of the filename, both as numeric and named entries
         */
    	public function parseFilenameFull( $fileName ) {
            /*
                tokens Schema:
    		        array(
                        1 => [TYPE] (ex. cm, lineage, etc.)
                        2 => [VERSION] (ex. 10.1.x, 10.2, 11, etc.)
                        3 => [DATE OF BUILD] (ex. 20140130)
                        4 => [CHANNEL OF THE BUILD] (ex. RC, RC2, NIGHTLY, etc.)
                        5 =>
                          CM        => [SNAPSHOT CODE] (ex. ZNH0EAO2O0, etc.)
                          LINEAGE   => [MODEL] (ex. i9100, i9300, etc.)
                        6 =>
                          CM        => [MODEL] (ex. i9100, i9300, etc.)
                          LINEAGE   => [SIGNED] (ex. signed)
                    )
            */
            $tokens = array( 'type' => '', 'version' => '', 'date' => '', 'channel' => '', 'code' => '', 'model' => '', 'signed' => '' );

            preg_match_all( '/([A-Za-z0-9]+)?-([0-9\.]+)-([\d_]+)?-([\w+]+)-([A-Za-z0-9_]+)?-?([\w+]+)?/', $fileName, $tokens );

            $result = $this->removeTrailingDashes( $tokens );

            if( count( $result ) == 7 ) {
                $result['type'] = $result[1];
                $result['version'] = $result[2];
                $result['date'] = $result[3];
                $result['channel'] = $result[4];

                if( $result[1] == 'cm' ) {
                    $result['code'] = $result[5];
                    $result['model'] = $result[6];
                    $result['signed'] = false;
                } else {
                    $result['code'] = false;
                    $result['model'] = $result[5];
                    $result['signed'] = $result[6];
                }
            }

            return $result;
        }

        /* Utility / Internal */

        /**
         * Parse a string for the tokens of lineage/cm delta archive
         * @param type $fileName The filename to be parsed
         * @return array The tokens of the filename
         */
    	protected function parseFilenameDeltal( $fileName ) {
            /*
		tokens Schema:
		array(
                    1 => [SOURCE VERSION] (eng.matthi.20200202.195647)
                    2 => [TARGET VERSION] (eng.matthi.20200305.185431)
                )
            */
            preg_match_all( '/([\w+]+)-([\w+]+)/', $fileName, $tokens );
            return $this->removeTrailingDashes( tokens );
        }

        /**
         * Remove trailing dashes
         * @param string $token The string where to do the operation
         * @return string The string without trailing dashes
         */
        protected function removeTrailingDashes( $token ) {
            foreach( $token as $key => $value ) {
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
        protected function _getChannel( $token, $type, $version ) {
            $ret = 'stable';

            $token = strtolower( $token );

            if( $token > '' ) {
                $ret = $token;

                if( $token == 'experimental' && ( $type == 'cm' || ( $type == 'lineage' && version_compare ( $version, '14.1', '<' ) ) ) ) $ret = 'snapshot';
                if( $token == 'unofficial' && ( $type == 'cm' || ( $type == 'lineage' && version_compare ( $version, '14.1', '<' ) ) ) ) $ret = 'nightly';
            }

            return $ret;
        }

        /**
         * Get the correct URL for the build
         * @param string $fileName The name of the file
         * @return string The absolute URL for the file to be downloaded
         */
        protected function _getUrl( $fileName, $basePath ) {
            $prop = $this->getBuildPropValue( 'ro.build.ota.url' );

            if( !empty( $prop ) )
                return $prop;

            if( empty( $fileName ) ) $fileName = $this->filename;

            return $basePath . '/' . $fileName;
        }

        /**
         * Get a property value based on the $key value.
         * It does it by searching inside the file build.prop of the current build.
         * @param string $key The key for the wanted value
         * @param string $fallback The fallback value if not found in build.prop
         * @return string The value for the specified key
         */
        protected function getBuildPropValue( $key, $fallback = null ) {
            $ret = $fallback ?: null;

            if( $this->buildProp ) {
                foreach( $this->buildProp as $line ) {
                    if( strpos( $line, $key ) !== false ) {
                        $tmp = explode( '=', $line );
                        $ret = $tmp[1];

                        break;
                    }
                }
            }

            return $ret;
        }
    }

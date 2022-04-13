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
    use \JX\CmOta\Helpers\Build;

    class BuildLocal extends Build {

        /**
         * Constructor of the BuildLocal class.
         * Here all the information about the current build will be collected
         * and make them available for the next time.
         * @param type $fileName The current filename of the build
         * @param type $physicalPath The current path where the build lives
         */
    	public function __construct($fileName, $physicalPath, $data=False) {
            // If data is passed in, just import it instead of doing the work to construct the object from scratch
            if( is_array( $data ) ) {
                $this->importData( $data );
            } else {
                $tokens = $this->parseFilenameFull( $fileName );

                $this->filePath = $physicalPath . '/' . $fileName;
                $this->filename = $fileName;

                // Try to load the build.prop from two possible paths:
                // - builds/CURRENT_ZIP_FILE.zip/system/build.prop
                // - builds/CURRENT_ZIP_FILE.zip.prop ( which must exist )
                $propsFileContent = @file_get_contents('zip://'.$this->filePath.'#system/build.prop');
                if ($propsFileContent === false || empty($propsFileContent)) {
                    $propsFileContent = @file_get_contents($this->filePath.'.prop');
                }
                $this->buildProp = explode( "\n", $propsFileContent );

                // Try to fetch build.prop values. In some cases, we can provide a fallback, in other a null value will be given
                $this->channel = $this->_getChannel( $this->getBuildPropValue( 'ro.lineage.releasetype' ) ?? str_replace( range( 0 , 9 ), '', $tokens[4] ), $tokens[1], $tokens[2] );
                $this->timestamp = intval( $this->getBuildPropValue( 'ro.build.date.utc' ) ?? filemtime($this->filePath) );
                $this->incremental = $this->getBuildPropValue( 'ro.build.version.incremental' ) ?? '';
                $this->apiLevel = $this->getBuildPropValue( 'ro.build.version.sdk' ) ?? '';
                $this->model = $this->getBuildPropValue( 'ro.lineage.device' ) ?? $this->getBuildPropValue( 'ro.cm.device' ) ?? ( $tokens[1] == 'cm' ? $tokens[6] : $tokens[5] );
                $this->version = $tokens[2];
                $this->uid = hash( 'sha256', $this->timestamp.$this->model.$this->apiLevel, false );
                $this->size = filesize($this->filePath);

                $position = strrpos( $physicalPath, '/builds/full' );
                if ( $position === FALSE )
                    $this->url = $this->_getUrl( '', Flight::cfg()->get('buildsPath') );
                else
                    $this->url = $this->_getUrl( '', Flight::cfg()->get('basePath') . substr( $physicalPath, $position ) );

                $this->changelogUrl = $this->_getChangelogUrl();
                $this->md5 = $this->_getMD5();
            }
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
                'md5' => $this->_getMD5( $deltaFilePath ),
                'url' => $this->_getUrl( $deltaFile, Flight::cfg()->get('deltasPath') ),
                'api_level' => $this->apiLevel,
                'incremental' => $targetToken->incremental
              );

            return $ret;
        }

    	/* Utility / Internal */

        /**
         * Return the MD5 value of the current build
         * @param string $path The path of the file
         * @return string The MD5 hash
         */
        private function _getMD5($path = ''){
            $ret = '';

            if ( empty($path) ) $path = $this->filePath;
            // Pretty much faster if it is available
            if ( file_exists( $path . ".md5sum" ) ) {
                $tmp = explode("  ", file_get_contents( $path . '.md5sum' ));
                $ret = $tmp[0];
            }
            elseif ( $this->commandExists( 'md5sum' ) ) {
                $tmp = explode("  ", exec( 'md5sum ' . $path));
                $ret = $tmp[0];
            } else {
                $ret = md5_file($path);
            }

            return $ret;
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
         * Checks if a command is available on the current server
         * @param string $cmd The current command to execute
         * @return boolean Return True if available, False if not
         */
        private function commandExists($cmd){
            if (!$this->functionEnabled('shell_exec'))
                return false;

            $returnVal = shell_exec("which $cmd");
            return (empty($returnVal) ? false : true);
        }

        /**
         * Checks if a php function is available on the server
         * @param string $func The function to check for
         * @return boolean true if the function is enabled, false if not
         */
        private function functionEnabled($func) {
            return is_callable($func) && false === stripos(ini_get('disable_functions'), $func);
        }
    }

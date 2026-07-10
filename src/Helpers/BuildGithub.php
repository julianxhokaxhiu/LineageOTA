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

    use \JX\CmOta\Helpers\Build;

    class BuildGithub extends Build {

        private $deltas = array();

        /**
         * Constructor of the BuildGithub class.
         * Here all the information about the current build will be collected
         * and make them available for the next time.
         * @param array $release The information provided by github API
         */
    	public function __construct( $release, $data=false ) {
    	    $archives = array();
    	    $properties = array();
    	    $md5sums = array();
    	    $sha256sums = array();
    	    $changelogs = array();
    	    $metadataFiles = array();

            // If data is passed in, just import it instead of doing the work to construct the object from scratch
            if( is_array( $data ) ) {
                $this->importData( $data );
            } else {
                // Split all Assets because they are not properly sorted
                foreach( $release['assets'] as $asset ) {
                    switch( $asset['content_type'] ) {
                        case 'application/zip':
                            array_push( $archives, $asset );

                            break;
                        default:
                            $extension = pathinfo( $asset['name'], PATHINFO_EXTENSION );

                            switch( $extension ) {
                                case 'txt':
                                case 'html':
                                    array_push( $changelogs, $asset );

                                    break;
                                case 'md5sum':
                                    array_push( $md5sums, $asset );

                                    break;
                                case 'sha256sum':
                                    array_push( $sha256sums, $asset );

                                    break;
                                case 'prop':
                                    array_push( $properties, $asset );

                                    break;
                                case 'metadata':
                                    array_push( $metadataFiles, $asset );

                                    break;
                            }
                    }
                }

                // If there are multiple zip's in the release, grab the largest one.
                $largestSize = -1;

                foreach( $archives as $archive ) {
                    if( $archive['size'] > $largestSize ) {
                        $tokens = $this->parseFilenameFull($archive['name']);

                        $this->filePath     = $archive['browser_download_url'];
                        $this->url          = $archive['browser_download_url'];
                        $this->channel      = $this->_getChannel( str_replace( range( 0 , 9 ), '', $tokens['channel'] ), $tokens['type'], $tokens['version'] );
                        $this->filename     = $archive['name'];
                        $this->timestamp    = strtotime( $archive['updated_at'] );
                        $this->model        = $tokens['model'];
                        $this->version      = $tokens['version'];
                        $this->size         = $archive['size'];

                        $largestSize = $this->size;
                    }
                }

                foreach( $properties as $property ) {
                    $this->buildProp    = explode( "\n", file_get_contents( $property['browser_download_url'] ) );
                    $this->timestamp    = intval( $this->getBuildPropValue( 'ro.build.date.utc' ) ?? $this->timestamp );
                    $this->incremental  = $this->getBuildPropValue( 'ro.build.version.incremental' ) ?? '';
                    $this->apiLevel     = $this->getBuildPropValue( 'ro.build.version.sdk' ) ?? '';
                    $this->model        = $this->getBuildPropValue( 'ro.lineage.device' ) ?? $this->getBuildPropValue( 'ro.cm.device' ) ?? $this->model;
                    $this->osPatchLevel = $this->getBuildPropValue( 'ro.build.version.security_patch' ) ?? '';
                }

                foreach( $metadataFiles as $metadataFile ) {
                    if( $metadataFile['name'] == $this->filename . '.metadata' || $metadataFile['name'] == 'metadata' ) {
                        $this->loadOtaMetadataFromContents( file_get_contents( $metadataFile['browser_download_url'] ) );
                    }
                }

                foreach ( $md5sums as $md5sum ) {
                    $md5 = $this->parseMD5( $md5sum['browser_download_url'] );

                    if( array_key_exists( $this->filename, $md5 ) ) {
                        $this->md5 = $md5[$this->filename];
                    }
                }

                foreach( $sha256sums as $sha256sum ) {
                    $sha256 = $this->parseChecksumList( file_get_contents( $sha256sum['browser_download_url'] ) );

                    if( array_key_exists( $this->filename, $sha256 ) ) {
                        $this->sha256 = $sha256[$this->filename];
                    }
                }

                foreach( $changelogs as $changelog ) {
                    $this->changelogUrl = $changelog['browser_download_url'];
                }

                $this->uid = hash( 'sha256', $this->timestamp . $this->model . $this->apiLevel, false );
            }
        }

        /**
         * Create a delta build based from the current build to the target build.
         * @param type $targetToken The target build from where to build the Delta
         * @return array/boolean Return an array performatted with the correct data inside, otherwise false if not possible to be created
         */
        public function getDelta( $targetToken ){
            $ret = false;

            // TO-DO: Figuring out a way to provide a delta build over github

            return $ret;
        }

    	/* Utility / Internal */

        /**
         * Return the MD5 value of the current build
         * @param string $file The path of the file containing the hashes
         * @return array The MD5 hashes
         */
        private function parseMD5( $file ){
            return $this->parseChecksumList( file_get_contents( $file ) );
        }

    }

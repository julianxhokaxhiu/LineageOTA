<?php
    /*
        The MIT License (MIT)

        Copyright (c) 2014 Julian Xhokaxhiu

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
    use Build;

    class Builds {

    	private $fullBuilds;

    	public function __construct() {
            $this->fullBuilds = array();

            // Internal Initialization routines
    		$this->getBuilds();
    	}

    	public function get() {
    		$ret = array();

            foreach ( $this->fullBuilds as $build ) {
                array_push( $ret, array(
                    'incremental' => $build->getIncremental(),
                    'api_level' => $build->getApiLevel(),
                    'url' => $build->getUrl(),
                    'timestamp' => $build->getTimestamp(),
                    'md5sum' => $build->getMD5(),
                    'changes' => $build->getChangelogUrl(),
                    'channel' => $build->getChannel(),
                    'filename' => $build->getFilename()
                ));
            }

            return $ret;
    	}

    	public function getDelta() {
    		return false;
    	}

    	private function getBuilds() {
            // Get physical paths of where the files resides
            $path = Flight::cfg()->get('realBasePath') . '/builds/full';
            // Get the POST data
            $postData = json_decode( Flight::request()->body, true);
            // Get the file list and parse it
    		$files = array_diff( scandir( $path ) , array( '..', '.' ) );
            if ( count( $files ) > 0  ) {
                foreach ( $files as $file ) {
                    $build = new Build();
                    $build
                    ->setFilename( $file )
                    ->setPath( $path );

                    if ( $build->isValid( $postData['params'] ) ) {
                        array_push( $this->fullBuilds , $build );
                    }
                }
            }
    	}

    }
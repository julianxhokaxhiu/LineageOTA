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

    class TokenCollection {
        var $postJson = array();
        var $list = array();

        public function __construct($physicalPath){
            $req = Flight::request();
            $this->postJson = json_decode($req->body, true);

            $files = preg_grep('/^([^.])/', scandir($physicalPath));
            if ( count( $files ) > 0  ) {
                foreach ( $files as $file ) {
                    $token = new Token( $file, $physicalPath, $req->base );

                    if ( $token->isValid( $this->postJson['params'] ) ) {
                        array_push($this->list, $token);
                    }
                }
            }
        }

        public function getDeltaUpdate(){
            $ret = false;

            $source = $this->postJson['source_incremental'];
            $target = $this->postJson['target_incremental'];
            if ( $source != $target ) {
                $sourceToken = null;
                foreach ($this->list as $token) {
                    if ( $token->incremental == $target ) {
                        $delta = $sourceToken->getDelta($token);
                        $ret = array(
                            'date_created_unix' => $delta['timestamp'],
                            'filename' => $delta['filename'],
                            'download_url' => $delta['url'],
                            'api_level' => $delta['api_level'],
                            'md5sum' => $delta['md5'],
                            'incremental' => $delta['incremental']
                        );
                    } else if ( $token->incremental == $source ) {
                        $sourceToken = $token;
                    }
                }
            }

            return $ret;
        }

        public function getUpdateList(){
            $ret = array();

            foreach ($this->list as $token) {
                array_push($ret, array(
                    'incremental' => $token->incremental,
                    'api_level' => $token->api_level,
                    'url' => $token->url,
                    'timestamp' => $token->timestamp,
                    'md5sum' => $token->getMD5(),
                    'changes' => $token->changelogUrl,
                    'channel' => $token->channel,
                    'filename' => $token->filename
                ));
            }

            return $ret;
        }
    };
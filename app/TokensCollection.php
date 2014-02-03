<?php
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
                        $file = $sourceToken->getDelta($token);
                        $ret = array(
                            'date_created_unix' => '',
                            'filename' => '',
                            'download_url' => '',
                            'api_level' => '',
                            'md5sum' => '',
                            'incremental' => ''
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
                    'api_level' => $token->getAPILevel(),
                    'url' => $token->url,
                    'timestamp' => $token->timestamp,
                    'md5sum' => $token->md5,
                    'changes' => $token->changelogUrl,
                    'channel' => $token->channel,
                    'filename' => $token->filename
                ));
            }

            return $ret;
        }
    };
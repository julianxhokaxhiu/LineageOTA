<?php
    class TokenCollection {
        var $list = array();

        public function __construct($physicalPath){
            $files = preg_grep('/^([^.])/', scandir($physicalPath));
            if ( count( $files ) > 0  ) {
                foreach ( $files as $file ) {
                    $token = new Token( $file, $physicalPath, Flight::request()->base );

                    if ( $token->isValid( json_decode(Flight::request()->body, true)['params'] ) ) {
                        array_push($this->list, array(
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
                }
            }
        }
    };
<?php
    class Token {
        var $version = '';
        var $date = '';
        var $channel = '';
        var $model = '';
        var $filename = '';
        var $url = '';
        var $changelogUrl = '';
        var $md5 = '';
        var $timestamp = '';
        var $incremental = '';
        var $filePath = '';
        var $baseUrl = '';

        public function __construct($fileName, $physicalPath, $baseUrl){
            /*
                $tokens = array(
                    1 => [CM VERSION] (ex. 10.1.x, 10.2, 11, etc.)
                    2 => [DATE OF BUILD] (ex. 20140130)
                    3 => [CHANNEL OF THE BUILD] (ex. RC, RC2, NIGHTLY, etc.)
                    4 => [MODEL] (ex. i9100, i9300, etc.)
                )
            */
            preg_match_all('/cm-([0-9\.]+-)(\d+-)?([a-zA-Z0-9]+-)?([a-zA-Z0-9]+)/', $fileName, $tokens);
            $tokens = $this->removeTrailingDashes($tokens);

            $this->filePath = $physicalPath.'/'.$fileName;
            $this->baseUrl = $baseUrl;
            $this->version = $tokens[1];
            $this->date = $tokens[2];
            $this->channel = $this->getChannel( str_replace(range(0,9), '', $tokens[3]) );
            $this->model = $tokens[4];
            $this->filename = $fileName;
            $this->url = $this->getUrl();
            $this->changelogUrl = $this->getChangelogUrl();
            $this->md5 = $this->getMD5();
            $this->timestamp = filemtime($this->filePath);
            $this->incremental = $this->getIncremental();
        }
        public function isValid($params){
            $ret = false;

            if ( $params['device'] == $this->model ) {
                if ( count($params['channels']) > 0 ) {
                    foreach ( $params['channels'] as $channel ) {
                        var_dump($channel);
                        if ( strtolower($channel) == $this->channel ) $ret = true;
                    }
                }
            }

            return $ret;
        }
        public function getAPILevel(){
            $ret = -1;

            if ( strpos($this->version, '10.1') !== false ) $ret = 17;
            else if ( strpos($this->version, '10.2') !== false ) $ret = 18;
            else if ( strpos($this->version, '11') !== false ) $ret = 19;

            return $ret;
        }
        public function getDelta($targetToken){
            $ret = false;

            // TODO...

            return $ret;
        }
        /* UTILITY */
        private function removeTrailingDashes($token){
            foreach ( $token as $key => $value ) {
                $token[$key] = rtrim( $value[0], '-' );
            }
            return $token;
        }
        private function getChannel($token){
            $ret = 'stable';

            $token = strtolower( $token );
            if ( $token > '' ) {
                $ret = $token;
                if ( $token != 'rc' && $token != 'nightly' ) $ret = 'snapshot';
            }

            return $ret;
        }
        private function getUrl(){
            return 'http://' . $_SERVER['SERVER_NAME'] . $this->baseUrl . '/_builds/' . $this->filename;
        }
        private function getChangelogUrl(){
            return str_replace('.zip', '.txt', $this->url);
        }
        private function getMD5(){
            return md5_file($this->filePath);
        }
        private function getIncremental(){
            $ret = '';

            // Read ZIP file build.prop to get incremental
            $buildProp = file_get_contents('zip://'.$this->filePath.'#system/build.prop');
            $buildProp = explode("\n", $buildProp);
            foreach ($buildProp as $line) {
                if ( strpos($line, 'ro.build.version.incremental') !== false ) {
                    $tmp = explode('=', $line);
                    $ret = $tmp[1];
                }
            }

            return $ret;
        }
    };
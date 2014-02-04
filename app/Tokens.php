<?php
    class Token {
        var $api_level = -1;
        var $channel = '';
        var $model = '';
        var $filename = '';
        var $url = '';
        var $changelogUrl = '';
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
            $this->channel = $this->getChannel( str_replace(range(0,9), '', $tokens[3]) );
            $this->filename = $fileName;
            $this->url = $this->getUrl();
            $this->changelogUrl = $this->getChangelogUrl();
            $this->timestamp = filemtime($this->filePath);
            $this->incremental = $this->getBuildPropValue('ro.build.version.incremental');
            $this->api_level = $this->getBuildPropValue('ro.build.version.sdk');
            $this->model = $this->getBuildPropValue('ro.cm.device');
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
        public function getDelta($targetToken){
            $ret = false;

            $deltaFile = $this->incremental.'-'.$targetToken->incremental.'.zip';
            $deltaFilePath = dirname( $this->filePath ).'/'.$deltaFile;

            if ( $this->commandExists('xdelta3') ) {

                if ( !file_exists($deltaFilePath) ) {
                    exec( 'xdelta3 -e -s '.$this->filePath.' '.$targetToken->filePath.' '.$deltaFilePath );
                }

                $ret = array(
                    'filename' => $deltaFile,
                    'timestamp' => filemtime( $deltaFilePath ),
                    'md5' => $this->getMD5( $deltaFilePath ),
                    'url' => $this->getUrl( $deltaFile ),
                    'api_level' => $this->api_level,
                    'incremental' => $targetToken->incremental
                );
            }

            return $ret;
        }
        public function getMD5($file){
            $ret = '';

            if ( empty($file) ) $file = $this->filePath;
            // Pretty much faster if it is available
            if ( $this->commandExists('md5sum') ) {
                $tmp = explode("  ", exec( 'md5sum '.$file));
                $ret = $tmp[0];
            } else {
                $ret = md5_file($file);
            }

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
        private function getUrl($file){
            if ( empty($file) ) $file = $this->filename;
            return 'http://' . $_SERVER['SERVER_NAME'] . $this->baseUrl . '/_builds/' . $file;
        }
        private function getChangelogUrl(){
            return str_replace('.zip', '.txt', $this->url);
        }
        private function getBuildPropValue($key){
            $ret = '';

            // Read ZIP file build.prop to get incremental
            $buildProp = file_get_contents('zip://'.$this->filePath.'#system/build.prop');
            $buildProp = explode("\n", $buildProp);
            foreach ($buildProp as $line) {
                if ( strpos($line, $key) !== false ) {
                    $tmp = explode('=', $line);
                    $ret = $tmp[1];
                    break;
                }
            }

            return $ret;
        }
        private function commandExists($cmd){
            $returnVal = shell_exec("which $cmd");
            return (empty($returnVal) ? false : true);
        }
    };
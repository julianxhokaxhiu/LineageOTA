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

    class Token {

        var $channel = '';
        var $filename = '';
        var $filePath = '';
        var $device = '';
        var $baseUrl = '';
        var $buildProp = '';
        var $md5file = '';
        var $incremental = '';
        var $api_level = -1;
        var $url = '';
        var $changelogUrl = '';
        var $timestamp = '';

        public function __construct($fileName, $physicalPath, $baseUrl, $device) {
            /*
                $tokens = array(
                    1 => [CM VERSION] (ex. 10.1.x, 10.2, 11, etc.)
                    2 => [DATE OF BUILD] (ex. 20140130)
                    3 => [CHANNEL OF THE BUILD] (ex. RC, RC2, NIGHTLY, etc.)
                    4 => [DEVICE] (ex. i9100, i9300, etc.)
                )
            */
            preg_match_all('/cm-([0-9\.]+-)(\d+-)?([a-zA-Z0-9]+-)?([a-zA-Z0-9]+)/', $fileName, $tokens);
//          preg_match_all('/cm-([a-zA-Z0-9\.]+[0-9]+)-?([0-9]+-[0-9]+-[0-9]+)-?([a-zA-Z0-9]+)-?([a-zA-Z0-9]+)/', $fileName, $tokens);
            $tokens = $this->removeTrailingDashes($tokens);
            $this->channel = $this->getChannel( str_replace(range(0,9), '', $tokens[3]) );
            $this->filename = $fileName;
            $this->filePath = $physicalPath.'/'.$fileName;
            $this->device = $device;
            $this->baseUrl = $baseUrl;

            $mcFile = Utils::mcCacheProps($this->filePath); // ANDROIDMEDA
            $this->buildProp = explode("\n", $mcFile[0] ); // ANDROIDMEDA
            $this->md5file = $mcFile[1]; // ANDROIDMEDA
            $this->incremental = Utils::getBuildPropValue($this->buildProp, 'ro.build.version.incremental');
            $this->api_level = Utils::getBuildPropValue($this->buildProp, 'ro.build.version.sdk');
            $this->url = Utils::getUrl($fileName, $device, false);
            $this->changelogUrl = $this->getChangelogUrl();
            $this->timestamp = filemtime($this->filePath);
        }

        public function isValid($params) {
            if (array_key_exists('channels', $params)) {
                foreach ($params['channels'] as $channel) {
                    if (strtolower($channel) == $this->channel) {
                        return true;
                    }
                }
            }
            return false;
        }

        private function removeTrailingDashes($token){
            foreach ($token as $key => $value) {
                $token[$key] = rtrim($value[0], '-');
            }
            return $token;
        }

        private function getChannel($token) {
            $ret = 'stable';
            $token = strtolower($token);
            if ( $token > '' ) {
                $ret = $token;
                if ($token == 'experimental')
                    $ret = 'snapshot';
            }
            return $ret;
        }

        private function getChangelogUrl() {
            return str_replace('.zip', '.txt', $this->url);
        }
    };

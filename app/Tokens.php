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
        var $device = '';
        var $filename = '';
        var $filePath = '';
        var $url = '';
        var $changelogUrl = '';
        var $timestamp = '';
        var $api_level = -1;
        var $incremental = '';
        var $md5file = '';

        public function __construct($fileName, $physicalPath, $device, $channel) {
            $this->channel = $channel;
            $this->device = $device;
            $this->filename = $fileName;
            $this->filePath = $physicalPath.'/'.$fileName;
            $this->url = Utils::getUrl($fileName, $device, false, $channel);
            $this->changelogUrl = $this->getChangelogUrl($this->url);
            $this->timestamp = filemtime($this->filePath);
            $this->mcCacheProps();
        }

        private function getChangelogUrl($url) {
            return str_replace('.zip', '.txt', $url);
        }

        private function mcCacheProps() {
            $mc = Flight::mc();
            $cache = $mc->get($this->filePath);
            if (!$cache && Memcached::RES_NOTFOUND == $mc->getResultCode()) {
                $buildpropArray = explode("\n", file_get_contents('zip://'.$this->filePath.'#system/build.prop'));
                $device = $this->getBuildPropValue($buildpropArray, 'ro.cm.device');
                $api_level = $this->getBuildPropValue($buildpropArray, 'ro.build.version.sdk');
                $incremental = $this->getBuildPropValue($buildpropArray, 'ro.build.version.incremental');
                $cache = array($device, $api_level, $incremental, Utils::getMD5($this->filePath));
                $mc->set($this->filePath, $cache);
                $mc->set($incremental, array($device, $this->filePath));
            }
            assert($cache[0] == $this->device);
            $this->api_level = $cache[1];
            $this->incremental = $cache[2];
            $this->md5file = $cache[3];
        }

        private function getBuildPropValue($buildProp, $key) {
            foreach ($buildProp as $line) {
                if (!empty($line) && strncmp($line, '#', 1) != 0) {
                    list($k, $v) = explode('=', $line, 2);
                    if ($k == $key) {
                        return $v;
                    }
                }
            }
            return '';
        }
    };

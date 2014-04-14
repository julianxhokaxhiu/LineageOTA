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

    class Utils {

        public static function mcFind($incremental) {
            $mc = Flight::mc();
            list($device, $zip) = $mc->get($incremental);
            if ($zip && !file_exists($zip)) {
                $mc->delete($zip);
                $mc->delete($incremental);
                $zip = NULL;
                $device = NULL;
            }
            return array($device, $zip);
        }

        public static function getUrl($fileName, $device, $isDelta, $channel) {
            $dldir = $isDelta ? '_deltas' : '_builds';
            $channelDir = ($channel == 'stable') ? 'stable/' : '';
            return 'http://' . $_SERVER['SERVER_NAME'] . '/' . $dldir . '/' . $device . '/' . $channelDir . $fileName;
        }

        public static function getMD5($file) {
            $ret = '';
            $md5sumFile = $file . '.md5sum';
            if (file_exists($md5sumFile)) {
                list($ret, $filename) = explode("  ", file_get_contents($md5sumFile));
                if (!empty($ret)) {
                    return $ret;
                }
            }
            if (Utils::commandExists('md5sum')) {
                $tmp = explode("  ", exec('md5sum '.$file));
                $ret = $tmp[0];
            } else {
                $ret = md5_file($file);
            }
            return $ret;
        }

        private static function commandExists($cmd) {
            $returnVal = shell_exec("which $cmd");
            return (empty($returnVal) ? false : true);
        }
    };

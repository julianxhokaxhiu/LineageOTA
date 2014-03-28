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

    class Delta {

        public static function find($source_incremental, $target_incremental) {
            $ret = array();
            $mc = Flight::mc();
            $source_zip = $mc->get($source_incremental);
            $target_zip = $mc->get($target_incremental);
            if ($source_zip && !file_exists($source_zip)) {
                $mc->delete($source_zip);
                $mc->delete($source_incremental);
                $source_zip = FALSE;
            }
            if ($target_zip && !file_exists($target_zip)) {
                $mc->delete($target_zip);
                $mc->delete($target_incremental);
                $target_zip = FALSE;
            }
            if (empty($source_zip) || empty($target_zip)) {
               return $ret;
            }
            $sourceArray = $mc->get($source_zip);
            $targetArray = $mc->get($target_zip);
            if (empty($sourceArray) || empty($targetArray)) {
                return $ret;
            }
            $sourceDevice = $sourceArray[0];
            $targetDevice = $targetArray[0];
            if ($sourceDevice != $targetDevice) {
                return $ret;
            }
            $deltaFile = 'incremental-'.$source_incremental.'-'.$target_incremental.'.zip';
            $deltaFullPath = realpath('./_deltas/'.$targetDevice) . '/' . $deltaFile;
            if (!file_exists($deltaFullPath)) {
                $mc->delete($deltaFullPath);
                return $ret;
            }
            $ret = $mc->get($deltaFullPath);
            if (!$ret && Memcached::RES_NOTFOUND == $mc->getResultCode()) {
                $ret = array(
                   'date_created_unix' => filemtime($deltaFullPath),
                   'filename' => $deltaFile,
                   'download_url' => Utils::getUrl($deltaFile, $targetDevice, true, ''),
                   'md5sum' => Utils::getMD5($deltaFullPath),
                   'incremental' => $target_incremental
                );
                $mc->set($deltaFullPath, $ret);
            }
            return $ret;
        }
    };

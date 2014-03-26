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

    require 'lib/flight/Flight.php';

    require 'app/Tokens.php';
    require 'app/TokensCollection.php';
    require 'app/Utils.php';

    // Map for shared memcached:
    // 1) [incrementalno] = string(fullpathnameofota.zip)
    // 2) [fullpathnameofota.zip] = array(buildprop + md5sum)
    // 3) [incremental-A-B.zip] = array(deltainfo + md5sum)
    Flight::register('mc', 'Memcached', array(), function($mc) {
        $mc->addServer('localhost', 11211);
    });

    // Root dir
    Flight::route('/', function(){
        Flight::redirect('/_builds');
    });

    // All builds
    Flight::route('/api', function(){
        $ret = array(
            'id' => null,
            'result' => array(),
            'error' => null
        );

        $req = Flight::request();
        $postJson = json_decode($req->body, true);
        if ($postJson != NULL &&
           array_key_exists('params', $postJson) &&
           array_key_exists('device', $postJson['params'])) {
           $device = $postJson['params']['device'];
           $devicePath = realpath('./_builds/'.$device);
           if (file_exists($devicePath)) {
               $after = 0;
               if (array_key_exists('source_incremental', $postJson['params'])) {
                   $source_incremental = $postJson['params']['source_incremental'];
                   if (!empty($source_incremental)) {
                       $mc = Flight::mc();
                       $source_zip = $mc->get($source_incremental);
                       if ($source_zip) {
                           if (file_exists($source_zip)) {
                               $after = filemtime($source_zip);
                           } else {
                               $mc->delete($source_zip);
                               $mc->delete($source_incremental);
                           }
                       }
                   }
               }

               $tokens = new TokenCollection($postJson, $devicePath, $req->base, $device, $after);
               $ret['result'] = $tokens->getUpdateList();
           }
        }

        Flight::json($ret);
    });

    // Deltas
    Flight::route('/api/v1/build/get_delta', function(){
        $ret = array();
        $req = Flight::request();
        $postJson = json_decode($req->body, true);
        if ($postJson != NULL &&
            array_key_exists('source_incremental', $postJson) &&
            array_key_exists('target_incremental', $postJson)) {
            $source_incremental = $postJson['source_incremental'];
            $target_incremental = $postJson['target_incremental'];
            if (!empty($source_incremental) && !empty($target_incremental) &&
                $source_incremental != $target_incremental) {
                $mc = Flight::mc();
                $source_zip = $mc->get($source_incremental);
                $target_zip = $mc->get($target_incremental);
                if ($source_zip && !file_exists($source_zip)) {
                    $mc->delete($source_zip);
                    $mc->delete($source_incremental);
                    $source_zip = NULL;
                }
                if ($target_zip && !file_exists($target_zip)) {
                    $mc->delete($target_zip);
                    $mc->delete($target_incremental);
                    $target_zip = NULL;
                }
                if ($source_zip && $target_zip) {
                    $sourceBuildProp = $mc->get($source_zip);
                    $targetBuildProp = $mc->get($target_zip);
                    if ($sourceBuildProp && $targetBuildProp) {
                        $sourceDevice = Utils::getBuildPropValue(explode("\n", $sourceBuildProp[0]), 'ro.cm.device');
                        $targetDevice = Utils::getBuildPropValue(explode("\n", $targetBuildProp[0]), 'ro.cm.device');
                        $targetApi = Utils::getBuildPropValue(explode("\n", $targetBuildProp[0]), 'ro.build.version.sdk');
                        if ($sourceDevice == $targetDevice) {
                            $ret = Utils::getDeltaIncremental($targetDevice, $source_incremental, $target_incremental, $targetApi);
                        }
                    }
                }
            }
        }

        if (empty($ret)) {
            $ret['errors'] = array(
                'message' => 'Unable to find delta'
            );
        }

        Flight::json($ret);
    });

    Flight::map('notFound', function(){
        // Display custom 404 page
        echo 'Sorry, 404!';
    });

    Flight::start();
?>

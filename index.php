<?php
    /*
        The MIT License (MIT)

        Copyright (c) 2020 Julian Xhokaxhiu

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

    require 'vendor/autoload.php';

    use \JX\CmOta\CmOta;

    if ( isset($_SERVER['HTTP_FORWARDED']) ) {
      $fwd_ar = explode(';', $_SERVER['HTTP_FORWARDED']);
      for ( $i = 0; $i < count($fwd_ar); $i++ ) {
        $kv = explode('=', $fwd_ar[$i]);
        if ( count($kv) > 1 ) {
          $forwarded[strtoupper($kv[0])] = $kv[1];
        }
      }
      if ( array_key_exists('HOST', $forwarded) ) {
        $_SERVER['HTTP_HOST'] = $forwarded['HOST'];
      }
      if ( array_key_exists('PROTO', $forwarded) && strtoupper($forwarded['PROTO']) === 'HTTPS') {
        $_SERVER['HTTPS'] = 'on';
      }
    } else {
      if ( isset($_SERVER['HTTP_X_FORWARDED_HOST']) ) {
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
      }
      if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtoupper($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'HTTPS' ) {
        $_SERVER['HTTPS'] = 'on';
      }
    }

    if( isset($_SERVER['HTTPS']) )
        $protocol = 'https://';
    else
        $protocol = 'http://';

    if ( isset($_ENV['LINEAGEOTA_BASE_PATH']) )
        $base_path = $_ENV['LINEAGEOTA_BASE_PATH'];
    else
        $base_path = $protocol.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']);

    $app = new CmOta();
    $app
    ->setConfig( 'basePath', $base_path )
    ->run();

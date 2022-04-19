<?php
    /*
        The MIT License (MIT)

        Copyright (c) 2022 Julian Xhokaxhiu
        Copyright (c) 2022 Matthias Leitl

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

    namespace JX\CmOta\Helpers;

    class CurlRequest {

        private $url = '';
        private $status = 0;
        private $response = '';
        private $header = array();

        /**
         * Constructor of the CurlRequest class.
         */
        public function __construct( $url ) {
            $this->url = $url;
            $this->addHeader( 'user-agent: curl/7.68.0' ); // Make sure a user-agent is being sent
    	}

        /**
         * Return the status code of the request
         * @param string $header The additional header to be sent
         */
    	public function addHeader( $header ) {
            array_push( $this->header, $header );
    	}

        /**
         * Executes the request and returns it's success
         * @return bool The success of the request
         */
        public function executeRequest() {
            $request = curl_init( $this->url );

            curl_setopt( $request, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $request, CURLOPT_HTTPHEADER, $this->header );

            $this->response = curl_exec( $request );
            $this->status = curl_getinfo( $request, CURLINFO_RESPONSE_CODE );
            curl_close( $request );

            if( $this->status == 200 ) return true;

            return false;
    	}

        /* Getters */

        /**
         * Return the status code of the request
         * @return int The status code
         */
        public function getStatus() {
            return $this->status;
        }

        /**
         * Return the response of the request
         * @return string The response
         */
        public function getResponse() {
            return $this->response;
        }
    }

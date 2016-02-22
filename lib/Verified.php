<?php

    class Verified
    {
        private $api_key;
        private $api_secret;
        private $config = array();

        private $_metadata;
        private $_links;
        private $_currentError;
        private $_customHeaders = array();

        /**
         * Constructor
         * Extends the default config array and sets the values of api_key and api_secret
         *
         * @param array $config
         */
        public function __construct($config = array())
        {
            //declare default options
            $defaults = array(
                "api_endpoint"    => 'http://verifiedapi.org/',
                "api_version"     => '1',
                "response_type"   => 'json'
            );
            foreach ($config as $key => $value) {
                //pluck the api key out of the config array
                if ($key == "api_key") {
                    $this->api_key = $value;
                    continue;
                }
                //pluck the api secret out of the config array
                if ($key == "api_secret") {
                    $this->api_secret = $value;
                    continue;
                }
                //overwrite the default value of config item if it exists
                if (array_key_exists($key, $defaults)) {
                    $defaults[$key] = $value;
                }
            }

            //store the config back into the class property
            $this->config = $defaults;
        }

        /**
         * Setter for $api_key
         * returns the class itself so calls are chainable
         *
         * @param string $api_key
         * @return $this
         */
        public function setKey($api_key)
        {
            $this->api_key = $api_key;

            return $this;
        }

        /**
         * Setter for $api_secret
         * returns the class itself so calls are chainable
         *
         * @param string $api_secret
         * @return $this
         */
        public function setSecret($api_secret)
        {
            $this->api_secret = $api_secret;

            return $this;
        }

        /**
         * Setter for config items
         * returns the class itself so calls are chainable
         *
         * @param string $key
         * @param string $val
         * @return $this
         */
        public function setConfig($key, $val)
        {
            if (array_key_exists($key, $this->config)) {
                $this->config[$key] = $val;
            }

            return $this;
        }

        /**
         * Getter for config items
         *
         * @param string $key
         * @return mixed
         */
        public function getConfig($key)
        {
            if (array_key_exists($key, $this->config)) {
                return $this->config[$key];
            }

            return null;
        }

        /**
         * Add custom headers to be sent in the request
         *
         * @param string $key
         * @param string $val
         * @return $this
         */
        public function addCustomHeader($key, $val)
        {
            if (!empty($key) && !empty($val)) {
                $keyParts = preg_split("/[-_]/", $key);
                $key      = implode(' ', $keyParts);
                $key      = str_replace(' ', '-', ucwords(strtolower(trim($key))));
                $reserved = array('Request-Time', 'Api-Key', 'Signature', 'Accept', 'Content-Type');
                if (!in_array($key, $reserved)) {
                    $this->_customHeaders[$key] = $val;
                }
            }

            return $this;
        }

        /**
         * Getter for all custom headers that have been added by the user
         *
         * @return array
         */
        public function getCustomHeaders()
        {
            return $this->_customHeaders;
        }

        /**
         * Deletes all customer headers
         *
         * @return $this
         */
        public function deleteCustomHeaders()
        {
            $this->_customHeaders = array();

            return $this;
        }

        /**
         * Getter for current error
         *
         * @return mixed
         */
        public function getError()
        {
            return $this->_currentError;
        }

        /**
         * Getter for reponse metadata
         *
         * @return mixed
         */
        public function getMetadata()
        {
            return $this->_metadata;
        }

        /**
         * Getter for response links
         *
         * @return mixed
         */
        public function getLinks()
        {
            return $this->_links;
        }

        /**
         * Magic method to catch non-existent methods and channel them into useful method calls
         * via the API
         *
         * @param string $method
         * @param array $args
         * @return mixed
         */
        public function __call($method, $args)
        {
            return $this->call($method, $args);
        }

        /**
         * Workhorse method that processes the methods intercepted by the __call magic method
         *
         * @param string $method
         * @param array $args
         * @return mixed
         */
        protected function call($method, $args)
        {
            // if the method exists, short out here
            if (method_exists($this, $method)) {
                return call_user_func_array($this->$method, $args);
            }

            $resource = $this->parseMethodCall($method);
            if ($resource !== false) {

                $data = array();
                $key = '';
                if (in_array($resource["method"], array('GET', 'DELETE', 'PUT'))) {
                    //for these methods the first argument should be the key
                    if (isset($args[0])) {
                        if (is_array($args[0])) {
                            //unless it is a getAll kind of method that has no key
                            $data = (array) $args[0];
                        } else {
                            $key = trim((string) $args[0]);
                        }
                    }
                } else {
                    //for POST, the first argument should be an array
                    if (isset($args[0])) {
                        $data = (array) $args[0];
                    }
                }

                if ($resource["method"] == 'PUT' || $resource["method"] == 'GET') {
                    //for PUT and GET the second argument should be an array
                    if (isset($args[1])) {
                        $data = (array) $args[1];
                    }
                }

                //process key
                if ($key != "") {
                    $resource["endpoint"] = $resource["endpoint"] . $key ."/";
                }
                //process subresource
                if (isset($resource["sub_resource"])) {
                    $resource["endpoint"] = $resource["endpoint"] . $resource["sub_resource"];
                }

                $resource["data"] = $data;

                return $this->callResource($resource);

            } else {
                throw new Exception("Invalid method signature, method does not exist");
            }
        }

        //=============================
        // Private Methods
        //=============================

        /**
         * Handles the actual request to the API
         *
         * @param string $resource
         * @return mixed
         */
        private function callResource($resource)
        {
            $time = date("c");

            $response_type = 'application/json';
            switch (strtolower($this->config['response_type'])) {
                case 'xml':
                $response_type = 'application/xml';
                break;
                case 'csv':
                $response_type = 'application/csv';
                break;
            }

            if ($resource['method'] == "GET") {
                $this->parseQ($resource);
            }

            $headers = array(
                'Request-Time' => $time,
                'Api-Key'      => $this->api_key,
                'Signature'    => $this->getSignature($resource, $time),
                'Accept'       => $response_type
            );

            if (empty($resource['data'])) {
                $resource['data'] = null;
            }

            if ($resource['method'] == "PUT" || $resource['method'] == "POST") {
                $headers['Content-Type'] = 'application/json';
                $resource['data'] = json_encode($resource['data']);
            }

            $headers = $headers + $this->_customHeaders;

            if (class_exists ('\Unirest')) {
                //$response = Unirest::{strtolower($resource['method'])}($resource["endpoint"], $headers, $resource['data']);
                $response = call_user_func_array(
                    array('Unirest', strtolower($resource['method'])),
                    array($resource["endpoint"], $headers, $resource['data']));
            } else {
                //$response = \Unirest\Request::{strtolower($resource['method'])}($resource["endpoint"], $headers, $resource['data']);
                $response = call_user_func_array(
                    '\Unirest\Request::'.strtolower($resource['method']),
                    array($resource["endpoint"], $headers, $resource['data']));
            }

            $responseHeaders = $response->headers;
            if ($response_type == 'application/json') {
                $this->_metadata = isset($response->body->_meta) ? $this->toArray($response->body->_meta) : false;
                $this->_links = isset($this->_metadata['links']) ? $this->_metadata['links'] : false;
                $data = $this->toArray($response->body->records);
            } else {
                $this->_metadata = false;
                $this->_links = false;
                $data = $response->body;
            }

            if (!isset($responseHeaders['Status']) ||
            $responseHeaders['Status'] == '200 OK' ||
            $responseHeaders['Status'] == '201 Created') {
                $this->_currentError = false;

                return $data;
            } else {
                $this->_currentError = $data;

                return false;
            }
        }

        /**
         * Signs requests as required by the API
         *
         * @param string $verb
         * @param string $endpoint
         * @param string $request_time
         * @return string
         */
        private function getSignature($resource, $request_time)
        {
            $url = $resource["endpoint"];
            // for GET requests we have to append the args to the url before generating a signature
            if ($resource['method'] == 'GET') {
                if (is_array($resource["data"])) {
                    if (strpos($url,'?') !== false) {
                        $url .= "&";
                    } else {
                        $url .= "?";
                    }
                    foreach ($resource["data"] as $parameter => $val) {
                        $url .= $parameter . "=" . $val . "&";
                    }
                    $url = substr($url, 0, strlen($url) - 1);
                }
            }

            $token = preg_replace("/\s+/", "", $request_time) .
                     strtoupper($resource["method"]) .
                     str_replace($this->config['api_endpoint'] , "", $url);

            return hash_hmac("sha256", $token, $this->api_secret);
        }

        /**
         * Parses the q parameter if its sent as an array
         * and modifies that paramter in-place
         *
         * @param array $resource
         * @return null
         */
        private function parseQ(&$resource)
        {
            if (is_array($resource['data'])) {
                foreach ($resource['data'] as $key => $value) {
                    if ($key == 'q') {
                        if (is_array($value)) {
                            $q_params = array();
                            foreach ($value as $k => $v) {
                                $q_params[] = $k . ':' . $v;
                            }
                            $q_params = '(' . implode(',', $q_params) . ')';
                            $resource['data']['q'] = $q_params;
                        }
                    }
                }
            }
        }

        /**
         * Method call parser that converts a method call into a REST resource endpoint
         *
         * @param string $method
         * @return array
         */
        private function parseMethodCall($method)
        {
            $ret = false;
            $verbs = array(
                "get"    => "GET",
                "add"    => "POST",
                "edit"   => "PUT",
                "delete" => "DELETE"
            );
            //split the camelcase
            $words = preg_split("/(?<=[a-z])(?=[A-Z])/x", $method);
            $mainResource = "";

            //first word is verb
            if (isset($verbs[strtolower($words[0])])) {
                $ret["method"] = $verbs[strtolower($words[0])];
                //move to next word
                array_shift($words);
                //this word should be the main resource
                if (isset($words[0])) {
                    $mainResource = strtolower($words[0]);
                    $ret["endpoint"] = $this->config['api_endpoint'].
                        'v' . $this->config['api_version'] . "/" .
                        $mainResource . "/";
                }
                //move to next word
                array_shift($words);
                $subResource = implode("", $words);
                if ($subResource != "") {
                    if ($mainResource == "report") {
                        //if the main resource is 'report', id comes after the sub-resource
                        $ret["endpoint"] .= lcfirst($subResource) . "/";
                    } else {
                        $ret["sub_resource"] = lcfirst($subResource);
                    }
                }
            }

            return $ret;
        }

        /**
         * converts mixed stdClass and array objects to plain arrays
         *
         * @param string $data
         * @return void
         */
        private function toArray($data)
        {
            return json_decode(json_encode($data),true);
        }

    }

**Please note:** This repository has been deprecated in favour of [wcealliance/php-sdk](https://github.com/wcealliance/php-sdk)

---------------------------------

Verified API SDK for PHP
================

[![Build Status](https://travis-ci.org/verified/verified-sdk-php.svg?branch=master)](https://travis-ci.org/verified/verified-sdk-php)

Communicate with the Verified REST API using PHP

### Install with Composer
If you're using [Composer](https://github.com/composer/composer) to manage
dependencies, you can add the Verified SDK with it.

```javascript
{
  "require" : {
    "verified/verified-sdk-php": "dev-master"
  },
  "minimum-stability": "dev"
}
```

### Install source from GitHub
The Verified SDK requires [Unirest](https://github.com/Mashape/unirest-php) as a dependency.
It also requires PHP `v5.3+` and `cURL` extensions for PHP. Download the PHP library from Github, and require in your script like so:

To install the source code:

```bash
$ git clone git@github.com:verified/verified-sdk-php.git
```

And include it in your scripts:

```php
// first include Unirest
require_once '/path/to/unirest-php/lib/Unirest.php';
// then include this
require_once '/path/to/verified-sdk-php/lib/Verified.php';
```

##Usage
The main requirements for running this SDK are `api_key` and `api_secret`. Please make sure you have them before you proceed any further.

The class can be configured in multiple ways,
by sending in a configuration array during class instantiation:
```php
// create a new instance
$config_array = array(
  "api_endpoint"   => "http://verifiedapi.org",
  "api_key"        => 'YOUR_API_KEY',
  "api_secret"     => 'YOUR_API_SECRET',
  "api_version"    => '1'
);
$verified = new Verified($config_array);
```
Or by using a more OOP approach:
```php
$verified = new Verified();
$verified->setKey('YOUR_API_KEY')
  ->setSecret('YOUR_API_SECRET')
  ->setConfig('api_version', '1');
```

Configuration parameters other than `api_key` and `api_secret` have setter and getter methods:
```php
// set a config param
$verified->setConfig('api_version', '1');
// get a config param
$version = $verified->getConfig('api_version');
```
 **Please Note:** By default, the `api_endpoint` parameter is set to the live URL, to use sandbox mode, please use the sandbox url `http://sandbox.verifiedapi.org`.

### Making REST calls to API resources
Please see the [Verified API docs](http://docs.verifiedapi.org/) for a list of available API resource endpoints. The class uses `__call()` magic method to work out which resource you are calling.

For example:
```php
// make a GET request to /user/user@email.com
$user = $verified->getUser('user@email.com');
// make a POST request to /user
$user = $verified->addUser(array('post_params'));
// make a PUT request to /user/user@email.com
$verified->editUser('user@email.com', array('post_params'));
// make a DELETE request to /user/user@email.com
$verified->deleteUser('user@email.com');
```

Calling sub-resources follow a similar `camelCased` pattern, for example:
```php
// make a GET request to /user/user@email.com/trainingProfile/
$training_profile = $verified->getUserTrainingProfile('user@email.com');
```
In simple terms:
- `getXXX()` maps to `GET` requests
- `addXXX()` maps to `POST` requests
- `editXXX()` maps to `PUT` requests
- `deleteXXX()` maps to `DELETE` requests

### Output data &amp; Error handling
All data from the SDK are in plain PHP arrays. If a method call fails, the output is `false`.

If you encounter the result of a query returning `false`, the error that occured can be obtained from the `getError()` method.

A very simple error resilient code snippet follows:
```php
$user = $verified->getUser('user@email.com');
if ($user == false) {
  $error = $verified->getError();
  if($error){
    throw new Exception($error['errorCode'] . $error['userMessage']);
  }
} else {
  //loop through the returned data
  //and do something with it
}
```

When calling `PUT` and `POST` endpoints (`addXXXX()` and `editXXXX()`), if the payload contains invalid data, data validation messages appear in the form of an array inside the error object:
```php
array(
  'errorCode'   => '400',
  'userMessage' => 'Bad Request',
  'devMessage'  => array(
      'email'   => 'Invalid email address'
  )
)
```

#### Response Metadata
In addition to the special getter `getError()`, there is also another getter method for metadata.
`getMetadata()` returns an array containing all the metadata that was sent with the response.

Metadata usually contains the response status, offset/limit values and the total number of records returned.
It also contains HATEOAS links which can be leveraged for paginating long lists.

To obtain HATEOAS links only, another special getter `getLinks()` can be used. It returns just the HATEOAS links from a response.

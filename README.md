# X-UI API PHP

This library has been developed to interface with the X-UI API using PHP 8.

### Usage

First, Clone the repository in your local and cd to cloned directory.

```shell
git clone https://github.com/sadegh19b/XUI-Api-PHP
```

Now, install the dependencies via composer.

```shell
composer install
```

Then, You must `require` composer autoload to you can work with library.

### Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

$api = new \XUI\Api('https://domain.com', 'username', 'password');

// After request to add inbound in return you can get added inbound response that delivered from x-ui api
$response = $api->addInbound('remark', 'vmess', 2000);

// After successful x-ui api response, you can get inbound data in array
echo $response['id'];
echo $response['remark'];

// You can get response status is success that delivered from x-ui api
$api->isResponseSuccess();

// You can get response message that delivered from x-ui api
$api->getResponseMessage();

// You can build x-ui config by `XUI\ConfigBuilder`
$config = (new \XUI\ConfigBuilder('vless'))->setSecurity('tls', 'domain.com');

// You can add inbound by config
$response = $api->addInboundByConfig($config);

// and more ...
```

### Docs

I will add library documents soon.
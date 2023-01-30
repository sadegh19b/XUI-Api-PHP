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

$xui_api = new \XUI\Api('https://domain.com', 'username', 'password');
$config = (new \XUI\ConfigBuilder)->setSecurity('tls', 'domain.com');

$request = $xui_api->addInbound('remark', 'vmess', 2000);
$request = $xui_api->addInboundByConfig($config);

// and more ...
```

### Docs

I will add library documents soon.
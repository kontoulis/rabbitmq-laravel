# RabbitMQLaravel

[![Latest Stable Version](https://poser.pugx.org/kontoulis/rabbitmq-laravel/v/stable)](https://packagist.org/packages/kontoulis/rabbitmq-laravel)
[![Latest Unstable Version](https://poser.pugx.org/kontoulis/rabbit-manager/v/unstable)](https://packagist.org/packages/kontoulis/rabbitmq-laravel)
[![License](https://poser.pugx.org/kontoulis/rabbit-manager/license)](https://packagist.org/packages/kontoulis/rabbitmq-laravel)
## Installation

Via Composer

``` bash
$ composer require kontoulis/rabbitmq-laravel
```

Add the Service Provider to config/app.php

``` php
Kontoulis\RabbitMQLaravel\RabbitMQLaravelServiceProvider::class,
```
Add the RabbitMQ facade to config/app.php

``` php
'RabbitMQ' => Kontoulis\RabbitMQLaravel\Facades\RabbitMQ::class,
```

Publish the configuration file and edit it if needed in config/rabbitmq-laravel.php
``` bash
$ php artisan vendor:publish
```

## Usage

You can use the RabbitMQ facade to do anything as seen in https://github.com/php-amqplib/php-amqplib/blob/master/PhpAmqpLib/Channel/AMQPChannel.php . Basically, the RabbitMQ facade is AMQPChannel.

- Publish Message to exchange

```
As a Facade
``` php
use PhpAmqpLib\Message\AMQPMessage;

$msg = new AMQPMessage('mypayload';         
RabbitMQ::basic_publish($msg, '', 'my-queue'));
```

## License

The MIT License (MIT). Please see [LICENCE.md](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/league/:package_name.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/thephpleague/:package_name/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/thephpleague/:package_name.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/thephpleague/:package_name.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/league/:package_name.svg?style=flat-square

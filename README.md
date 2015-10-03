# :package_name

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]


## Install

Via Composer

``` bash
$ composer require kontoulis/rabbitmq-laravel
```

## Usage
- Publish Message to queue

As a Dependency injection
``` php
function __construct(RabbitMQ $rabbitMQ){
$this->rabbitMQ = $rabbitMQ;
// Overide the default queueName
$this->rabbitMQ->setQueue("MyNewQueueName");
$this->rabbitMQ->sendMessage(json_encode($anArray));

```
As a Facade
``` php
// Overide the default queueName
RabbitMQ::setQueue("MyNewQueueName");
RabbitMQ->sendMessage(json_encode($anArray));

```
- Listen to queue (not recommended to listen on an HTTP request. Check the standalone [rabbit-manager](https://github.com/kontoulis/rabbit-manager)

You need to extend the Kontoulis\RabbitMQLaravel\Handlers\Handler; or use the DefaultHandler just to echo the message

``` php
// If you don't provide a handler and/or a queueName, the defaults will be used
RabbitMQ::listenToQueue("Path\\To\\My\\Handler", "queueName");

```


## License

The MIT License (MIT). Please see [LICENCE.md](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/league/:package_name.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/thephpleague/:package_name/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/thephpleague/:package_name.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/thephpleague/:package_name.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/league/:package_name.svg?style=flat-square

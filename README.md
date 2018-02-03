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
- Routing Key / Queue Name

The default routing key can be set in config file, or env("APP_NAME")."_queue") will be used
Also most methods take argument `$routingKey` which can override the default
```php
RabbitMQ::setRoutingKey("myRoutingKey/queueName");
```

- Exchange

If you don't set an exchange the default `''` exchange will be used.
If you set one, make sure the bindings are set in RabbitMQ system.
if you are not familiar with exchanges you will probably do not need to set that.
```php
RabbitMQ::setExchange("myExchange")'
```


You can use the RabbitMQ facade to do anything as seen in https://github.com/php-amqplib/php-amqplib/blob/master/PhpAmqpLib/Channel/AMQPChannel.php . 
Basically, the RabbitMQ facade uses the Broker class which is an extension of AMQPChannel.

- Publishing

``` php
// Single message
 
$msg1 = [
    "key1" => "value1", 
    "key2" => "value2"
    ];         
RabbitMQ::publishMesssage($msg1);

// OR
RabbitMQ::publishMessage($msg, "myRoutingKey");

// Batch publishing

$messages = [
    [ "messsage_1_key1" => "value1", 
      "messsage_1_key2" => "value2"
    ],
    [
    "messsage_2_key1" => "value1", 
    "messsage_2_key2" => "value2"
    ]
];
RabbitMQ::publishBatch($messages);
// OR
RabbitMQ::publishBatch($messages, "myRoutingKey");
```

- Consuming the Queue

In order to consume the queue, you could either use the AmqpChannel through the Facade,
or use a better to manage approach with QueueHandlers.
A QueueHandler should extend the Kontoulis\RabbitMQLaravel\Handlers\Handler class and the built-in ListenToQueue method accepts an array of handlers to process the queue message.
If more than one handler exists in array, there are some Handler return values that will use the follow up QueueHandlers in cases of failure of the previous.
There is also a Kontoulis\RabbitMQLaravel\Handlers\Handler\DefaultHandler class as example and/or debug handler.

```php
namespace Kontoulis\RabbitMQLaravel\Handlers;

use Kontoulis\RabbitMQLaravel\Message\Message;

/**
 * Class DefaultHandler
 * @package Kontoulis\RabbitMQLaravel\Handlers
 */
class DefaultHandler extends Handler{

    /**
     * Tries to process the incoming message.
     * @param Message $msg
     * @return int One of the possible return values defined as Handler
     * constants.
     */
    /**
     * Tries to process the incoming message.
     * @param Message $msg
     * @return int One of the possible return values defined as Handler
     * constants.
     */
    public function process(Message $msg)
    {
        return $this->handleSuccess($msg);

    }

    /**
     * @param $msg
     * @return int
     */
     protected function handleSuccess($msg)
       {
           var_dump($msg);
           /**
            * For more Handler return values see the parent class
            */
           return Handler::RV_SUCCEED_STOP;
       }
}
```

In order to Listen to the Queue you have to pass an array of Handlers to the method
```php
$handlers = ["\\App\\QueueHandlers\\MyHandler"];
\RabbitMQ::listenToQueue($handlers);
```

## License

The MIT License (MIT). Please see [LICENCE.md](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/league/:package_name.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/thephpleague/:package_name/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/thephpleague/:package_name.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/thephpleague/:package_name.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/league/:package_name.svg?style=flat-square

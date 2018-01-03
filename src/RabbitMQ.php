<?php

namespace Kontoulis\RabbitMQLaravel;
use Kontoulis\RabbitMQLaravel\Broker\Broker;

/**
 * Class RabbitMQ
 * @package Kontoulis\RabbitMQLaravel
 */
class RabbitMQ extends Broker
{
	/**
	 * Create a new Skeleton Instance
	 * @param $config
	 */
    public function __construct($config)
    {
        parent::__construct($config);
    }

}

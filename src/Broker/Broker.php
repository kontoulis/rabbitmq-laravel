<?php
/**
 * Created by PhpStorm.
 * User: verwilst
 * Date: 2/05/17
 * Time: 21:49
 */

namespace Kontoulis\RabbitMQLaravel\Broker;

use Kontoulis\RabbitMQLaravel\Message\Message;
use Kontoulis\RabbitMQLaravel\Handlers\Handler;
use Kontoulis\RabbitMQLaravel\Exception\BrokerException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Channel\AMQPChannel;

class Broker extends AMQPChannel
{
    /**
     * @param $config
     * @throws Kontoulis\RabbitMQLaravel\Exception\BrokerException
     */
    function __construct($config)
    {

        $this->host = $config['amqp_host'];
        $this->port = $config['amqp_port'];
        $this->user = $config['amqp_user'];
        $this->password = $config['amqp_pass'];
        $this->vhost = $config['amqp_vhost'];

        try {

            /* Open RabbitMQ connection */

            $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
            parent::__construct($connection);

        } catch (AMQPRuntimeException $ex) {

            throw new BrokerException(

                'Fatal error while initializing AMQP connection: '

                . $ex->getMessage(),

                $ex->getCode()

            );

        }
    }
}
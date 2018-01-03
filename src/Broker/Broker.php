<?php

namespace Kontoulis\RabbitMQLaravel\Broker;

use Kontoulis\RabbitMQLaravel\Message\Message;
use Kontoulis\RabbitMQLaravel\Handlers\Handler;
use Kontoulis\RabbitMQLaravel\Exception\BrokerException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class Broker
 * @package Kontoulis\RabbitMQLaravel\Broker
 */
class Broker extends AMQPChannel
{
    /**
     * @var string
     */
    protected $exchange = '';
    /**
     * @var
     */
    protected $host;
    /**
     * @var
     */
    protected $port;
    /**
     * @var
     */
    protected $user;
    /**
     * @var
     */
    protected $password;
    /**
     * @var
     */
    protected $vhost;
    /**
     * @var
     */
    protected $defaultQueue;

    /**
     * If set, the consumer will exit after the timeout is passed (e.g. if the queue is empty after the timeout passes)
     * @var
     */
    protected $consumeTimeout = 0 ;

    /**
     * Broker constructor.
     * @param \PhpAmqpLib\Connection\AbstractConnection $config
     */
    function __construct($config)
    {
        $this->host = $config['amqp_host'];
        $this->port = $config['amqp_port'];
        $this->user = $config['amqp_user'];
        $this->password = $config['amqp_pass'];
        $this->vhost = $config['amqp_vhost'];
        $this->defaultQueue = (!empty($config["amqp_default_queue"]) ? $config["amqp_default_queue"] : env("APP_NAME")."_queue");

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

    /**
     * @param int $timeout
     */
    public function setConsumeTimeout($timeout = 0){
        $this->consumeTimeout = $timeout;
    }

    /**
     * @param $exchange
     * @param string $type
     */
    public function setExchange($exchange, $type = "direct")
    {
        $this->exchange = $exchange;
        $this->exchange_declare($exchange, $type, false, true, false);
    }

    /**
     * @param $message
     * @param null $routingKey
     */
    public function publishMessage($message, $routingKey = null)
    {
        $this->queueDeclareBind($routingKey);
        $msg = new Message($message, $routingKey);
        // Create the message
        $amqpMessage = $msg->getAMQPMessage();
        $this->basic_publish($amqpMessage, $this->exchange, $routingKey);
    }

    /**
     * @param $messages
     * @param null $routingKey
     */
    public function publishBatch($messages, $routingKey = null)
    {
        $this->queueDeclareBind($routingKey);
        // Create the messages
        foreach ($messages as $message) {
            $this->batch_basic_publish(
                (new Message($message))->getAMQPMessage(), $this->exchange, $routingKey
            );
        }
        $this->publish_batch();
    }

    /**
     * @param callable $callback
     * @param null $routingKey
     * @param array $options
     * @return bool
     */
    public function basicConsume(callable $callback, $routingKey = null, $options = [])
    {
        $this->queueDeclareBind($routingKey);

        /* Start consuming */
        $this->basic_qos(
            (isset($options["prefetch_size"]) ? $options["prefetch_size"] : null),
            (isset($options["prefetch_count"]) ? $options["prefetch_count"] : 1),
            (isset($options["a_global"]) ? $options["a_global"] : null)
        );

        $this->basic_consume(
            $routingKey,
            (isset($options["consumer_tag"]) ? $options["consumer_tag"] : ''),
            (isset($options["no_local"]) ? (bool)$options["no_local"] : false),
            (isset($options["no_ack"]) ? (bool)$options["no_ack"] : false),
            (isset($options["exclusive"]) ? (bool)$options["exclusive"] : false),
            (isset($options["no_wait"]) ? (bool)$options["no_wait"] : false),
            function (AMQPMessage $amqpMsg) use($callback) {
                $msg = Message::fromAMQPMessage($amqpMsg);
                return  call_user_func($callback, $msg);;
            }
        );

        return $this->waitConsume($options);
    }

    /**
     * Starts to listen to a queue for incoming messages.
     * @param array $handlers Array of handler class instances
     * @param null $routingKey
     * @param array $options
     * @return bool
     * @internal param string $queueName The AMQP queue
     */
    public function listenToQueue(array $handlers , $routingKey = null, $options =[] )
    {
        /* Look for handlers */
        $handlersMap = array();
        foreach ($handlers as $handlerClassPath) {
            if (!class_exists($handlerClassPath)) {
                $handlerClassPath = "Kontoulis\\RabbitMQLaravel\\Handlers\\DefaultHandler";
                if (!class_exists($handlerClassPath)) {
                    throw new BrokerException(
                        "Class $handlerClassPath was not found!"
                    );
                }
            }
            $handlerOb = new $handlerClassPath();
            $classPathParts = explode("\\", $handlerClassPath);
            $handlersMap[$classPathParts[count($classPathParts) - 1]] = $handlerOb;
        }

        $this->queueDeclareBind($routingKey);
        /* Start consuming */
        $this->basic_qos(
            (isset($options["prefetch_size"]) ? $options["prefetch_size"] : null),
            (isset($options["prefetch_count"]) ? $options["prefetch_count"] : 1),
            (isset($options["a_global"]) ? $options["a_global"] : null)
        );
        $this->basic_consume(
            $routingKey,
            (isset($options["consumer_tag"]) ? $options["consumer_tag"] : ''),
            (isset($options["no_local"]) ? (bool)$options["no_local"] : false),
            (isset($options["no_ack"]) ? (bool)$options["no_ack"] : false),
            (isset($options["exclusive"]) ? (bool)$options["exclusive"] : false),
            (isset($options["no_wait"]) ? (bool)$options["no_wait"] : false),
            function (AMQPMessage $amqpMsg) use($handlersMap) {
                $msg = Message::fromAMQPMessage($amqpMsg);
                $this->handleMessage($msg, $handlersMap);
            }
        );
        return $this->waitConsume($options);
    }

    /**
     * @param Message $msg
     * @param array   $handlersMap
     * @return bool
     */
    public function handleMessage(Message $msg, array $handlersMap)
    {
        /* Try to process the message */
        foreach ($handlersMap as $handler) {
            $retVal = $handler->process($msg);
            switch ($retVal) {
                case Handler::RV_SUCCEED_STOP:
                    /* Handler succeeded, you MUST stop processing */
                    return $handler->handleSucceedStop($msg);

                case Handler::RV_SUCCEED_CONTINUE:
                    /* Handler succeeded, you SHOULD continue processing */
                    $handler->handleSucceedContinue($msg);
                    continue;

                case Handler::RV_PASS:
                    /**
                     * Just continue processing (I have no idea what
                     * happened in the handler)
                     */
                    continue;

                case Handler::RV_FAILED_STOP:
                    /* Handler failed and MUST stop processing */
                    return $handler->handleFailedStop($msg);

                case Handler::RV_FAILED_REQUEUE:
                    /**
                     * Handler failed and MUST stop processing but the message
                     * will be rescheduled
                     */
                    return $handler->handleFailedRequeue($msg);

                case Handler::RV_FAILED_REQUEUE_STOP:
                    /**
                     * Handler failed and MUST stop processing but the message
                     * will be rescheduled
                     */
                    return $handler->handleFailedRequeueStop($msg, true);

                case Handler::RV_FAILED_CONTINUE:
                    /* Well, handler failed, but you may try another */
                    $handler->handleFailedContinue($msg);
                    continue;

                default:
                    return false;
            }

        }
        /* If haven't return yet, send an ACK */
        $msg->sendAck();
    }

    /**
     * @param array $options
     * @return bool
     */
    protected function waitConsume($options = []){
        $consume = true;
        while (count($this->callbacks) && $consume) {
            try{
                $this->wait((isset($options["allowed_methods"]) ?$options["allowed_methods"] : null),
                    (isset($options["non_blocking"]) ?$options["non_blocking"] : false), $this->consumeTimeout);
            }
            catch (AMQPTimeoutException $e){
                if($e->getMessage() === "The connection timed out after {$this->consumeTimeout} sec while awaiting incoming data") {
                    $consume = false;
                }else{
                    throw($e);
                }
            }
        }
        return true;
    }

    /**
     * @param $routingKey
     */
    protected function queueDeclareBind(&$routingKey)
    {
        if (is_null($routingKey)) {
            // Set the routing key if missing
            $routingKey = $this->defaultQueue;
        }

        // Create/declare queue
        $this->queue_declare($routingKey, false, true, false, false);

        if ($this->exchange != "") {
            // Bind the queue to the exchange
            $this->queue_bind($routingKey, $this->exchange, $routingKey);
        }
    }

    /**
     * @param null $routingKey
     * @return mixed
     */
    public function getQueueInfo($routingKey = null)
    {
        if (is_null($routingKey)) {
            // Set the routing key if missing
            $routingKey = $this->defaultQueue;
        }

        $ch = curl_init();
        $vhost = ($this->vhost != "/" ? $this->vhost : "%2F");
        $url = "http://" . $this->host . ":15672" . "/api/queues/$vhost/" .$routingKey;

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);

        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result);
    }

}
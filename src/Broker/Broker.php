<?php

namespace Kontoulis\RabbitMQLaravel\Broker;

use Kontoulis\RabbitMQLaravel\Message\Message;
use Kontoulis\RabbitMQLaravel\Handlers\Handler;
use Kontoulis\RabbitMQLaravel\Exception\BrokerException;
use Kontoulis\RabbitMQLaravel\Traits\SingletonTrait;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;


/**
 * Class Broker
 * @package Kontoulis\RabbitMQLaravel\Broker
 */
class Broker
{
use SingletonTrait;
	/**
	 * @var
	 */
    protected $host;
    /**
     * @var
     */
    protected $port;
    /**
     * @var string
     */
    protected $user;
    /**
     * @var string
     */
    protected $password;
    /**
     * @var string
     */
    protected $vhost;
    /**
     * @var
     */
    protected $exchange;
    /**
     * @var
     */
    protected $queueName;
    /**
     * @var AMQPConnection
     */
    protected $connection;
    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel;
    /**
     * @var
     */
    protected $consumer_tag;
    /**
     * @var Logger
     */
    protected $logger;

    public $timeout = 10;
    public $prefetchCount = 1;

    /**
     * @param array $config
     * @param bool $multichannel
     * @throws \Exception
     * @internal param string $host
     * @internal param int $port
     * @internal param string $user
     * @internal param string $password
     * @internal param string $vhost
     */
    function __construct($config = [], $multichannel = false)
    {
        $this->multichannel = $multichannel;
        $this->host = (isset($config['host']) ? $config['host'] : (defined('AMQP_HOST') ? AMQP_HOST : 'localhost'));
        $this->port = (isset($config['port']) ? $config['port'] : (defined('AMQP_PORT') ? AMQP_PORT : 5672));
        $this->user = (isset($config['user']) ? $config['user'] : (defined('AMQP_USER') ? AMQP_USER : 'guest'));
        $this->password = (isset($config['password']) ? $config['password'] : (defined('AMQP_PASS') ? AMQP_PASS : 'guest'));
        $this->vhost = (isset($config['vhost']) ? $config['vhost'] : (defined('AMQP_vhost') ? AMQP_PASS : '/'));

//        $this->logger = new Logger();
        try {

            /* Open RabbitMQ connection */

            if(!isset($GLOBALS['AMQP_CONNECTION'])){
                $GLOBALS['AMQP_CONNECTION'] = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
            }
            $this->connection = $GLOBALS['AMQP_CONNECTION'];
            if(!isset($GLOBALS['AMQP_MAIN_CHANNEL'])){
                $GLOBALS['AMQP_MAIN_CHANNEL'] = $this->connection->channel();
            }
            $this->channel = $GLOBALS['AMQP_MAIN_CHANNEL'];


        } catch (AMQPRuntimeException $ex) {

            /* Something went wrong apparently... */

//            $this->logger->addError(
//
//                'Fatal error while initializing AMQP connection: '
//
//                . $ex->getMessage()
//
//            );

            throw new \Exception(

                'Fatal error while initializing AMQP connection: '

                . $ex->getMessage(),

                $ex->getCode()

            );

        }
    }


    /**
     * Starts to listen a queue for incoming messages.
     * @param array $handlers Array of handler class instances
     * @param string $queueName The AMQP queue
     * @param null $channelId
     * @return bool
     * @internal param bool $destroyOnEmpty
     */

    public function listenToQueue($handlers = [], $queueName = null, $channelId = null)
    {
        if (!is_null($queueName)) {
            $this->queueName = $queueName;
        }
//        $channel = $this->connection->channel();
        /* Look for handlers */
//        if($this->multichannel){
//            $channel = $this->connection->channel($channelId);
//        }else{
//            $channel = $this->channel;
//        }
        $handlersMap = array();
        if (is_array($handlers)) {
            foreach ($handlers as $handlerClassPath) {

                if (!class_exists($handlerClassPath)) {

                    $handlerClassPath = "Kontoulis\\RabbitMQLaravel\\Handlers\\DefaultHandler";

                    if (!class_exists($handlerClassPath)) {

                        throw new BrokerException(
                            "Class $handlerClassPath was not found!"
                        );

                    }

                }

                $handlerOb = new $handlerClassPath($this);

                $classPathParts = explode("\\", $handlerClassPath);

                $handlersMap[$classPathParts[count(

                    $classPathParts

                ) - 1]] = $handlerOb;

            }
        } else {
            $handlerClassPath = $handlers;
            if (!class_exists($handlerClassPath)) {

                $handlerClassPath = "Kontoulis\\RabbitMQLaravel\\Handlers\\DefaultHandler";

                if (!class_exists($handlerClassPath)) {

                    throw new BrokerException(
                        "Class $handlerClassPath was not found!"
                    );

                }

            }

            $handlerOb = new $handlerClassPath($this);

            $classPathParts = explode("\\", $handlerClassPath);

            $handlersMap[$classPathParts[count(

                $classPathParts

            ) - 1]] = $handlerOb;
        }


        /* Create queue */

        $this->channel->queue_declare($this->queueName, false, true, false, false);


        /* Start consuming */

        $this->channel->basic_qos(null, $this->prefetchCount, null);

        $this->channel->basic_consume(

            $this->queueName, '', false, false, false, false, function ($amqpMsg) use ($handlersMap) {


            $msg = Message::fromAMQPMessage($amqpMsg);

            $this->handleMessage($msg, $handlersMap);

        }

        );

        /* Iterate until ctrl+c is received... */

        while (count($this->channel->callbacks)) {
            $this->channel->wait(null, null, $this->timeout);
        }

    }

    /**
     * @param $queueName
     */
    public function setQueue($queueName)
    {
        $this->queueName = $queueName;
    }

    /**
     * @param $message
     * @param $queueName
     * @internal param Kontoulis\RabbitMQLaravel\Message\Message $msg
     */

    public function sendMessage($message, $queueName = null)
    {

        if (is_null($queueName)) {
            $queueName = $this->queueName;
        }

        $msg = new Message($queueName, ["message" => $message]);
        /* Create the message */

        $amqpMessage = $msg->getAMQPMessage();


        /* Create queue */

        $this->channel->queue_declare(

            $msg->queueName, false, true, false, false

        );


        /* Publish message */

        $this->channel->basic_publish(

            $amqpMessage, '', $msg->queueName

        );


    }

    /**
     * Publishes a batch of messages in queue
     * @param $data
     */
    public function publish_batch($data)
    {
        if (is_null($queueName = null)) {
            $queueName = $this->queueName;
        }
        /* Create queue */

        $this->channel->queue_declare(

            $queueName, false, true, false, false

        );
        foreach ($data as $item) {
            $msg = new Message($queueName, ["message" => $item]);
            /* Create the message */

            $amqpMessage = $msg->getAMQPMessage();

            $this->channel->batch_basic_publish(
                $amqpMessage, '', $msg->queueName
            );
        }
        /* Publish message */

        $this->channel->publish_batch();
    }

    /**
     * @param Message $msg
     * @param array $handlersMap
     * @return bool
     */
    public function handleMessage(Message $msg, array $handlersMap)
    {

        /* Try to process the message */

        foreach ($handlersMap as $code => $ob) {

            $retVal = $ob->tryProcessing($msg);

            $msg->updateAMQPMessage();

            switch ($retVal) {

                case Handler::RV_SUCCEED_STOP:

                    /* Handler succeeded, you MUST stop processing */

                    return $this->handleSucceedStop($msg);


                case Handler::RV_SUCCEED_CONTINUE:

                    /* Handler succeeded, you SHOULD continue processing */

                    $this->handleSucceedContinue($msg);

                    continue;


                case Handler::RV_PASS:

                    /**
                     * Just continue processing (I have no idea what
                     * happened in the handler)
                     */

                    continue;


                case Handler::RV_FAILED_STOP:

                    /* Handler failed and MUST stop processing */


                    return $this->handleFailedStop($msg);


                case Handler::RV_FAILED_REQUEUE:

                    /**
                     * Handler failed and MUST stop processing but the message
                     * will be rescheduled
                     */

                    return $this->handleFailedRequeue($msg);


                case Handler::RV_FAILED_CONTINUE:

                    /* Well, handler failed, but you may try another */

                    $this->handleFailedContinue($msg);

                    continue;


                default:

                    return false;

            }

        }

        /* If haven't return yet, send an ACK */

        $msg->sendAck();

    }

    public function basicGet($queue = '', $no_ack = false, $ticket = null)
    {
        if ($queue == '') {
            $queue = $this->queueName;
        }
        return $this->channel->basic_get($queue);
    }

    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param Message $msg
     * @return bool
     */

    protected function handleSucceedStop(Message $msg)
    {
        $msg->sendAck();

        $remaining = $this->getStatus($msg);

        if ($remaining < 1) {

            exit(0);

        }

        return true;

    }

    /**
     * @param null $msg
     * @return int
     */
    public function getStatus($msg = null)
    {

        $request = [

            "count" => 10,

            "requeue" => true,

            "encoding" => "auto"

        ];

        if (!is_null($msg) && strlen($msg->queueName) > 0) {

            $queueName = $msg->queueName;

        } else {

            $queueName = $this->queueName;

        }

        $ch = curl_init();

        $url = "http://" . $this->host . ":" . $this->port . "/api/queues/%2F/" .

            $queueName . '/get';


        $fields = json_encode($request);

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POST, count($fields));

        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);

        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);


        $result = curl_exec($ch);
        curl_close($ch);


        $data = json_decode($result);

        $messages = $data;
        if (!empty($messages)) {
            if (is_array($messages)) {
                return $messages[0]->message_count;
            } else {
                return $messages->message_count;
            }
        } else {
            return -1;
        }

    }

    /**
     * @param Message $msg
     * @return bool
     */
    protected function handleSucceedContinue(Message $msg)
    {
        return true;
    }

    /**
     * @param Message $msg
     * @throws BrokerException
     */
    protected function handleFailedStop(Message $msg)
    {
        $msg->sendNack();

        throw new BrokerException(

            "Handler failed for message"

            . " {$msg->getDeliveryTag()}."

            . " Execution stops but message is not rescheduled."

        );
    }

    /**
     * @param Message $msg $
     * @return bool
     */
    protected function handleFailedRequeue(Message $msg)
    {
        $msg->sendNack();

        $msg->republish();

        return true;

    }

    /**
     * @param Message $msg
     * @return bool
     */
    protected function handleFailedContinue(Message $msg)
    {
        return true;
    }

}
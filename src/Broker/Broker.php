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
use PhpAmqpLib\Message\AMQPMessage;

class Broker extends AMQPChannel
{
    private $queueName;
    private $host;
    private $port;
    private $user;
    private $password;
    private $vhost;

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
    public function listenToQueue($handlers = [] , $queueName = null )
    {
        if(!is_null($queueName)) {
            $this->queueName = $queueName;
        }
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

            $handlersMap[$classPathParts[count(

                $classPathParts

            ) - 1]] = $handlerOb;

        }


        /* Create queue */

        $this->queue_declare(

            $this->queueName, false, true, false, false

        );


        /* Start consuming */

        $this->basic_qos(null, 1, null);

        $this->basic_consume(

            $this->queueName, '', false, false, false, false, function (AMQPMessage $amqpMsg) use ($handlersMap) {

//            dd($amqpMsg->get("delivery_tag"));
            $msg = Message::fromAMQPMessage($amqpMsg);

            $this->handleMessage($msg, $handlersMap);

        }

        );

        /* Iterate until ctrl+c is received... */

        while (count($this->callbacks)) {

            $this->wait();

        }

    }

    /**
     * @param Message $msg
     * @param array   $handlersMap
     * @return bool
     */
    public function handleMessage($msg, array $handlersMap)
    {

        /* Try to process the message */

        foreach ($handlersMap as $code => $ob) {

            $retVal = $ob->tryProcessing($msg);

//            $msg->updateAMQPMessage();

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

        $this->basic_ack($msg->get("delivery_tag"));

    }

    /**
     * @param null $msg
     * @return int
     */
    public function getStatus($msg = null)
    {

        $request = [

            "count"    => 10,

            "requeue"  => true,

            "encoding" => "auto"

        ];

        if (!is_null($msg) && strlen($msg->get("routing_key")) > 0) {

            $queueName = $msg->get("routing_key");

        } else {

            $queueName = $this->queueName;

        }

        $ch = curl_init();

        $url = "http://" . $this->host . ":" . $this->port . "/api/queues/%2F/" .

            $queueName . '/get';


        $fields = json_encode($request);

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POST, count($request));

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
     * @param $queueName
     */
    public function setQueue($queueName){
        $this->queueName = $queueName;
    }

    /**
     * @param Message $msg
     * @return bool
     */

    protected function handleSucceedStop($msg)
    {
        $this->basic_ack(

            $msg->get("delivery_tag")

        );

        $remaining = $this->getStatus($msg);

        if ($remaining < 1) {

            exit(0);

        }

        return true;

    }

    /**
     * @param Message $msg
     * @return bool
     */
    protected function handleSucceedContinue($msg)
    {
        return true;
    }


    /**
     * @param Message $msg
     * @throws Kontoulis\RabbitMQLaravel\Exception\BrokerException
     */
    protected function handleFailedStop($msg)
    {
        $msg->sendNack();

        throw new BrokerException(

            "Handler failed for message"

            . " {$msg->getDeliveryTag()}."

            . " Execution stops but message is not rescheduled."

        );
    }


    /**
     * @param Message $msg$
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

    /**
     * @param $queueName
     * @param $message
     * @internal param Kontoulis\RabbitMQLaravel\Message\Message $msg
     */

    public function sendMessage($message, $queueName=null)
    {

        if(is_null($queueName)){
            $queueName = $this->queueName;
        }

        $msg = new Message($message);
        /* Create the message */

        $amqpMessage = $msg->getAMQPMessage();


        /* Create queue */

        $this->queue_declare(

            $queueName, false, true, false, false

        );


        /* Publish message */

        $this->basic_publish(

            $amqpMessage, '', $msg->queueName

        );


    }
}
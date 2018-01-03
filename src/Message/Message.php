<?php
/**
 * Package : RabbitMQ Laravel
 * User: kontoulis
 * Date: 12/9/2015
 * Time: 1:24 μμ
 */
namespace Kontoulis\RabbitMQLaravel\Message;

use PhpAmqpLib\Message\AMQPMessage;


/**
 * Class Message
 * @package RabbitMQLaravel\Libs
 */
class Message

{
    /** @var string */

    protected $routingKey;

    /** @var array */

    protected $config;

    /** @var \PhpAmqpLib\Message\AMQPMessage */

    protected $amqpMessage;


    /**************************************************************************
     * Constructors
     *************************************************************************/


    /**
     * Creates a new message
     * @param array                          $msg
     * @param string                          $routingKey
     * @param array                           $config
     */

    public function __construct(array $msg, $routingKey = null, array $config = array() )
    {
        /* Dynamic properties */
        foreach($msg as $key => $value){
            $this->$key = $value;
        }
        $this->routingKey = $routingKey;
        $this->config = $config;
        $this->amqpMessage = new AMQPMessage(json_encode($msg), $config);
    }

    /**
     * @return string
     */
    public function getRoutingKey()
    {
        return $this->routingKey;
    }

    /**
     * @param string $routingKey
     */
    public function setRoutingKey($routingKey)
    {
        $this->routingKey = $routingKey;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Return AMQP message
     * @return \PhpAmqpLib\Message\AMQPMessage
     */

    public function getAMQPMessage()
    {
        return $this->amqpMessage;
    }


    /**
     * @param AMQPMessage $AMQPMessage
     */
    public function setAMQPMessage(AMQPMessage $AMQPMessage){
        $this->amqpMessage = $AMQPMessage;
    }

    /**
     * @param bool $assoc
     * @return object|array
     */
    public function getData($assoc = false){
        return json_decode($this->getBody(), $assoc);
    }


    /**
     * Creates a message given the AMQP message and the queue it was received
     * from
     * @param AMQPMessage $AMQPMessage
     * @return Message
     * @internal param AMQPMessage $msg
     */
    public static function fromAMQPMessage(AMQPMessage $AMQPMessage)
    {
        $msg = new Message(
            json_decode($AMQPMessage->body, true),
            $AMQPMessage->delivery_info['routing_key'],
            $AMQPMessage->get_properties()
        );
        $msg->setAMQPMessage($AMQPMessage);
        return $msg;

    }

    /**
     * @return string
     */
    public function getBody(){

        return $this->amqpMessage->getBody();
    }


    /**
     * @return string
     */
    public function routingKey()
    {
        return $this->amqpMessage->delivery_info['routing_key'];
    }

    /**
     * Sends an acknowledgment
     */

    public function sendAck()
    {
        $this->amqpMessage->delivery_info['channel']->basic_ack(
            $this->getDeliveryTag()
        );
    }

    /**
     * @return string
     */

    public function getDeliveryTag()
    {
        return $this->amqpMessage->delivery_info['delivery_tag'];
    }

    /**
     * Sends a negative acknowledgment
     * @param bool $requeue Will the message be requeued
     */

    public function sendNack($requeue = false)
    {
        $this->amqpMessage->delivery_info['channel']->basic_nack(
            $this->getDeliveryTag(),
            true, // ignore all unacknowledged messages
            $requeue // reschedule the message
        );
    }


    /**
     * Re-publishes the message to the queue.
     */

    public function republish()
    {
        $this->amqpMessage->delivery_info['channel']->basic_publish(
            $this->getAMQPMessage(),
            $this->amqpMessage->delivery_info['exchange'],
            $this->routingKey
        );
    }

    /**
     * Returns new AMQP message
     * @return \PhpAmqpLib\Message\AMQPMessage
     */

    protected function getNewAMQPMessage()
    {
        return new AMQPMessage(
            json_encode($this->config),
            array(
                'delivery_mode' => 2,
                'routing_key'   => $this->routingKey
            )
        );
    }

    /**
     * Update the AMQP message (e.g.: before rescheduling) with new config.
     */
    public function updateAMQPMessage()
    {
        $this->amqpMessage->setBody(json_encode($this->getBody()));
    }

}


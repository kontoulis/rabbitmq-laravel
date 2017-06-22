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
class Message extends AMQPMessage
{

    /**
     * @param string $body
     * @param array $properties
     */
    public function __construct($body = '', $properties = array())
    {
        if (gettype($body) == "object")
        {
            $body = json_encode($body);
        }
        parent::__construct($body, $properties);
    }

	/**************************************************************************
	 * AMQP message high level API
	 *************************************************************************/

	/**
	 * @return string
	 */

	public function routingKey()
	{
		return $this->delivery_info['routing_key'];
	}

	/**
	 * Sends an acknowledgment
	 */

	public function sendAck()
	{

		$this->delivery_info['channel']->basic_ack(

			$this->getDeliveryTag()

		);

	}

	/**
	 * @return string
	 */

	public function getDeliveryTag()

	{

		return $this->delivery_info['delivery_tag'];

	}

	/**
	 * Sends a negative acknowledgment
	 * @param bool $requeue Will the message be requeued
	 */

	public function sendNack($requeue = false)

	{

		$this->delivery_info['channel']->basic_nack(

			$this->getDeliveryTag(),

			false, // ignore all unacknowledged messages

			$requeue // reschedule the message

		);

	}


	/**
	 * Re-publishes the message to the queue.
	 */

	public function republish()

	{

		$this->delivery_info['channel']->basic_publish(

			$this,

            $this->delivery_info['exchange'],

			$this->routingKey()

		);

	}

    public static function fromAMQPMessage(AMQPMessage $msg)
    {
        return new Message(
            $msg->delivery_info['routing_key'],
            (array)json_decode($msg->body),
            $msg
        );
    }

}


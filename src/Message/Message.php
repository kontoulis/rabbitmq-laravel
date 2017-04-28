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

	protected $_routingKey;

	/** @var array */

	public $config;

	/** @var \PhpAmqpLib\Message\AMQPMessage */

	private $amqpMessage;


	/**************************************************************************
	 * Constructors
	 *************************************************************************/


	/**
	 * Creates a new message
	 * @param string                          $routingKey
	 * @param array                           $config
	 * @param \PhpAmqpLib\Message\AMQPMessage $msg
	 */

	public function __construct(

		$routingKey,

		array $config = array(),

		AMQPMessage $msg = null

	)
	{

		$this->routingKey = $routingKey;

		$this->config = $config;

		$this->amqpMessage = $msg;

	}


	/**
	 * Creates a message given the AMQP message and the queue it was received
	 * from
	 * @param \PhpAmqpLib\Message\AMQPMessage $msg
	 * @return Message
	 */

	public static function fromAMQPMessage(AMQPMessage $msg)

	{

		return new Message(

			$msg->delivery_info['routing_key'],

			(array)json_decode($msg->body),

			$msg

		);

	}


	/**************************************************************************
	 * AMQP message high level API
	 *************************************************************************/

	/**
	 * @return string
	 */

	public function routingKey()

	{

		return $this->_routingKey;

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

			false, // ignore all unacknowledged messages

			$requeue // reschedule the message

		);

	}


	/**
	 * Re-publishes the message to the queue.
	 */

	public function republish()

	{

		$this->amqpMessage->delivery_info['channel']->basic_publish(

			$this->getNewAMQPMessage(),

			'',

			$this->routingKey()

		);

	}


	/**************************************************************************
	 * AMQP message low level API
	 *************************************************************************/

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

				'routing_key'   => $this->routingKey()

			)

		);

	}

	/**
	 * Return AMQP message
	 * @return \PhpAmqpLib\Message\AMQPMessage
	 */

	public function getAMQPMessage()

	{

		if (!isset($this->amqpMessage)) {

			$this->amqpMessage = $this->getNewAMQPMessage();

		} else {

			$this->updateAMQPMessage();

		}


		return $this->amqpMessage;

	}

	/**
	 * Update the AMQP message (e.g.: before rescheduling) with new config.
	 */

	public function updateAMQPMessage()

	{

		$this->amqpMessage->setBody(json_encode($this->config));

	}

}


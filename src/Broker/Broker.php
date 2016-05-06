<?php

namespace Kontoulis\RabbitMQLaravel\Broker;

use Kontoulis\RabbitMQLaravel\Message\Message;
use Kontoulis\RabbitMQLaravel\Handlers\Handler;
use Kontoulis\RabbitMQLaravel\Exception\BrokerException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPRuntimeException;


/**
 * Class Broker
 * @package Kontoulis\RabbitMQLaravel\Broker
 */
class Broker
{

	/**
	 * @var
	 */
	protected $exchange;

	/**
	 * @var
	 */
	protected $queueName;

	/**
	 * @var \PhpAmqpLib\Connection\AMQPStreamConnection
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
	 * @type
	 */
	protected $host;

	/**
	 * @type
	 */
	protected $port;

	/**
	 * @type
	 */
	protected $user;

	/**
	 * @type
	 */
	protected $password;

	/**
	 * @type
	 */
	protected $vhost;

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
		$this->queueName = $config["amqp_queue"];

		try {

			/* Open RabbitMQ connection */

			$this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);

			$this->channel = $this->connection->channel();

		} catch (AMQPRuntimeException $ex) {

			throw new BrokerException(

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
	 * @param bool $destroyOnEmpty
	 * @return bool
	 */

	public function listenToQueue($handlers = [], $queueName = null, $destroyOnEmpty = false)
	{
		if (!is_null($queueName)) {
			$this->queueName = $queueName;
		}
		/* Look for handlers */

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

				$handlerOb = new $handlerClassPath();

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

			$handlerOb = new $handlerClassPath();

			$classPathParts = explode("\\", $handlerClassPath);

			$handlersMap[$classPathParts[count(

					$classPathParts

			) - 1]] = $handlerOb;
		}


		/* Create queue */

		$this->channel->queue_declare($this->queueName, false, true, false, false);


		/* Start consuming */

		$this->channel->basic_qos(null, 1, null);

		$this->channel->basic_consume(

				$this->queueName, '', false, false, false, false, function ($amqpMsg) use ($handlersMap) {


			$msg = Message::fromAMQPMessage($amqpMsg);

			$this->handleMessage($msg, $handlersMap);

		}

		);

		/* Iterate until ctrl+c is received... */

		while (count($this->channel->callbacks)) {
			$this->channel->wait(null, null, 1);
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
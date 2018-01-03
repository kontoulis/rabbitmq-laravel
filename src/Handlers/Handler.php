<?php

namespace Kontoulis\RabbitMQLaravel\Handlers;

use Kontoulis\RabbitMQLaravel\Exception\HandlerException;
use Kontoulis\RabbitMQLaravel\Message\Message;

/**
 * Class Handler
 * @package Kontoulis\RabbitMQLaravel\Handlers
 */
abstract class Handler

{

	/**************************************************************************
	 * Message processing return values
	 *************************************************************************/

	/**
	 * Pass this message and proceed with the next
	 */
	const RV_PASS = 1;
	/**
	 * Continue and ignore the failed message
	 */
	const RV_FAILED_CONTINUE = 10;
	/**
	 * We failed to do our job with this message (e.g. failed to store it in the database),
	 * Force exit
	 */
	const RV_FAILED_STOP = 11;
	/**
	 * We failed to do our job with this message (e.g. failed to store it in the database),
	 * put it again in the queue
	 */
	const RV_FAILED_REQUEUE = 12;

    /**
     * We failed to do our job with this message
     * put it again in the queue and stop execution
     */
    const RV_FAILED_REQUEUE_STOP = 13;
	/**
	 * Keep listening to the queue after successfully parsing the message
	 */
	const RV_SUCCEED_CONTINUE = 20;
	/**
	 *  Force stop listening after successfully parsing a message
	 */
	const RV_SUCCEED_STOP = 21;
	/**
	 *
	 */
	const RV_SUCCEED = Handler::RV_SUCCEED_CONTINUE;
	/**
	 *
	 */
	const RV_FAILED = Handler::RV_FAILED_CONTINUE;
	/**
	 *
	 */
	const RV_ACK = Handler::RV_SUCCEED;
	/**
	 *
	 */
	const RV_NACK = Handler::RV_FAILED_STOP;

	/**
	 *
	 */


	/**************************************************************************
	 * Message processing method
	 *************************************************************************/


	/**
	 * Tries to process the incoming message.
	 * @param Message $msg
	 * @return int One of the possible return values defined as Handler
	 * constants.
	 */

	abstract public function process(Message $msg);

	/**
	 * @param $msg
	 * @return int
	 */
	abstract protected function handleSuccess($msg);

    /**
     * @param Message $msg
     * @return bool
     */

    public function handleSucceedStop(Message $msg)
    {
        $msg->sendAck();
        return true;
    }

    /**
     * @param Message $msg
     * @return bool
     */
    public function handleSucceedContinue(Message $msg)
    {
        return true;
    }

    /**
     * @param Message $msg
     * @throws HandlerException
     */
    public function handleFailedStop(Message $msg)
    {
        $msg->sendNack();
        throw new HandlerException(
            "Handler failed for message"
            . " {$msg->getDeliveryTag()}."
            . " Execution stopped but message is not rescheduled."
        );
    }

    /**
     * @param Message $msg$
     * @return bool
     */
    public function handleFailedRequeue(Message $msg)
    {
        $msg->sendNack(true);
        return true;
    }

    public function handleFailedRequeueStop(Message $msg, $debug = false)
    {
        $msg->sendNack(true);
        throw new HandlerException(
            "Handler failed for message"
            . " {$msg->getDeliveryTag()}."
            . " Execution stopped , message is  rescheduled."
            . ($debug ? $msg->getBody() : "")
        );
    }


    /**
     * @param Message $msg
     * @return bool
     */
    public function handleFailedContinue(Message $msg)
    {
        $msg->sendNack();
        return true;
    }
}


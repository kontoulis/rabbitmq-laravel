<?php

namespace Kontoulis\RabbitMQLaravel\Handlers;

use Kontoulis\RabbitMQLaravel\Broker\Broker;
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

    /** @var \Monolog\Logger */

    protected $logger;
    /**
     * The name of the logger for the handler.
     * @var string
     */

    protected $loggerName = 'Messaging/Handler';

    /**
     * @var null
     */
    protected $messagingBroker;

    /**
     * @param null $broker
     */
    public function __construct($broker = null)

    {

//		$this->logger = new Logger($this->loggerName);

        if(is_null($broker)){
            $broker = Broker::instance();
        }
        $this->messagingBroker = $broker;

    }


    /**************************************************************************
     * Message processing method
     *************************************************************************/


    /**
     * Tries to process the incoming message.
     * @param Message $msg
     * @return int One of the possible return values defined as Handler
     * constants.
     */

    abstract public function tryProcessing(Message $msg);

    /**
     * @param $msg
     * @return mixed
     */
    abstract protected function handleSuccess($msg);
}


<?php
/**
 * Created by PhpStorm.
 * User: zhangyi
 * Date: 2020-09-29
 * Time: 21:08
 */

namespace DS_DB\commands;

use DS_DB\DSDB;
use mikemadisonweb\rabbitmq\components\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;


class RollbackConsumer implements ConsumerInterface
{
    use DSDB;
    public function execute(AMQPMessage $msg)
    {
        $data = unserialize($msg->body);
        // 全局ID
        self::$CURRENT_GLOBAL_ID = $data['GID'];
        try {
            $this->startTransactionRollback();

            return self::MSG_ACK;
        }
        catch (\Throwable $e) {
            return self::MSG_REQUEUE;
        }
    }
}
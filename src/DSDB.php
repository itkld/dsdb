<?php
/**
 * Created by PhpStorm.
 * User: zhangyi
 * Date: 2020-09-10
 * Time: 23:17
 */

namespace DS_DB;

use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\Response;


trait DSDB
{
    // 执行事务操作
    protected static $OPERATION_EXECUTE = 'execute';
    // 回滚事务操作
    protected static $OPERATION_ROLLBACK = 'rollback';

    // 事务状态
    protected static $TRANSACTION_STATUS_PENDING = 10;
    protected static $TRANSACTION_STATUS_SUCCESS = 20;
    protected static $TRANSACTION_STATUS_FAIL = 30;
    protected static $TRANSACTION_STATUS_ROLLBACK_PENDING = 40;
    protected static $TRANSACTION_STATUS_ROLLBACK_SUCCESS = 50;
    protected static $TRANSACTION_STATUS_ROLLBACK_FAIL = 60;

    // 当前全局事务ID
    protected static $CURRENT_GLOBAL_ID;

    // 当前操作
    public static $CURRENT_OPERATION;

    // 执行过的事务项
    public static $TRANSACTION_ITEMS = [];

    /**
     * 开始事务记录
     */
    protected function startTransactionExec() {
        \Yii::info('开始事务记录', 'ds_db');
        self::$CURRENT_OPERATION = self::$OPERATION_EXECUTE;
        // 检查事务参数
        $this->checkTransactionParams();
        // 检查事务是否允许被执行
        $this->canExecuteTransaction();
        $this->recordTransaction();
        Event::on(ActiveRecord::class, '*', function ($event) {
            $eventName = $event->name;
            if(self::$OPERATION_EXECUTE == self::$CURRENT_OPERATION && in_array($eventName, [ActiveRecord::EVENT_AFTER_INSERT, ActiveRecord::EVENT_AFTER_UPDATE,
                    ActiveRecord::EVENT_AFTER_DELETE])) {
                $model = $event->sender;

                /* 旧记录保存，用于事件回滚 */
                $oldData = $model->getAttributes();

                if($eventName == ActiveRecord::EVENT_AFTER_UPDATE) {
                    //用旧数据覆盖更新后的数据
                    $oldData = array_merge($oldData, $event->changedAttributes);
                }

                /* 回滚部分 */
                //主键及值
                $keys = $model->getPrimaryKey(true);
                //表名
                $modelCls = get_class($model);
                self::$TRANSACTION_ITEMS[] = [
                    'global_id' => self::$CURRENT_GLOBAL_ID,
                    'operation' => self::getTransactionItemName($eventName),
                    'origin_data' => json_encode($oldData),
                    'model' => $modelCls,
                    'primary_keys' => json_encode($keys),
                ];
            }
        });

        Event::on(Response::class, Response::EVENT_BEFORE_SEND, function($event) {
            // 禁止记录本事务
            Event::off(ActiveRecord::class, '*');
            //未发生异常，则对事务进行提交
            if(!\Yii::$app->getErrorHandler()->exception) {
                \Yii::info('业务正常执行，开始写入事务记录项', 'ds_db');
                $this->recordTransactionItem();
                // 标记事务为成功
                $this->updateTransactionStatus(self::$CURRENT_GLOBAL_ID, self::$TRANSACTION_STATUS_SUCCESS);
                \Yii::info('事务记录完成', 'ds_db');
            }
            else {
                self::$TRANSACTION_ITEMS = [];
                \Yii::error('业务执行失败', 'ds_db');
                // 标记事务为失败
                $this->updateTransactionStatus(self::$CURRENT_GLOBAL_ID, self::$TRANSACTION_STATUS_FAIL);
            }
        });
    }


    /**
     * 开始事务回滚
     */
    protected function startTransactionRollback() {
        \Yii::info('开始事务回滚', 'ds_db');
        self::$CURRENT_OPERATION = self::$OPERATION_ROLLBACK;
        // 检查事务参数
        $this->checkTransactionParams();
        // 判断事务是否可回滚
        $this->canRollbackTransaction();
        //开始回滚事务
        $this->updateTransactionStatus(self::$CURRENT_GLOBAL_ID, self::$TRANSACTION_STATUS_ROLLBACK_PENDING);

        $transactionItems = TransactionItems::find()->where(['global_id' => self::$CURRENT_GLOBAL_ID])->orderBy(['id' => SORT_DESC])->all();

        try {
            \Yii::$app->db->transaction(function() use ($transactionItems) {
                foreach ($transactionItems as $item) {
                    $primaryKeys = json_decode($item['primary_keys'], true);
                    $orginData = json_decode($item['origin_data'], true);
                    $class = $item['model'];
                    switch($item['operation']) {
                        case 'insert':
                            $obj = $this->getInstanceByPrimaryKeys($class, $primaryKeys);
                            // TODO compare with originData
                            $obj->delete();
                            break;

                        case 'update':
                            $obj = $this->getInstanceByPrimaryKeys($class, $primaryKeys);
                            $obj->setAttributes($orginData, false);
                            $obj->save();
                            break;
                        case 'delete':
                            $obj = new $class();
                            $obj->setAttributes($orginData, false);
                            $obj->save();
                            break;
                        default:
                            throw new \yii\base\Exception('未知操作' . $item['operation']);
                            break;
                    }
                }
                self::updateTransactionStatus(self::$CURRENT_GLOBAL_ID, self::$TRANSACTION_STATUS_ROLLBACK_SUCCESS);
            });
        } catch (\Throwable $e) {
            self::updateTransactionStatus(self::$CURRENT_GLOBAL_ID, self::$TRANSACTION_STATUS_ROLLBACK_FAIL);
            throw $e;
        }
    }

    /**
     * 根据模型和主键查询实例
     *
     * @param $model
     * @param $primaryKeys
     * @return mixed
     */
    private function getInstanceByPrimaryKeys($model, $primaryKeys) {
        return $model::findOne(json_decode($primaryKeys, true));
    }

    /**
     * 判断事务是否可被开启
     *
     * @return bool
     * @throws BadRequestHttpException
     */
    private function checkTransactionParams() {
        if(self::$CURRENT_GLOBAL_ID) {
            return true;
        }

        throw new BadRequestHttpException("事务参数缺失");
    }

    /**
     * 记录主事务
     *
     * @throws Exception
     */
    private function recordTransaction() {
        //记录主事务记录，考虑到幂等性和执行记录，所以不加入到事务中
        $ts = new Transactions();
        $ts->setAttributes([
            'global_id' => self::$CURRENT_GLOBAL_ID,
            'status' => self::$TRANSACTION_STATUS_PENDING
        ]);
        if(!$ts->save()) {
            throw new Exception('记录事务信息失败', $ts->getErrors());
        }
    }

    /**
     * 更新事务状态
     *
     * @param $globalId
     * @param $status
     * @return int
     */
    private function updateTransactionStatus($globalId, $status) {
        return Transactions::updateAll(['status' => $status], ['global_id' => $globalId]);
    }

    /**
     * 记录事务项目
     *
     */
    private function recordTransactionItem() {
        return \Yii::$app->db->transaction(function() {
            foreach (self::$TRANSACTION_ITEMS as $item) {
                $tsi = new TransactionItems();
                $tsi->setAttributes($item);
                if(!$tsi->save()) {
                    throw new Exception('记录事务信息失败', $tsi->getErrors());
                }
            }

            return true;
        });
    }

    /**
     * 获取事务项名称
     *
     * @param $eventName
     * @return string
     */
    private function getTransactionItemName($eventName) {
        return strtolower(str_replace('after', '', $eventName));
    }

    /**
     * 判断是否可执行事务
     *
     * @return bool
     * @throws BadRequestHttpException
     */
    private function canExecuteTransaction() {
        // 获取事务执行状态
        $transaction = Transactions::findOne(['global_id' => self::$CURRENT_GLOBAL_ID]);
        // 简单处理，事务已存在
        if ($transaction) {
            throw new BadRequestHttpException("事务进行中");
        }

        // 其它可执行
        return true;
    }

    /**
     * 判断是否允许回滚执行事务
     *
     * @return bool
     * @throws BadRequestHttpException
     */
    private function canRollbackTransaction() {
        // 获取事务执行状态
        $transaction = Transactions::findOne(['global_id' => self::$CURRENT_GLOBAL_ID]);
        // 简单处理，事务已存在
        if ($transaction && $transaction->status == self::$TRANSACTION_STATUS_SUCCESS) {
            return true;
        }

        // 其它不可执行
        throw new BadRequestHttpException("事务状态不正确");
    }
}
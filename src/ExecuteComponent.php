<?php
/**
 * 只支持 activerecord 数据操作回滚
 *
 * TODO
 * 1. 回滚数据存储（table, operation, old_data）
 * 2. 事务逻辑
 */

namespace DS_DB;

use yii\base\Component;

class ExecuteComponent extends Component
{
    use DSDB;

    public function init()
    {
        // 全局ID
        self::$CURRENT_GLOBAL_ID = \Yii::$app->request->getHeaders()->get('DS-GID', '1');
        // 操作
        self::$CURRENT_OPERATION = self::$OPERATION_EXECUTE;
        $this->start();
    }
}
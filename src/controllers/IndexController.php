<?php
/**
 * Created by PhpStorm.
 * User: zhangyi
 * Date: 2020-09-10
 * Time: 23:11
 */

namespace DS_DB\controllers;

use DS_DB\DSDB;
use yii\web\Controller;

class IndexController extends Controller
{
    use DSDB;

    public function actionIndex() {
        // 全局ID
        self::$CURRENT_GLOBAL_ID = \Yii::$app->request->getHeaders()->get('DS-GID');
        try {
            $this->startTransactionRollback();
            $this->asJson([
                'status' => 200,
                'msg' => '',
            ]);
        }
        catch (\Throwable $e) {
            $this->asJson([
                'status' => 500,
                'msg' => $e->getMessage()
            ]);
        }
    }
}
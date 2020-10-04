Distributed Transaction Database 
====================
This is a distributed transaction system implemented by database.

Usage
-------------
To use this extension, you need to generate you Global Transaction ID.

1. Start Record Transaction
    ``` php
    <?php
    namespace app\controllers;
    
    use app\models\Country;
    use DS_DB\DSDB;
    use Yii;
    use yii\web\Controller;
    class SiteController extends Controller
    {
        use DSDB;
        
        public function actionIndex()
        {
            // Global Transaction ID
            $gid = (string)rand();
            // Set Global Transaction ID
            self::$CURRENT_GLOBAL_ID = \Yii::$app->request->getHeaders()->get('DS-GID', $gid);
            // Start Transaction
            $this->startTransactionExec();
    //        $country = new Country();
    //        $country->setAttributes(['code' => 'DS', 'name' => 'fdfdfdfd', 'population' => 12345], false);
    //        $country->save();
    //
    //        $country->code = '00';
    //        $country->save();
    
    
            $ct = Country::find()->where(['id' => 3])->one();
            $ct->name = 'abcd4edf';
            $ct->save();
            $ct->delete();
            
            // rollback using rabbitmq
            $producer = Yii::$app->rabbitmq->getProducer('rollback');
            $msg = serialize(['GID' => $gid]);
            $producer->publish($msg, 'rollback', 'rollback');
            return $this->render('index');
        }
    }
    ```
2. Rollback
    - restful api
        ``` php
        'rollback' => [
            'class' => 'DS_DB\Module',
        ]
        ```
        send request with header "DS-GID" to "http://xxx/rollback/index/index".
    - manual rollback
        ``` php
        self::$CURRENT_GLOBAL_ID = $GID;
        $this->startTransactionRollback(); 
        ```
        
    

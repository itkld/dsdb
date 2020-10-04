<?php

namespace DS_DB;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "transactions".
 *
 * @property string $global_id 全局事务ID
 * @property int $status 10: 执行中 20: 执行成功 30：执行失败
 */
class Transactions extends ActiveRecord
{
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'transactions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['global_id'], 'required'],
            [['status'], 'integer'],
            [['global_id'], 'string', 'max' => 50],
            [['global_id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'global_id' => '全局事务ID',
            'status' => '10: 执行中 20: 执行成功 30：执行失败',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }
}

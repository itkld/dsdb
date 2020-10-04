<?php

namespace DS_DB;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "transaction_items".
 *
 * @property int $id ID
 * @property string $global_id 全局事务ID
 * @property string $operation 执行的操作，可选值 insert, update, delete
 * @property string $origin_data 原始数据
 * @property string $model 模型类
 * @property string $primary_keys 主要数据，json
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 */
class TransactionItems extends ActiveRecord
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
        return 'transaction_items';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['global_id', 'operation', 'origin_data', 'model', 'primary_keys'], 'required'],
            [['origin_data'], 'string'],
            [['global_id', 'primary_keys'], 'string', 'max' => 50],
            [['operation'], 'string', 'max' => 10],
            [['model'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'global_id' => '全局事务ID',
            'operation' => '执行的操作，可选值 insert, update, delete',
            'origin_data' => '原始数据',
            'model' => '模型类',
            'primary_keys' => '主要数据，json',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
        ];
    }
}

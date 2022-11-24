<?php

namespace addons\TinyShop\services\marketing;

use Yii;
use common\components\Service;
use addons\TinyShop\common\models\marketing\PointConfig;

/**
 * Class PointConfigService
 * @package addons\TinyShop\services\marketing
 * @author Rf <1458015476@qq.com>
 */
class PointConfigService extends Service
{
    /**
     * @return array|\yii\db\ActiveRecord|null
     */
    public function findOne($merchant_id)
    {
        return PointConfig::find()
            ->where(['merchant_id' => $merchant_id])
            ->asArray()
            ->one();
    }

    /**
     * @return PointConfig
     */
    public function one($merchant_id)
    {
        /* @var $model PointConfig */
        if (empty(($model = PointConfig::find()->where(['merchant_id' => $merchant_id])->one()))) {
            $model = new PointConfig();

            return $model->loadDefaultValues();
        }

        return $model;
    }
}
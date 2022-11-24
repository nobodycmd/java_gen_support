<?php

namespace addons\TinyShop\api\modules\v1\controllers\member;

use common\helpers\ArrayHelper;
use Yii;
use yii\data\ActiveDataProvider;
use addons\TinyShop\common\models\member\Footprint;
use api\controllers\UserAuthController;
use common\enums\StatusEnum;

/**
 * 足迹
 *
 * Class FootprintController
 * @package addons\TinyShop\api\modules\v1\controllers\member
 * @author Rf <1458015476@qq.com>
 */
class FootprintController extends UserAuthController
{
    /**
     * @var Footprint
     */
    public $modelClass = Footprint::class;

    /**
     * 首页
     *
     * @return ActiveDataProvider
     */
    public function actionIndex()
    {
        $start_time = Yii::$app->request->get('start_time');
        $end_time = Yii::$app->request->get('end_time');

        $data = new ActiveDataProvider([
            'query' => $this->modelClass::find()
                ->where(['status' => StatusEnum::ENABLED, 'member_id' => Yii::$app->user->identity->member_id])
                ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
                ->andFilterWhere(['>', 'updated_at', $start_time])
                ->andFilterWhere(['<', 'updated_at', $end_time])
                ->with('product')
                ->orderBy('updated_at desc')
                ->asArray(),
            'pagination' => [
                'pageSize' => $this->pageSize,
                'validatePage' => false,// 超出分页不返回data
            ],
        ]);

        return $data;
    }
}
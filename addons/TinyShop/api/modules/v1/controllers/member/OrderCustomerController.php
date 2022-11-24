<?php

namespace addons\TinyShop\api\modules\v1\controllers\member;

use Yii;
use yii\web\NotFoundHttpException;
use common\helpers\ResultHelper;
use api\controllers\UserAuthController;
use addons\TinyShop\common\models\forms\CustomerRefundForm;
use addons\TinyShop\common\models\order\Customer;

/**
 * 售后
 *
 * Class OrderCustomerController
 * @package addons\TinyShop\api\modules\v1\controllers\member
 * @author Rf <1458015476@qq.com>
 */
class OrderCustomerController extends UserAuthController
{
    /**
     * @var Customer
     */
    public $modelClass = Customer::class;

    /**
     * 退款申请
     *
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     * @throws \yii\web\UnprocessableEntityHttpException
     */
    public function actionApply()
    {
        $model = new CustomerRefundForm();
        $model->setScenario('apply');
        $model->attributes = Yii::$app->request->post();
        if (!$model->validate()) {
            return ResultHelper::json(422, $this->getError($model));
        }

        $product = Yii::$app->tinyShopService->orderProduct->findById($model->id);
        empty($model->refund_require_money) && $model->refund_require_money = $product->product_money;
        if ($model->refund_require_money > $product->product_money) {
            $model->refund_require_money = $product->product_money;
        }
        $member = Yii::$app->services->member->get(Yii::$app->user->identity->member_id);

        return Yii::$app->tinyShopService->orderCustomer->refundApply($model, $member->id, $member->nickname);
    }

    /**
     * 退货提交
     *
     * @param $id
     * @return mixed
     * @throws NotFoundHttpException
     * @throws \yii\web\UnprocessableEntityHttpException
     */
    public function actionSalesReturn()
    {
        $model = new CustomerRefundForm();
        $model->setScenario('salesReturn');
        $model->attributes = Yii::$app->request->post();
        if (!$model->validate()) {
            return ResultHelper::json(422, $this->getError($model));
        }

        $member = Yii::$app->services->member->get(Yii::$app->user->identity->member_id);

        return Yii::$app->tinyShopService->orderCustomer->refundSalesReturn($model, $member->id, $member->nickname);
    }

    /**
     * 关闭申请
     *
     * @return mixed|void
     * @throws NotFoundHttpException
     * @throws \yii\web\UnprocessableEntityHttpException
     */
    public function actionClose()
    {
        $model = new CustomerRefundForm();
        $model->attributes = Yii::$app->request->post();
        if (!$model->validate()) {
            return ResultHelper::json(422, $this->getError($model));
        }

        $member = Yii::$app->services->member->get(Yii::$app->user->identity->member_id);

        return Yii::$app->tinyShopService->orderCustomer->refundClose($model->id, $member->id, $member->nickname);
    }
}
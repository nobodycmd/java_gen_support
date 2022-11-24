<?php

namespace addons\TinyShop\services\order;

use addons\TinyShop\common\enums\SubscriptionActionEnum;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\Json;
use yii\web\UnprocessableEntityHttpException;
use common\components\Service;
use common\enums\StatusEnum;
use common\helpers\BcHelper;
use common\helpers\ArrayHelper;
use common\helpers\EchantsHelper;
use addons\TinyShop\common\models\order\Customer;
use addons\TinyShop\common\models\order\Order;
use addons\TinyShop\common\models\order\OrderProduct;
use addons\TinyShop\common\enums\RefundStatusEnum;
use addons\TinyShop\common\enums\OrderStatusEnum;
use addons\TinyShop\common\enums\RefundTypeEnum;
use addons\TinyShop\common\models\forms\RefundForm;
use addons\TinyShop\common\enums\ExplainStatusEnum;

/**
 * Class OrderProductService
 * @package addons\TinyShop\services\order
 * @author Rf <1458015476@qq.com>
 */
class ProductService extends Service
{
    /**
     * 退货/退款申请
     *
     * @param $refundForm
     * @param $member_id
     * @return Customer|void
     * @throws UnprocessableEntityHttpException
     */
    public function refundApply(RefundForm $refundForm, $member_id, $nickname)
    {
        $model = $this->findByIdAndVerify($refundForm->id, $member_id);
        $model->refund_type = $refundForm->refund_type;
        $model->refund_reason = $refundForm->refund_reason;
        $model->refund_evidence = is_array($refundForm->refund_evidence) ? $refundForm->refund_evidence : Json::decode($refundForm->refund_evidence);
        $model->refund_require_money = $refundForm->refund_require_money;
        $model->refund_explain = $refundForm->refund_explain;

        // 待发货、已发货
        if (!in_array($model->order_status, [OrderStatusEnum::PAY, OrderStatusEnum::SHIPMENTS])) {
            throw new UnprocessableEntityHttpException('非法操作');
        }

        // 未发货只能选择只退款
        if (
            $model->order_status == OrderStatusEnum::PAY &&
            $model->refund_type != RefundTypeEnum::MONEY &&
            $model->shipping_status == StatusEnum::DISABLED
        ) {
            throw new UnprocessableEntityHttpException('只可选择仅退款');
        }

        if ($model->refund_status == RefundStatusEnum::NO_PASS_ALWAYS) {
            throw new UnprocessableEntityHttpException('退款已经被取消，不能再次申请');
        }

        if (!in_array($model->refund_status, [0, RefundStatusEnum::NO_PASS])) {
            throw new UnprocessableEntityHttpException('退款处理中，请不要重复申请');
        }

        $model->refund_status = RefundStatusEnum::APPLY;
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        // 记录行为
        Yii::$app->tinyShopService->orderRefund->create(
            Yii::$app->id,
            $model->order_id,
            $model->id,
            $model->refund_status,
            $member_id,
            $nickname
        );
    }

    /**
     * 同意退款申请
     *
     * @param $id
     * @return Order|array|\yii\db\ActiveRecord|null
     * @throws UnprocessableEntityHttpException
     */
    public function refundPass($id)
    {
        $model = $this->findByIdAndVerify($id);
        // 退货、退款
        $model->refund_status = RefundStatusEnum::SALES_RETURN;
        // 仅退款
        if ($model->refund_type == RefundTypeEnum::MONEY) {
            $model->refund_status = RefundStatusEnum::AFFIRM_RETURN_MONEY;
        }

        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        // 记录行为
        Yii::$app->tinyShopService->orderRefund->create(
            Yii::$app->id,
            $model->order_id,
            $model->id,
            $model->refund_status,
            Yii::$app->user->identity->id,
            Yii::$app->user->identity->username
        );

        return $model;
    }

    /**
     * 拒绝退款申请
     *
     * @param $id
     * @param $always
     * @return Order|array|\yii\db\ActiveRecord|null
     * @throws UnprocessableEntityHttpException
     */
    public function refundNoPass($id, $always)
    {
        $model = $this->findByIdAndVerify($id);

        if ($model->refund_status != RefundStatusEnum::APPLY) {
            throw new UnprocessableEntityHttpException('操作失败,未申请退款或已被处理');
        }

        $model->refund_status = $always == true ? RefundStatusEnum::NO_PASS_ALWAYS : RefundStatusEnum::NO_PASS;
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        // 记录行为
        Yii::$app->tinyShopService->orderRefund->create(
            Yii::$app->id,
            $model->order_id,
            $model->id,
            $model->refund_status,
            Yii::$app->user->identity->id,
            Yii::$app->user->identity->username
        );

        return $model;
    }

    /**
     * 退货提交
     *
     * @param RefundForm $refundForm
     * @param $member_id
     * @throws UnprocessableEntityHttpException
     */
    public function refundSalesReturn(RefundForm $refundForm, $member_id, $nickname)
    {
        $model = $this->findByIdAndVerify($refundForm->id, $member_id);
        $model->refund_shipping_code = $refundForm->refund_shipping_code;
        $model->refund_shipping_company = $refundForm->refund_shipping_company;

        if ($model->refund_type != RefundTypeEnum::MONEY_AND_PRODUCT) {
            throw new UnprocessableEntityHttpException('未申请退货退款');
        }

        if ($model->refund_status == RefundStatusEnum::AFFIRM_SALES_RETURN) {
            throw new UnprocessableEntityHttpException('已经提交退货申请');
        }

        if ($model->refund_status != RefundStatusEnum::SALES_RETURN) {
            throw new UnprocessableEntityHttpException('操作失败,已经已被处理');
        }

        $model->refund_status = RefundStatusEnum::AFFIRM_SALES_RETURN;
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        // 记录行为
        Yii::$app->tinyShopService->orderRefund->create(
            Yii::$app->id,
            $model->order_id,
            $model->id,
            $model->refund_status,
            $member_id,
            $nickname
        );
    }

    /**
     * 关闭退款/退货申请
     *
     * @param $id
     * @param $member_id
     * @throws UnprocessableEntityHttpException
     */
    public function refundClose($id, $member_id, $nickname)
    {
        $model = $this->findByIdAndVerify($id, $member_id);

        if ($model->refund_status == 0) {
            throw new UnprocessableEntityHttpException('未申请退款');
        }

        if ($model->refund_status == RefundStatusEnum::CONSENT) {
            throw new UnprocessableEntityHttpException('已经关闭成功');
        }

        $model->refund_status = RefundStatusEnum::CANCEL;
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        // 记录行为
        Yii::$app->tinyShopService->orderRefund->create(
            Yii::$app->id,
            $model->order_id,
            $model->id,
            $model->refund_status,
            $member_id,
            $nickname
        );
    }

    /**
     * 确认退款
     *
     * @param $id
     * @return OrderProduct
     * @throws UnprocessableEntityHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    public function refundReturnMoney($id, $refund_balance_money)
    {
        /** @var OrderProduct $model */
        $model = $this->findByIdAndVerify($id);
        /** @var Order $order */
        $order = $model->order;

        // 实际退款金额
        $model->refund_balance_money = $refund_balance_money;

        $model->refund_status = RefundStatusEnum::CONSENT;
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        // 退款为 0
        if ($model->refund_balance_money > 0) {
            // 增加本身订单退款金额
            Order::updateAllCounters(['refund_balance_money' => $model->refund_balance_money], ['id' => $order->id]);
        }

        // 自动更新订单状态
        Yii::$app->tinyShopService->order->autoUpdateStatus($model['order_id']);

        // 记录行为
        Yii::$app->tinyShopService->orderRefund->create(
            Yii::$app->id,
            $model->order_id,
            $model->id,
            $model->refund_status,
            Yii::$app->user->identity->id,
            Yii::$app->user->identity->username
        );

        return $model;
    }

    /**
     * 确认收货
     *
     * @param $id
     * @throws UnprocessableEntityHttpException
     */
    public function refundDelivery($id)
    {
        $model = $this->findByIdAndVerify($id);

        if ($model->refund_status != RefundStatusEnum::AFFIRM_SALES_RETURN) {
            throw new UnprocessableEntityHttpException('确认收货失败，已经被处理');
        }

        $model->refund_status = RefundStatusEnum::AFFIRM_RETURN_MONEY;
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException($this->getError($model));
        }

        return $model;
    }

    /**
     * 查询并校验订单
     *
     * @param $id
     * @param string $member_id
     * @return array|\yii\db\ActiveRecord|null|Order
     * @throws UnprocessableEntityHttpException
     */
    public function findByIdAndVerify($id, $member_id = '')
    {
        $model = OrderProduct::find()
            ->where(['id' => $id, 'status' => StatusEnum::ENABLED])
            ->andFilterWhere(['member_id' => $member_id])
            ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
            ->one();

        if (!$model) {
            throw new UnprocessableEntityHttpException('订单产品不存在');
        }

        if ($member_id && $member_id != $model['member_id']) {
            throw new UnprocessableEntityHttpException('权限不足');
        }

        return $model;
    }

    /**
     * 正常发货
     *
     * @param $ids
     * @param $order_id
     * @throws \yii\web\NotFoundHttpException
     * @throws \yii\web\UnprocessableEntityHttpException
     */
    public function deliver($ids, $order_id)
    {
        OrderProduct::updateAll(['shipping_status' => StatusEnum::ENABLED], ['in', 'id', $ids]);

        // 自动更新订单状态
        Yii::$app->tinyShopService->order->autoUpdateStatus($order_id);
    }

    /**
     * 评价
     *
     * @param $order_product_id
     * @return int
     */
    public function evaluate($order_product_id)
    {
        return OrderProduct::updateAll(['is_evaluate' => ExplainStatusEnum::EVALUATE], ['id' => $order_product_id]);
    }

    /**
     * 追加评价
     *
     * @param $order_product_id
     * @return int
     */
    public function superadditionEvaluate($order_product_id)
    {
        return OrderProduct::updateAll(['is_evaluate' => ExplainStatusEnum::SUPERADDITION], ['id' => $order_product_id]);
    }

    /**
     * 获取退款金额
     *
     * @param Order $order
     * @param OrderProduct|Customer $orderProduct
     * @return int|string|null
     */
    public function getRefundBalanceMoney(Order $order, $orderProduct)
    {
        $refund_shipping_money = 0;
        if ($order->shipping_money > 0) {
            // 退款数量百分比
            $percentage = BcHelper::div($orderProduct->num, $order->product_count, 10);
            $refund_shipping_money = BcHelper::mul($percentage, $order->shipping_money);
        }

        // 申请默认退款金额
        return $orderProduct->product_money + $refund_shipping_money;
    }

    /**
     * 获取售后数量
     *
     * @param string $member_id
     * @return false|string|null
     */
    public function getAfterSaleCount($member_id)
    {
        return OrderProduct::find()
            ->select(['count(distinct order_id) as count'])
            ->where(['status' => StatusEnum::ENABLED])
            ->andFilterWhere(['member_id' => $member_id])
            ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
            ->andWhere([
                'or',
                ['in', 'refund_status', RefundStatusEnum::refund()],
                ['is_customer' => StatusEnum::ENABLED]
            ])
            ->scalar();
    }

    /**
     * 获取后台售后数量
     *
     * @param string $member_id
     * @return false|string|null
     */
    public function getAfterSaleCountByBackend()
    {
        return OrderProduct::find()
            ->select(['count(distinct order_id) as count'])
            ->where(['in', 'refund_status', RefundStatusEnum::refund()])
            ->andWhere(['status' => StatusEnum::ENABLED])
            ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
            ->scalar();
    }

    /**
     * 获取订单售后数量
     *
     * @param $order_id
     * @return false|string|null
     */
    public function getAfterSaleCountByOrderId($order_id)
    {
        return OrderProduct::find()
            ->select(['count(id) as count'])
            ->where(['status' => StatusEnum::ENABLED])
            ->andWhere(['order_id' => $order_id])
            ->andWhere(['in', 'refund_status', RefundStatusEnum::refund()])
            ->scalar();
    }

    /**
     * @param $id
     * @return array|\yii\db\ActiveRecord|null|OrderProduct
     */
    public function findById($id)
    {
        return OrderProduct::find()
            ->where(['id' => $id, 'status' => StatusEnum::ENABLED])
            ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
            ->one();
    }

    /**
     * @param $id
     * @return array|\yii\db\ActiveRecord|null|OrderProduct
     */
    public function findByIds($ids)
    {
        return OrderProduct::find()
            ->select(['id', 'product_picture', 'product_name'])
            ->where(['status' => StatusEnum::ENABLED])
            ->andWhere(['in', 'id', $ids])
            ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
            ->asArray()
            ->one();
    }

    /**
     * 获取某个产品的购买数量
     *
     * @param $product_id
     * @param $member_id
     * @return false|string|null
     */
    public function getSumByMember($product_id, $member_id)
    {
        return OrderProduct::find()
            ->select('sum(num)')
            ->where([
                'product_id' => $product_id,
                'member_id' => $member_id,
                'status' => StatusEnum::ENABLED
            ])
            ->andWhere(['in', 'order_status', OrderStatusEnum::haveBought()])
            ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
            ->scalar();
    }

    /**
     * 判断订单内的产品是否正常
     *
     * @param $ids
     * @param $order_id
     * @return int|string
     */
    public function isNormal($ids, $order_id)
    {
        $list =  OrderProduct::find()
            ->select(['shipping_status', 'refund_status'])
            ->where(['order_id' => $order_id, 'status' => StatusEnum::ENABLED])
            ->andWhere(['in', 'id', $ids])
            ->asArray()
            ->all();

        foreach ($list as $item) {
            // 已发货
            if ($item['shipping_status'] == StatusEnum::ENABLED) {
                return false;
            }

            // 发起了退款请求
            if (!in_array($item['refund_status'], RefundStatusEnum::deliver())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $order_id
     * @return array|\yii\db\ActiveRecord[]
     */
    public function findByOrderId($order_id)
    {
        return OrderProduct::find()
            ->where(['order_id' => $order_id])
            ->asArray()
            ->all();
    }

    /**
     * @param $order_id
     * @return array|\yii\db\ActiveRecord[]
     */
    public function findByOrderIdWithVirtualType($order_id)
    {
        return OrderProduct::find()
            ->where(['order_id' => $order_id])
            ->with('virtualType')
            ->asArray()
            ->all();
    }

    /**
     * 获取指定时间内的产品出售数量和金额
     *
     * @param $models
     * @param $time
     * @return array
     */
    public function getCountMoneyStat($models, $time)
    {
        $models = ArrayHelper::toArray($models);
        $ids = array_column($models, 'id');

        $orderProduct = OrderProduct::find()
            ->select(['product_id', 'sum(num) as num', 'sum(price) as money'])
            ->where(['in', 'product_id', $ids])
            ->andWhere(['>=', 'created_at', $time])
            ->andWhere(['order_status' => OrderStatusEnum::ACCOMPLISH, 'gift_flag' => StatusEnum::DISABLED])
            ->groupBy('product_id')
            ->asArray()
            ->all();

        $orderProduct && $orderProduct = ArrayHelper::arrayKey($orderProduct, 'product_id');

        foreach ($models as &$model) {
            $model['stat_num'] = isset($orderProduct[$model['id']]) ? $orderProduct[$model['id']]['num'] : 0;
            $model['stat_money'] = isset($orderProduct[$model['id']]) ? $orderProduct[$model['id']]['money'] : 0;
        }

        return $models;
    }

    /**
     * 获取产品的最多出售数量和价格
     *
     * @param int $num
     * @param string $orderBy
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getMaxCountMoney($type, $num = 30, $orderBy = 'num')
    {
        // 获取时间和格式化
        list($time, $format) = EchantsHelper::getFormatTime($type);
        // 获取数据
        return EchantsHelper::lineGraphic(function ($start_time, $end_time) use ($num, $orderBy) {
           $data = OrderProduct::find()
                ->select(['product_id', "sum($orderBy) as count"])
                ->where(['order_status' => OrderStatusEnum::ACCOMPLISH, 'gift_flag' => StatusEnum::DISABLED])
                ->andWhere(['between', 'created_at', $start_time, $end_time])
                ->andFilterWhere(['merchant_id' => $this->getMerchantId()])
                ->groupBy(['product_id'])
                ->with('product')
                ->orderBy("count asc")
                ->limit($num)
                ->asArray()
                ->all();

           return [array_column($data, 'count'), array_column(array_column($data, 'product'), 'name')];
        }, $time);
    }
}
<?php

namespace addons\TinyShop\services\order;

use Yii;
use yii\helpers\Json;
use common\components\Service;
use common\enums\StatusEnum;
use common\helpers\AddonHelper;
use addons\TinyShop\common\models\order\ProductExpress;
use addons\TinyShop\common\models\SettingForm;

/**
 * Class ProductExpressService
 * @package addons\TinyShop\services\order
 * @author Rf <1458015476@qq.com>
 */
class ProductExpressService extends Service
{
    /**
     * 获取物流追踪状态
     *
     * @param $order_id
     * @return array
     */
    public function getStatusByOrderId($order_id, $member_id)
    {
        $data = $this->findByOrderIdAndMemberId($order_id, $member_id);

        $setting = new SettingForm();
        $setting->attributes = AddonHelper::getConfig();

        foreach ($data as &$record) {
            !is_array($record['order_product_ids']) && $record['order_product_ids'] = Json::decode($record['order_product_ids']);
            $record['order_product'] = Yii::$app->tinyShopService->orderProduct->findByIds($record['order_product_ids']);
            //物流追踪
            $record['trace'] = [];
            // 需要物流
            if ($record['shipping_type'] == ProductExpress::SHIPPING_TYPE_LOGISTICS) {
                $record['trace'] = Yii::$app->tinyShopService->expressCompany->getTrace($record['express_no'], $record['express_company']);
            }
        }

        return [
            'count' => count($data),
            'data' => $data,
        ];
    }

    /**
     * 重置订单产品获取快递号
     *
     * @param $product
     * @param $order_id
     * @return mixed
     */
    public function regroupProduct($product, $order_id)
    {
        $list = $this->findByOrderId($order_id);

        foreach ($product as &$item) {
            $item['express'] = '';

            foreach ($list as $record) {
                if (in_array($item['id'], $record['order_product_ids'])) {
                    $item['express'] = $record['express_company'] . ' | ' .  $record['express_no'];

                    if ($record['shipping_type'] == ProductExpress::SHIPPING_TYPE_NOT_LOGISTICS) {
                        $item['express'] = '无需物流';
                    }
                }
            }
        }

        return $product;
    }

    /**
     * @param $order_id
     * @param $buyer_id
     * @return array|\yii\db\ActiveRecord[]
     */
    public function findByOrderIdAndMemberId($order_id, $buyer_id)
    {
        return ProductExpress::find()
            ->where([
                'order_id' => $order_id,
                'buyer_id' => $buyer_id,
                'status' => StatusEnum::ENABLED
            ])
            ->asArray()
            ->all();
    }

    /**
     * @param $order_id
     * @return array|\yii\db\ActiveRecord[]
     */
    public function findByOrderId($order_id)
    {
        return ProductExpress::find()
            ->where(['order_id' => $order_id, 'status' => StatusEnum::ENABLED])
            ->all();
    }
}
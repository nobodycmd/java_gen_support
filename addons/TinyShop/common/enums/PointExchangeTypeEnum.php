<?php

namespace addons\TinyShop\common\enums;

use common\enums\BaseEnum;

/**
 * 积分类型
 *
 * Class PointExchangeTypeEnum
 * @package addons\TinyShop\common\enums
 * @author Rf <1458015476@qq.com>
 */
class PointExchangeTypeEnum extends BaseEnum
{
    const NOT_EXCHANGE = 1;
    const INTEGRAL_AND_MONEY = 2;
    const INTEGRAL_OR_MONEY = 3;
    const INTEGRAL = 4;

    /**
     * @return array
     */
    public static function getMap(): array
    {
       return [
           self::NOT_EXCHANGE => '非积分兑换',
           self::INTEGRAL_AND_MONEY => '积分加现金购买',
           self::INTEGRAL_OR_MONEY => '积分兑换或直接购买   ',
           self::INTEGRAL => '只支持积分兑换',
       ];
    }

    /**
     * 返回类型
     *
     * @param bool $status
     * @return array
     */
    public static function isIntegral($status = true)
    {
        if ($status == true) {
            return [self::INTEGRAL_AND_MONEY, self::INTEGRAL];
        }

        return [self::NOT_EXCHANGE, self::INTEGRAL_OR_MONEY];
    }

    /**
     * 是否积分下单类型
     *
     * @param $point_exchange_type
     * @return bool
     */
    public static function isIntegralBuy($point_exchange_type)
    {
        if (in_array($point_exchange_type, [self::INTEGRAL_AND_MONEY, self::INTEGRAL])) {
            return true;
        }

        return false;
    }
}
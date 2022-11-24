<?php

namespace addons\TinyShop\api\modules\v1\forms;

use Yii;
use yii\base\Model;
use common\helpers\RegularHelper;
use common\models\common\SmsLog;

/**
 * Class SmsCodeForm
 * @package api\modules\v1\forms
 * @author Rf <1458015476@qq.com>
 */
class SmsCodeForm extends Model
{
    /**
     * @var
     */
    public $mobile;

    /**
     * @var
     */
    public $usage;

    /**
     * @return array
     */
    public function rules()
    {
        return [
            [['mobile', 'usage'], 'required'],
            [['mobile'], 'isBeforeSend'],
            [['usage'], 'in', 'range' => array_keys(SmsLog::$usageExplain)],
            ['mobile', 'match', 'pattern' => RegularHelper::mobile(), 'message' => '请输入正确的手机号'],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [
            'mobile' => '手机号码',
            'usage' => '用途',
        ];
    }

    /**
     * @param $attribute
     */
    public function isBeforeSend($attribute)
    {
        if ($this->usage == SmsLog::USAGE_REGISTER && Yii::$app->services->member->findByMobile($this->mobile)) {
            $this->addError($attribute, '该手机号码已注册');
        }

        if (
            in_array($this->usage, [SmsLog::USAGE_LOGIN, SmsLog::USAGE_UP_PWD]) &&
            !Yii::$app->services->member->findByMobile($this->mobile)) {
            $this->addError($attribute, '该手机号码未注册');
        }
    }

    /**
     * @throws \yii\web\UnprocessableEntityHttpException
     */
    public function send()
    {
        $code = rand(1000, 9999);
        return Yii::$app->services->sms->send($this->mobile, $code, $this->usage);
    }
}
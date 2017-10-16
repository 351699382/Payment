<?php
/**
 * SuJun (https://github.com/351699382)
 * 微信 条码 支付
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Charge\Wx;

use Payment\Common\Weixin\Data\Charge\BarChargeData;
use Payment\Common\Weixin\WxBaseStrategy;

/**
 * @createTime: 2017-03-06 18:29
 * @description: 微信 刷卡支付  对应支付宝的条码支付
 */
class Bar extends WxBaseStrategy
{
    protected $reqUrl = 'https://api.mch.weixin.qq.com/{debug}/pay/micropay';

    public function getBuildDataClass()
    {
        return BarChargeData::class;
    }

    /**
     * 返回的数据
     * @param array $ret
     * @return array
     */
    protected function retData(array $ret)
    {
        $ret['total_fee'] = bcdiv($ret['total_fee'], 100, 2);
        $ret['cash_fee']  = bcdiv($ret['cash_fee'], 100, 2);

        if ($this->config->returnRaw) {
            return $ret;
        }

        return $ret;
    }
}

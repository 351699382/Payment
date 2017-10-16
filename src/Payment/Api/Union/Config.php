<?php
/**
 * SuJun (https://github.com/351699382)
 * 微信配置文件
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Union;

use Payment\Api\ConfigAbstract;
use Payment\PayException;

final class Config extends ConfigAbstract
{
    // 微信分配的公众账号ID
    public $appId;

    // 微信支付分配的商户号
    public $mchId;

    // 随机字符串，不长于32位
    public $nonceStr;

    // 符合ISO 4217标准的三位字母代码
    public $feeType = 'CNY';

    // 交易开始时间 格式为yyyyMMddHHmmss
    public $timeStart;

    // 用于加密的md5Key
    public $md5Key;

    // 安全证书的路径
    public $cacertPath;

    // cert证书路径或者内容
    public $appCertPem;

    // key文件路径或者内容
    public $appKeyPem;

    //     支付类型
    public $tradeType;

    // 指定回调页面
    public $returnUrl;

    // 关闭订单url  尚未接入
    const CLOSE_URL = 'https://api.mch.weixin.qq.com/{debug}/pay/closeorder';

    // 短连接转化url  尚未接入
    const SHORT_URL = 'https://api.mch.weixin.qq.com/{debug}/tools/shorturl';

    // 退款账户
    const REFUND_UNSETTLED = 'REFUND_SOURCE_UNSETTLED_FUNDS'; // 未结算资金退款（默认使用未结算资金退款）
    const REFUND_RECHARGE  = 'REFUND_SOURCE_RECHARGE_FUNDS'; // 可用余额退款(限非当日交易订单的退款）

    // 沙箱测试相关
    const SANDBOX_PRE = 'sandboxnew';

    // 沙盒测试url
    const SANDBOX_URL = 'https://api.mch.weixin.qq.com/sandboxnew/pay/getsignkey';

    /**
     * 初始化配置文件参数
     * @param array $config
     * @throws PayException
     */
    protected function initConfig(array $config)
    {

    }

}

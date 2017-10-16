<?php
/**
 * SuJun (https://github.com/351699382)
 * 支付宝 手机网站支付 接口
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Ali\Charge;

use Payment\Utils\ArrayUtil;

class Wap extends ChargeAbstract
{
    // wap 支付接口名称
    protected $method = 'alipay.trade.wap.pay';

    /**
     * 实际执行操作，策略总控
     * 检测相关特殊配置-》检测基础数据-》构建发送数据-》设置签名-》构建返回数据格式-》返回相关数据
     * @param array $data
     * @return array|string
     * @throws PayException
     */
    public function handle()
    {
        $data         = $this->buildData();
        $data['sign'] = $this->makeSign($data);
        // 组装成 key=value&key=value 形式返回
        return $this->config['getewayUrl'] . '?' . http_build_query($data);
    }

    /**
     * 构建数据
     * @return [type] [description]
     */
    protected function buildData()
    {
        //业务参数
        $content = [
            'body'                 => strval($this->sendData['body']),
            'subject'              => strval($this->sendData['subject']),
            'out_trade_no'         => strval($this->sendData['order_no']),
            'total_amount'         => strval($this->sendData['amount']),

            // 销售产品码，商家和支付宝签约的产品码，为固定值QUICK_WAP_PAY
            'product_code'         => 'QUICK_WAP_PAY',
            'goods_type'           => $this->sendData['goods_type'],
            'passback_params'      => $this->sendData['return_param'],

            'disable_pay_channels' => $this->config['limitPay'], //限制使用的支付
            'store_id'             => $this->sendData['store_id'], //不懂
            // TODO 在收银台出现返回按钮
            'quit_url'             => $this->quit_url,
            // TODO 优惠信息待支持  业务扩展参数，待支持
            // 'promo_params' => '',
            // 'extend_params => '',
        ];

        $timeExpire = $this->sendData['timeout_express']; //订单失效时间
        if (!empty($timeExpire)) {
            $express                                      = floor(($timeExpire - strtotime($this->config['timestamp'])) / 60);
            ($express > 0) && $content['timeout_express'] = $express . 'm'; // 超时时间 统一使用分钟计算
        }
        $bizContent = ArrayUtil::paraFilter($content); // 过滤掉空值，下面不用在检查是否为空

        // 公共参数
        $signData = [
            'app_id'      => $this->config['appId'],
            'method'      => $this->method,
            'format'      => $this->config['format'],
            'charset'     => $this->config['charset'],
            'sign_type'   => $this->config['signType'],
            'timestamp'   => $this->config['timestamp'],
            'version'     => $this->config['version'],
            'notify_url'  => $this->config['notifyUrl'],

            // 业务参数
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];

        // 电脑支付  wap支付添加额外参数
        if (in_array($this->method, ['alipay.trade.page.pay', 'alipay.trade.wap.pay'])) {
            $signData['return_url'] = $this->sendData['returnUrl'];
        }

        // 移除数组中的空值
        return ArrayUtil::paraFilter($signData);
    }

    /**
     * 初始化数据
     * @param  array  $param [description]
     * @return [type]        [description]
     */
    public function initData(array $param)
    {
        //检测数据
    }

    /**
     * 初始化配置
     * @param  array  $param [description]
     * @return [type]        [description]
     */
    public function initConfig(array $param)
    {
        //检测配置
    }

}

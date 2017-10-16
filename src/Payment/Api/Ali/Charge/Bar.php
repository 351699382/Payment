<?php
/**
 * SuJun (https://github.com/351699382)
 * 商户扫用户的二维码
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Charge\Ali;

use GuzzleHttp\Client;
use Payment\PayException;
use Payment\Utils\ArrayUtil;

class Bar extends ChargeAbstract
{
    // app 支付接口名称
    protected $method = 'alipay.trade.pay';

    /**
     * 处理扫码支付的返回值
     * 实际执行操作，策略总控
     * 检测相关特殊配置-》检测基础数据-》构建发送数据-》设置签名-》构建返回数据格式-》返回相关数据
     * @param array $data
     * @return array|string 可生产二维码的uri
     * @throws PayException
     */
    public function handle()
    {
        $data         = $this->buildData();
        $data['sign'] = $this->makeSign($data);
        // 发起网络请求
        $data = $this->sendReq($data);
        return $data['qr_code'];
    }

    /**
     * 构建数据
     * @return [type] [description]
     */
    protected function buildData()
    {
        //业务参数
        $content = [
            'out_trade_no' => strval($this->sendData['order_no']),
            'scene'        => $this->sendData['scene'],
            'auth_code'    => $this->sendData['auth_code'],
            'product_code' => 'FACE_TO_FACE_PAYMENT',
            'subject'      => strval($this->sendData['subject']),
            // TODO 支付宝用户ID
            // 'seller_id' => $this->partner,
            'body'         => strval($this->sendData['body']),
            'total_amount' => strval($this->sendData['amount']),
            // TODO 折扣金额
            // 'discountable_amount' => '',
            // TODO  业务扩展参数 订单商品列表信息，待支持
            // 'extend_params => '',
            // 'goods_detail' => '',
            'operator_id'  => $this->sendData['operator_id'],
            'store_id'     => $this->sendData['store_id'], //不懂
            'terminal_id'  => $this->sendData['terminal_id'],
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
     * 支付宝业务发送网络请求，并验证签名
     * @param array $data
     * @param string $method 网络请求的方法， get post 等
     * @return mixed
     * @throws PayException
     */
    protected function sendReq(array $data, $method = 'GET')
    {
        $client = new Client([
            'base_uri' => $this->config['getewayUrl'],
            'timeout'  => '10.0',
        ]);

        $method  = strtoupper($method);
        $options = [];
        if ($method === 'GET') {
            $options = [
                'query'       => $data,
                'http_errors' => false,
            ];
        } elseif ($method === 'POST') {
            $options = [
                'form_params' => $data,
                'http_errors' => false,
            ];
        }
        // 发起网络请求
        $response = $client->request($method, '', $options);

        if ($response->getStatusCode() != '200') {
            throw new PayException('网络发生错误，请稍后再试curl返回码：' . $response->getReasonPhrase());
        }

        $body = $response->getBody()->getContents();
        try {
            $body = \GuzzleHttp\json_decode($body, true);
        } catch (InvalidArgumentException $e) {
            throw new PayException('返回数据 json 解析失败');
        }

        $responseKey = str_ireplace('.', '_', $this->method) . '_response';
        if (!isset($body[$responseKey])) {
            throw new PayException('支付宝系统故障或非法请求');
        }

        // 验证签名，检查支付宝返回的数据
        $flag = $this->verifySign($body[$responseKey], $body['sign']);
        if (!$flag) {
            throw new PayException('支付宝返回数据被篡改。请检查网络是否安全！');
        }

        // 这里可能带来不兼容问题。原先会检查code ，不正确时会抛出异常，而不是直接返回
        return $body[$responseKey];
    }

    /**
     * 检查支付宝数据 签名是否被篡改
     * @param array $data
     * @param string $sign  支付宝返回的签名结果
     * @return bool
     */
    protected function verifySign(array $data, $sign)
    {
        $preStr = \GuzzleHttp\json_encode($data, JSON_UNESCAPED_UNICODE); // 主要是为了解决中文问题

        if ($this->config['signType'] === 'RSA') {
            // 使用RSA
            $rsa = new RsaEncrypt($this->config['rsaAliPubKey']);
            return $rsa->rsaVerify($preStr, $sign);
        } elseif ($this->config->signType === 'RSA2') {
            // 使用rsa2方式
            $rsa = new Rsa2Encrypt($this->config['rsaAliPubKey']);
            return $rsa->rsaVerify($preStr, $sign);
        } else {
            return false;
        }
    }

    /**
     * 初始化数据
     * @param  array  $param [description]
     * @return [type]        [description]
     */
    public function initData(array $param)
    {
        //检测数据
        parent::initData($param); // TODO: Change the autogenerated stub

        $scene    = $param['scene'];
        $authCode = $param['auth_code'];

        if (empty($scene) || !in_array($scene, ['bar_code', 'wave_code'])) {
            throw new PayException('支付场景 scene 必须设置 条码支付：bar_code 声波支付：wave_code');
        }

        if (empty($authCode)) {
            throw new PayException('请提供支付授权码');
        }
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

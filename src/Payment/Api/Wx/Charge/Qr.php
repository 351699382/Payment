<?php
/**
 * SuJun (https://github.com/351699382)
 * 微信 扫码支付  主要用于网站上
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Wx\Charge;

use GuzzleHttp\Client;
use Payment\PayException;
use Payment\Utils\ArrayUtil;
use Payment\Utils\DataParser;

class Qr extends ChargeAbstract
{
    // 沙箱测试相关
    const SANDBOX_PRE = 'sandboxnew';
    /**
     * 需要像微信请求的url。默认是统一下单url
     * @var string $reqUrl
     */
    protected $reqUrl = 'https://api.mch.weixin.qq.com/{debug}/pay/unifiedorder';

    /**
     * 实际执行操作，策略总控
     * 检测相关特殊配置-》检测基础数据-》构建发送数据-》设置签名-》构建返回数据格式-》返回相关数据
     * @param array $data
     * @return array|string
     * @throws PayException
     */
    public function handle()
    {

        // 商户系统和微信支付系统主要交互说明：
        // 步骤1：用户在商户APP中选择商品，提交订单，选择微信支付。
        // 步骤2：商户后台收到用户支付单，调用微信支付统一下单接口。参见【统一下单API】。
        // 步骤3：统一下单接口返回正常的prepay_id，再按签名规范重新生成签名后，将数据传输给APP。参与签名的字段名为appid，partnerid，prepayid，noncestr，timestamp，package。注意：package的值格式为Sign=WXPay
        // 步骤4：商户APP调起微信支付。api参见本章节【app端开发步骤说明】
        // 步骤5：商户后台接收支付通知。api参见【支付结果通知API】
        // 步骤6：商户后台查询支付结果。，api参见【查询订单API】
        //

        $data         = $this->buildData();
        $data['sign'] = $this->makeSign($data);
        $xml          = DataParser::toXml($data);
        $ret          = $this->sendReq($xml);

        // 检查返回的数据是否被篡改
        $flag = $this->verifySign($ret);
        if (!$flag) {
            throw new PayException('微信返回数据被篡改。请检查网络是否安全！');
        }

        if ($this->config['returnRaw']) {
            return $ret;
        }

        // 扫码支付，返回链接
        return $ret;
    }

    /**
     * 检查微信返回的数据是否被篡改过
     * @param array $retData
     * @return boolean
     */
    protected function verifySign(array $retData)
    {
        $retSign = $retData['sign'];
        $values  = ArrayUtil::removeKeys($retData, ['sign', 'sign_type']);

        $values = ArrayUtil::paraFilter($values);

        $values = ArrayUtil::arraySort($values);

        $signStr = ArrayUtil::createLinkstring($values);

        $signStr .= '&key=' . $this->config['md5Key'];
        switch ($this->config['signType']) {
            case 'MD5':
                $sign = md5($signStr);
                break;
            case 'HMAC-SHA256':
                $sign = hash_hmac('sha256', $signStr, $this->config['md5Key']);
                break;
            default:
                $sign = '';
        }

        return strtoupper($sign) === $retSign;
    }
    /**
     * 发送完了请求
     * @param string $xml
     * @return mixed
     * @throws PayException
     */
    protected function sendReq($xml)
    {
        $url = $this->reqUrl;
        if (is_null($url)) {
            throw new PayException('目前不支持该接口。请联系开发者添加');
        }

        if ($this->config['useSandbox']) {
            $url = str_ireplace('{debug}', self::SANDBOX_PRE, $url);
        } else {
            $url = str_ireplace('{debug}/', '', $url);
        }

        $client = new Client([
            'timeout' => '10.0',
        ]);
        // @note: 微信部分接口并不需要证书支持。这里为了统一，全部携带证书进行请求
        $options = [
            'body'        => $xml,
            'http_errors' => false,
            'verify'      => false,
        ];
        $response = $client->request('POST', $url, $options);
        if ($response->getStatusCode() != '200') {
            throw new PayException('网络发生错误，请稍后再试curl返回码：' . $response->getReasonPhrase());
        }

        $body = $response->getBody()->getContents();

        // 格式化为数组
        $retData = DataParser::toArray($body);
        if (strtoupper($retData['return_code']) != 'SUCCESS') {
            throw new PayException('微信返回错误提示：' . $retData['return_msg']);
        }
        if (strtoupper($retData['result_code']) != 'SUCCESS') {
            $msg = $retData['err_code_des'] ? $retData['err_code_des'] : $retData['err_msg'];
            throw new PayException('微信返回错误提示：' . $msg);
        }

        return $retData;
    }

    /**
     * 构建数据
     * @return [type] [description]
     */
    protected function buildData()
    {
        if (isset($this->sendData['scene_info'])) {
            $info      = $this->sendData['scene_info'];
            $sceneInfo = [];
            if ($info && is_array($info)) {
                $sceneInfo['store_info'] = $info;
            }
        } else {
            $sceneInfo = '';
        }

        $signData = [
            'appid'            => trim($this->config['appId']),
            'mch_id'           => trim($this->config['mchId']),
            'device_info'      => 'WEB',
            'nonce_str'        => $this->config['nonceStr'],
            'sign_type'        => $this->config['signType'],
            'body'             => trim($this->sendData['subject']),
            //'detail' => json_encode($this->body, JSON_UNESCAPED_UNICODE),
            'attach'           => trim($this->sendData['return_param']),
            'out_trade_no'     => trim($this->sendData['order_no']),
            'fee_type'         => $this->config['feeType'],
            'total_fee'        => $this->sendData['amount'],
            'spbill_create_ip' => trim($this->sendData['client_ip']),
            'time_start'       => $this->config['timeStart'],
            'time_expire'      => $this->sendData['timeout_express'],
            //'goods_tag' => '订单优惠标记',
            'notify_url'       => $this->config['notifyUrl'],
            'trade_type'       => 'NATIVE', //设置APP支付
            'product_id'       => $this->sendData['product_id'],
            'limit_pay'        => $this->config['limitPay'], // 指定不使用信用卡
            'openid'           => isset($this->sendData['openid']) ? $this->sendData['openid'] : '',
            'scene_info'       => $sceneInfo ? json_encode($sceneInfo, JSON_UNESCAPED_UNICODE) : '',

            // 服务商
            // 'sub_appid'        => $this->sub_appid,
            // 'sub_mch_id'       => $this->sub_mch_id,
        ];

        // 移除数组中的空值
        return ArrayUtil::paraFilter($signData);
    }

    /**
     * 签名算法实现
     * @param string $signStr
     * @return string
     */
    protected function makeSign(array $param)
    {

        $values = ArrayUtil::removeKeys($param, ['sign']);

        $values = ArrayUtil::arraySort($values);

        $signStr = ArrayUtil::createLinkstring($values);

        switch ($this->config['signType']) {
            case 'MD5':
                $signStr .= '&key=' . $this->config['md5Key'];
                $sign = md5($signStr);
                break;
            case 'HMAC-SHA256':
                $sign = base64_encode(hash_hmac('sha256', $signStr, $this->config['md5Key']));
                break;
            default:
                $sign = '';
        }

        return strtoupper($sign);
    }

    /**
     * 初始化数据
     * @param  array  $param [description]
     * @return [type]        [description]
     */
    public function initData(array $param)
    {
        $data = parent::initData($param);
        //检测数据
        // 扫码支付,必须设置product_id  参数
        if (empty($param['product_id'])) {
            throw new PayException('扫码支付,必须设置商品ID.');
        }
        return $data;
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

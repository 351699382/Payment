<?php
/**
 * SuJun (https://github.com/351699382)
 * 银联wap支付
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Union\Charge;

use Payment\PayException;
use Payment\Utils\ArrayUtil;

class Wap extends ChargeAbstract
{

    /**
     * 实际执行操作，策略总控
     * 检测相关特殊配置-》检测基础数据-》构建发送数据-》设置签名-》构建返回数据格式-》返回相关数据
     * @param array $data
     * @return array|string
     * @throws PayException
     */
    public function handle()
    {

        $data = $this->buildData();
        $flag = $this->makeSign($data);
        if (!$flag) {
            throw new PayException('银联返回数据被篡改。请检查网络是否安全！');
        }
        if ($this->config['use_sandbox']) {
            $uri = "https://gateway.test.95516.com/gateway/api/frontTransReq.do";
        } else {
            $uri = "https://gateway.95516.com/gateway/api/frontTransReq.do";
        }

        $html_form = $this->createAutoFormHtml($data, $uri);
        return $html_form;
    }

    public function createAutoFormHtml($params, $reqUrl)
    {
        // <body onload="javascript:document.pay_form.submit();">
        $encodeType = isset($params['encoding']) ? $params['encoding'] : 'UTF-8';
        $html       = <<<eot
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset={$encodeType}" />
</head>
<body onload="javascript:document.pay_form.submit();">
    <form id="pay_form" name="pay_form" action="{$reqUrl}" method="post">

eot;
        foreach ($params as $key => $value) {
            $html .= "    <input type=\"hidden\" name=\"{$key}\" id=\"{$key}\" value=\"{$value}\" />\n";
        }
        $html .= <<<eot
   <!-- <input type="submit" type="hidden">-->
    </form>
</body>
</html>
eot;
        return $html;
    }

    /**
     * 构建数据
     * @return [type] [description]
     */
    protected function buildData()
    {

        $signData = [

            //以下信息非特殊情况不需要改动
            'version'      => '5.1.0', //版本号
            'encoding'     => 'utf-8', //编码方式
            'txnType'      => '01', //交易类型
            'txnSubType'   => '01', //交易子类
            'bizType'      => '000201', //业务类型
            'frontUrl'     => $this->config['redirect_url'], //前台通知地址
            'backUrl'      => $this->config['notify_url'], //后台通知地址
            'signMethod'   => '01', //签名方法
            'channelType'  => '08', //渠道类型，07-PC，08-手机
            'accessType'   => '0', //接入类型
            'currencyCode' => '156', //交易币种，境内商户固定156
            //TODO 以下信息需要填写
            'merId'        => $this->config['mer_id'], //商户代码，请改自己的测试商户号
            'orderId'      => $this->sendData['order_id'], //商户订单号，8-32位数字字母，不能含“-”或“_”，
            'txnTime'      => $this->sendData['txn_time'], //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间
            'txnAmt'       => $this->sendData['txn_amt'], //交易金额，单位分，此处默认取demo演示页面传递的参数
            // 订单超时时间。
            // 超过此时间后，除网银交易外，其他交易银联系统会拒绝受理，提示超时。 跳转银行网银交易如果超时后交易成功，会自动退款，大约5个工作日金额返还到持卡人账户。
            // 此时间建议取支付时的北京时间加15分钟。
            // 超过超时时间调查询接口应答origRespCode不是A6或者00的就可以判断为失败。
            'payTimeout'   => date('YmdHis', strtotime('+30 minutes')),
            //回传字段
            'reqReserved'  => $this->sendData['reqReserved'],
        ];

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
        $data = parent::initData($param);
        if (empty($param['order_id'])) {
            throw new PayException("订单号不能为空", 1);
        }
        if (empty($param['txn_time'])) {
            throw new PayException("订单时间不能为空", 1);
        }
        if (empty($param['txn_amt'])) {
            throw new PayException("金额不能为空", 1);
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
        if (empty($param['mer_id'])) {
            throw new PayException("商户号不能为空", 1);
        }
        if (empty($param['notify_url'])) {
            throw new PayException("后台通知地址不能为空", 1);
        }
    }

}

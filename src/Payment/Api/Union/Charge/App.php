<?php
/**
 * SuJun (https://github.com/351699382)
 * 银联App支付
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Union\Charge;

use GuzzleHttp\Client;
use Payment\PayException;
use Payment\Utils\ArrayUtil;

class App extends ChargeAbstract
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
        $this->makeSign($data);
        $htmlForm = $this->sendReq($data, 'POST');
        return $htmlForm;
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
            'merId'        => $this->config['mer_id'], //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'orderId'      => $this->sendData['order_id'], //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
            'txnTime'      => $this->sendData['txn_time'], //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
            'txnAmt'       => $this->sendData['txn_amt'], //交易金额，单位分，此处默认取demo演示页面传递的参数
            //回传字段
            'reqReserved'  => $this->sendData['reqReserved'],
            // 请求方保留域，
            // 透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据。
            // 出现部分特殊字符时可能影响解析，请按下面建议的方式填写：
            // 1. 如果能确定内容不会出现&={}[]"'等符号时，可以直接填写数据，建议的方法如下。
            //    'reqReserved' =>'透传信息1|透传信息2|透传信息3',
            // 2. 内容可能出现&={}[]"'符号时：
            // 1) 如果需要对账文件里能显示，可将字符替换成全角＆＝｛｝【】“‘字符（自己写代码，此处不演示）；
            // 2) 如果对账文件没有显示要求，可做一下base64（如下）。
            //    注意控制数据长度，实际传输的数据长度不能超过1024位。
            //    查询、通知等接口解析时使用base64_decode解base64后再对数据做后续解析。
            //    'reqReserved' => base64_encode('任意格式的信息都可以'),

            //TODO 其他特殊用法请查看 pages/api_05_app/special_use_purchase.php
        ];

        // 移除数组中的空值
        return ArrayUtil::paraFilter($signData);
    }

    /**
     * 发送网络请求
     * @param array $data
     * @param string $method 网络请求的方法， get post 等
     * @return mixed
     * @throws PayException
     */
    protected function sendReq(array $data, $method = 'GET')
    {
        if ($this->config['use_sandbox']) {
            $uri = "https://gateway.test.95516.com/gateway/api/appTransReq.do";
        } else {
            $uri = "https://gateway.95516.com/gateway/api/appTransReq.do";
        }
        $client = new Client([
            'base_uri' => $uri,
            'timeout'  => '10.0',
        ]);

        $method  = strtoupper($method);
        $options = [];
        if ($method === 'GET') {
            $options = [
                'query'       => $data,
                'http_errors' => false,
                'verify'      => false,
            ];
        } elseif ($method === 'POST') {
            $options = [
                'form_params' => $data,
                'http_errors' => false,
                'verify'      => false,
            ];
        }
        // 发起网络请求
        $response = $client->request($method, '', $options);

        if ($response->getStatusCode() != '200') {
            throw new PayException('网络发生错误，请稍后再试curl返回码：' . $response->getReasonPhrase());
        }

        $body = $response->getBody()->getContents();
        // try {
        //     $body = \GuzzleHttp\json_decode($body, true);
        // } catch (InvalidArgumentException $e) {
        //     throw new PayException('返回数据 json 解析失败');
        // }

        return $body;
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

<?php
/**
 * SuJun (https://github.com/351699382)
 * 支付宝移动支付接口
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Wx\Charge;

use Payment\Api\Wx\Config;
use Payment\PayException;

abstract class ChargeAbstract
{

    /**
     * 配置信息
     * @var [type]
     */
    protected $config;

    protected $sendData;

    /**
     * BaseData constructor.
     * @param ConfigInterface $config
     * @param array $reqData
     * @throws PayException
     */
    public function __construct(array $config, array $param)
    {
        //检测配置
        $this->initConfig($config);

        $config = new Config($config);

        //配置贼值
        $this->config = $config->toArray();

        //
        $param          = $this->initData($param);
        $this->sendData = $param;
    }

    /**
     * 初始化配置文件
     * @param array $config
     * @throws PayException
     */
    abstract protected function initConfig(array $config);

    /**
     * 检测最基本需要传入的参数
     * 检查传入的支付业务参数是否正确
     * 如果输入参数不符合规范，直接抛出异常
     * @param  array  $param [description]
     * @return [type]        [description]
     */
    protected function initData(array $param)
    {

        // 检查订单号是否合法
        if (empty($param['order_no']) || mb_strlen($param['order_no']) > 64) {
            throw new PayException('订单号不能为空，并且长度不能超过64位');
        }

        // 检查金额不能低于0.01
        if ($param['amount'] < 0.01) {
            throw new PayException('支付金额不能低于0.01元');
        }

        // 检查 商品名称 与 商品描述
        if (empty($param['subject']) || empty($param['body'])) {
            throw new PayException('必须提供商品名称与商品详情');
        }

        // 初始 微信订单过期时间，最短失效时间间隔必须大于5分钟
        if ($param['timeout_express'] - strtotime($this->config['timeStart']) < 5) {
            throw new PayException('必须设置订单过期时间,且需要大于5分钟.如果不正确请检查是否正确设置时区');
        } else {
            $param['timeout_express'] = date('YmdHis', $param['timeout_express']);
        }

        // 微信使用的单位位分.此处进行转化
        $param['amount'] = bcmul($param['amount'], 100, 0);

        // 设置ip地址
        if (empty($param['client_ip'])) {
            $param['client_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        }

        // 设置设备号
        if (empty($param['device_info'])) {
            $param['device_info'] = 'WEB';
        }
        return $param;
    }

}

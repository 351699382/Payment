<?php
/**
 * SuJun (https://github.com/351699382)
 * 银联支付接口
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Union\Charge;

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
        //配置贼值
        $this->config = $config;

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
        return $param;
    }

}
